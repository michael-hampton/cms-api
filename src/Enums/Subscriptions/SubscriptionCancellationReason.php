<?php

namespace App\Enums\Subscriptions;

enum SubscriptionCancellationReason: string
{
    case TooExpensive = 'too_expensive';
    case NotUsing = 'not_using';
    case SwitchingToOther = 'switching_to_other';
    case PausingTemporarily = 'pausing_temporarily';
    case TechnicalIssues = 'technical_issues';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::TooExpensive => 'It\'s too expensive',
            self::NotUsing => 'I\'m not using it enough',
            self::SwitchingToOther => 'I\'m switching to another service',
            self::PausingTemporarily => 'I\'m pausing for now',
            self::TechnicalIssues => 'Technical issues',
            self::Other => 'Other reason',
        };
    }
}