<?php

namespace App\Enums;

enum ImageRights: string
{
    case CONTRIBUTOR_OWNED = 'contributor_owned';
    case STAFF_OWNED = 'staff_owned';
    case THIRD_PARTY_LICENSED = 'third_party_licensed';
    case AGENCY = 'agency';
    case EDITORIAL_USE_ONLY = 'editorial_use_only';

    case ALL_RIGHTS_RESERVED = 'all_rights_reserved';
    case ATTRIBUTION_REQUIRED = 'attribution_required';
    case ROYALTY_FREE = 'royalty_free';
    case PUBLIC_DOMAIN = 'public_domain';
    case CREATIVE_COMMONS = 'creative_commons';
    case CUSTOM_LICENSE = 'custom_license';
    case UNKNOWN = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::CONTRIBUTOR_OWNED => 'Contributor-owned',
            self::STAFF_OWNED => 'Staff-owned',
            self::THIRD_PARTY_LICENSED => 'Licensed third-party',
            self::AGENCY => 'Agency',
            self::EDITORIAL_USE_ONLY => 'Editorial use only',
            self::ALL_RIGHTS_RESERVED => 'All Rights Reserved',
            self::ATTRIBUTION_REQUIRED => 'Attribution Required',
            self::ROYALTY_FREE => 'Royalty Free',
            self::PUBLIC_DOMAIN => 'Public Domain',
            self::CREATIVE_COMMONS => 'Creative Commons',
            self::CUSTOM_LICENSE => 'Custom License',
            self::UNKNOWN => 'Rights not confirmed',
        };
    }

    public function requiresAttribution(): bool
    {
        return match ($this) {
            self::CONTRIBUTOR_OWNED,
            self::THIRD_PARTY_LICENSED,
            self::AGENCY,
            self::EDITORIAL_USE_ONLY,
            self::ATTRIBUTION_REQUIRED,
            self::CREATIVE_COMMONS => true,
            default => false,
        };
    }

    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}