<?php

namespace App\Enums\PublicContent;

enum PublicPageType: string
{
    case Article = 'article';
    case Content = 'content';
    case LandingPage = 'landing-page';

    public static function fromPage(mixed $pageType): ?self
    {
        return self::tryFrom((string) $pageType);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn(self $type): string => $type->value,
            self::cases(),
        );
    }

    /**
     * @return list<string>
     */
    public static function editorialValues(): array
    {
        return [
            self::Article->value,
            self::Content->value,
        ];
    }
}
