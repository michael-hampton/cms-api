<?php

namespace App\Framework\ServiceProvider;

use App\Services\Billing\Stripe\Contracts\StripeSubscriptionGatewayInterface;
use App\Services\Billing\Stripe\Contracts\StripeSubscriptionScheduleGatewayInterface;
use App\Services\Billing\Stripe\StripeSubscriptionGateway;
use App\Services\Billing\Stripe\StripeSubscriptionScheduleGateway;

/**
 * Service Service Provider - Business logic services
 */
class ServiceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->autoRegister('services', null, false);

        $this->container->bind(
            StripeSubscriptionGatewayInterface::class,
            StripeSubscriptionGateway::class,
        );

        $this->container->bind(
            StripeSubscriptionScheduleGatewayInterface::class,
            StripeSubscriptionScheduleGateway::class,
        );
    }
}
