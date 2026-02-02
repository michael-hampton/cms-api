<?php

namespace App\Repositories\Billing;

use App\Framework\Support\Collection;
use App\Models\Shipment;
use App\Repositories\Repository;

class ShipmentRepository extends Repository
{
    protected function getModelClass(): string
    {
        return Shipment::class;
    }

    public function findByOrderId(int $orderId): ?Shipment
    {
        return Shipment::where('order_id', $orderId)->first();
    }

    public function getByCheckoutId(string $checkoutId): Collection
    {
        return Shipment::where('checkout_id', $checkoutId)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function getByMerchantId(int $merchantId): Collection
    {
        return Shipment::where('merchant_id', $merchantId)
            ->orderBy('created_at', 'desc')
            ->get();
    }
}