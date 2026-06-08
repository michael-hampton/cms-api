<?php

namespace App\Services\Cms\Pages;

use App\Enums\Pages\PageStatus;
use App\Models\Page;
use App\Repositories\Cms\Pages\PageMetadataRepository;

class PremiumPagePurchaseEligibilityService
{
    public function __construct(
        private readonly PageMetadataRepository $metadataRepository,
    ) {
    }

    public function assertPurchasable(Page $page): void
    {
        $failures = [];

        if ((string) $page->status !== PageStatus::PUBLISHED->value) {
            $failures[] = 'Page is not published.';
        }

        if (!(bool) $page->is_paid) {
            $failures[] = 'Page is not marked as paid.';
        }

        if ((int) $page->price <= 0) {
            $failures[] = 'Page does not have a valid premium price.';
        }

        if (empty($page->premium_approved_at)) {
            $failures[] = 'Page has not been approved for premium monetisation.';
        }

        if (!empty($page->monetisation_disabled_at)) {
            $failures[] = 'Page monetisation has been disabled.';
        }

        if (empty($page->contributor_id)) {
            $failures[] = 'Page does not have a contributor.';
        }

        $metadata = $page->metadata ?? $this->metadataRepository->findByPageId((int) $page->id);

        if (($metadata->visibility ?? null) !== 'premium') {
            $failures[] = 'Page metadata visibility is not premium.';
        }

        if (!empty($failures)) {
            throw new \InvalidArgumentException(
                'Page is not purchasable: ' . implode(' ', $failures)
            );
        }
    }

    public function isPurchasable(Page $page): bool
    {
        try {
            $this->assertPurchasable($page);
            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }
}