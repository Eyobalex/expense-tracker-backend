<?php

namespace App\Domain\Accounting;

use App\Domain\Accounting\Enums\JournalType;
use App\Domain\Currency\ValueObjects\CurrencyCode;

final readonly class JournalEntryDefinition
{
    /** @param list<JournalLineDefinition> $lines */
    public function __construct(
        public JournalType $type,
        public CurrencyCode $functionalCurrency,
        public array $lines,
    ) {}
}
