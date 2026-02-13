<?php

namespace App\Services\Shipping;

use App\DTO\Checkout\DeliveryMethodConfig;
use App\DTO\Checkout\EstimatedDelivery;
use DateTimeImmutable;

interface DeliveryEstimatorInterface
{
    public function estimate(
        FulfilmentTypeInterface $fulfilment,
        DeliveryMethodConfig    $deliveryMethod,
        DateTimeImmutable       $orderDate
    ): EstimatedDelivery;
}