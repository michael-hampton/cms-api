<?php

namespace App\Services\PublicContent\Deals;

use App\DTO\PublicContent\Sources\SourceResult;
use App\Framework\Support\Logger;
use App\Services\Offers\DealsService;
use App\Services\PublicContent\Config\PublicContentConfigSource;
use Throwable;

/**
 * Public-content deals/prices source. Never invents default deals on failure —
 * wrong prices are worse than no prices.
 */
final class PublicContentDealsSource
{
    public function __construct(
        private readonly DealsService $deals,
        private readonly PublicContentConfigSource $publicContentConfig,
        private readonly Logger $logger,
    ) {
    }

    public function resolve(int $siteId, string $pageType, int $limit = 10): SourceResult
    {
        $pageTypes = $this->publicContentConfig->get($siteId, 'widgets.deals.page_types', ['*']);

        if (is_array($pageTypes)
            && !in_array('*', $pageTypes, true)
            && !in_array($pageType, $pageTypes, true)
        ) {
            return SourceResult::empty();
        }

        try {
            $items = $this->deals->getFeaturedDealsOnly($limit, $siteId);

            if (!is_array($items)) {
                $this->logger->warning('Public content deals source returned malformed data.', [
                    'site_id' => $siteId,
                ]);

                return SourceResult::degraded('malformed');
            }

            if ($items === []) {
                return SourceResult::empty();
            }

            return SourceResult::ok($items);
        } catch (Throwable $exception) {
            $this->logger->warning('Public content deals source failed.', [
                'site_id' => $siteId,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return SourceResult::degraded('unavailable');
        }
    }
}
