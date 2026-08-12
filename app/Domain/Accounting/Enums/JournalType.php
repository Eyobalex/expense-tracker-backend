<?php

namespace App\Domain\Accounting\Enums;

enum JournalType: string
{
    case Normal = 'normal';
    case OpeningBalance = 'opening_balance';
    case Reversal = 'reversal';
    case Correction = 'correction';
    case Fx = 'fx';
    case Fee = 'fee';
}
