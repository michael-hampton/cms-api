<?php

namespace App\Parsers\Renderers;

use App\Parsers\Dtos\BlockDtoInterface;
use App\Parsers\Dtos\DealBlockDto;

class DealBlockRenderer extends BaseBlockRenderer
{
    public function render(BlockDtoInterface $dto): string
    {
        if (!$dto instanceof DealBlockDto) {
            throw new \InvalidArgumentException('Expected DealBlockDto');
        }

        $contextClass = $dto->context === 'sidebar' ? ' deal-sidebar' : '';
        $html = "<div class=\"deal-block{$contextClass}\">";

        if ($dto->sponsored) {
            $html .= "<span class=\"sponsored-badge\">Sponsored</span>";
        }

        // Add voucher badge if present
        if (!empty($dto->voucherId)) {
            $html .= "<span class=\"voucher-badge\">🎟️ Voucher Available</span>";
        }

        if (!empty($dto->image) && !empty($dto->image['src'])) {
            $html .= "<div class=\"deal-image\">";
            $html .= "<img src=\"{$dto->image['src']}\" alt=\"" . $this->escape($dto->productName) . "\" class=\"deal-img\">";
            $html .= "</div>";
        }

        $html .= "<div class=\"deal-content\">";
        $html .= "<h3 class=\"deal-title\">" . $this->escape($dto->title) . "</h3>";

        if (!empty($dto->brand)) {
            $html .= "<div class=\"deal-brand\">" . $this->escape($dto->brand) . "</div>";
        }

        $html .= "<h4 class=\"deal-product\">" . $this->escape($dto->productName) . "</h4>";

        if (!empty($dto->description)) {
            $html .= "<div class=\"deal-description\">" . $this->escapeWithBreaks($dto->description) . "</div>";
        }

        $savings = $dto->price > $dto->salePrice ? $dto->price - $dto->salePrice : 0;
        $savingsPercent = $dto->price > 0 ? round(($savings / $dto->price) * 100) : 0;

        $html .= "<div class=\"deal-pricing\">";
        if ($savings > 0) {
            $html .= "<span class=\"deal-original-price\">{$dto->currency}" . number_format($dto->price, 2) . "</span>";
            $html .= "<span class=\"deal-sale-price\">{$dto->currency}" . number_format($dto->salePrice, 2) . "</span>";
            $html .= "<span class=\"deal-savings\">Save {$dto->currency}" . number_format($savings, 2) . " ({$savingsPercent}%)</span>";
        } else {
            $html .= "<span class=\"deal-price\">{$dto->currency}" . number_format($dto->price, 2) . "</span>";
        }
        $html .= "</div>";

        // Add voucher section before the button
        if (!empty($dto->voucherId)) {
            $html .= "<div class=\"deal-voucher\">";
            $html .= "<span class=\"voucher-label\">Use Code:</span>";
            $html .= "<span class=\"voucher-code\">{$dto->voucherId}</span>";
            $html .= "<button class=\"voucher-copy-btn\" onclick=\"navigator.clipboard.writeText('{$dto->voucherId}')\">Copy</button>";
            $html .= "</div>";
        }

        if ($dto->showDealButton && !empty($dto->link)) {
            $linkAttrs = '';
            if ($dto->openInNewTab) {
                $linkAttrs .= ' target="_blank"';
            }

            $relValues = [];
            if ($dto->noFollow) $relValues[] = 'nofollow';
            if ($dto->sponsored) $relValues[] = 'sponsored';
            if ($dto->openInNewTab) $relValues[] = 'noopener noreferrer';

            if (!empty($relValues)) {
                $linkAttrs .= ' rel="' . implode(' ', array_unique($relValues)) . '"';
            }

            $html .= "<a href=\"{$dto->link}\"{$linkAttrs} class=\"deal-button\">Get Deal</a>";
        }

        $html .= "</div>";
        $html .= "</div>";

        return $html;
    }

    protected function getSupportedType(): string
    {
        return 'deal';
    }
}