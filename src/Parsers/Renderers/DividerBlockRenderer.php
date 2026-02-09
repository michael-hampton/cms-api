<?php

namespace App\Parsers\Renderers;

use App\Parsers\Dtos\BlockDtoInterface;
use App\Parsers\Dtos\DividerBlockDto;

class DividerBlockRenderer extends BaseBlockRenderer
{
    protected function getSupportedType(): string
    {
        return 'divider';
    }

    public function render(BlockDtoInterface $dto): string
    {
        if (!$dto instanceof DividerBlockDto) {
            return '';
        }

        // Dividers typically render as HR or simple div
        return "<hr class=\"{$dto->getCssClass()}\" style=\"border-width: {$dto->getThickness()};\">";
    }
}