<?php

namespace App\Domain\Transactions\Enums;

enum AdjustmentSubtype: string
{
    case BalanceCorrection = 'balance_correction';
    case FinancialSystemAdjustment = 'financial_system_adjustment';
}
