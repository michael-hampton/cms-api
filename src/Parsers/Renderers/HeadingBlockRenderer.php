<?php

namespace App\Parsers\Renderers;

use App\Parsers\Dtos\BlockDtoInterface;
use App\Parsers\Dtos\HeadingBlockDto;

class HeadingBlockRenderer extends BaseBlockRenderer
{
    protected function getSupportedType(): string
    {
        return 'heading';
    }

    public function render(BlockDtoInterface $dto): string
    {
        if (!$dto instanceof HeadingBlockDto) {
            return '';
        }

        $contextClass = $dto->context === 'sidebar' ? ' heading-sidebar' : '';
        $html = "<div class=\"heading-block heading-level-{$dto->level}{$contextClass}\">";

        $html .= "<h{$dto->level} class=\"heading-text\">{$this->escape($dto->text)}</h{$dto->level}>";

        if (!empty($dto->subtitle)) {
            $html .= "<div class=\"heading-subtitle\">{$this->escape($dto->subtitle)}</div>";
        }

        $html .= "</div>";

        return $html;
    }
}