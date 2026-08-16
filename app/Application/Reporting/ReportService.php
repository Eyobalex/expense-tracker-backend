<?php

namespace App\Application\Reporting;

use App\Application\Notifications\NotificationService;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Reporting\Enums\ReportFormat;
use App\Domain\Reporting\Enums\ReportType;
use App\Domain\Reporting\ReportFilter;
use App\Models\AuditEvent;
use App\Models\ReportJob;
use App\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use ZipArchive;

final readonly class ReportService
{
    public function __construct(
        private ReportQueryService $queries,
        private FullAccountExportBuilder $fullAccountExports,
        private NotificationService $notifications,
    ) {}

    /** @param array<string, mixed> $filters */
    public function request(User $user, ReportType $type, ReportFormat $format, array $filters, string $requestId): ReportJob
    {
        $this->assertFormat($type, $format);
        $report = $user->base_currency_code;
        $job = $user->reportJobs()->create([
            'type' => $type->value, 'format' => $format->value, 'filters' => $filters,
            'base_currency_code' => $report, 'timezone' => $user->budget_timezone, 'status' => 'queued', 'progress' => 0,
            'request_id' => $requestId, 'expires_at' => now()->addDays($this->retentionDays($type)),
        ]);
        AuditEvent::query()->create([
            'actor_user_id' => $user->getKey(), 'user_id' => $user->getKey(), 'event_name' => 'report.requested',
            'aggregate_type' => 'report_job', 'aggregate_id' => $job->getKey(), 'request_id' => $requestId,
            'summary' => ['type' => $type->value, 'format' => $format->value, 'filters' => array_keys($filters)],
        ]);

        return $job;
    }

    public function shouldRunSynchronously(ReportJob $report): bool
    {
        if ($report->format !== ReportFormat::Csv->value || ! config('reports.allow_synchronous_csv')) {
            return false;
        }
        $type = ReportType::from($report->type);
        if ($type->isPortabilityExport()) {
            return false;
        }

        return $this->queries->transactionCount($report->user, $this->filter($report)) <= (int) config('reports.synchronous_csv_row_limit');
    }

    public function generate(string $reportJobId, ?string $queueJobId = null): void
    {
        $report = ReportJob::query()->with('user')->findOrFail($reportJobId);
        if ($report->status === 'completed' || $report->status === 'expired') {
            return;
        }
        $startedAtNanoseconds = hrtime(true);
        $queueAgeMilliseconds = $report->created_at?->diffInMilliseconds(now());
        $report->forceFill(['status' => 'processing', 'progress' => 10, 'started_at' => now(), 'job_id' => $queueJobId])->save();
        try {
            $type = ReportType::from($report->type);
            $format = ReportFormat::from($report->format);
            $artifact = match ($type) {
                ReportType::FullAccountExport => $this->fullAccountZip($report),
                ReportType::FullJsonDataExport => $this->fullJson($report),
                default => $this->render($this->queries->report($report->user, $type, $this->filter($report)), $format, $report),
            };
            $this->store($report, $artifact);
            $report->forceFill(['status' => 'completed', 'progress' => 100, 'completed_at' => now(), 'error_code' => null])->save();
            AuditEvent::query()->create([
                'actor_user_id' => $report->user_id, 'user_id' => $report->user_id, 'event_name' => 'report.completed',
                'aggregate_type' => 'report_job', 'aggregate_id' => $report->getKey(), 'request_id' => $report->request_id,
                'summary' => ['type' => $report->type, 'format' => $report->format, 'byte_size' => $report->byte_size, 'job_id' => $report->job_id],
            ]);
            Log::info('report.generated', [
                'request_id' => $report->request_id,
                'report_job_id' => $report->getKey(),
                'job_id' => $report->job_id,
                'user_id' => $report->user_id,
                'type' => $report->type,
                'format' => $report->format,
                'queue_age_ms' => $queueAgeMilliseconds,
                'generation_duration_ms' => round((hrtime(true) - $startedAtNanoseconds) / 1_000_000, 3),
                'byte_size' => $report->byte_size,
            ]);
            $this->notifications->create($report->user, NotificationType::ReportReady, 'report-ready:'.$report->getKey(), 'Report ready', 'Your requested report is ready for secure download.', ['report_job_id' => $report->getKey(), 'type' => $report->type, 'format' => $report->format, 'expires_at' => $report->expires_at->toISOString()], requestId: $report->request_id);
        } catch (\Throwable $exception) {
            $this->fail($report, $exception);
            throw $exception;
        }
    }

    public function fail(ReportJob $report, \Throwable $exception): void
    {
        if ($report->status === 'completed' || $report->status === 'expired') {
            return;
        }
        $report->forceFill(['status' => 'failed', 'error_code' => class_basename($exception), 'completed_at' => now()])->save();
        AuditEvent::query()->create([
            'actor_user_id' => $report->user_id, 'user_id' => $report->user_id, 'event_name' => 'report.failed',
            'aggregate_type' => 'report_job', 'aggregate_id' => $report->getKey(), 'request_id' => $report->request_id,
            'summary' => ['type' => $report->type, 'format' => $report->format, 'failure_code' => class_basename($exception), 'job_id' => $report->job_id],
        ]);
        Log::warning('report.generation_failed', [
            'request_id' => $report->request_id,
            'report_job_id' => $report->getKey(),
            'job_id' => $report->job_id,
            'user_id' => $report->user_id,
            'type' => $report->type,
            'format' => $report->format,
            'failure_code' => class_basename($exception),
        ]);
    }

    private function filter(ReportJob $report): ReportFilter
    {
        return new ReportFilter($report->filters, $report->timezone, $report->base_currency_code);
    }

    /** @return array{content:string,extension:string,mime_type:string,filename:string} */
    private function fullJson(ReportJob $report): array
    {
        $payload = $this->fullAccountExports->build($report->user)['data'];

        return ['content' => json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), 'extension' => 'json', 'mime_type' => 'application/json', 'filename' => 'full-json-data-export-'.$report->getKey().'.json'];
    }

    /** @return array{content:string,extension:string,mime_type:string,filename:string} */
    private function fullAccountZip(ReportJob $report): array
    {
        $export = $this->fullAccountExports->build($report->user, includeReceiptMedia: true);
        $path = $this->temporaryPath('zip');
        try {
            $zip = new ZipArchive;
            if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('The full-account export archive could not be created.');
            }
            $temporaryReceipts = [];
            try {
                $data = json_encode($export['data'], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
                $zip->addFromString('data.json', $data);
                $zip->addFromString('manifest.json', json_encode([
                    'schema_version' => 1, 'export_type' => 'full_account_export', 'report_job_id' => $report->getKey(),
                    'generated_at' => now()->toISOString(), 'data_sha256' => hash('sha256', $data), 'receipt_count' => count($export['receipts']),
                ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
                $disk = Storage::disk((string) config('reports.disk'));
                foreach ($export['receipts'] as $receipt) {
                    if (! $disk->exists($receipt['key'])) {
                        throw new \RuntimeException('A retained receipt object is unavailable for the requested full-account export.');
                    }
                    $source = $disk->readStream($receipt['key']);
                    if (! is_resource($source)) {
                        throw new \RuntimeException('A retained receipt object could not be read for export.');
                    }
                    $localReceipt = $this->temporaryPath($receipt['extension']);
                    $temporaryReceipts[] = $localReceipt;
                    $target = fopen($localReceipt, 'wb');
                    if ($target === false) {
                        fclose($source);
                        throw new \RuntimeException('A retained receipt object could not be prepared for export.');
                    }
                    try {
                        stream_copy_to_stream($source, $target);
                    } finally {
                        fclose($source);
                        fclose($target);
                    }
                    $zip->addFile($localReceipt, 'receipts/'.$receipt['id'].'.'.$receipt['extension']);
                }
            } finally {
                $zip->close();
                foreach ($temporaryReceipts as $temporaryReceipt) {
                    if (is_file($temporaryReceipt)) {
                        unlink($temporaryReceipt);
                    }
                }
            }
            $content = file_get_contents($path);
            if (! is_string($content)) {
                throw new \RuntimeException('The full-account export archive could not be read.');
            }

            return ['content' => $content, 'extension' => 'zip', 'mime_type' => 'application/zip', 'filename' => 'full-account-export-'.$report->getKey().'.zip'];
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    /** @param array<string, mixed> $document
     * @return array{content:string,extension:string,mime_type:string,filename:string}
     */
    private function render(array $document, ReportFormat $format, ReportJob $report): array
    {
        return match ($format) {
            ReportFormat::Csv => ['content' => $this->csv($document), 'extension' => 'csv', 'mime_type' => 'text/csv', 'filename' => Str::slug($document['title']).'-'.$report->getKey().'.csv'],
            ReportFormat::Json => ['content' => json_encode($document, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), 'extension' => 'json', 'mime_type' => 'application/json', 'filename' => Str::slug($document['title']).'-'.$report->getKey().'.json'],
            ReportFormat::Xlsx => ['content' => $this->xlsx($document), 'extension' => 'xlsx', 'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'filename' => Str::slug($document['title']).'-'.$report->getKey().'.xlsx'],
            ReportFormat::Pdf => ['content' => $this->pdf($document), 'extension' => 'pdf', 'mime_type' => 'application/pdf', 'filename' => Str::slug($document['title']).'-'.$report->getKey().'.pdf'],
            ReportFormat::Zip => throw new \LogicException('ZIP is reserved for full-account export.'),
        };
    }

    /** @param array<string, mixed> $document */
    private function csv(array $document): string
    {
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            throw new \RuntimeException('The CSV export stream could not be opened.');
        }
        fputcsv($stream, ['report', $document['title']]);
        foreach ($document['context'] as $key => $value) {
            fputcsv($stream, [$key, is_scalar($value) || $value === null ? $value : json_encode($value, JSON_THROW_ON_ERROR)]);
        }
        fputcsv($stream, []);
        fputcsv($stream, $document['columns']);
        foreach ($document['rows'] as $row) {
            fputcsv($stream, array_map(fn (mixed $value): string => $this->safeCell($value), $row));
        }
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);
        if (! is_string($content)) {
            throw new \RuntimeException('The CSV export stream could not be read.');
        }

        return $content;
    }

    /** @param array<string, mixed> $document */
    private function xlsx(array $document): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Report');
        $sheet->fromArray([$document['columns']], null, 'A1');
        $rowNumber = 2;
        foreach ($document['rows'] as $row) {
            $sheet->fromArray([array_map(fn (mixed $value): string => $this->safeCell($value), $row)], null, 'A'.$rowNumber++);
        }
        $sheet->freezePane('A2');
        $path = $this->temporaryPath('xlsx');
        (new Xlsx($spreadsheet))->save($path);
        $content = file_get_contents($path);
        @unlink($path);
        if (! is_string($content)) {
            throw new \RuntimeException('The spreadsheet export could not be read.');
        }

        return $content;
    }

    /** @param array<string, mixed> $document */
    private function pdf(array $document): string
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('reports.document', ['document' => $document])->render());
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return $dompdf->output();
    }

    /** @param array{content:string,extension:string,mime_type:string,filename:string} $artifact */
    private function store(ReportJob $report, array $artifact): void
    {
        $prefix = $report->type === ReportType::FullAccountExport->value ? 'exports/full-account' : 'reports';
        $key = sprintf('%s/%s/%s.%s', $prefix, $report->user->uuid ?? $report->user_id, $report->getKey(), $artifact['extension']);
        $disk = Storage::disk((string) config('reports.disk'));
        if (! $disk->put($key, $artifact['content'], ['visibility' => 'private', 'ContentType' => $artifact['mime_type']])) {
            throw new \RuntimeException('The generated report could not be stored privately.');
        }
        $report->forceFill([
            'private_object_key' => $key, 'artifact_filename' => $artifact['filename'], 'mime_type' => $artifact['mime_type'],
            'byte_size' => strlen($artifact['content']), 'checksum_sha256' => hash('sha256', $artifact['content']), 'progress' => 90,
        ])->save();
    }

    private function temporaryPath(string $extension): string
    {
        $path = tempnam(sys_get_temp_dir(), 'expense-tracker-report-');
        if ($path === false) {
            throw new \RuntimeException('A temporary report path could not be created.');
        }
        $target = $path.'.'.$extension;
        if (! rename($path, $target)) {
            @unlink($path);
            throw new \RuntimeException('A temporary report path could not be prepared.');
        }

        return $target;
    }

    private function safeCell(mixed $value): string
    {
        $value = is_scalar($value) || $value === null ? (string) $value : json_encode($value, JSON_THROW_ON_ERROR);

        return in_array($value[0] ?? '', ['=', '+', '-', '@'], true) ? "'".$value : $value;
    }

    private function retentionDays(ReportType $type): int
    {
        return $type === ReportType::FullAccountExport ? (int) config('reports.full_account_export_retention_days') : (int) config('reports.retention_days');
    }

    private function assertFormat(ReportType $type, ReportFormat $format): void
    {
        if (($type === ReportType::FullJsonDataExport && $format !== ReportFormat::Json) || ($type === ReportType::FullAccountExport && $format !== ReportFormat::Zip) || (! $type->isPortabilityExport() && $format === ReportFormat::Zip)) {
            throw new \InvalidArgumentException('The requested report type and format are not compatible.');
        }
    }
}
