<?php

use App\Models\IdempotencyOperation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);
test('profile is authenticated and uses a stable versioned resource', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['api'])->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->uuid)
        ->assertJsonPath('data.version', 1);
});

test('stale profile versions return a conflict', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['api'])->plainTextToken;

    $this->withToken($token)
        ->withHeader('If-Match', '0')
        ->withHeader('Idempotency-Key', (string) Str::uuid())
        ->patchJson('/api/v1/me', ['name' => 'Updated Name', 'email' => $user->email])
        ->assertConflict()
        ->assertJsonPath('error.code', 'STALE_VERSION');
});

test('idempotent profile retry replays the original response without another version increment', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['api'])->plainTextToken;
    $key = (string) Str::uuid();
    $payload = ['name' => 'Updated Name', 'email' => $user->email];

    $firstResponse = $this->withToken($token)
        ->withHeader('If-Match', '1')
        ->withHeader('Idempotency-Key', $key)
        ->patchJson('/api/v1/me', $payload);

    $firstResponse->assertOk()->assertJsonPath('data.version', 2);

    $this->withToken($token)
        ->withHeader('If-Match', '1')
        ->withHeader('Idempotency-Key', $key)
        ->patchJson('/api/v1/me', $payload)
        ->assertOk()
        ->assertHeader('Idempotency-Replayed', 'true')
        ->assertJsonPath('data.version', 2);

    $this->assertSame(2, $user->fresh()->version);
    $this->assertDatabaseCount('idempotency_operations', 1);
    $this->assertNotNull(IdempotencyOperation::query()->value('completed_at'));
});
