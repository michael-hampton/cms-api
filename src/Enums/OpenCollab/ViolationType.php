<?php

namespace App\Enums\OpenCollab;

enum ViolationType: string
{
    case Plagiarism = 'plagiarism';
    case Spam = 'spam';
    case Misinformation = 'misinformation';
    case Policy = 'policy';
    case Quality = 'quality';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Plagiarism => 'Plagiarism',
            self::Spam => 'Spam',
            self::Misinformation => 'Misinformation',
            self::Policy => 'Policy violation',
            self::Quality => 'Quality',
            self::Other => 'Other',
        };
    }
}