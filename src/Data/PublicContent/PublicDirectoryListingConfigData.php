<?php
declare(strict_types=1);

namespace App\Data\PublicContent;

final readonly class PublicDirectoryListingConfigData
{
    /**
     * @param list<int> $perPageOptions
     * @param list<string> $indexSorts values of PublicDirectoryIndexSort
     * @param list<string> $pageSorts values of PublicDirectoryPageSort
     * @param list<string> $pageFacets values of PublicDirectoryFacet
     */
    public function __construct(
        public array $perPageOptions,
        public int $defaultPerPage,
        public int $maxPerPage,
        public array $indexSorts,
        public array $pageSorts,
        public array $pageFacets,
    ) {
    }
}