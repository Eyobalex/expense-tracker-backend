<?php

namespace App\Domain\Shared\Exceptions;

enum DomainErrorCode: string
{
    case InvalidMoney = 'INVALID_MONEY';
    case CurrencyMismatch = 'CURRENCY_MISMATCH';
    case InvalidCurrencyCode = 'INVALID_CURRENCY_CODE';
    case InvalidCurrencyExponent = 'INVALID_CURRENCY_EXPONENT';
    case InvalidExchangeRate = 'INVALID_EXCHANGE_RATE';
    case InvalidOperationId = 'INVALID_OPERATION_ID';
    case InvalidUuid = 'INVALID_UUID';
    case InvalidPeriod = 'INVALID_PERIOD';
    case InvalidTimezone = 'INVALID_TIMEZONE';
    case InvalidStateTransition = 'INVALID_STATE_TRANSITION';
    case BaseCurrencyLocked = 'BASE_CURRENCY_LOCKED';
    case AccountCurrencyLocked = 'ACCOUNT_CURRENCY_LOCKED';
    case UnsupportedCurrency = 'UNSUPPORTED_CURRENCY';
    case InactiveCurrency = 'INACTIVE_CURRENCY';
    case InvalidRateLock = 'INVALID_RATE_LOCK';
    case SyncCursorExpired = 'SYNC_CURSOR_EXPIRED';
    case SyncDependencyUnresolved = 'SYNC_DEPENDENCY_UNRESOLVED';
    case SyncDeviceInvalid = 'SYNC_DEVICE_INVALID';
    case ConcurrencyConflict = 'CONCURRENCY_CONFLICT';
    case UnauthorizedAction = 'UNAUTHORIZED_ACTION';
    case ResourceNotFound = 'RESOURCE_NOT_FOUND';
}
