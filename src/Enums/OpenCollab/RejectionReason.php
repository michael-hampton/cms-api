<?php

namespace App\Enums\OpenCollab;

enum RejectionReason: string
{
    case PolicyViolation = 'policy_violation';
    case Quality = 'quality';
    case OffTopic = 'off_topic';
    case Plagiarism = 'plagiarism';
    case Misinformation = 'misinformation';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::PolicyViolation => 'Policy violation',
            self::Quality => 'Does not meet quality standards',
            self::OffTopic => 'Off-topic or not a fit',
            self::Plagiarism => 'Plagiarism detected',
            self::Misinformation => 'Contains misinformation',
            self::Other => 'Other',
        };
    }
}