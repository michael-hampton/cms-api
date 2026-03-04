<?php

namespace App\Parsers\Renderers\Page;

use App\Framework\Support\Logger;
use App\Parsers\Dtos\DealBlockDto;
use App\Parsers\Dtos\ProductBlockDto;
use App\Parsers\Renderers\DealBlockRenderer;
use App\Parsers\Renderers\ProductBlockRenderer;
use App\Repositories\Offers\ProductOfferRepository;
use App\Repositories\Product\ProductRepository;

class PageBoostRenderer
{
    public function __construct(
        private readonly ProductRepository      $productRepository,
        private readonly ProductOfferRepository $offerRepository,
        private readonly ProductBlockRenderer   $productRenderer,
        private readonly DealBlockRenderer      $dealRenderer,
        private readonly Logger                 $logger,
    )
    {
    }

    public function render(array $boostData): string
    {
        try {
            $boostableType = $boostData['boostable_type'] ?? '';
            $boostableId = (int)($boostData['boostable_id'] ?? 0);

            $innerHtml = $boostableType === 'offer'
                ? $this->renderOffer($boostableId)
                : $this->renderProduct($boostableId);

            if (empty($innerHtml)) {
                return '';
            }

            return '<div data-advert="boost" class="advert-block boost-block">'
                . '<span class="advert-label boost-label">⭐ Featured</span>'
                . $innerHtml
                . '</div>';

        } catch (\Exception $e) {
            $this->logger->error('PageBoostRenderer failed', [
                'boost_data' => $boostData,
                'error' => $e->getMessage(),
            ]);
            return '';
        }
    }

    private function renderOffer(int $offerId): string
    {
        $offer = $this->offerRepository->find($offerId);

        if (!$offer) {
            return '';
        }

        $dto = DealBlockDto::fromArray([
            'link' => $offer->link ?? '#',
            'title' => $offer->product?->name ?? 'Special Offer',
            'productName' => $offer->product?->name ?? 'Special Offer',
            'brand' => $offer->product?->brand?->name ?? '',
            'price' => $offer->original_price ?? 0,
            'salePrice' => $offer->sale_price ?? 0,
            'currency' => $offer->currency ?? '£',
            'description' => $offer->description ?? '',
            'image' => $offer->product?->main_image_url ? ['src' => $offer->product->main_image_url] : null,
            'sponsored' => true,
            'openInNewTab' => true,
        ]);

        return $this->dealRenderer->render($dto);
    }

    private function renderProduct(int $productId): string
    {
        $product = $this->productRepository->find($productId);

        if (!$product) {
            return '';
        }

        $dto = ProductBlockDto::fromArray([
            'link' => $product->link ?? '#',
            'name' => $product->name,
            'brand' => $product->brand?->name ?? '',
            'productName' => $product->name,
            'price' => $product->price ?? 0,
            'salePrice' => $product->sale_price ?? 0,
            'currency' => $product->currency ?? '£',
            'description' => $product->description ?? '',
            'image' => $product->main_image_url ? ['src' => $product->main_image_url] : null,
            'sponsored' => true,
        ]);

        return $this->productRenderer->render($dto);
    }
}