<?php

namespace App\Parsers\Renderers;

use App\Parsers\Dtos\BlockDtoInterface;
use App\Parsers\Dtos\ProductComparisonBlockDto;

class ProductComparisonBlockRenderer extends BaseBlockRenderer
{
    protected function getSupportedType(): string
    {
        return 'product-comparison';
    }

    public function render(BlockDtoInterface $dto): string
    {
        if (!$dto instanceof ProductComparisonBlockDto) {
            return '';
        }

        $html = "<div class=\"product-comparison-block\">";

        $html .= "<h3 class=\"comparison-title\">{$this->escape($dto->title)}</h3>";

        $html .= "<div class=\"comparison-table\">";
        $html .= "<div class=\"comparison-header\">";
        $html .= "<div class=\"comparison-header-cell\"></div>";
        $html .= "<div class=\"comparison-header-cell product-a\">{$this->escape($dto->productA)}</div>";
        $html .= "<div class=\"comparison-header-cell product-b\">{$this->escape($dto->productB)}</div>";
        $html .= "</div>";

        foreach ($dto->comparisons as $comparison) {
            $html .= "<div class=\"comparison-row\">";
            $html .= "<div class=\"comparison-label\">{$this->escape($comparison['subtitle'])}</div>";

            foreach ($comparison['items'] as $item) {
                $html .= "<div class=\"comparison-value\">{$this->escape($item)}</div>";
            }

            $html .= "</div>";
        }

        $html .= "</div>";
        $html .= "</div>";

        return $html;
    }
}