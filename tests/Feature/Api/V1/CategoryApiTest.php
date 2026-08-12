<?php

namespace Tests\Feature\Api\V1;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_parent_child_and_archive_restore_categories(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test', ['api'])->plainTextToken;
        $parent = $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/categories', ['name' => 'Living', 'kind' => 'expense'])
            ->assertCreated()->json('data');

        $child = $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/categories', ['name' => 'Food', 'kind' => 'expense', 'parent_id' => $parent['id'], 'budget_enabled' => true, 'base_limit_minor_units' => 10000])
            ->assertCreated()->assertJsonPath('data.parent_id', $parent['id'])->json('data');

        $this->withToken($token)->withHeader('If-Match', '1')->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/v1/categories/{$child['id']}/archive")
            ->assertOk()->assertJsonPath('data.is_active', false)->assertJsonPath('data.version', 2);
        $this->withToken($token)->withHeader('If-Match', '2')->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson("/api/v1/categories/{$child['id']}/restore")
            ->assertOk()->assertJsonPath('data.is_active', true)->assertJsonPath('data.version', 3);
    }

    public function test_category_parent_must_be_owned_active_and_same_kind(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test', ['api'])->plainTextToken;
        $incomeParent = Category::factory()->for($user)->create(['kind' => 'income']);

        $this->withToken($token)->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/categories', ['name' => 'Food', 'kind' => 'expense', 'parent_id' => $incomeParent->id])
            ->assertUnprocessable()->assertJsonPath('error.code', 'INVALID_STATE_TRANSITION');
    }

    public function test_another_user_cannot_view_a_category(): void
    {
        $category = Category::factory()->create();
        $other = User::factory()->create();

        $this->withToken($other->createToken('test', ['api'])->plainTextToken)
            ->getJson("/api/v1/categories/{$category->id}")
            ->assertNotFound()->assertJsonPath('error.code', 'RESOURCE_NOT_FOUND');
    }
}
