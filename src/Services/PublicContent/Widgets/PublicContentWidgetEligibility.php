<?php

namespace App\Services\PublicContent\Widgets;

use App\DTO\PublicContent\PublicContentContext;
use App\Enums\PublicContent\PublicPageType;
use App\Services\PublicContent\Config\PublicContentConfigSource;

class PublicContentWidgetEligibility
{
    public function __construct(
        private readonly PublicContentConfigSource $publicContentConfig,
    ) {
    }

    public function isLanding(PublicContentContext $context): bool
    {
        return $this->pageType($context) === PublicPageType::LandingPage;
    }

    public function isNotLanding(PublicContentContext $context): bool
    {
        return !$this->isLanding($context);
    }

    public function isEditorial(PublicContentContext $context): bool
    {
        $pageType = $this->pageType($context);

        return $pageType === PublicPageType::Article
            || $pageType === PublicPageType::Content;
    }

    public function supportsWidget(PublicContentContext $context, string $widgetKey): bool
    {
        if ($context->overridesArticleTypeFor($widgetKey)) {
            return true;
        }

        $pageTypes = $this->publicContentConfig->get($context->siteId, "widgets.{$widgetKey}.page_types", ['*']);

        if (!is_array($pageTypes)) {
            return true;
        }

        return in_array('*', $pageTypes, true)
            || in_array($this->pageTypeValue($context), $pageTypes, true);
    }

    public function hasBreadcrumbs(PublicContentContext $context): bool
    {
        if (!$this->supportsWidget($context, 'breadcrumbs')) {
            return false;
        }

        $categories = $context->page->categories ?? null;

        if ($categories && method_exists($categories, 'count')) {
            return $categories->count() > 0;
        }

        return is_countable($categories) && count($categories) > 0;
    }

    public function hasAuthors(PublicContentContext $context): bool
    {
        if (!$this->supportsWidget($context, 'authors')) {
            return false;
        }

        $authors = $context->page->authors ?? null;
        if ($authors && method_exists($authors, 'count') && $authors->count() > 0) {
            return true;
        }

        $pageAuthors = $context->page->pageAuthors ?? null;
        if ($pageAuthors && method_exists($pageAuthors, 'count')) {
            foreach ($pageAuthors as $pageAuthor) {
                if (!empty($pageAuthor->author)) {
                    return true;
                }
            }
        }

        return !empty($context->page->author_id)
            || !empty($context->page->author);
    }

    public function hasHomepageCategories(PublicContentContext $context): bool
    {
        return $this->supportsWidget($context, 'categories-widget')
            && !empty($context->viewData['categories']);
    }

    public function isBuyingGuide(PublicContentContext $context): bool
    {
        return $this->pageType($context) === PublicPageType::BuyingGuide;
    }

    public function hasProducts(PublicContentContext $context): bool
    {
        return !empty($context->page->products)
            && count($context->page->products) > 0;
    }

    public function hasDeals(PublicContentContext $context): bool
    {
        return !empty($context->viewData['todaysDeals']);
    }

    public function hasVouchers(PublicContentContext $context): bool
    {
        return $this->supportsWidget($context, 'vouchers')
            && !empty($context->viewData['vouchers'])
            && count($context->viewData['vouchers']) > 0;
    }

    public function hasCategorySections(PublicContentContext $context): bool
    {
        return $this->supportsWidget($context, 'category-pages')
            && !empty($context->viewData['categoriesWithPages']);
    }

    public function hasClaimedGift(PublicContentContext $context): bool
    {
        return !empty($context->viewData['claimedGift']);
    }

    public function hasSubscriptionModal(PublicContentContext $context): bool
    {
        return !empty($context->viewData['subscriptionModalData']);
    }

    public function hasMember(PublicContentContext $context): bool
    {
        return $context->member !== null
            && !empty($context->viewData['badgeModalData']);
    }

    private function pageType(PublicContentContext $context): ?PublicPageType
    {
        return PublicPageType::fromPage($context->page->page_type);
    }

    private function pageTypeValue(PublicContentContext $context): string
    {
        return $this->pageType($context)?->value ?? (string) $context->page->page_type;
    }
}
