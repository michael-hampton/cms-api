<?php

namespace App\Services\Newsletter\Renderers;

use App\Framework\Support\Logger;
use App\Framework\Support\Str;
use App\Repositories\Offers\ProductOfferRepository;
use App\Services\Adverts\OfferVisibilityResolver;
use App\Services\Adverts\RenderContext;
use App\Services\Newsletter\Contracts\EmailBlockRenderer;
use App\Services\Newsletter\DTOs\BlockData\BaseBlockData;
use App\Services\Newsletter\DTOs\BlockData\OfferBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;
use App\Services\Newsletter\Services\OfferTrackingService;
use App\Services\Newsletter\Services\TrackingUrlBuilder;

class OfferBlockRenderer implements EmailBlockRenderer
{
    public $type = 'offer';
    public function __construct(
        private readonly ProductOfferRepository  $offerRepository,
        private readonly OfferVisibilityResolver $offerVisibilityResolver,
        private readonly OfferTrackingService    $trackingService,
        private readonly TrackingUrlBuilder      $trackingUrlBuilder,
        private readonly Logger $logger
    )
    {
    }

    public function supports(string $type): bool
    {
        return $type === $this->type;
    }

    public function render(BaseBlockData $blockData, NewsletterRenderContext $newsletterRenderContext): RenderedBlock
    {
        if (!$blockData instanceof OfferBlockData) {
            $this->logger->error('Invalid block data type for OfferBlockRenderer', [
                'expected' => OfferBlockData::class,
                'received' => get_class($blockData)
            ]);
            return RenderedBlock::skipped();
        }

        try {
            $offer = $this->offerRepository->find($blockData->offerId);

            if (!$offer) {
                $this->logger->warning('Offer not found', [
                    'offer_id' => $blockData->offerId
                ]);
                return RenderedBlock::skipped();
            }

            $context = RenderContext::forNewsletter($newsletterRenderContext->newsletter->id, $newsletterRenderContext->member);

            $eligibility = $this->offerVisibilityResolver->resolve(
                $offer,
                $context
            );

            if (!$eligibility->shouldRender) {
                $this->logger->info('Offer suppressed', [
                    'offer_id' => $offer->id,
                    'reason' => $eligibility->reason
                ]);
                return RenderedBlock::skipped();
            }

            // Track AFTER eligibility check, BEFORE rendering
            $this->trackingService->trackRender($offer, $context->surfaceId, $newsletterRenderContext);

            $html = $this->renderHtml($offer, $newsletterRenderContext);
            return RenderedBlock::rendered($html);

        } catch (\Exception $e) {
            $this->logger->error('Failed to render offer block', [
                'error' => $e->getMessage(),
                'offer_id' => $blockData->offerId
            ]);
            return RenderedBlock::skipped();
        }
    }

    private function renderHtml($offer, NewsletterRenderContext $context): string
    {
        $trackingUrl = $this->trackingUrlBuilder->buildOfferUrl(
            $offer->id,
            $context->sendId,
            $context->includeTracking,
            $context->newsletter->id
        );

        $html = [];
        $html[] = '<div style="border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px; margin: 20px 0; background: #fafafa;">';
        $html[] = '<div style="color: #666; font-size: 12px; text-transform: uppercase; margin-bottom: 10px;">Partner Offer</div>';

        if ($offer->product) {
            $html[] = '<h3 style="margin: 0 0 10px 0;">' . Str::sanitize($offer->product->name) . '</h3>';

            if ($offer->sale_price) {
                $html[] = '<div style="margin: 10px 0;">';
                $html[] = '<span style="text-decoration: line-through; color: #999;">' . ($offer->currency ?? '$') . $offer->original_price . '</span>';
                $html[] = ' <span style="color: #d9534f; font-size: 24px; font-weight: bold;">' . ($offer->currency ?? '$') . $offer->sale_price . '</span>';
                $html[] = '</div>';
            }
        }

        if ($offer->description) {
            $html[] = '<p style="color: #666; margin: 10px 0;">' . Str::sanitize($offer->description) . '</p>';
        }

        $html[] = '<a href="' . $trackingUrl . '" style="display: inline-block; padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin-top: 10px;">View Offer</a>';
        $html[] = '</div>';

        return implode("\n", $html);
    }
}