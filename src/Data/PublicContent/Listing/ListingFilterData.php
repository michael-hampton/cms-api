<?php
declare(strict_types=1);

namespace App\Data\PublicContent\Listing;

final readonly class ListingFilterData
{
    /**
     * @param array<string, list<string>> $facets keyed by PublicDirectoryFacet::value
     */
    public function __construct(
        public ?string $search,
        public string $sort,
        public int $page,
        public int $perPage,
        public array $facets = [],
    ) {
    }

    /**
     * @return list<string> selected values for a given facet key
     */
    public function facetValues(string $key): array
    {
        return $this->facets[$key] ?? [];
    }

    public function withoutFacet(string $key): self
    {
        $facets = $this->facets;
        unset($facets[$key]);

        return new self($this->search, $this->sort, $this->page, $this->perPage, $facets);
    }
}