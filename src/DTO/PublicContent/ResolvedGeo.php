<?php

namespace App\DTO\PublicContent;

use App\Enums\PublicContent\GeoSource;

final readonly class ResolvedGeo
{
    public function __construct(
        public ?string $country,
        public ?string $region,
        public GeoSource $source,
    ) {
    }

    public function toArray(): array
    {
        return [
            'country' => $this->country,
            'region' => $this->region,
            'geo_source' => $this->source->value,
        ];
    }
}
