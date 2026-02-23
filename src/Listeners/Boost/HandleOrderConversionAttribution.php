<?php

namespace App\Listeners\Boost;

use App\Enums\Boost\BoostEventType;
use App\Events\Orders\OrderCreatedEvent;
use App\Framework\Support\Config;
use App\Framework\Support\Logger;
use App\Repositories\Adverts\Boost\BoostEventRepository;
use App\Repositories\Adverts\Boost\BoostRepository;
use App\Services\Adverts\Boost\BoostEventService;

class HandleOrderConversionAttribution
{
    private int $clickWindowHours;
    private int $impressionWindowHours;

    public function __construct(
        private readonly BoostRepository      $boostRepository,
        private readonly BoostEventRepository $boostEventRepository,
        private readonly BoostEventService    $boostEventService,
    )
    {
        $windows = Config::get('boost.attribution_windows');

        $this->clickWindowHours = (int)$windows['click'];
        $this->impressionWindowHours = (int)$windows['impression'];
    }

    public function handle(OrderCreatedEvent $event): void
    {
        $order = $event->order;

        foreach ($order->items as $item) {
            try {
                $this->attributeItem($item, $order->session_hash);
            } catch (\Exception $e) {
                Logger::error('Failed to attribute boost conversion', [
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function attributeItem(object $item, ?string $sessionHash): void
    {
        $boost = $this->boostRepository->findActiveOrRecentForTarget('product', $item->product_id);

        if (!$boost) {
            return;
        }

        // Prefer click attribution over impression attribution.
        // A click within 24h is a stronger signal than an impression within 7 days.
        $attributed = $this->boostEventRepository->hasEventWithinWindow(
            boostId: $boost->id,
            type: BoostEventType::Click,
            sessionHash: $sessionHash,
            withinHours: $this->clickWindowHours,
        );

        if (!$attributed) {
            $attributed = $this->boostEventRepository->hasEventWithinWindow(
                boostId: $boost->id,
                type: BoostEventType::Impression,
                sessionHash: $sessionHash,
                withinHours: $this->impressionWindowHours,
            );
        }

        if (!$attributed) {
            return;
        }

        $this->boostEventService->recordConversion(
            boostId: $boost->id,
            sessionHash: $sessionHash,
            metadata: ['order_item_id' => $item->id, 'product_id' => $item->product_id],
        );
    }
}