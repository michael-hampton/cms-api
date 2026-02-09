<?php

namespace App\Parsers\Renderers;

use App\Parsers\Dtos\BlockDtoInterface;
use App\Parsers\Dtos\CtaBlockDto;

class CtaBlockRenderer extends BaseBlockRenderer
{
    protected function getSupportedType(): string
    {
        return 'cta';
    }

    public function render(BlockDtoInterface $dto): string
    {
        if (!$dto instanceof CtaBlockDto) {
            return '';
        }

        $styleClass = 'cta-' . $dto->style;
        $sizeClass = 'cta-' . $dto->size;
        $alignmentClass = 'cta-align-' . $dto->alignment;
        $contextClass = $dto->context === 'sidebar' ? 'cta-sidebar' : '';

        $html = "<div class=\"cta-block {$alignmentClass} {$contextClass}\">";

        $attrs = '';
        foreach ($dto->linkAttributes as $attr => $value) {
            $attrs .= " {$attr}=\"{$this->escape($value)}\"";
        }

        $sponsoredBadge = $dto->sponsored ? '<span class="sponsored-badge">Sponsored</span>' : '';

        $html .= "<a href=\"{$this->escape($dto->url)}\"{$attrs} class=\"cta-button {$styleClass} {$sizeClass}\">";
        $html .= $this->escape($dto->text);
        $html .= $sponsoredBadge;
        $html .= "</a>";

        $html .= "</div>";

        return $html;
    }
}