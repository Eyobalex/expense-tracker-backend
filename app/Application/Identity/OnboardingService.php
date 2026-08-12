<?php

namespace App\Application\Identity;

use App\Application\Accounts\FinancialHistoryInspector;
use App\Application\Accounts\StarterDataService;
use App\Domain\Shared\Exceptions\DomainErrorCode;
use App\Domain\Shared\Exceptions\DomainException;
use App\Models\User;
use DateTimeZone;
use Illuminate\Support\Facades\DB;

final readonly class OnboardingService
{
    public function __construct(
        private FinancialHistoryInspector $financialHistory,
        private StarterDataService $starterData,
    ) {}

    /** @param array{base_currency_code: string, timezone: string, budget_timezone: string, notification_preferences?: array<string, mixed>, seed_starter_data?: bool} $attributes */
    public function update(User $user, int $expectedVersion, array $attributes): User
    {
        $baseCurrency = strtoupper($attributes['base_currency_code']);
        if ($user->base_currency_code !== null
            && $baseCurrency !== $user->base_currency_code
            && $this->financialHistory->userHasPostedTransactions($user)) {
            throw DomainException::for(DomainErrorCode::BaseCurrencyLocked, 'The base currency cannot change after posted financial history exists.');
        }

        $this->assertTimezone($attributes['timezone']);
        $this->assertTimezone($attributes['budget_timezone']);

        return DB::transaction(function () use ($user, $expectedVersion, $attributes, $baseCurrency): User {
            $updated = User::query()->whereKey($user->getKey())->where('version', $expectedVersion)->update([
                'base_currency_code' => $baseCurrency,
                'timezone' => $attributes['timezone'],
                'budget_timezone' => $attributes['budget_timezone'],
                'notification_preferences' => $attributes['notification_preferences'] ?? $user->notification_preferences,
                'onboarding_completed' => true,
                'version' => $expectedVersion + 1,
            ]);
            if ($updated !== 1) {
                throw DomainException::for(DomainErrorCode::ConcurrencyConflict, 'The onboarding version is stale.');
            }

            if ($attributes['seed_starter_data'] ?? true) {
                $this->starterData->seedFor($user);
            }

            return $user->refresh();
        });
    }

    private function assertTimezone(string $timezone): void
    {
        try {
            new DateTimeZone($timezone);
        } catch (\Exception) {
            throw DomainException::for(DomainErrorCode::InvalidTimezone, 'A valid IANA timezone is required.');
        }
    }
}
