<?php

use App\Application\Notifications\NotificationDeliveryService;
use App\Application\Notifications\NotificationService;
use App\Domain\Notifications\Enums\NotificationType;
use App\Jobs\DeliverUserNotification;
use App\Mail\FinancialInsightNotificationMail;
use App\Models\Device;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seedCurrencies();
});

function notificationUser(array $overrides = []): User
{
    return User::factory()->create(array_merge(['base_currency_code' => 'ETB'], $overrides));
}

function notificationToken(User $user): string
{
    return $user->createToken('notification-test', ['api'])->plainTextToken;
}

test('notification preferences are user scoped, validated, and optimistic-concurrency protected', function (): void {
    $user = notificationUser();
    $token = notificationToken($user);

    $this->withToken($token)->getJson('/api/v1/notification-preferences')
        ->assertOk()
        ->assertJsonPath('data.preferences.budget_thresholds.0', 50)
        ->assertJsonPath('data.preferences.channels.email', true);
    $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())->withHeader('If-Match', '1')
        ->putJson('/api/v1/notification-preferences', ['channels' => ['email' => false], 'budget_thresholds' => [75, 100]])
        ->assertOk()
        ->assertJsonPath('data.preferences.channels.email', false)
        ->assertJsonPath('data.preferences.budget_thresholds', [75, 100]);
    $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())->withHeader('If-Match', '1')
        ->putJson('/api/v1/notification-preferences', ['enabled' => false])
        ->assertConflict()
        ->assertJsonPath('error.code', 'STALE_VERSION');
    $invalid = $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())->withHeader('If-Match', '2')
        ->putJson('/api/v1/notification-preferences', ['budget_thresholds' => [101]])
        ->assertUnprocessable();
    expect($invalid->json('error.fields'))->toHaveKey('budget_thresholds.0');
});

test('the in-app inbox is owned, exposes local payload delivery state, and marks notifications read', function (): void {
    $owner = notificationUser();
    $other = notificationUser();
    $notification = UserNotification::factory()->for($owner, 'user')->create(['payload' => ['schema_version' => 1, 'route' => 'budget']]);
    NotificationDelivery::factory()->for($notification, 'notification')->create(['channel' => 'local', 'status' => 'delivered']);
    $otherNotification = UserNotification::factory()->for($other, 'user')->create();
    $token = notificationToken($owner);

    $this->withToken($token)->getJson('/api/v1/notifications?unread_only=1')
        ->assertOk()
        ->assertJsonCount(1, 'data.notifications')
        ->assertJsonPath('data.notifications.0.id', $notification->id)
        ->assertJsonPath('data.notifications.0.deliveries.0.channel', 'local');
    $read = $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())->withHeader('If-Match', '1')
        ->postJson("/api/v1/notifications/{$notification->id}/read")
        ->assertOk()
        ->assertJsonPath('data.version', 2);
    expect($read->json('data.read_at'))->not()->toBeNull();
    $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())->withHeader('If-Match', '1')
        ->postJson("/api/v1/notifications/{$notification->id}/read")
        ->assertConflict()
        ->assertJsonPath('error.code', 'CONCURRENCY_CONFLICT');
    $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())->withHeader('If-Match', '1')
        ->postJson("/api/v1/notifications/{$otherNotification->id}/read")
        ->assertNotFound()
        ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
});

test('only the active authenticated device may register an encrypted FCM token', function (): void {
    $user = notificationUser();
    $token = $user->createToken('notification-device', ['api']);
    $device = Device::factory()->for($user)->create(['personal_access_token_id' => $token->accessToken->getKey()]);
    $otherDevice = Device::factory()->for($user)->create();

    $this->withToken($token->plainTextToken)->withHeader('Idempotency-Key', (string) Str::uuid())
        ->putJson("/api/v1/devices/{$device->id}/push-token", ['push_token' => 'fcm-registration-token', 'provider' => 'fcm'])
        ->assertOk()
        ->assertJsonPath('data.push_token_registered', true);
    expect($device->fresh()->push_token)->toBe('fcm-registration-token')
        ->and($device->fresh()->push_token_provider)->toBe('fcm');
    $this->withToken($token->plainTextToken)->withHeader('Idempotency-Key', (string) Str::uuid())
        ->putJson("/api/v1/devices/{$otherDevice->id}/push-token", ['push_token' => 'not-allowed', 'provider' => 'fcm'])
        ->assertNotFound();
});

test('notification creation is deduplicated, snapshots financial insights, and queues enabled delivery channels', function (): void {
    Queue::fake();
    $user = notificationUser(['notification_preferences' => ['channels' => ['push' => false]]]);
    Device::factory()->for($user)->create();
    $service = app(NotificationService::class);
    $insight = ['formula' => ['name' => 'category_aware_projected_spend', 'version' => 1], 'timezone' => 'Africa/Addis_Ababa', 'base_currency_code' => 'ETB', 'calculated_at' => '2026-08-16T09:00:00+00:00', 'projected_month_end_spend_minor_units' => 12000];

    $first = $service->create($user, NotificationType::ProjectedBudgetOverspend, 'forecast:2026-08', 'Projected budget overspend', 'Projection exceeds budget.', ['period' => ['year' => 2026, 'month' => 8]], $insight);
    $second = $service->create($user, NotificationType::ProjectedBudgetOverspend, 'forecast:2026-08', 'Projected budget overspend', 'Projection exceeds budget.', ['period' => ['year' => 2026, 'month' => 8]], $insight);

    expect($first?->id)->toBe($second?->id);
    $this->assertDatabaseCount('user_notifications', 1);
    $this->assertDatabaseCount('insight_snapshots', 1);
    $this->assertDatabaseCount('notification_deliveries', 3);
    Queue::assertPushed(DeliverUserNotification::class, 2);
});

test('email and FCM adapters deliver through queued delivery records without exposing tokens', function (): void {
    Queue::fake();
    Mail::fake();
    Http::preventStrayRequests();
    Http::fake(['https://fcm.googleapis.com/*' => Http::response(['name' => 'projects/test/messages/1'], 200)]);
    config()->set('services.firebase.project_id', 'expense-tracker');
    config()->set('services.firebase.access_token', 'short-lived-access-token');
    $user = notificationUser();
    $device = Device::factory()->for($user)->create(['push_token' => 'device-token', 'push_token_provider' => 'fcm']);
    $notification = app(NotificationService::class)->create($user, NotificationType::BudgetThreshold, 'budget:2026-08:75', 'Budget threshold reached', 'A budget category reached a threshold.', ['category_id' => (string) Str::uuid()]);
    $deliveries = $notification->deliveries()->whereIn('channel', ['email', 'push'])->get()->keyBy('channel');

    app(NotificationDeliveryService::class)->deliver($deliveries['email']->id, 'email-job');
    app(NotificationDeliveryService::class)->deliver($deliveries['push']->id, 'push-job');

    Mail::assertSent(FinancialInsightNotificationMail::class);
    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer short-lived-access-token') && $request['message']['token'] === $device->push_token);
    expect($deliveries['email']->fresh()->status)->toBe('delivered')
        ->and($deliveries['push']->fresh()->status)->toBe('delivered');
});

test('unconfigured FCM is recorded as a skipped delivery and exhausted delivery jobs are audited', function (): void {
    Queue::fake();
    config()->set('services.firebase.project_id', null);
    config()->set('services.firebase.access_token', null);
    config()->set('services.firebase.service_account_credentials_path', null);
    $user = notificationUser();
    Device::factory()->for($user)->create(['push_token' => 'device-token', 'push_token_provider' => 'fcm']);
    $notification = app(NotificationService::class)->create($user, NotificationType::BudgetThreshold, 'budget:unconfigured-fcm', 'Budget threshold reached', 'A budget category reached a threshold.', []);
    $push = $notification->deliveries()->where('channel', 'push')->firstOrFail();
    $email = $notification->deliveries()->where('channel', 'email')->firstOrFail();

    app(NotificationDeliveryService::class)->deliver($push->id, 'push-job');
    (new DeliverUserNotification($email->id))->failed(new RuntimeException('mail provider unavailable'));

    expect($push->fresh()->status)->toBe('skipped')
        ->and($push->fresh()->failure_code)->toBe('FCM_NOT_CONFIGURED')
        ->and($email->fresh()->status)->toBe('failed');
    $this->assertDatabaseHas('audit_events', ['user_id' => $user->id, 'event_name' => 'notification.delivery_failed', 'aggregate_id' => $notification->id]);
});

test('a disabled notification type creates neither persistent notification nor delivery work', function (): void {
    Queue::fake();
    $user = notificationUser(['notification_preferences' => ['types' => ['budget_threshold' => false]]]);

    $notification = app(NotificationService::class)->create($user, NotificationType::BudgetThreshold, 'budget:disabled', 'Budget threshold reached', 'A budget category reached a threshold.', []);

    expect($notification)->toBeNull();
    $this->assertDatabaseCount('user_notifications', 0);
    Queue::assertNothingPushed();
});
