<?php

use App\Domain\Accounting\ValueObjects\Money;
use App\Domain\Currency\Enums\RoundingMode;
use App\Domain\Currency\ValueObjects\CurrencyCode;
use App\Domain\Currency\ValueObjects\CurrencyMetadata;
use App\Domain\Currency\ValueObjects\ExchangeRate;
use App\Domain\Shared\Audit\AuditEvent;
use App\Domain\Shared\Audit\AuditPayloadRedactor;
use App\Domain\Shared\Exceptions\DomainErrorCode;
use App\Domain\Shared\Exceptions\DomainException;
use App\Domain\Shared\Time\FrozenClock;
use App\Domain\Shared\Time\MonthlyPeriod;
use App\Domain\Shared\ValueObjects\OperationId;
use App\Domain\Shared\ValueObjects\Uuid;

test('money uses minor units and rejects currency mismatch', function (): void {
    $usd = CurrencyCode::fromString('usd');
    $etb = CurrencyCode::fromString('ETB');

    $this->assertSame(375, (new Money(500, $usd))->subtract(new Money(125, $usd))->minorUnits);

    $this->expectException(DomainException::class);
    $this->expectExceptionMessage('Money arithmetic requires matching currencies.');

    (new Money(500, $usd))->add(new Money(100, $etb));
});

test('exchange rates convert exactly and apply the requested rounding mode', function (): void {
    $jpy = new CurrencyMetadata(CurrencyCode::fromString('JPY'), 0);
    $usd = new CurrencyMetadata(CurrencyCode::fromString('USD'), 2);
    $rate = ExchangeRate::fromDecimal('1.005');

    $this->assertSame(101, $rate->convert(new Money(1, $jpy->code), $jpy, $usd, RoundingMode::HalfUp)->minorUnits);
    $this->assertSame(100, $rate->convert(new Money(1, $jpy->code), $jpy, $usd, RoundingMode::HalfEven)->minorUnits);
    $this->assertSame('1.005', $rate->decimal());
});

test('invalid currency and rate input return stable domain errors', function (): void {
    try {
        CurrencyCode::fromString('US');
        $this->fail('Invalid currency input should fail.');
    } catch (DomainException $exception) {
        $this->assertSame(DomainErrorCode::InvalidCurrencyCode, $exception->errorCode());
    }

    try {
        ExchangeRate::fromDecimal('0');
        $this->fail('Zero exchange rate should fail.');
    } catch (DomainException $exception) {
        $this->assertSame(DomainErrorCode::InvalidExchangeRate, $exception->errorCode());
    }
});

test('uuid and operation ids are normalized and validated', function (): void {
    $value = '9B6DD41D-4A0D-4E09-8B0F-ED9C181B758D';
    $uuid = Uuid::fromString($value);
    $operation = OperationId::fromString($value);

    $this->assertSame(strtolower($value), $uuid->toString());
    $this->assertSame(strtolower($value), $operation->toString());

    try {
        OperationId::fromString('not-a-uuid');
        $this->fail('Invalid operation ID should fail.');
    } catch (DomainException $exception) {
        $this->assertSame(DomainErrorCode::InvalidOperationId, $exception->errorCode());
    }
});

test('monthly periods snapshot timezone boundaries and injected clocks are deterministic', function (): void {
    $period = MonthlyPeriod::forMonth(2026, 3, 'America/New_York');
    $clock = new FrozenClock(new DateTimeImmutable('2026-03-31T23:30:00-04:00'));

    $this->assertSame('2026-03-01T00:00:00-05:00', $period->startsAt()->format(DATE_ATOM));
    $this->assertSame('2026-04-01T00:00:00-04:00', $period->endsAt()->format(DATE_ATOM));
    $this->assertTrue($period->contains($clock->now()));

    $clock->travelTo(new DateTimeImmutable('2026-04-01T00:00:00-04:00'));
    $this->assertFalse($period->contains($clock->now()));
});

test('audit payloads redact secrets and raw sensitive artifacts recursively', function (): void {
    $event = new AuditEvent(
        'transaction.posted',
        'transaction',
        'transaction-uuid',
        12,
        'device-uuid',
        OperationId::fromString('9b6dd41d-4a0d-4e09-8b0f-ed9c181b758d'),
        new DateTimeImmutable('2026-08-12T00:00:00Z'),
        ['token' => 'secret', 'nested' => ['raw_ocr_response' => 'sensitive']],
        ['minor_units' => 500],
    );

    $payload = $event->toRedactedPayload(new AuditPayloadRedactor);

    $this->assertSame('[REDACTED]', $payload['before']['token']);
    $this->assertSame('[REDACTED]', $payload['before']['nested']['raw_ocr_response']);
    $this->assertSame(500, $payload['after']['minor_units']);
});
