<?php

namespace App\Parsers\Renderers\Page;

use App\Framework\Support\Logger;
use App\Repositories\Offers\ProductOfferRepository;
use App\Services\Adverts\DealTrackingRecorder;
use App\Services\Adverts\OfferVisibilityResolver;
use App\Services\Adverts\RenderContext;

class PageOfferRenderer
{
    public function __construct(
        private readonly ProductOfferRepository  $offerRepository,
        private readonly OfferVisibilityResolver $visibilityResolver,
        private readonly DealTrackingRecorder    $trackingRecorder,
        private readonly Logger                  $logger,
    )
    {
    }

    public function render(int $offerId, RenderContext $context, string $ip = '', string $userAgent = ''): string
    {
        try {
            $offer = $this->offerRepository->find($offerId);

            if (!$offer) {
                return '';
            }

            $decision = $this->visibilityResolver->resolve($offer, $context);

            if (!$decision->shouldRender) {
                return '';
            }

            $this->trackingRecorder->recordOfferRender(
                offerId: $offerId,
                dealId: $decision->metadata['deal_id'] ?? null,
                context: $context,
                ip: $ip,
                userAgent: $userAgent,
            );

            return $this->renderHtml($offer, $context);

        } catch (\Exception $e) {
            $this->logger->error('PageOfferRenderer failed', [
                'offer_id' => $offerId,
                'error' => $e->getMessage(),
            ]);
            return '';
        }
    }

    private function renderHtml(object $offer, RenderContext $context): string
    {
        $name = htmlspecialchars($offer->product->name ?? 'Special Offer');
        $description = htmlspecialchars($offer->description ?? '');
        $currency = htmlspecialchars($offer->currency ?? '£');
        $original = number_format((float)($offer->original_price ?? 0), 2);
        $sale = number_format((float)($offer->sale_price ?? 0), 2);
        $hasSale = $offer->sale_price && $offer->sale_price < $offer->original_price;

        $clickUrl = $this->buildClickUrl('offer', $offer->id, $context);

        $html = '<div data-advert="offer" class="advert-block offer-block">';
        $html .= '<span class="advert-label">Partner Offer</span>';

        if ($offer->product?->main_image_url) {
            $html .= '<div class="offer-image">';
            $html .= '<img src="' . htmlspecialchars($offer->product->main_image_url) . '" alt="' . $name . '">';
            $html .= '</div>';
        }

        $html .= '<div class="offer-content">';
        $html .= '<h3 class="offer-title">' . $name . '</h3>';

        if ($description) {
            $html .= '<p class="offer-description">' . $description . '</p>';
        }

        $html .= '<div class="offer-pricing">';
        if ($hasSale) {
            $html .= '<span class="offer-price-original">' . $currency . $original . '</span>';
            $html .= '<span class="offer-price-sale">' . $currency . $sale . '</span>';
        } else {
            $html .= '<span class="offer-price">' . $currency . $original . '</span>';
        }
        $html .= '</div>';

        $html .= '<a href="' . $clickUrl . '" class="offer-cta btn-primary" rel="sponsored">View Offer</a>';
        $html .= '</div>';
        $html .= '</div>';

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