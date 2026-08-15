<?php

namespace App\Domain\Insights\Enums;

enum FormulaName: string
{
    case SafeToSpend = 'safe_to_spend';
    case CategoryAwareProjectedSpend = 'category_aware_projected_spend';
    case IncomeConcentration = 'income_concentration';
    case ItemPriceMovement = 'item_price_movement';
}
