<?php

namespace App\Parsers\Renderers;

use App\Parsers\Dtos\BlockDtoInterface;

class MapLocationBlockRenderer extends BaseBlockRenderer
{
    public function render(BlockDtoInterface $dto): string
    {
        $contextClass = $dto->context === 'sidebar' ? ' map-location-sidebar' : '';
        $html = "<div class=\"map-location-block{$contextClass}\">";

        if (!empty($dto->title)) {
            $html .= "<h3 class=\"map-title\">{$dto->title}</h3>";
        }

        $html .= "<div class=\"map-container\" style=\"height: {$dto->height}px;\">";
        $html .= "<iframe ";
        $html .= "width=\"100%\" ";
        $html .= "height=\"{$dto->height}\" ";
        $html .= "frameborder=\"0\" ";
        $html .= "style=\"border:0\" ";
        $html .= "referrerpolicy=\"no-referrer-when-downgrade\" ";
        $html .= "src=\"https://www.google.com/maps/embed/v1/place?key=YOUR_API_KEY&q=" . urlencode($dto->address) . "&zoom={$dto->zoom}\" ";
        $html .= "allowfullscreen>";
        $html .= "</iframe>";
        $html .= "</div>";

        if (!empty($dto->description)) {
            $html .= "<div class=\"map-description\">{$dto->description}</div>";
        }

        $html .= "</div>";

        return $html;
    }

    protected function getSupportedType(): string
    {
        return 'map-location';
    }
}