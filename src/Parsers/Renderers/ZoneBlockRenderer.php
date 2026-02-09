<?php

namespace App\Parsers\Renderers;

use App\Parsers\Dtos\BlockDtoInterface;
use App\Parsers\Dtos\ZoneBlockDto;

class ZoneBlockRenderer extends BaseBlockRenderer
{
    protected function getSupportedType(): string
    {
        return 'zone';
    }

    public function render(BlockDtoInterface $dto): string
    {
        if (!$dto instanceof ZoneBlockDto) {
            return '';
        }

        // Zones don't render themselves directly
        // They're handled by buildZonesHtml in the service layer
        return '';
    }
}