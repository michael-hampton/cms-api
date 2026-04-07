<?php

namespace App\Services\Newsletter\Services;

use App\Framework\Support\Logger;
use App\Models\Product;
use App\Services\Adverts\DealTrackingRecorder;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;

class DealTrackingService
{
    public function __construct(
        private readonly DealTrackingRecorder $trackingRecorder,
        private readonly Logger $logger
    )
    {
    }

    public function trackRender(Product $product, NewsletterRenderContext $context): void
    {
        try {

            $advertContext = $context->toAdvertContext();

            $this->trackingRecorder->recordDealRender(
                $product->id,
                $advertContext,
                '',
                '',
                $context->siteId
            );
        } catch (\Exception $e) {
            // Tracking failures must not suppress rendering
            $this->logger->error('Failed to track deal render', [
                'product_id' => $product->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}