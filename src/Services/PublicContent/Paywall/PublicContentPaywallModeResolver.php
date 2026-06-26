<?php

namespace App\Services\PublicContent\Paywall;

use App\Models\Page;
use App\Services\Cms\Pages\PremiumPagePurchaseEligibilityService;

final class PublicContentPaywallModeResolver
{
    public const MODE_ARTICLE_PURCHASE = 'article_purchase';
    public const MODE_SUBSCRIPTION = 'subscription';

    public function __construct(
        private readonly PremiumPagePurchaseEligibilityService $purchaseEligibility,
    ) {
    }

    public function resolve(Page $page): string
    {
        return $this->purchaseEligibility->isPurchasable($page)
            ? self::MODE_ARTICLE_PURCHASE
            : self::MODE_SUBSCRIPTION;
    }

    public function isOpenCollabPage(Page $page): bool
    {
        return (bool) $page->is_public_contribution
            || !empty($page->contributor_id);
    }
}
