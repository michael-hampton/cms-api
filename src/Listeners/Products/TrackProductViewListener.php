<?php
// src/Listeners/Product/TrackProductViewListener.php

namespace App\Listeners\Products;

use App\Events\Products\ProductViewedEvent;
use App\Repositories\Product\ProductViewRepository;
use App\Services\Product\RecentlyViewedService;

class TrackProductViewListener
{
    public function __construct(
        private readonly ProductViewRepository $productViewRepository,
        private readonly RecentlyViewedService $recentlyViewedService
    )
    {
    }

    public function handle(ProductViewedEvent $event): void
    {
        // Track in session
        $this->recentlyViewedService->addProduct($event->product);

        // Track in database
        $this->productViewRepository->trackView(
            $event->product,
            $event->userId,
            $event->sessionId,
            $event->ipAddress
        );
    }
}