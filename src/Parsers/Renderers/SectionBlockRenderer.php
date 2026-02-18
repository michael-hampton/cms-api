<?php

namespace App\Parsers\Renderers;

use App\Parsers\Dtos\BlockDtoInterface;
use App\Parsers\Dtos\SectionBlockDto;

class SectionBlockRenderer extends BaseBlockRenderer
{
    protected function getSupportedType(): string
    {
        return 'section';
    }

    public function render(BlockDtoInterface $dto): string
    {
        if (!$dto instanceof SectionBlockDto) {
            return '';
        }

        $text = $this->escape($dto->navigationText) ?? $this->escape($dto->title);

        $level = $dto->getHeadingLevel();
        $contextClass = $dto->context === 'sidebar' ? ' section-sidebar' : '';

        $html = "<div class=\"section-block section-level-{$level}{$contextClass}\">";
        $html .= "<{$dto->headingType} class=\"section-title\">{$text}</{$dto->headingType}>";
        $html .= "</div>";

        return $html;
    }
}