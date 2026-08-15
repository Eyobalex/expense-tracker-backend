<?php

namespace App\Domain\Insights\Enums;

enum ForecastBehavior: string
{
    case Fixed = 'fixed';
    case Periodic = 'periodic';
    case Variable = 'variable';
}
