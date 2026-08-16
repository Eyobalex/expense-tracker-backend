<?php

namespace App\Domain\Reporting\Enums;

enum ReportType: string
{
    case Transaction = 'transaction';
    case AccountStatement = 'account_statement';
    case Budget = 'budget';
    case Expense = 'expense';
    case Income = 'income';
    case Merchant = 'merchant';
    case ItemPrice = 'item_price';
    case MultiCurrency = 'multi_currency';
    case FullJsonDataExport = 'full_json_data_export';
    case FullAccountExport = 'full_account_export';

    public function isPortabilityExport(): bool
    {
        return in_array($this, [self::FullJsonDataExport, self::FullAccountExport], true);
    }
}
