<?php

namespace App\Services\Newsletter\Renderers;

use App\Framework\Support\Str;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\BlockData\StaticDealBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;

class StaticDealBlockRenderer implements EmailBlockRenderer
{
    public function supports(string $type): bool
    {
        return $type === 'deal';
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $newsletterRenderContext): RenderedBlock
    {
        if (!$blockData instanceof StaticDealBlockData) {
            return RenderedBlock::skipped();
        }

        $html = [];
        $html[] = '<div style="border: 2px solid #ff4757; border-radius: 8px; padding: 20px; margin: 20px 0; background-color: #fff5f5;">';

        $html[] = '<div style="margin-bottom: 15px;">';
        if ($blockData->sponsored) {
            $html[] = '<span style="background-color: #ffc107; color: #333; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; margin-right: 10px;">Sponsored</span>';
        }
        if ($blockData->voucherId) {
            $html[] = '<span style="background-color: #28a745; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold;">🎟️ Voucher Available</span>';
        }
        $html[] = '</div>';

        $html[] = '<div style="display: table; width: 100%;">';

        if ($blockData->image && isset($blockData->image['src'])) {
            $html[] = '<div style="display: table-cell; vertical-align: top; width: 150px; padding-right: 20px;">';
            $html[] = '<img src="' . Str::sanitize($blockData->image['src']) . '" alt="' . Str::sanitize($blockData->productName) . '" style="width: 150px; height: auto; border-radius: 4px;">';
            $html[] = '</div>';
        }

        $html[] = '<div style="display: table-cell; vertical-align: top;">';
        $html[] = '<h3 style="color: #333; margin: 0 0 5px 0; font-size: 20px;">' . Str::sanitize($blockData->title) . '</h3>';

        if ($blockData->brand) {
            $html[] = '<div style="color: #666; font-size: 14px; margin-bottom: 5px;">' . Str::sanitize($blockData->brand) . '</div>';
        }

        $html[] = '<h4 style="color: #333; margin: 0 0 10px 0; font-size: 16px;">' . Str::sanitize($blockData->productName) . '</h4>';

        if ($blockData->description) {
            $html[] = '<p style="color: #666; margin: 0 0 15px 0; font-size: 14px; line-height: 1.6;">' . Str::sanitize($blockData->description) . '</p>';
        }

        // Pricing
        $html[] = '<div style="margin-bottom: 15px;">';
        if ($blockData->salePrice < $blockData->price) {
            $html[] = '<span style="color: #999; text-decoration: line-through; font-size: 16px; margin-right: 10px;">' . Str::sanitize($blockData->currency) . number_format($blockData->price, 2) . '</span>';
            $html[] = '<span style="color: #ff4757; font-size: 24px; font-weight: bold;">' . Str::sanitize($blockData->currency) . number_format($blockData->salePrice, 2) . '</span>';
            $html[] = '<div style="color: #28a745; font-size: 14px; font-weight: bold; margin-top: 5px;">Save ' . Str::sanitize($blockData->currency) . number_format($blockData->savings, 2) . ' (' . $blockData->savingsPercent . '%)</div>';
        } else {
            $html[] = '<span style="color: #333; font-size: 24px; font-weight: bold;">' . Str::sanitize($blockData->currency) . number_format($blockData->price, 2) . '</span>';
        }
        $html[] = '</div>';

        // Voucher code
        if ($blockData->voucherId) {
            $html[] = '<div style="background-color: white; border: 2px dashed #28a745; padding: 10px; border-radius: 4px; margin-bottom: 15px;">';
            $html[] = '<span style="color: #666; font-size: 12px;">Use Code:</span> ';
            $html[] = '<span style="color: #333; font-size: 16px; font-weight: bold; font-family: monospace;">' . Str::sanitize($blockData->voucherId) . '</span>';
            $html[] = '</div>';
        }

        if ($blockData->link) {
            $html[] = '<a href="' . Str::sanitize($blockData->link) . '" style="display: inline-block; padding: 12px 30px; background-color: #ff4757; color: white; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 16px;">Get Deal</a>';
        }

        $html[] = '</div>';
        $html[] = '</div>';
        $html[] = '</div>';

        return RenderedBlock::rendered(implode("\n", $html));
    }
}