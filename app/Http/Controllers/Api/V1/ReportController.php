<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Reporting\ReportService;
use App\Domain\Reporting\Enums\ReportFormat;
use App\Domain\Reporting\Enums\ReportType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreReportRequest;
use App\Http\Resources\Api\V1\ReportJobResource;
use App\Jobs\GenerateReport;
use App\Models\ReportJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $reports = ReportJob::query()->ownedBy($request->user())->orderByDesc('created_at')->orderByDesc('id')->cursorPaginate(min($request->integer('per_page', 25), 100));

        return $this->success($request, ['reports' => ReportJobResource::collection($reports->items())->resolve($request)], meta: ['next_cursor' => $reports->nextCursor()?->encode()]);
    }

    public function store(StoreReportRequest $request, ReportService $reports): JsonResponse
    {
        $validated = $request->validated();
        $report = $reports->request(
            $request->user(),
            ReportType::from((string) $validated['type']),
            ReportFormat::from((string) $validated['format']),
            Arr::except($validated, ['type', 'format']),
            (string) $request->attributes->get('request_id'),
        );
        if ($reports->shouldRunSynchronously($report)) {
            GenerateReport::dispatchSync($report->getKey());

            return $this->success($request, (new ReportJobResource($report->fresh()))->resolve($request), JsonResponse::HTTP_CREATED);
        }
        GenerateReport::dispatch($report->getKey())->onQueue('reports')->afterCommit();

        return $this->success($request, (new ReportJobResource($report))->resolve($request), JsonResponse::HTTP_ACCEPTED);
    }

    public function show(Request $request, ReportJob $report): JsonResponse
    {
        if ($report->user_id !== $request->user()->getKey() || $request->user()->cannot('view', $report)) {
            return $this->notFound($request);
        }

        return $this->success($request, (new ReportJobResource($report))->resolve($request));
    }

    public function download(Request $request, ReportJob $report): StreamedResponse|JsonResponse
    {
        if ($report->user_id !== $request->user()->getKey() || $request->user()->cannot('view', $report)) {
            return $this->notFound($request);
        }
        if ($report->status !== 'completed' || $report->private_object_key === null || $report->expires_at->isPast()) {
            return $this->error($request, 'REPORT_NOT_READY', 'The requested report is not available for download.', JsonResponse::HTTP_CONFLICT);
        }
        $disk = Storage::disk((string) config('reports.disk'));
        $stream = $disk->readStream($report->private_object_key);
        if (! is_resource($stream)) {
            return $this->error($request, 'REPORT_ARTIFACT_UNAVAILABLE', 'The requested report artifact is unavailable.', JsonResponse::HTTP_CONFLICT);
        }

        return response()->streamDownload(function () use ($stream): void {
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, $report->artifact_filename ?? 'report.'.$report->format, ['Content-Type' => $report->mime_type ?? 'application/octet-stream']);
    }

    private function notFound(Request $request): JsonResponse
    {
        return $this->error($request, 'RESOURCE_NOT_FOUND', 'The requested resource was not found.', JsonResponse::HTTP_NOT_FOUND);
    }
}
