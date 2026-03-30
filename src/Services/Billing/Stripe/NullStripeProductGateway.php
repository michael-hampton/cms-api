<?php

namespace App\Services\Billing\Stripe;

use App\Services\Billing\Stripe\Contracts\StripeProductGatewayInterface;

class NullStripeProductGateway implements StripeProductGatewayInterface
{
    public function createProduct(string $name): string
    {
        return 'prod_test_' . uniqid();
    }

    public function deleteProduct(string $productId): void
    {
    }
}