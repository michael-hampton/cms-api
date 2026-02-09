<?php

namespace App\Services\Newsletter\Renderers;

use App\Repositories\Product\ProductRepositoryInterface;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\BlockData\DealBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;
use App\Services\Newsletter\Services\TrackingUrlBuilder;
use Psr\Log\LoggerInterface;

class DealBlockRenderer implements EmailBlockRenderer
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly TrackingUrlBuilder         $trackingUrlBuilder,
        private readonly LoggerInterface            $logger
    )
    {
    }

    public function supports(string $type): bool
    {
        return $type === 'offer-deal';
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $context): RenderedBlock
    {
        if (!$blockData instanceof DealBlockData) {
            $this->logger->error('Invalid block data type for DealBlockRenderer', [
                'expected' => DealBlockData::class,
                'received' => get_class($blockData)
            ]);
            return RenderedBlock::skipped();
        }

        try {
            $product = $this->productRepository->findWithRelations(
                $blockData->productId,
                ['brand', 'images', 'category']
            );

            if (!$product) {
                $this->logger->warning('Deal product not found', [
                    'product_id' => $blockData->productId
                ]);
                return RenderedBlock::skipped();
            }

            $eligibility = $this->eligibilityService->checkEligibility(
                $product,
                $context->member,
                $context->siteId
            );

            if (!$eligibility->isEligible) {
                $this->logger->info('Deal suppressed', [
                    'product_id' => $product->id,
                    'reason' => $eligibility->reason
                ]);
                return RenderedBlock::skipped();
            }

            // Track AFTER eligibility check, BEFORE rendering
            $this->trackingService->trackRender($product, $context);

            $html = $this->renderHtml($product, $context);
            return RenderedBlock::rendered($html);

        } catch (\Exception $e) {
            $this->logger->error('Failed to render deal block', [
                'error' => $e->getMessage(),
                'product_id' => $blockData->productId
            ]);
            return RenderedBlock::skipped();
        }
    }

    private function renderHtml($product, RenderContext $context): string
    {
        $savings = $product->price - $product->sale_price;
        $savingsPercent = $product->discount_percentage;

        $dealUrl = $this->trackingUrlBuilder->buildProductUrl(
            $product->slug,
            $context->sendId,
            $context->includeTracking
        );

        $html = [];
        $html[] = '<div style="border: 2px solid #28a745; border-radius: 8px; padding: 20px; margin: 20px 0; background-color: #f0fff4;">';
        $html[] = '<div style="margin-bottom: 15px;">';
        $html[] = '<span style="background-color: #28a745; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold;">🔥 Deal Alert</span>';
        $html[] = '</div>';
        $html[] = '<div style="display: table; width: 100%;">';

        if ($product->main_image_url) {
            $html[] = '<div style="display: table-cell; vertical-align: top; width: 150px; padding-right: 20px;">';
            $html[] = '<img src="' . $this->stringHelper->sanitize($product->main_image_url) . '" alt="' . $this->stringHelper->sanitize($product->name) . '" style="width: 150px; height: auto; border-radius: 4px;">';
            $html[] = '</div>';
        }

        $html[] = '<div style="display: table-cell; vertical-align: top;">';

        if ($product->brand) {
            $html[] = '<div style="color: #666; font-size: 14px; margin-bottom: 5px;">' . $this->stringHelper->sanitize($product->brand->name) . '</div>';
        }

        $html[] = '<h3 style="color: #333; margin: 0 0 10px 0; font-size: 20px;">' . $this->stringHelper->sanitize($product->name) . '</h3>';

        if ($product->description) {
            $truncated = $this->stringHelper->truncate($product->description, 150);
            $html[] = '<p style="color: #666; margin: 0 0 15px 0; font-size: 14px; line-height: 1.6;">' . $this->stringHelper->sanitize($truncated) . '</p>';
        }

        $html[] = '<div style="margin-bottom: 15px;">';
        $html[] = '<span style="color: #999; text-decoration: line-through; font-size: 16px; margin-right: 10px;">£' . number_format($product->price, 2) . '</span>';
        $html[] = '<span style="color: #28a745; font-size: 24px; font-weight: bold;">£' . number_format($product->sale_price, 2) . '</span>';
        $html[] = '<div style="color: #28a745; font-size: 14px; font-weight: bold; margin-top: 5px;">Save £' . number_format($savings, 2) . ' (' . $savingsPercent . '%)</div>';
        $html[] = '</div>';

        $html[] = '<a href="' . $dealUrl . '" style="display: inline-block; padding: 12px 30px; background-color: #28a745; color: white; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 16px;">View Deal</a>';

        $html[] = '</div>';
        $html[] = '</div>';
        $html[] = '</div>';

        return implode("\n", $html);
    }
}