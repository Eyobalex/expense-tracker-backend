<?php

namespace Tests\Feature\Api\V1;

use App\Models\IdempotencyOperation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_is_authenticated_and_uses_a_stable_versioned_resource(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test', ['api'])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->uuid)
            ->assertJsonPath('data.version', 1);
    }

    public function test_stale_profile_versions_return_a_conflict(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test', ['api'])->plainTextToken;

        $this->withToken($token)
            ->withHeader('If-Match', '0')
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->patchJson('/api/v1/me', ['name' => 'Updated Name', 'email' => $user->email])
            ->assertConflict()
            ->assertJsonPath('error.code', 'STALE_VERSION');
    }

    public function test_idempotent_profile_retry_replays_the_original_response_without_another_version_increment(): void
    {
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
    }
}
