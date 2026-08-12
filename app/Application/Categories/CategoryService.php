<?php

namespace App\Application\Categories;

use App\Domain\Shared\Exceptions\DomainErrorCode;
use App\Domain\Shared\Exceptions\DomainException;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CategoryService
{
    /** @param array{name: string, kind: string, parent_id?: string|null, budget_enabled?: bool, base_limit_minor_units?: int|null, rollover_enabled?: bool, overspend_carry_enabled?: bool, borrowing_enabled?: bool, budget_currency_code?: string|null} $attributes */
    public function create(User $user, array $attributes): Category
    {
        return DB::transaction(function () use ($user, $attributes): Category {
            $this->assertValidParent($user, $attributes['parent_id'] ?? null, $attributes['kind']);
            $this->assertBudgetCurrency($user, $attributes['budget_currency_code'] ?? $user->base_currency_code);

            $category = $user->categories()->create([
                'name' => $attributes['name'],
                'kind' => $attributes['kind'],
                'parent_id' => $attributes['parent_id'] ?? null,
                'budget_enabled' => $attributes['budget_enabled'] ?? false,
                'base_limit_minor_units' => $attributes['base_limit_minor_units'] ?? null,
                'rollover_enabled' => $attributes['rollover_enabled'] ?? false,
                'overspend_carry_enabled' => $attributes['overspend_carry_enabled'] ?? false,
                'borrowing_enabled' => $attributes['borrowing_enabled'] ?? false,
                'budget_currency_code' => $attributes['budget_currency_code'] ?? $user->base_currency_code,
            ]);

            return $category->refresh();
        });
    }

    /** @param array{name?: string, kind?: string, parent_id?: string|null, budget_enabled?: bool, base_limit_minor_units?: int|null, rollover_enabled?: bool, overspend_carry_enabled?: bool, borrowing_enabled?: bool, budget_currency_code?: string|null, is_active?: bool, archived_at?: mixed} $attributes */
    public function update(User $user, Category $category, int $expectedVersion, array $attributes): Category
    {
        if ($category->user_id !== $user->id) {
            throw DomainException::for(DomainErrorCode::ResourceNotFound, 'The category was not found.');
        }
        if ($category->is_system) {
            throw DomainException::for(DomainErrorCode::UnauthorizedAction, 'System categories cannot be changed through this endpoint.');
        }

        $kind = $attributes['kind'] ?? $category->kind;
        $parentId = array_key_exists('parent_id', $attributes) ? $attributes['parent_id'] : $category->parent_id;
        if ($parentId === $category->getKey()) {
            throw DomainException::for(DomainErrorCode::InvalidStateTransition, 'A category cannot be its own parent.');
        }
        if ($parentId !== null && $this->isDescendant($category, $parentId)) {
            throw DomainException::for(DomainErrorCode::InvalidStateTransition, 'A category cannot be assigned to one of its descendants.');
        }
        $this->assertValidParent($user, $parentId, $kind);
        if (array_key_exists('budget_currency_code', $attributes)) {
            $this->assertBudgetCurrency($user, $attributes['budget_currency_code']);
        }

        $attributes['version'] = $expectedVersion + 1;
        $updated = Category::query()->ownedBy($user)->whereKey($category->getKey())->where('version', $expectedVersion)->update($attributes);
        if ($updated !== 1) {
            throw DomainException::for(DomainErrorCode::ConcurrencyConflict, 'The category version is stale.');
        }

        /** @var Category $fresh */
        $fresh = $category->fresh();

        return $fresh;
    }

    public function archive(User $user, Category $category, int $expectedVersion): Category
    {
        return $this->update($user, $category, $expectedVersion, ['is_active' => false, 'archived_at' => now()]);
    }

    public function restore(User $user, Category $category, int $expectedVersion): Category
    {
        return $this->update($user, $category, $expectedVersion, ['is_active' => true, 'archived_at' => null]);
    }

    private function isDescendant(Category $category, string $candidateParentId): bool
    {
        $candidate = Category::query()->find($candidateParentId);
        while ($candidate instanceof Category) {
            if ($candidate->parent_id === $category->getKey()) {
                return true;
            }
            $candidate = $candidate->parent;
        }

        return false;
    }

    private function assertBudgetCurrency(User $user, ?string $currencyCode): void
    {
        if ($currencyCode !== null && $currencyCode !== $user->base_currency_code) {
            throw DomainException::for(DomainErrorCode::CurrencyMismatch, 'Budget limits are maintained in the user base currency.');
        }
    }

    private function assertValidParent(User $user, ?string $parentId, string $kind): void
    {
        if ($parentId === null) {
            return;
        }

        $parent = Category::query()->ownedBy($user)->find($parentId);
        if (! $parent instanceof Category || $parent->kind !== $kind || $parent->archived_at !== null) {
            throw DomainException::for(DomainErrorCode::InvalidStateTransition, 'A category parent must be an active category of the same kind owned by the user.');
        }
    }
}
