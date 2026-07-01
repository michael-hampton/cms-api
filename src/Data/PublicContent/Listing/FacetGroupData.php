<?php
declare(strict_types=1);

namespace App\Data\PublicContent\Listing;

final readonly class FacetGroupData
{
    /**
     * @param list<FacetOptionData> $options
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $options,
    ) {
    }
}