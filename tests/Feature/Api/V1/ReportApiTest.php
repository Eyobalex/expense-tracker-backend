<?php

use App\Application\Reporting\ReportService;
use App\Jobs\GenerateReport;
use App\Models\AuditEvent;
use App\Models\Category;
use App\Models\FinancialAccount;
use App\Models\Receipt;
use App\Models\ReportJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seedCurrencies();
    Storage::fake('minio');
    config()->set('reports.disk', 'minio');
    config()->set('reports.allow_synchronous_csv', true);
});

function reportUser(): User
{
    return User::factory()->create(['base_currency_code' => 'ETB', 'timezone' => 'Africa/Addis_Ababa', 'budget_timezone' => 'Africa/Addis_Ababa']);
}

function reportToken(User $user): string
{
    return $user->createToken('report-test', ['api'])->plainTextToken;
}

function postedReportExpense(User $user, FinancialAccount $account, Category $category, int $amount, string $occurredAt): array
{
    $token = reportToken($user);
    $created = test()->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/transactions', [
        'financial_account_id' => $account->id, 'category_id' => $category->id, 'type' => 'expense', 'occurred_at' => $occurredAt,
        'occurred_timezone' => 'Africa/Addis_Ababa', 'original_amount_minor_units' => $amount, 'original_currency_code' => 'ETB',
    ])->assertCreated()->json('data');

    return test()->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())->withHeader('If-Match', '1')->postJson("/api/v1/transactions/{$created['id']}/post")->assertOk()->json('data');
}

test('a small user-scoped CSV transaction report is generated synchronously and streams only from private storage', function (): void {
    $owner = reportUser();
    $account = FinancialAccount::factory()->for($owner)->create(['currency_code' => 'ETB']);
    $category = Category::factory()->for($owner)->create(['kind' => 'expense', 'budget_currency_code' => 'ETB']);
    postedReportExpense($owner, $account, $category, 1234, '2026-08-10T10:00:00Z');

    $requestId = (string) Str::uuid();
    $response = $this->withToken(reportToken($owner))->withHeaders(['Idempotency-Key' => (string) Str::uuid(), 'X-Request-Id' => $requestId])->postJson('/api/v1/reports', [
        'type' => 'transaction', 'format' => 'csv', 'from' => '2026-08-01', 'to' => '2026-08-31',
    ])->assertCreated()
        ->assertJsonPath('data.type', 'transaction')
        ->assertJsonPath('data.format', 'csv')
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.download_available', true);

    $reportId = $response->json('data.id');
    $report = ReportJob::query()->findOrFail($reportId);
    expect($report->private_object_key)->toStartWith('reports/'.$owner->uuid.'/')
        ->and($response->json('data'))->not->toHaveKey('private_object_key')
        ->and($report->request_id)->toBe($requestId);
    expect(AuditEvent::query()->where('event_name', 'report.requested')->where('aggregate_id', $report->id)->sole()->request_id)->toBe($requestId)
        ->and(AuditEvent::query()->where('event_name', 'report.completed')->where('aggregate_id', $report->id)->sole()->request_id)->toBe($requestId);
    Storage::disk('minio')->assertExists($report->private_object_key);

    $download = $this->withToken(reportToken($owner))->get("/api/v1/reports/{$reportId}/download")
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    expect($download->streamedContent())->toContain('Transaction Report')->toContain('1234');
});

test('report endpoints require authentication, validate compatible formats, and conceal another users report', function (): void {
    $owner = reportUser();
    $other = reportUser();
    $report = ReportJob::factory()->for($owner)->create(['base_currency_code' => 'ETB']);
    expect($owner->getKey())->not->toBe($other->getKey())
        ->and($report->user_id)->toBe($owner->getKey());

    $this->postJson('/api/v1/reports', ['type' => 'transaction', 'format' => 'csv'])->assertUnauthorized();
    $invalidFormat = $this->withToken(reportToken($owner))->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/reports', ['type' => 'full_account_export', 'format' => 'json'])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'VALIDATION_FAILED');
    expect($invalidFormat->json('error.fields'))->toHaveKey('format');
    $this->actingAs($other, 'sanctum')->getJson("/api/v1/reports/{$report->id}")->assertNotFound()->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
    $this->actingAs($other, 'sanctum')->get("/api/v1/reports/{$report->id}/download")->assertNotFound()->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
});

test('report filters cannot reference resources owned by another user', function (): void {
    $owner = reportUser();
    $other = reportUser();
    $foreignAccount = FinancialAccount::factory()->for($other)->create(['currency_code' => 'ETB']);

    $response = $this->withToken(reportToken($owner))->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/reports', [
        'type' => 'transaction', 'format' => 'csv', 'financial_account_id' => $foreignAccount->id,
    ])->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_FAILED');

    expect($response->json('error.fields'))->toHaveKey('financial_account_id');
});

test('large CSV requests and all non-CSV report formats are queued, while every standard report type has a deterministic renderer', function (): void {
    Queue::fake();
    $user = reportUser();
    config()->set('reports.synchronous_csv_row_limit', 0);
    $account = FinancialAccount::factory()->for($user)->create(['currency_code' => 'ETB']);
    $category = Category::factory()->for($user)->create(['kind' => 'expense', 'budget_currency_code' => 'ETB']);
    postedReportExpense($user, $account, $category, 100, '2026-08-10T10:00:00Z');
    $response = $this->withToken(reportToken($user))->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/reports', [
        'type' => 'transaction', 'format' => 'csv',
    ])->assertAccepted()->assertJsonPath('data.status', 'queued');
    Queue::assertPushed(GenerateReport::class, fn (GenerateReport $job): bool => $job->reportJobId === $response->json('data.id'));

    $service = app(ReportService::class);
    foreach (['transaction', 'account_statement', 'budget', 'expense', 'income', 'merchant', 'item_price', 'multi_currency'] as $type) {
        $report = ReportJob::factory()->for($user)->create(['type' => $type, 'format' => 'json', 'base_currency_code' => 'ETB', 'timezone' => $user->budget_timezone]);
        $service->generate($report->id, 'json-'.$type);
        $payload = json_decode(Storage::disk('minio')->get($report->fresh()->private_object_key), true, flags: JSON_THROW_ON_ERROR);
        expect($payload)->toHaveKeys(['title', 'columns', 'rows', 'summary', 'context']);
    }

    foreach ([['xlsx', 'PK'], ['pdf', '%PDF']] as [$format, $prefix]) {
        $report = ReportJob::factory()->for($user)->create(['type' => 'transaction', 'format' => $format, 'base_currency_code' => 'ETB', 'timezone' => $user->budget_timezone]);
        $service->generate($report->id, $format.'-report');
        expect(Storage::disk('minio')->get($report->fresh()->private_object_key))->toStartWith($prefix);
    }
});

test('a full-account export is queued, contains the full JSON manifest and original receipt media, and remains private', function (): void {
    Queue::fake();
    $user = reportUser();
    $receipt = Receipt::factory()->for($user)->create(['extension' => 'jpg']);
    Storage::disk('minio')->put($receipt->original_object_key, 'original-receipt-media', ['visibility' => 'private']);
    $response = $this->withToken(reportToken($user))->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/reports', ['type' => 'full_account_export', 'format' => 'zip'])
        ->assertAccepted()
        ->assertJsonPath('data.status', 'queued')
        ->assertJsonPath('data.type', 'full_account_export');
    $report = ReportJob::query()->findOrFail($response->json('data.id'));

    app(ReportService::class)->generate($report->id, 'test-report-job');
    $report->refresh();
    expect($report->status)->toBe('completed')
        ->and($report->private_object_key)->toStartWith('exports/full-account/'.$user->uuid.'/');
    $archive = Storage::disk('minio')->get($report->private_object_key);
    $path = tempnam(sys_get_temp_dir(), 'report-zip-');
    file_put_contents($path, $archive);
    $zip = new ZipArchive;
    expect($zip->open($path))->toBe(true)
        ->and($zip->locateName('manifest.json'))->not->toBeFalse()
        ->and($zip->locateName('data.json'))->not->toBeFalse()
        ->and($zip->getFromName('receipts/'.$receipt->id.'.jpg'))->toBe('original-receipt-media');
    $zip->close();
    unlink($path);
});

test('full JSON data export preserves identifiers and relationships without calling it a backup', function (): void {
    Queue::fake();
    $user = reportUser();
    $account = FinancialAccount::factory()->for($user)->create(['currency_code' => 'ETB']);
    $category = Category::factory()->for($user)->create(['kind' => 'expense', 'budget_currency_code' => 'ETB']);
    $transaction = postedReportExpense($user, $account, $category, 500, '2026-08-11T10:00:00Z');
    $report = ReportJob::factory()->for($user)->create(['type' => 'full_json_data_export', 'format' => 'json', 'base_currency_code' => 'ETB', 'timezone' => $user->budget_timezone]);

    app(ReportService::class)->generate($report->id, 'full-json-job');
    $payload = json_decode(Storage::disk('minio')->get($report->fresh()->private_object_key), true, flags: JSON_THROW_ON_ERROR);

    expect($payload['export_type'])->toBe('full_json_data_export')
        ->and($payload['entities']['financial_accounts'][0]['id'])->toBe($account->id)
        ->and(collect($payload['entities']['financial_transactions'])->pluck('id'))->toContain($transaction['id'])
        ->and(json_encode($payload))->not->toContain('backup');
});
