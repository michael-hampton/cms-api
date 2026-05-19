<?php

namespace App\Services\Billing\Stripe;

use App\Services\Billing\Stripe\Contracts\StripeProductGatewayInterface;
use Stripe\StripeClient;

class StripeProductGateway implements StripeProductGatewayInterface
{
    public function __construct(private readonly StripeClient $stripe)
    {
    }

    public function createProduct(string $name): string
    {
        $product = $this->stripe->products->create(['name' => $name]);

        return $product->id;
    }

    /**
     * Delete a Stripe product. Swallows not-found errors so compensation
     * is always safe to call, even on retry.
     */
    public function deleteProduct(string $stripeProductId): void
    {
        try {
            $this->stripe->products->delete($stripeProductId);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            // Product already deleted or never existed — safe to ignore.
        }
    }
}