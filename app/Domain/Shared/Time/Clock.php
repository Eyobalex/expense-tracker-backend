<?php

namespace App\Domain\Shared\Time;

use DateTimeImmutable;

interface Clock
{
    public function now(): DateTimeImmutable;
}
