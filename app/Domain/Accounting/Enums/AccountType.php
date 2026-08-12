<?php

namespace App\Domain\Accounting\Enums;

enum AccountType: string
{
    case Cash = 'cash';
    case Bank = 'bank';
    case MobileWallet = 'mobile_wallet';
    case CreditCard = 'credit_card';
    case Loan = 'loan';
    case Asset = 'asset';
    case Liability = 'liability';
}
