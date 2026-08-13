<?php

use App\Application\Currency\CurrencyRegistry;
use App\Application\Currency\RateLockService;
use App\Domain\Accounting\ValueObjects\Money;
use App\Domain\Currency\Enums\RoundingMode;
use App\Domain\Currency\ValueObjects\CurrencyCode;
use App\Domain\Shared\Exceptions\DomainErrorCode;
use App\Domain\Shared\Exceptions\DomainException;
use App\Models\Currency;
use App\Models\FinancialAccount;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
beforeEach(function (): void {
    $this->seedCurrencies();
});

test('seeded active registry returns iso metadata and exponents', function (): void {
    $registry = app(CurrencyRegistry::class);

    $this->assertSame(2, $registry->activeMetadata('ETB')->exponent);
    $this->assertSame(0, $registry->activeMetadata('JPY')->exponent);
    $this->assertSame('Ethiopian Birr', $registry->activeMetadata('ETB')->displayName);
});

test('inactive or unknown currencies are rejected', function (): void {
    Currency::query()->whereKey('USD')->update(['is_active' => false]);
    $registry = app(CurrencyRegistry::class);

    foreach (['USD' => DomainErrorCode::InactiveCurrency, 'ZZZ' => DomainErrorCode::UnsupportedCurrency] as $currency => $error) {
        try {
            $registry->activeMetadata($currency);
            $this->fail('An inactive or unsupported currency must be rejected.');
        } catch (DomainException $exception) {
            $this->assertSame($error, $exception->errorCode());
        }
    }
});

test('currency foreign key rejects unknown account currency', function (): void {
    $user = User::factory()->create();

    $this->expectException(QueryException::class);

    FinancialAccount::factory()->for($user)->create(['currency_code' => 'ZZZ']);
});

test('currency foreign key rejects unknown user base currency', function (): void {
    $this->expectException(QueryException::class);

    User::factory()->create(['base_currency_code' => 'ZZZ']);
});

test('rate lock converts with registry exponents and keeps the used rate', function (): void {
    $service = app(RateLockService::class);
    $locked = $service->lock(
        CurrencyCode::fromString('ETB'),
        CurrencyCode::fromString('USD'),
        '0.00715',
        new DateTimeImmutable('2026-08-12T00:00:00Z'),
        'manual',
        RoundingMode::HalfUp,
        '0.00710',
        'Receipt settlement rate',
    );

    $baseAmount = $service->convertToBase(
        new Money(100_00, CurrencyCode::fromString('ETB')),
        $locked,
    );

    $this->assertSame(72, $baseAmount->minorUnits);
    $this->assertSame('USD', $baseAmount->currency->toString());
    $this->assertSame('0.00715', $locked->usedRate->decimal());
});
