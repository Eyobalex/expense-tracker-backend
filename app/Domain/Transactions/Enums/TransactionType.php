<?php

namespace App\Domain\Transactions\Enums;

enum TransactionType: string
{
    case Expense = 'expense';
    case Income = 'income';
    case Transfer = 'transfer';
    case CreditCardPurchase = 'credit_card_purchase';
    case CreditCardRepayment = 'credit_card_repayment';
    case Refund = 'refund';
    case Fee = 'fee';
    case OpeningBalance = 'opening_balance';
    case Adjustment = 'adjustment';
}
