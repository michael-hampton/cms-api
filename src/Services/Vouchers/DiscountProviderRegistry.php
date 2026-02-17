<?php

namespace App\Services\Vouchers;

use App\Services\Vouchers\Contracts\DiscountProvider;

class DiscountProviderRegistry
{
    /** @var DiscountProvider[] */
    private array $providers = [];

    /**
     * @return DiscountProvider[]
     */
    public function all(): array
    {
        return $this->providers;
    }

    /**
     * @return DiscountProvider[]
     */
    public function sortedByPriority(): array
    {
        $providers = $this->providers;

        usort(
            $providers,
            fn(DiscountProvider $a, DiscountProvider $b) => $a->priority() <=> $b->priority()
        );

        return $providers;
    }

    /**
     * Replace all providers (ideal for tests)
     *
     * @param DiscountProvider[] $providers
     */
    public function setProviders(array $providers): void
    {
        $this->providers = [];

        foreach ($providers as $provider) {
            $this->register($provider);
        }
    }

    public function register(DiscountProvider $provider): void
    {
        $this->providers[] = $provider;
    }

    public function clear(): void
    {
        $this->providers = [];
    }
}
