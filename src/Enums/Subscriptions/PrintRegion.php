<?php

declare(strict_types=1);

namespace App\Enums\Subscriptions;

/**
 * Classifies a delivery address as UK or Export for print run copy counting.
 *
 * Rule: country_code === 'GB' → UK, everything else → Export.
 * The country code is taken from the subscriber's delivery address.
 */
enum PrintRegion: string
{
    case UK     = 'UK';
    case Export = 'EXPORT';

    /**
     * Derive the print region from a delivery address country code.
     *
     * @param string|null $countryCode ISO 3166-1 alpha-2, e.g. 'GB', 'US', 'DE'.
     */
    public static function fromCountryCode(?string $countryCode): self
    {
        if ($countryCode === null) {
            return self::Export;
        }

        return strtoupper(trim($countryCode)) === 'GB'
            ? self::UK
            : self::Export;
    }

    public function label(): string
    {
        return match ($this) {
            self::UK     => 'UK',
            self::Export => 'Overseas (Export)',
        };
    }
}