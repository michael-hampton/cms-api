<?php

namespace App\Enums\OpenCollab;

enum EarningsAdjustmentSource: string
{
    case RevenueReversal = 'revenue_reversal';
    case ContentTakedown = 'content_takedown';
    case ManualFinanceAdjustment = 'manual_finance_adjustment';
    case DisputeResolution = 'dispute_resolution';
    case Clawback = 'clawback';
    case ModerationAction = 'moderation_action';
}