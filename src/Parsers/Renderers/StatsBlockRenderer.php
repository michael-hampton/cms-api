<?php

namespace App\Parsers\Renderers;

use App\Parsers\Dtos\BlockDtoInterface;
use App\Parsers\Dtos\StatsBlockDto;

class StatsBlockRenderer extends BaseBlockRenderer
{
    protected function getSupportedType(): string
    {
        return 'stats';
    }

    public function render(BlockDtoInterface $dto): string
    {
        if (!$dto instanceof StatsBlockDto) {
            return '';
        }

        $contextClass = $dto->context === 'sidebar' ? ' stats-sidebar' : '';
        $html = "<section class=\"stats-block stats-layout-{$dto->layout}{$contextClass}\">";

        if (!empty($dto->title)) {
            $html .= "<h2 class=\"stats-title\">{$this->escape($dto->title)}</h2>";
        }

        $html .= "<div class=\"stats-grid\">";

        foreach ($dto->stats as $stat) {
            $html .= "<div class=\"stat-item\">";

            if (!empty($stat['icon'])) {
                $html .= "<div class=\"stat-icon\">{$stat['icon']}</div>";
            }

            $html .= "<div class=\"stat-number\">{$this->escape($stat['number'])}</div>";
            $html .= "<div class=\"stat-label\">{$this->escape($stat['label'])}</div>";

            if (!empty($stat['description'])) {
                $html .= "<div class=\"stat-description\">{$this->escape($stat['description'])}</div>";
            }

            $html .= "</div>";
        }

        $html .= "</div>";
        $html .= "</section>";

        return $html;
    }
}