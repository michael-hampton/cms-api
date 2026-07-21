<?php

namespace App\Services\PublicContent\Recirculation;

use App\DTO\PublicContent\Sources\SourceResult;
use App\Framework\Support\Collection;
use App\Models\Page;
use App\Services\PublicContent\Config\PublicContentConfigSource;
use App\Services\Recommendations\ContentRecommendationService;
use Throwable;

/**
 * Recirculation ("read this next") source bound to the recommendations service
 * through the shared {@see SourceResult} contract.
 *
 * Eligibility comes from widgets.recirculation.page_types in the public content
 * config document (same pattern as vouchers and other widgets). Slow, failing,
 * or malformed upstream answers degrade to typed-empty so the page still serves.
 */
final class RecirculationRecommendationsSource implements RecirculationSourceInterface
{
    public function __construct(
        private readonly ContentRecommendationService $recommendations,
        private readonly RecirculationSourceLogger $logger,
        private readonly PublicContentConfigSource $publicContentConfig,
    ) {
    }

    public function resolve(Page $page, int $siteId, int $limit = 4): SourceResult
    {
        if (!$this->supportsPage($page, $siteId)) {
            return SourceResult::empty();
        }

        try {
            $items = $this->recommendations->forPage($page, $siteId, $limit);

            if (!$this->isWellFormed($items)) {
                $this->logger->warning('Recirculation recommendations response was malformed.', [
                    'page_id' => (int) $page->id,
                    'site_id' => $siteId,
                ]);

                return SourceResult::degraded('malformed');
            }

            if ($items->count() === 0) {
                return SourceResult::empty();
            }

            return SourceResult::ok($items);
        } catch (Throwable $exception) {
            $this->logger->warning('Recirculation recommendations source failed.', [
                'page_id' => (int) $page->id,
                'site_id' => $siteId,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return SourceResult::degraded('unavailable');
        }
    }

    private function supportsPage(Page $page, int $siteId): bool
    {
        $pageTypes = $this->publicContentConfig->get($siteId, 'widgets.recirculation.page_types', ['*']);

        if (!is_array($pageTypes)) {
            return true;
        }

        return in_array('*', $pageTypes, true)
            || in_array((string) $page->page_type, $pageTypes, true);
    }

    private function isWellFormed(mixed $items): bool
    {
        if (!$items instanceof Collection) {
            return false;
        }

        foreach ($items as $item) {
            if (!is_object($item) || !isset($item->id)) {
                return false;
            }
        }

        return true;
    }
}
