<?php

namespace App\ViewModels\Checkout;

use App\Services\Pricing\CartLineDisclosureService;

final readonly class CheckoutPageViewModel
{
    private function __construct(
        public array $items,
        public float $subtotal,
        public float $shipping,
        public float $tax,
        public float $taxRate,
        public float $finalTotal,
        public string $currency,
        public string $checkoutMode,
        public bool $requiresShipping,
        public bool $forceAddress,
        public bool $isMixedCart,
        public bool $isSubscriptionCart,
        public bool $isOneTimeCart,
        public bool $isMixedSubscriptionCart,
        public string $basketType,
        public array $subscriptionCartSnapshot,
        public array $hasPreOrders,
        public ?array $appliedVoucher,
        public string $site,
        public string $apiBase,
    ) {
    }

    public static function make(
        array $items,
        float $shipping,
        float $tax,
        float $taxRate,
        string $currency,
        string $checkoutMode,
        bool $requiresShipping,
        bool $memberHasAddresses,
        bool $isOneTimeCart,
        bool $isMixedSubscriptionCart,
        array $hasPreOrders,
        ?array $appliedVoucher,
        string $site,
        CartLineDisclosureService $disclosures,
        array $plansById,
        string $locale = 'en_GB',
        array $copyOverrides = [],
        array $formatterSettings = [],
    ): self {
        $subscriptionItems = [];
        $productItems = [];
        $resolvedItems = [];
        $hasPrint = false;
        $hasDigital = false;

        foreach ($items as $item) {
            $planId = (int)($item['subscription_plan_id'] ?? 0);

            if ($planId > 0) {
                $plan = $plansById[$planId] ?? null;
                $planFacts = [
                    'billing_period' => $plan?->billing_period ?? 'monthly',
                    'trial_days' => $plan?->trial_days,
                    'is_one_time' => $plan?->isOneTime() ?? false,
                    'renewal_date' => $item['options']['renewal_date'] ?? null,
                ];

                $item = $disclosures->enrich(
                    $item,
                    $planFacts,
                    $locale,
                    $currency,
                    $copyOverrides,
                    $formatterSettings,
                );

                $deliveryType = strtolower((string)($item['options']['delivery_type'] ?? 'print'));
                $hasDigital = $hasDigital || str_contains($deliveryType, 'digital');
                $hasPrint = $hasPrint || $deliveryType !== 'digital';
                $subscriptionItems[] = $item;
            } else {
                $hasPrint = true;
                $productItems[] = $item;
            }

            $resolvedItems[] = $item;
        }

        $basketType = match (true) {
            $hasPrint && $hasDigital => 'print_and_digital',
            $hasDigital => 'digital_only',
            default => 'print_only',
        };

        $effectiveRequiresShipping = $basketType === 'digital_only'
            ? false
            : $requiresShipping;

        $subtotal = array_reduce(
            $resolvedItems,
            static fn(float $total, array $item): float => $total + (float)($item['subtotal'] ?? 0),
            0.0,
        );

        $voucherDiscount = (float)($appliedVoucher['discount'] ?? 0);
        $finalTotal = max(0, $subtotal + $shipping + $tax - $voucherDiscount);

        $snapshot = array_map(static function (array $item) use ($plansById): array {
            $planId = (int)($item['subscription_plan_id'] ?? 0);
            $options = $item['options'] ?? [];
            $plan = $plansById[$planId] ?? null;

            return [
                'subscription_plan_id' => $planId,
                'delivery_type' => $options['delivery_type'] ?? 'print',
                'pricing_tier_id' => $options['pricing_tier_id'] ?? null,
                'start_date' => $options['start_date'] ?? null,
                'is_one_time' => $plan?->isOneTime() ?? false,
                'promotion' => $item['promotion'] ?? null,
                'line_summary' => $item['line_summary'] ?? null,
            ];
        }, $subscriptionItems);

        return new self(
            items: array_values($resolvedItems),
            subtotal: $subtotal,
            shipping: $shipping,
            tax: $tax,
            taxRate: $taxRate,
            finalTotal: $finalTotal,
            currency: strtoupper($currency),
            checkoutMode: $checkoutMode,
            requiresShipping: $effectiveRequiresShipping,
            forceAddress: !$memberHasAddresses,
            isMixedCart: $subscriptionItems !== [] && $productItems !== [],
            isSubscriptionCart: $subscriptionItems !== [],
            isOneTimeCart: $isOneTimeCart,
            isMixedSubscriptionCart: $isMixedSubscriptionCart,
            basketType: $basketType,
            subscriptionCartSnapshot: array_values($snapshot),
            hasPreOrders: $hasPreOrders,
            appliedVoucher: $appliedVoucher,
            site: $site,
            apiBase: '/api/' . $site,
        );
    }

    public function checkoutBootstrap(): array
    {
        return [
            'apiBase' => $this->apiBase,
            'planCurrency' => $this->currency,
            'taxRate' => $this->taxRate,
            'initialSubtotal' => $this->subtotal,
            'initialShipping' => $this->shipping,
            'subscriptionCartSnapshot' => $this->subscriptionCartSnapshot,
            'cartItems' => array_map(static fn(array $item): array => [
                'id' => $item['id'] ?? null,
                'product_name' => $item['product_name'] ?? $item['plan_name'] ?? 'Subscription',
                'plan_name' => $item['plan_name'] ?? null,
                'subscription_plan_id' => $item['subscription_plan_id'] ?? null,
                'price' => (float)($item['price'] ?? 0),
                'subtotal' => (float)($item['subtotal'] ?? 0),
                'quantity' => (int)($item['quantity'] ?? 1),
                'options' => $item['options'] ?? [],
                'promotion' => $item['promotion'] ?? null,
                'line_summary' => $item['line_summary'] ?? null,
            ], $this->items),
        ];
    }

    public function checkoutConfig(string $stripeKey): array
    {
        return [
            'site' => $this->site,
            'stripeKey' => $stripeKey,
            'checkoutMode' => $this->checkoutMode,
            'requiresShipping' => $this->requiresShipping,
            'basketType' => $this->basketType,
            'isMixedCart' => $this->isMixedCart,
            'isMixedSubscriptionCart' => $this->isMixedSubscriptionCart,
            'isSubscriptionCart' => $this->isSubscriptionCart,
            'isOneTimeCart' => $this->isOneTimeCart,
            'forceAddress' => $this->forceAddress,
        ];
    }
}
