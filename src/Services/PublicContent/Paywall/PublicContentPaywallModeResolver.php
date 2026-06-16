<?php

namespace App\Services\PublicContent\Paywall;

use App\Models\Page;

final class PublicContentPaywallModeResolver
{
    public const MODE_ARTICLE_PURCHASE = 'article_purchase';
    public const MODE_SUBSCRIPTION = 'subscription';

    public function resolve(Page $page): string
    {
        return $this->isOpenCollabPage($page)
            && (bool) $page->is_paid
            && (int) $page->price > 0
            && empty($page->monetisation_disabled_at)
                ? self::MODE_ARTICLE_PURCHASE
                : self::MODE_SUBSCRIPTION;
    }

    public function isOpenCollabPage(Page $page): bool
    {
        return (bool) $page->is_public_contribution
            || !empty($page->contributor_id);
    }
}
