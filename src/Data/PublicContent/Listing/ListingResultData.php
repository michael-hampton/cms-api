<?php
declare(strict_types=1);

namespace App\Data\PublicContent\Listing;

use App\Framework\Support\Collection;

final readonly class ListingResultData
{
    /**
     * @param list<FacetGroupData> $facets
     */
    public function __construct(
        public Collection $items,
        public int $total,
        public int $currentPage,
        public int $lastPage,
        public int $perPage,
        public array $facets = [],
    ) {
    }
}