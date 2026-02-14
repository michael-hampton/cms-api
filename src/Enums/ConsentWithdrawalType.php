<?php

namespace App\Enums;

enum ConsentWithdrawalType: string
{
    case SPECIFIC_CONSENT = 'specific_consent';
    case ALL_MARKETING = 'all_marketing';
    case COMPLETE_DELETION = 'complete_deletion';
}