<?php

namespace App\Services\Billing\Stripe\Contracts;

interface StripeProductGatewayInterface
{
    /**
     * Create a product in Stripe and return the stripe_product_id.
     */
    public function createProduct(string $name): string;

    /**
     * Delete a Stripe product by ID.
     *
     * Used for compensation when a DB update fails after a product was created.
     * Implementations should swallow not-found errors (idempotent cleanup).
     */
    public function deleteProduct(string $stripeProductId): void;
}