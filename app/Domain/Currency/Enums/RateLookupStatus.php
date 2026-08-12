<?php

namespace App\Domain\Currency\Enums;

enum RateLookupStatus: string
{
    case Exact = 'exact';
    case LatestValid = 'latest_valid';
    case Stale = 'stale';
    case Unavailable = 'unavailable';
}
