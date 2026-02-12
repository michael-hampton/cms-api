<?php

namespace App\Services\Commission;

use App\Models\Merchant;
use App\Models\Product;
use App\Repositories\Product\ProductRepository;
use App\Services\Commission\Contracts\CommissionStrategyInterface;
use App\Services\Commission\Strategies\BundleCommissionStrategy;
use App\Services\Commission\Strategies\DealCommissionStrategy;
use App\Services\Commission\Strategies\DefaultCommissionStrategy;
use App\Services\Commission\Strategies\OfferCommissionStrategy;
use App\Services\Commission\Strategies\SubscriptionCommissionStrategy;

class CommissionService
{
    /**
     * @var CommissionStrategyInterface[]
     */
    private array $strategies;

    public function __construct(private readonly ProductRepository $productRepository, array $strategies = [])
    {
        if (empty($strategies)) {
            // Priority order: Bundle > Subscription > Offer > Deal > Default
            $this->strategies = [
                new BundleCommissionStrategy($this->productRepository),
                new SubscriptionCommissionStrategy(),
                new OfferCommissionStrategy($this->productRepository),
                new DealCommissionStrategy($this->productRepository),
                new DefaultCommissionStrategy(),
            ];
        }
    }

    public function determineRate(Product $product, Merchant $merchant): float
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($product)) {
                return $strategy->getRate($product, $merchant);
            }
        }

        throw new \RuntimeException('No commission strategy matched.');
    }

    public function calculate(float $amount, float $rate): array
    {
        $commission = round($amount * $rate, 2);
        $net = round($amount - $commission, 2);

        return [
            'rate' => $rate,
            'commission_amount' => $commission,
            'net_amount' => $net,
        ];
    }
}