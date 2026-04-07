<?php

namespace App\Parsers\Renderers\Page;

use App\Framework\Support\Logger;
use App\Repositories\Product\ProductRepository;
use App\Services\Adverts\DealTrackingRecorder;
use App\Services\Adverts\DealVisibilityResolver;
use App\Services\Adverts\RenderContext;

class PageDealRenderer
{
    public function __construct(
        private readonly ProductRepository      $productRepository,
        private readonly DealVisibilityResolver $visibilityResolver,
        private readonly DealTrackingRecorder   $trackingRecorder,
        private readonly Logger                 $logger,
    )
    {
    }

    public function render(int $productId, RenderContext $context, string $ip = '', string $userAgent = '', ?int $siteId = null): string
    {
        try {
            $product = $this->productRepository->findWithRelations($productId, ['brand', 'images']);

            if (!$product) {
                return '';
            }

            $decision = $this->visibilityResolver->resolve($product, $context);

            if (!$decision->shouldRender) {
                return '';
            }

            $this->trackingRecorder->recordDealRender(
                productId: $productId,
                context: $context,
                ip: $ip,
                userAgent: $userAgent,
                siteId: $siteId
            );

            return $this->renderHtml($product, $context);

        } catch (\Exception $e) {
            $this->logger->error('PageDealRenderer failed', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);
            return '';
        }
    }

    private function renderHtml(object $product, RenderContext $context): string
    {
        $name = htmlspecialchars($product->name);
        $brand = htmlspecialchars($product->brand->name ?? '');
        $description = htmlspecialchars($product->description ?? '');
        $price = number_format((float)$product->price, 2);
        $salePrice = number_format((float)$product->sale_price, 2);
        $savings = number_format((float)($product->price - $product->sale_price), 2);
        $savingsPct = $product->discount_percentage ?? 0;

        $clickUrl = $this->buildClickUrl('deal', $product->id, $context);

        $html = '<div data-advert="deal" class="advert-block deal-block">';
        $html .= '<span class="advert-label deal-label">🔥 Deal Alert</span>';
        $html .= '<div class="deal-inner">';

        if ($product->main_image_url) {
            $html .= '<div class="deal-image">';
            $html .= '<img src="' . htmlspecialchars($product->main_image_url) . '" alt="' . $name . '">';
            $html .= '</div>';
        }

        $html .= '<div class="deal-content">';

        if ($brand) {
            $html .= '<div class="deal-brand">' . $brand . '</div>';
        }

        $html .= '<h3 class="deal-title">' . $name . '</h3>';

        if ($description) {
            $html .= '<p class="deal-description">' . $description . '</p>';
        }

        $html .= '<div class="deal-pricing">';
        $html .= '<span class="deal-original-price">£' . $price . '</span>';
        $html .= '<span class="deal-sale-price">£' . $salePrice . '</span>';
        $html .= '<span class="deal-savings">Save £' . $savings . ' (' . $savingsPct . '%)</span>';
        $html .= '</div>';

        $html .= '<a href="' . $clickUrl . '" class="deal-button btn-primary" rel="sponsored noopener" target="_blank">Get Deal</a>';
        $html .= '</div>'; // deal-content
        $html .= '</div>'; // deal-inner
        $html .= '</div>'; // advert-block

        return $html;
    }

    private function buildClickUrl(string $type, int $id, RenderContext $context): string
    {
        return url('/go/' . $type . '/' . $id) . '?' . http_build_query([
                'channel' => $context->channel,
                'surface_type' => $context->surfaceType,
                'surface_id' => $context->surfaceId,
            ]);
    }
}