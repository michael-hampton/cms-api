<?php

namespace App\Services\PublicContent\Widgets;

use App\DTO\PublicContent\PublicContentContext;

final class PublicContentWidgetEligibility
{
    public function isLanding(PublicContentContext $context): bool
    {
        return $this->pageType($context) === 'landing-page';
    }

    public function isNotLanding(PublicContentContext $context): bool
    {
        return !$this->isLanding($context);
    }

    public function isEditorial(PublicContentContext $context): bool
    {
        return in_array($this->pageType($context), ['article', 'content'], true);
    }

    public function hasAuthors(PublicContentContext $context): bool
    {
        if (!$this->isNotLanding($context)) {
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
        return $this->isLanding($context)
            && !empty($context->viewData['categories']);
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

    public function hasCategorySections(PublicContentContext $context): bool
    {
        return $this->isLanding($context)
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

    private function pageType(PublicContentContext $context): string
    {
        return (string) $context->page->page_type;
    }
}
