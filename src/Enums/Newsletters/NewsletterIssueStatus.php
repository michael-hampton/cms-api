<?php

namespace App\Enums\Newsletters;

enum NewsletterIssueStatus: string
{
    case Draft = 'draft';
    case Ready = 'ready';
    case Sent = 'sent';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Ready => 'Ready to Send',
            self::Sent => 'Sent',
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Draft => $target === self::Ready || $target === self::Sent,
            self::Ready => $target === self::Draft || $target === self::Sent,
            self::Sent => false,
        };
    }

    public function isSendable(): bool
    {
        return match ($this) {
            self::Draft, self::Ready => true,
            self::Sent => false,
        };
    }
}