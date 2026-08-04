<?php

namespace App\Enums\PublicContent;

/**
 * User-facing advert frequency for config editor / site config.
 * Maps to content-block gaps used by AdvertInjectionPlanner.
 */
enum AdvertFrequency: string
{
    case Less = 'less';
    case Balanced = 'balanced';
    case More = 'more';

    public static function tryFromConfig(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return self::tryFrom(is_string($value) ? $value : '') ?? self::Balanced;
    }

    /**
     * Minimum main-content blocks between inline ads on typical pages.
     */
    public function blocksBetweenAds(): int
    {
        return match ($this) {
            self::Less => 3,
            self::Balanced => 2,
            self::More => 2,
        };
    }

    /**
     * Gap on long pages (>12 main blocks). "More" keeps ads closer; "Less" spaces further.
     */
    public function longPageBlocksBetweenAds(): int
    {
        return match ($this) {
            self::Less => 4,
            self::Balanced => 3,
            self::More => 2,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Less => 'Less often',
            self::Balanced => 'Balanced',
            self::More => 'More often',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Less => 'More space between ads. Best when you want a quieter reading experience.',
            self::Balanced => 'Ads appear every few sections. On longer articles, a few more can appear while staying spread out.',
            self::More => 'Ads appear a bit closer together, including on longer articles, still scattered through the page.',
        };
    }
}
