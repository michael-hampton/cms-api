<?php

namespace App\Enums;

enum ImageRights: string
{
    case ALL_RIGHTS_RESERVED = 'all_rights_reserved';
    case ATTRIBUTION_REQUIRED = 'attribution_required';
    case ROYALTY_FREE = 'royalty_free';
    case PUBLIC_DOMAIN = 'public_domain';
    case CREATIVE_COMMONS = 'creative_commons';
    case CUSTOM_LICENSE = 'custom_license';

    public function label(): string
    {
        return match($this) {
            self::ALL_RIGHTS_RESERVED => 'All Rights Reserved',
            self::ATTRIBUTION_REQUIRED => 'Attribution Required',
            self::ROYALTY_FREE => 'Royalty Free',
            self::PUBLIC_DOMAIN => 'Public Domain',
            self::CREATIVE_COMMONS => 'Creative Commons',
            self::CUSTOM_LICENSE => 'Custom License',
        };
    }

    public function requiresAttribution(): bool
    {
        return match($this) {
            self::ATTRIBUTION_REQUIRED,
            self::CREATIVE_COMMONS => true,
            default => false,
        };
    }

    public static function values(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }
}