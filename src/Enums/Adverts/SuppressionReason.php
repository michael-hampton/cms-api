<?php

namespace App\Enums\Adverts;

enum SuppressionReason: string
{
    case OFFER_INACTIVE = 'offer_inactive';
    case OFFER_NOT_STARTED = 'offer_not_started';
    case OFFER_EXPIRED = 'offer_expired';
    case REQUIRES_PAID_MEMBERSHIP = 'requires_paid_membership';
    case PLAN_MISMATCH = 'plan_mismatch';
    case SEGMENT_MISMATCH = 'segment_mismatch';
    case NOT_AUTHENTICATED = 'not_authenticated';
    case WRONG_MEMBER = 'wrong_member';
    case ALREADY_CLAIMED = 'already_claimed';
    case REWARD_EXPIRED = 'reward_expired';
    case REWARD_DECLINED = 'reward_declined';
    case INVALID_STATUS = 'invalid_status';
}