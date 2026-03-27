<?php

namespace App\Enums\Member;

enum MemberStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public static function fromBool(bool $isActive): self
    {
        return $isActive ? self::Active : self::Inactive;
    }

    public function isActive(): bool
    {
        return $this === self::Active;
    }

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
        };
    }
}