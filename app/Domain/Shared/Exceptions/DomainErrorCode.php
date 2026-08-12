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
    case ConcurrencyConflict = 'CONCURRENCY_CONFLICT';
    case UnauthorizedAction = 'UNAUTHORIZED_ACTION';
    case ResourceNotFound = 'RESOURCE_NOT_FOUND';
}
