<?php

namespace App\Services\PublicContent\Routing;

use App\DTO\PublicContent\ResolvedPublicContentRoute;
use App\Enums\PublicContent\PublicContentPageKind;
use App\Models\Page;

/**
 * Classifies a resolved route into a first-class {@see PublicContentPageKind}.
 *
 * Every known target maps to a kind with an explicit positive test. Unrecognised
 * targets land in {@see PublicContentPageKind::Unknown}. Routes that fail read-time
 * validity expectations land in {@see PublicContentPageKind::Invalid}.
 */
final class PublicContentPageKindClassifier
{
    /** @var array<string, PublicContentPageKind> */
    private const array TARGET_MAP = [
        'article_view' => PublicContentPageKind::Article,
        'article' => PublicContentPageKind::Article,
        'homepage' => PublicContentPageKind::Homepage,
        'home' => PublicContentPageKind::Homepage,
        'category' => PublicContentPageKind::Category,
        'review' => PublicContentPageKind::Review,
        'buying_guide' => PublicContentPageKind::BuyingGuide,
        'buying-guide' => PublicContentPageKind::BuyingGuide,
        'content' => PublicContentPageKind::Content,
        'landing-page' => PublicContentPageKind::LandingPage,
        'landing_page' => PublicContentPageKind::LandingPage,
    ];

    public function classify(ResolvedPublicContentRoute $route): PublicContentPageKind
    {
        if ($this->isInvalid($route)) {
            return PublicContentPageKind::Invalid;
        }

        $normalised = strtolower(trim($route->target));

        return self::TARGET_MAP[$normalised] ?? PublicContentPageKind::Unknown;
    }

    public function fromPageType(mixed $pageType): PublicContentPageKind
    {
        $value = strtolower(trim((string) $pageType));

        return match ($value) {
            'article' => PublicContentPageKind::Article,
            'homepage', 'home' => PublicContentPageKind::Homepage,
            'category' => PublicContentPageKind::Category,
            'review' => PublicContentPageKind::Review,
            'buying-guide', 'buying_guide' => PublicContentPageKind::BuyingGuide,
            'content' => PublicContentPageKind::Content,
            'landing-page', 'landing_page' => PublicContentPageKind::LandingPage,
            '' => PublicContentPageKind::Unknown,
            default => PublicContentPageKind::Unknown,
        };
    }

    public function fromPage(Page $page): PublicContentPageKind
    {
        return $this->fromPageType($page->page_type ?? null);
    }

    private function isInvalid(ResolvedPublicContentRoute $route): bool
    {
        $target = trim($route->target);

        if ($target === '') {
            return true;
        }

        $normalised = strtolower($target);

        // article_view is valid with a document address OR both slug and article type.
        if ($normalised === 'article_view') {
            return !$route->hasAddress() && !$route->hasSlugAndArticleType();
        }

        // All other targets require an address when present.
        return !$route->hasAddress();
    }
}
