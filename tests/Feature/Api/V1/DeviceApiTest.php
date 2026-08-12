<?php

namespace Tests\Feature\Api\V1;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeviceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_cannot_revoke_another_users_device(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $device = Device::factory()->for($owner)->create();
        $token = $otherUser->createToken('test', ['api'])->plainTextToken;

        $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())->deleteJson('/api/v1/devices/'.$device->getKey())
            ->assertNotFound()
            ->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');

        $this->assertNull($device->fresh()->revoked_at);
    }

    public function test_logout_revokes_the_active_token_and_device_session(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test', ['api']);
        $device = Device::factory()->for($user)->create(['personal_access_token_id' => $token->accessToken->getKey()]);

        $this->withToken($token->plainTextToken)->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('data.revoked', true);

        $this->assertNotNull($device->fresh()->revoked_at);
        $this->withToken($token->plainTextToken)->getJson('/api/v1/me')->assertUnauthorized();
    }
}
