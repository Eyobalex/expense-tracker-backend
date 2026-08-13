<?php

namespace App\Providers;

use App\Domain\Receipts\Contracts\ReceiptOcrProvider;
use App\Domain\Shared\Events\DomainEventBus;
use App\Domain\Shared\Time\Clock;
use App\Domain\Shared\Time\SystemClock;
use App\Infrastructure\Events\LaravelDomainEventBus;
use App\Infrastructure\Ocr\HttpPaddleOcrProvider;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Clock::class, SystemClock::class);
        $this->app->singleton(DomainEventBus::class, LaravelDomainEventBus::class);
        $this->app->bind(ReceiptOcrProvider::class, HttpPaddleOcrProvider::class);
    }

    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureApiRateLimits();
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(app()->isProduction());

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    private function configureApiRateLimits(): void
    {
        RateLimiter::for('auth-api', fn (Request $request): Limit => Limit::perMinute(5)->by(mb_strtolower((string) $request->input('email', '')).'|'.$request->ip()));
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(120)->by((string) ($request->user()?->getKey() ?? $request->ip())));
        RateLimiter::for('receipt-upload', fn (Request $request): Limit => Limit::perMinute(10)->by((string) ($request->user()?->getKey() ?? $request->ip())));
    }
}
