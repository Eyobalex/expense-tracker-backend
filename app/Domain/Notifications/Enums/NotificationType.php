<?php

namespace App\Domain\Notifications\Enums;

enum NotificationType: string
{
    case BudgetThreshold = 'budget_threshold';
    case ActualBudgetOverspend = 'actual_budget_overspend';
    case ProjectedBudgetOverspend = 'projected_budget_overspend';
    case BorrowingConsequence = 'borrowing_consequence';
    case FxRateStale = 'fx_rate_stale';
    case OcrFailed = 'ocr_failed';
    case ReportReady = 'report_ready';
    case ItemPriceMovement = 'item_price_movement';
}
