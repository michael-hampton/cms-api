<?php

namespace App\Services\Shopping;

class ShippingService
{
    private array $config;

    public function __construct()
    {
        // Load from config file or database
        $this->config = [
            'free_shipping_threshold' => 100.00,
            'default_rate' => 10.00,
            'rates_by_country' => [
                'US' => 10.00,
                'CA' => 15.00,
                'GB' => 12.00,
                'AU' => 20.00,
            ]
        ];
    }

    public function calculateShipping(float $subtotal, array $data, bool $requiresShipping = true): float
    {
        // No shipping for digital items
        if (!$requiresShipping) {
            return 0.00;
        }

        // Free shipping over threshold
        if ($subtotal >= $this->config['free_shipping_threshold']) {
            return 0.00;
        }

        // Get country-specific rate
        $country = $data['country'] ?? 'US';
        return $this->config['rates_by_country'][$country] ?? $this->config['default_rate'];
    }

    public function getFreeShippingThreshold(): float
    {
        return $this->config['free_shipping_threshold'];
    }

    public function getShippingRate(string $country): float
    {
        return $this->config['rates_by_country'][$country] ?? $this->config['default_rate'];
    }
}