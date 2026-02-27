<?php

namespace App\Enums\Promotions;

enum GiftPromotionTriggerType: string
{
    case SUBSCRIPTION_PLAN = 'subscription_plan';
    case SUBSCRIPTION_COUNT = 'subscription_count';
    case ISSUE_NUMBER = 'issue_number';
    case MEMBER_TAG = 'member_tag';
    case RENEWAL_COUNT = 'renewal_count';

    public function label(): string
    {
        return match ($this) {
            self::SUBSCRIPTION_PLAN => 'Subscription Plan',
            self::SUBSCRIPTION_COUNT => 'Subscription Count',
            self::ISSUE_NUMBER => 'Issue Number',
            self::MEMBER_TAG => 'Member Tag',
            self::RENEWAL_COUNT => 'Renewal Count',
        };
    }

    /** Operators valid for this trigger type */
    public function allowedOperators(): array
    {
        return match ($this) {
            self::SUBSCRIPTION_PLAN, self::MEMBER_TAG => [
                GiftPromotionTriggerOperator::EQUALS,
                GiftPromotionTriggerOperator::NOT_EQUALS,
            ],
            self::SUBSCRIPTION_COUNT, self::ISSUE_NUMBER, self::RENEWAL_COUNT => [
                GiftPromotionTriggerOperator::EQUALS,
                GiftPromotionTriggerOperator::GREATER_THAN,
                GiftPromotionTriggerOperator::LESS_THAN,
                GiftPromotionTriggerOperator::GREATER_THAN_OR_EQUAL,
                GiftPromotionTriggerOperator::LESS_THAN_OR_EQUAL,
            ],
        };
    }
}