<?php

namespace App\Domain\Budgeting\Enums;

enum BudgetAdjustmentType: string
{
    case Rollover = 'rollover';
    case Underflow = 'underflow';
    case ReallocationIn = 'reallocation_in';
    case ReallocationOut = 'reallocation_out';
    case BorrowingIn = 'borrowing_in';
    case BorrowingReserved = 'borrowing_reserved';
    case Correction = 'correction';
}
