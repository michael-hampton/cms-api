<?php

namespace App\Parsers\Renderers;

use App\Parsers\Dtos\BlockDtoInterface;
use App\Parsers\Dtos\InfoBlockDto;

class InfoBlockRenderer extends BaseBlockRenderer
{
    protected function getSupportedType(): string
    {
        return 'info';
    }

    public function render(BlockDtoInterface $dto): string
    {
        if (!$dto instanceof InfoBlockDto) {
            return '';
        }

        $contextClass = $dto->context === 'sidebar' ? ' info-sidebar' : '';
        $html = "<div class=\"info-block info-type-{$dto->infoType}{$contextClass}\">";

        $html .= "<div class=\"info-header\">";
        $html .= "<span class=\"info-icon\">{$dto->getIcon()}</span>";
        $html .= "<span class=\"info-type\">" . ucfirst($dto->infoType) . "</span>";
        $html .= "</div>";

        $html .= "<div class=\"info-content\">";
        $html .= $this->escapeWithBreaks($dto->description);
        $html .= "</div>";

        $html .= "</div>";

        return $html;
    }
}