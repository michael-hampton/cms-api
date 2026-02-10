<?php

namespace App\Parsers\Renderers;

use App\Parsers\Dtos\AwardBlockDto;
use App\Parsers\Dtos\BlockDtoInterface;

class AwardBlockRenderer extends BaseBlockRenderer
{
    public function render(BlockDtoInterface $dto): string
    {
        if (!$dto instanceof AwardBlockDto) {
            throw new \InvalidArgumentException('Expected AwardBlockDto');
        }

        $contextClass = $dto->context === 'sidebar' ? ' award-sidebar' : '';
        $html = "<div class=\"award-block{$contextClass}" . ($dto->winner ? ' award-winner' : '') . "\">";

        if (!empty($dto->image)) {
            $html .= "<div class=\"award-image\">";
            $html .= "<img src=\"{$dto->image['src']}\" alt=\"" . $this->escape($dto->alt) . "\" class=\"award-img\">";
            $html .= "</div>";
        }

        $html .= "<div class=\"award-content\">";

        $html .= "<div class=\"award-subcategory\">" . $this->escape($dto->subcategory) . "</div>";
        $html .= "<h3 class=\"award-product-name\">" . $this->escape($dto->productName) . "</h3>";

        if ($dto->winner) {
            $html .= "<div class=\"award-winner-badge\">Winner</div>";
        }

        if (!empty($dto->strapline)) {
            $html .= "<div class=\"award-strapline\">" . $this->escapeWithBreaks($dto->strapline) . "</div>";
        }

        if (!empty($dto->caption)) {
            $html .= "<div class=\"award-caption\">" . $this->escapeWithBreaks($dto->caption) . "</div>";
        }

        if ($dto->rating > 0) {
            $html .= "<div class=\"award-rating\">Rating: {$dto->rating}/5</div>";
        }

        $html .= "</div>";
        $html .= "</div>";

        return $html;
    }

    protected function getSupportedType(): string
    {
        return 'award';
    }
}