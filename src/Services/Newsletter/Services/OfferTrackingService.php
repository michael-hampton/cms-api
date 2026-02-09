<?php

namespace App\Services\Newsletter\Services;

use App\Framework\Support\Logger;
use App\Models\ProductOffer;
use App\Services\Adverts\DealTrackingRecorder;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;

class OfferTrackingService
{
    public function __construct(
        private readonly DealTrackingRecorder $trackingRecorder,
        private readonly Logger $logger
    )
    {
    }

    public function trackRender(ProductOffer $offer, ?int $dealId, NewsletterRenderContext $context): void
    {
        try {
            $advertContext = $context->toAdvertContext();

            $this->trackingRecorder->recordOfferRender(
                $offer->id,
                $dealId,
                $advertContext
            );
        } catch (\Exception $e) {
            // Tracking failures must not suppress rendering
            $this->logger->error('Failed to track offer render', [
                'offer_id' => $offer->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}