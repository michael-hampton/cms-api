<?php
declare(strict_types=1);

namespace App\Data\PublicContent\Listing;

final readonly class FacetOptionData
{
    public function __construct(
        public string $value,
        public string $label,
        public int $count,
        public bool $selected,
    ) {
    }
}