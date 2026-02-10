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

        $level = $dto->getHeadingLevel();
        $contextClass = $dto->context === 'sidebar' ? ' section-sidebar' : ''; //todo missing navigationText and excludeFromNav

        $html = "<div class=\"section-block section-level-{$level}{$contextClass}\">";
        $html .= "<{$dto->headingType} class=\"section-title\">{$this->escape($dto->title)}</{$dto->headingType}>";
        $html .= "</div>";

        return $html;
    }
}