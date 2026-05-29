<?php

namespace App\Enums\Member;

enum SegmentSubjectType: string
{
    case Member       = 'member';
    case Subscription = 'subscription';
    case Plan         = 'plan';

    /**
     * Returns all valid string values — useful for validation rules.
     *
     * @return string[]
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}