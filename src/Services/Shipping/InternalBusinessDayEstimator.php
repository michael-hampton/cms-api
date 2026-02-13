<?php

namespace App\Services\Shipping;


use App\DTO\Checkout\DeliveryMethodConfig;
use App\DTO\Checkout\EstimatedDelivery;
use DateTimeImmutable;

class InternalBusinessDayEstimator implements DeliveryEstimatorInterface
{
    public function __construct(
        private readonly BusinessDayCalculator $calculator,
        private readonly CutOffTimeResolver    $cutOffResolver
    )
    {
    }

    public function estimate(
        FulfilmentTypeInterface $fulfilment,
        DeliveryMethodConfig    $deliveryMethod,
        DateTimeImmutable       $orderDate
    ): EstimatedDelivery
    {
        // Digital items get instant delivery
        if (!$fulfilment->requiresShipping()) {
            return EstimatedDelivery::digital();
        }

        // Resolve start date based on cut-off time
        $startDate = $this->cutOffResolver->resolveStartDate(
            $orderDate,
            $deliveryMethod->cutoffTime
        );

        // Calculate dispatch date
        $dispatchDate = $this->calculator->addBusinessDays(
            $startDate,
            $fulfilment->dispatchDays()
        );

        // Calculate delivery window
        $from = $this->calculator->addBusinessDays(
            $dispatchDate,
            $deliveryMethod->transitMinDays
        );

        $to = $this->calculator->addBusinessDays(
            $dispatchDate,
            $deliveryMethod->transitMaxDays
        );

        return EstimatedDelivery::physical($dispatchDate, $from, $to);
    }
}