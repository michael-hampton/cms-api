<?php

namespace App\Parsers\Renderers;

use App\Parsers\Dtos\BlockDtoInterface;
use App\Parsers\Dtos\ServicesBlockDto;

class ServicesBlockRenderer extends BaseBlockRenderer
{
    protected function getSupportedType(): string
    {
        return 'services';
    }

    public function render(BlockDtoInterface $dto): string
    {
        if (!$dto instanceof ServicesBlockDto) {
            return '';
        }

        $html = "<section class=\"services-block services-layout-{$dto->layout}\">";

        $html .= "<div class=\"services-header\">";
        $html .= "<h2>{$this->escape($dto->title)}</h2>";
        if (!empty($dto->subtitle)) {
            $html .= "<p>{$this->escape($dto->subtitle)}</p>";
        }
        $html .= "</div>";

        $html .= "<div class=\"services-grid\">";

        foreach ($dto->services as $service) {
            $html .= "<div class=\"service-card\">";

            if ($service['image']) {
                $html .= "<div class=\"service-image\">";
                $html .= "<img src=\"{$this->escape($service['image']['src'])}\" alt=\"{$this->escape($service['title'])}\">";
                $html .= "</div>";
            } else {
                $html .= "<div class=\"service-icon\">{$service['icon']}</div>";
            }

            $html .= "<h3 class=\"service-title\">{$this->escape($service['title'])}</h3>";
            $html .= "<div class=\"service-description\">{$this->escapeWithBreaks($service['description'])}</div>";

            if ($service['url'] !== '#') {
                $html .= "<a href=\"{$this->escape($service['url'])}\" class=\"service-link\">Learn More</a>";
            }

            $html .= "</div>";
        }

        $html .= "</div>";
        $html .= "</section>";

        return $html;
    }
}