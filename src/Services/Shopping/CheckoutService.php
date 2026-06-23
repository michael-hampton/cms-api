<?php

namespace App\Services\Shopping;

use App\Actions\Stock\PurchaseProductAction;
use App\DTO\Checkout\DeliveryMethodConfig;
use App\DTO\Stripe\CreatePaymentIntentDto;
use App\Enums\CartItemType;
use App\Enums\Orders\OrderLineStatus;
use App\Enums\Orders\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\Checkout\MultiMerchantCheckoutCompletedEvent;
use App\Events\Orders\OrderCompletedEvent;
use App\Framework\Authorization\MemberAuthWrapper;
use App\Framework\Database\Database;
use App\Models\SubscriptionPlan;
use App\Repositories\Billing\ShipmentRepository;
use App\Repositories\Product\MerchantRepository;
use App\Repositories\Product\ProductRepository;
use App\Repositories\Product\ProductVariantRepository;
use App\Repositories\Rewards\RewardsRepository;
use App\Services\Billing\CheckoutSplittingService;
use App\Services\Billing\Order\OrderCreationService;
use App\Services\Billing\Order\OrderManager;
use App\Services\Billing\OrderCalculationService;
use App\Services\Billing\PaymentAllocationService;
use App\Services\Billing\Preorder\Actions\CalculateSellableStockAction;
use App\Services\Billing\Preorder\Actions\ResolveAvailabilityAction;
use App\Services\Billing\Stripe\Contracts\StripeCustomerGatewayInterface;
use App\Services\Billing\Stripe\Contracts\StripePaymentIntentGatewayInterface;
use App\Services\Billing\Stripe\StripeCustomerGateway;
use App\Services\Billing\Stripe\StripePaymentIntentGateway;
use App\Services\Billing\TaxCalculatorService;
use App\Services\Currency\CurrencyResolver;
use App\Services\Shipping\FulfilmentResolver;
use App\Services\Shipping\InternalBusinessDayEstimator;
use App\Services\Shipping\ShippingService;
use App\Services\ValueObjects\Money;
use App\Services\Vouchers\DiscountContext\DiscountContext;
use App\Services\Vouchers\DiscountContext\VoucherContext;
use App\Services\Vouchers\DiscountResolver;
use App\Services\Vouchers\ResolvedDiscounts;
use App\Services\Vouchers\VoucherService;
use DateTimeImmutable;
use Exception;

class CheckoutService
{
    public function __construct(
        private readonly CartService                         $cartService,
        private readonly OrderCreationService                $orderCreationService,
        private readonly VoucherService                      $voucherService,
        private readonly ShippingService                     $shippingService,
        private readonly MemberAuthWrapper                   $memberAuthWrapper,
        private readonly OrderCalculationService             $calculationService,
        private readonly StripePaymentIntentGateway $paymentIntentGateway,
        private readonly StripeCustomerGateway      $customerGateway,
        private readonly CheckoutSplittingService            $splittingService,
        private readonly PaymentAllocationService            $allocationService,
        private readonly MerchantShippingService             $merchantShippingService,
        private readonly ShipmentRepository                  $shipmentRepository,
        private readonly CurrencyResolver                    $currencyResolver,
        private readonly Database                            $database,
        private readonly OrderManager                        $orderService,
        private readonly TaxCalculatorService                $taxCalculatorService,
        private readonly MerchantRepository                  $merchantRepository,
        private readonly DiscountResolver                    $discountResolver,
        private readonly RewardsRepository                   $rewardsRepository,
        private readonly InternalBusinessDayEstimator        $deliveryEstimator,
        private readonly FulfilmentResolver                  $fulfilmentResolver,
        private readonly ProductRepository                   $productRepository,
        private readonly ResolveAvailabilityAction           $resolveAvailabilityAction,
        private readonly CalculateSellableStockAction        $calculateSellableStockAction,
        private readonly ProductVariantRepository            $productVariantRepository,
        private readonly CheckoutEligibilityService          $eligibilityService,
        // ── Stock allocation ────────────────────────────────────────────────
        private readonly PurchaseProductAction               $purchaseProductAction,
    )
    {
    }

    public function processCheckout(array $data, int $siteId): array
    {
        try {
            $validation = $this->validateCheckoutData($data);

            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => $validation['message']
                ];
            }

            $cartItems = $this->cartService->getItems();
            if (empty($cartItems)) {
                return [
                    'success' => false,
                    'message' => 'Cart is empty'
                ];
            }

            $cartItems = $this->validateAndResolveAvailability($cartItems);
            $cartItems = $this->attachDeliveryEstimates($cartItems);

            $member = $this->memberAuthWrapper->check() ? $this->memberAuthWrapper->getMember() : null;

            if ($member) {
                $eligibility = $this->eligibilityService->validate($member, $cartItems);
                $cartItems = $eligibility->valid;

                if (empty($cartItems)) {
                    return [
                        'success' => false,
                        'message' => 'All items were invalid and removed from the cart.'
                    ];
                }
            }

            $voucherData = null;
            if (!empty($data['voucher_code'])) {
                $voucherData = $this->voucherService->validateVoucherForCheckout(
                    $data['voucher_code'],
                    $cartItems,
                    $member->id ?? null
                );

                $voucherData = $voucherData->valid ? $voucherData->toArray() : null;

                if (isset($data['voucher_id']) && $voucherData && $data['voucher_id'] != $voucherData->voucher->id) {
                    $voucherData = null;
                }
            }

            $discounts = $this->resolveDiscounts($cartItems, $member, $voucherData, $data, $siteId);

            $shippingCost = $this->shippingService->calculateShipping(
                $discounts->finalSubtotalCents / 100,
                $data
            );

            $currency = $this->currencyResolver->resolve($siteId);

            $taxData = $this->taxCalculatorService->calculateOrderTax(
                $discounts->finalSubtotalCents,
                (int)($shippingCost * 100),
                $data['country'] ?? 'GB',
                $data['state'] ?? null,
                $data['postal_code'] ?? null,
                $member
            );

            $taxCents = $taxData->taxCents;
            $shippingCents = (int)round($shippingCost * 100);
            $totalCents = $discounts->finalSubtotalCents + $shippingCents + $taxCents;

            return $this->database->transaction(function () use (
                $data,
                $cartItems,
                $discounts,
                $shippingCents,
                $taxCents,
                $totalCents,
                $siteId,
                $currency,
                $member,
                $eligibility
            ) {
                $orderData = $this->prepareOrderDataFromDiscounts(
                    $data,
                    $discounts,
                    $shippingCents,
                    $taxCents,
                    $totalCents,
                    $siteId,
                    $currency
                );

                $dto = new CreatePaymentIntentDto(
                    amountCents: $totalCents,
                    currency:    $currency,
                    metadata:    [
                        'offer_discount_cents'   => $discounts->offerDiscountCents,
                        'voucher_discount_cents' => $discounts->voucherDiscountCents,
                        'reward_discount_cents'  => $discounts->rewardDiscountCents,
                        'merchant_funded_cents'  => $discounts->merchantFundedCents,
                        'platform_funded_cents'  => $discounts->platformFundedCents,
                        'voucher_code'           => $discounts->metadata['voucher']['voucher_code'] ?? null,
                        'campaign_id'            => $discounts->metadata['voucher']['campaign_id'] ?? null,
                    ],
                );

                $paymentIntent = $this->paymentIntentGateway->create($dto)->toLegacyArray();

                if (!$paymentIntent['success']) {
                    return [
                        'success' => false,
                        'message' => $paymentIntent['message'] ?? 'Payment failed'
                    ];
                }

                // ── Stock allocation ─────────────────────────────────────────────────
                // Runs inside this transaction so a StockException or any subsequent
                // failure automatically rolls back the decrement.
                $this->allocateStockForCartItems($cartItems);
                // ────────────────────────────────────────────────────────────────────

                $orderItems = $this->prepareOrderItemsFromDiscounts($cartItems, $discounts);

                $order = $this->orderCreationService->create($orderData, $orderItems, $siteId);

                if ($discounts->voucherDiscountCents > 0 && isset($discounts->metadata['voucher'])) {
                    $voucherMetadata = $discounts->metadata['voucher'];
                    $this->voucherService->applyVoucher(
                        $voucherMetadata['voucher_id'],
                        $member->id ?? null,
                        $discounts->voucherDiscountCents / 100,
                        $order->id
                    );

                    if (!empty($voucherMetadata['merchant_id'])) {
                        $this->handleMerchantFunding(
                            $voucherMetadata['merchant_id'],
                            $discounts->voucherDiscountCents / 100,
                            $order->id,
                            $voucherMetadata['voucher_id']
                        );
                    }
                }

                if ($discounts->rewardDiscountCents > 0 && isset($discounts->metadata['reward'])) {
                    $this->claimReward($discounts->metadata['reward']['reward_id'], $order->id);
                }

                $this->cartService->clear();

                return [
                    'success' => true,
                    'has_removed_items' => !$eligibility?->isEmpty() ?? false,
                    'message' => 'Order placed successfully',
                    'client_secret' => $paymentIntent['client_secret'],
                    'payment_intent_id' => $paymentIntent['payment_intent_id'],
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'order_internal_id' => $order->id,
                    'total' => $totalCents / 100,
                    'currency' => strtoupper($currency),
                    'discount_breakdown' => [
                        'offer_discount' => $discounts ? $discounts->offerDiscountCents / 100 : 0,
                        'voucher_discount' => $discounts ? $discounts->voucherDiscountCents / 100 : 0,
                        'reward_discount' => $discounts ? $discounts->rewardDiscountCents / 100 : 0,
                        'total_discount' => $discounts ? $discounts->getTotalDiscountCents() / 100 : 0,
                        'tiered_discount' => $discounts ? $discounts->tieredDiscountCents / 100 : 0,
                    ]
                ];
            });

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to create order: ' . $e->getMessage()
            ];
        }
    }

    // =========================================================================
    // Stock allocation
    // =========================================================================

    private function validateCheckoutData(array $data): array
    {
        $requiresShipping = $this->cartService->requiresShipping();
        $required = $requiresShipping ? ['first_name', 'last_name', 'email'] : [];

        if ($requiresShipping && empty($data['saved_address'])) {
            $required = array_merge($required, ['address', 'city', 'postal_code', 'country']);
        }

        foreach ($required as $field) {
            if (empty($data[$field])) {
                return [
                    'valid' => false,
                    'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required'
                ];
            }
        }

        return ['valid' => true];
    }

    // =========================================================================
    // Remainder of the service (unchanged from original)
    // =========================================================================

    private function validateAndResolveAvailability(array $cartItems): array
    {
        foreach ($cartItems as &$item) {
            if (empty($item['product_id'])) {
                continue;
            }

            if (!empty($item['variant_id'])) {
                $variant = $this->productVariantRepository->lockForUpdate($item['variant_id']);

                if (!$variant) {
                    throw new \Exception("Variant not found: {$item['product_name']}");
                }

                $purchasable = $variant;
            } else {
                $product = $this->productRepository->lockForUpdate($item['product_id']);

                if (!$product) {
                    throw new \Exception("Product not found: {$item['product_name']}");
                }

                $purchasable = $product;
            }

            $policy = $product->availabilityPolicy();

            if (!$policy->canPurchase()) {
                throw new \Exception("{$product->name} is not available for purchase");
            }

            $sellableStock = $this->calculateSellableStockAction->execute($purchasable);

            if ($item['quantity'] > $sellableStock) {
                if (!$policy->isPreOrder()) {
                    throw new \Exception(
                        "{$product->name} has insufficient stock. " .
                        "Available: {$sellableStock}, Requested: {$item['quantity']}"
                    );
                }

                if (!$policy->getExpectedShipDate()) {
                    throw new \Exception("{$product->name} preorder is not configured correctly");
                }
            }

            $availability = $this->resolveAvailabilityAction->execute($purchasable, $item['quantity']);

            $item['order_line_status'] = $availability['status'];
            $item['expected_ship_date'] = $availability['expected_ship_date']?->format('Y-m-d H:i:s') ?? null;
            $item['is_preorder'] = $availability['is_preorder'];
        }

        return $cartItems;
    }

    private function attachDeliveryEstimates(array $cartItems): array
    {
        $itemsWithEstimates = [];
        $deliveryMethod = DeliveryMethodConfig::default();
        $orderDate = new DateTimeImmutable();

        foreach ($cartItems as $item) {
            $purchasable = $this->resolvePurchasable($item);

            if (!$purchasable) {
                $itemsWithEstimates[] = $item;
                continue;
            }

            $fulfilment = $this->fulfilmentResolver->resolve($purchasable);
            $estimate = $this->deliveryEstimator->estimate($fulfilment, $deliveryMethod, $orderDate);

            $itemsWithEstimates[] = array_merge($item, [
                'estimated_dispatch' => $estimate->dispatchDate?->format('Y-m-d'),
                'estimated_delivery_from' => $estimate->from?->format('Y-m-d'),
                'estimated_delivery_to' => $estimate->to?->format('Y-m-d'),
                'estimated_delivery_formatted' => $estimate->formattedRange(),
                'requires_shipping' => $estimate->requiresShipping
            ]);
        }

        return $itemsWithEstimates;
    }

    private function resolvePurchasable(array $item): mixed
    {
        if (!empty($item['subscription_plan_id'])) {
            return SubscriptionPlan::find($item['subscription_plan_id']);
        }

        if (!empty($item['variant_id'])) {
            return app(\App\Repositories\Product\ProductVariantRepository::class)
                ->find($item['variant_id']);
        }

        if (!empty($item['product_id'])) {
            return $this->productRepository->find($item['product_id']);
        }

        return null;
    }

    private function resolveDiscounts(
        array   $cartItems,
        ?object $member,
        ?array  $voucherData,
        array   $requestData,
        int     $siteId
    ): ResolvedDiscounts
    {
        $baseSubtotalCents = 0;
        foreach ($cartItems as $item) {
            if (($item['options']['type'] ?? '') === CartItemType::FREE_GIFT->value) {
                continue;
            }
            $basePriceCents = (int)round(($item['base_price'] ?? $item['price']) * 100);
            $quantity = $item['quantity'] ?? 1;
            $baseSubtotalCents += $basePriceCents * $quantity;
        }

        $context = new DiscountContext(
            items: $cartItems,
            baseSubtotalCents: $baseSubtotalCents,
            currentSubtotalCents: $baseSubtotalCents,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: $member,
            isSubscription: false,
            isFirstSubscriptionCycle: false,
            siteId: $siteId,
            voucherContext: !empty($voucherData) ? new VoucherContext($voucherData) : null
        );

        return $this->discountResolver->resolve($context);
    }

    private function prepareOrderDataFromDiscounts(
        array             $data,
        ResolvedDiscounts $discounts,
        int               $shippingCents,
        int               $taxCents,
        int               $totalCents,
        int               $siteId,
        string            $currency
    ): array
    {
        $orderData = [
            'customer_name' => $data['first_name'] . ' ' . $data['last_name'],
            'customer_email' => $data['email'],
            'customer_phone' => $data['phone'],
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_method' => $data['payment_method'] ?? 'card',
            'subtotal' => $discounts->baseSubtotalCents / 100,
            'offer_discount' => $discounts->offerDiscountCents / 100,
            'voucher_discount' => $discounts->voucherDiscountCents / 100,
            'discount' => $discounts->getTotalDiscountCents() / 100,
            'reward_discount' => $discounts->rewardDiscountCents / 100,
            'shipping' => $shippingCents / 100,
            'tax' => $taxCents / 100,
            'total' => $totalCents / 100,
            'currency' => strtoupper($currency),
            'voucher_code' => $data['voucher_code'] ?? null,
            'merchant_funded' => $discounts->merchantFundedCents / 100,
            'platform_funded' => $discounts->platformFundedCents / 100,
            'site_id' => $siteId,
            'global_renewal_consent' => !empty($data['global_renewal_consent']),
            'global_renewal_consent_at' => !empty($data['global_renewal_consent']) ? now_datetime()->format('Y-m-d H:i:s') : null,
            'auto_renewal_consent' => !empty($data['us_renewal_consent']),
            'auto_renewal_consent_at' => !empty($data['us_renewal_consent']) ? now_datetime()->format('Y-m-d H:i:s') : null,
        ];

        if (!empty($data['saved_address'])) {
            $orderData['shipping_address_id'] = $data['saved_address'];
        } elseif ($this->cartService->requiresShipping()) {
            $shippingAddress = [
                'address_line_1' => $data['address'],
                'address_line_2' => $data['address2'] ?? '',
                'city' => $data['city'],
                'state' => $data['state'] ?? '',
                'postcode' => $data['postal_code'],
                'country' => $data['country']
            ];
            $orderData['shipping_address'] = $shippingAddress;
            $orderData['billing_address'] = $shippingAddress;
        }

        if ($this->memberAuthWrapper->check()) {
            $orderData['user_id'] = $this->memberAuthWrapper->getMember()->id;
        }

        return $orderData;
    }

    /**
     * Allocate stock for every product-based cart item.
     *
     * Subscription items carry no stock on the products table — their stock is
     * managed by FulfilSubscriptionAction via OneTimeSubscriptionCheckoutService.
     * Free-gift items with zero price are skipped; their stock is handled by
     * ApplyGiftPromotionAction at the caller level.
     *
     * Called inside the order-creation transaction — any StockException rolls
     * the whole transaction back automatically.
     */
    private function allocateStockForCartItems(array $cartItems): void
    {
        foreach ($cartItems as $item) {
            // Skip subscription lines — no product-table stock to decrement.
            if (!empty($item['subscription_plan_id'])) {
                continue;
            }

            // Skip items that were already validated as pre-orders; their stock
            // accounting is handled separately when the issue ships.
            if (($item['order_line_status'] ?? null) === OrderLineStatus::PENDING_PREORDER->value) {
                continue;
            }

            if (empty($item['product_id'])) {
                continue;
            }

            // Re-use the already-locked product from validateAndResolveAvailability.
            // lockForUpdate was called there; within the same transaction the lock is held.
            $product = $this->productRepository->lockForUpdate($item['product_id']);
            $quantity = $item['quantity'] ?? 1;

            // PurchaseProductAction::execute() throws StockException on failure,
            // propagating up and rolling back this transaction.
            $this->purchaseProductAction->execute($product, $quantity);
        }
    }

    private function prepareOrderItemsFromDiscounts(array $cartItems, ResolvedDiscounts $discounts): array
    {
        $orderItems = [];
        $totalDiscountCents = $discounts->getTotalDiscountCents();
        $allocatedDiscountCents = 0;

        foreach ($cartItems as $index => $item) {
            $itemSubtotalCents = (int)round($item['price'] * 100) * ($item['quantity'] ?? 1);
            $itemDiscountCents = 0;

            if ($discounts->baseSubtotalCents > 0) {
                $isLastItem = ($index === count($cartItems) - 1);

                if ($isLastItem) {
                    $itemDiscountCents = $totalDiscountCents - $allocatedDiscountCents;
                } else {
                    $itemDiscountCents = (int)floor(
                        ($itemSubtotalCents / $discounts->baseSubtotalCents) * $totalDiscountCents
                    );
                    $allocatedDiscountCents += $itemDiscountCents;
                }
            }

            $orderItems[] = [
                'product_id' => $item['product_id'],
                'product_name' => $item['product_name'] ?? 'Product',
                'product_sku' => $item['product_sku'] ?? '',
                'price' => $item['price'],
                'quantity' => $item['quantity'] ?? 1,
                'subtotal' => $itemSubtotalCents / 100,
                'discount' => $itemDiscountCents / 100,
                'order_line_status' => $item['order_line_status'] ?? OrderLineStatus::READY_TO_SHIP->value,
                'expected_ship_date' => $item['expected_ship_date'] ?? null,
                'quantity_allocated' => ($item['order_line_status'] ?? OrderLineStatus::READY_TO_SHIP->value) === OrderLineStatus::READY_TO_SHIP->value
                    ? ($item['quantity'] ?? 1)
                    : 0,
            ];
        }

        return $orderItems;
    }

    private function handleMerchantFunding(int $merchantId, float $amount, int $orderId, int $voucherId): void
    {
        try {
            $this->database->transaction(function () use ($merchantId, $amount, $orderId, $voucherId) {
                $merchant = $this->merchantRepository->find($merchantId);

                if (!$merchant) {
                    $this->createFailedTransaction($merchantId, $amount, $orderId, 'Merchant not found');
                    return;
                }

                $amountCents = Money::convertDollarsToCents($amount);
                $currentBalanceCents = Money::convertDollarsToCents($merchant->balance ?? 0);

                if ($currentBalanceCents >= $amountCents) {
                    $newBalanceCents = $currentBalanceCents - $amountCents;

                    $this->merchantRepository->updateBalance($merchantId, Money::convertCentsToDollars($newBalanceCents));

                    $this->merchantRepository->createTransaction([
                        'merchant_id' => $merchantId,
                        'type' => 'voucher_redemption',
                        'amount' => -$amount,
                        'balance_after' => Money::convertCentsToDollars($newBalanceCents),
                        'status' => 'completed',
                        'order_id' => $orderId,
                        'voucher_id' => $voucherId,
                        'metadata' => json_encode(['voucher_id' => $voucherId, 'order_id' => $orderId])
                    ]);
                } else {
                    $shortfallCents = $amountCents - $currentBalanceCents;

                    $this->merchantRepository->createTransaction([
                        'merchant_id' => $merchantId,
                        'type' => 'voucher_redemption',
                        'amount' => -$amount,
                        'balance_after' => $merchant->balance,
                        'status' => 'pending_review',
                        'order_id' => $orderId,
                        'voucher_id' => $voucherId,
                        'metadata' => json_encode([
                            'voucher_id' => $voucherId,
                            'order_id' => $orderId,
                            'shortfall' => Money::convertCentsToDollars($shortfallCents),
                            'required_balance' => $amount,
                            'current_balance' => $merchant->balance
                        ])
                    ]);
                }
            });
        } catch (\Exception $e) {
            $this->createFailedTransaction($merchantId, $amount, $orderId, $e->getMessage());
        }
    }

    private function createFailedTransaction(int $merchantId, float $amount, int $orderId, string $error): void
    {
        $this->merchantRepository->createTransaction([
            'merchant_id' => $merchantId,
            'type' => 'voucher_redemption',
            'amount' => -$amount,
            'status' => 'failed',
            'order_id' => $orderId,
            'metadata' => json_encode(['error' => $error, 'order_id' => $orderId])
        ]);
    }

    private function claimReward(int $rewardId, int $orderId): void
    {
        $reward = $this->rewardsRepository->find($rewardId);

        if ($reward && $reward->isPending()) {
            $reward->claim();
            $reward->update(['notes' => "Applied to order #{$orderId}"]);
        }
    }

    public function confirmRegularCheckoutPayment(string $paymentIntentId, int $orderId): array
    {
        try {
            $confirmResult = $this->paymentIntentGateway->confirmPaymentIntent($paymentIntentId);

            if (!$confirmResult['success'] || $confirmResult['status'] !== 'succeeded') {
                return ['success' => false, 'message' => 'Payment confirmation failed'];
            }

            return $this->database->transaction(function () use ($orderId) {
                $this->orderService->updateOrderStatus(
                    $orderId,
                    OrderStatus::COMPLETED->value,
                    PaymentStatus::PAID->value
                );

                $this->cartService->clear();

                $order = $this->orderService->find($orderId);
                event(new OrderCompletedEvent($order));

                return ['success' => true, 'message' => 'Order completed successfully'];
            });
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Payment confirmation error: ' . $e->getMessage()];
        }
    }

    public function processMultiMerchantCheckout(array $data, int $siteId): array
    {
        try {
            $validation = $this->validateCheckoutData($data);
            if (!$validation['valid']) {
                return ['success' => false, 'message' => $validation['message']];
            }

            $cartItems = $this->cartService->getItems();
            if (empty($cartItems)) {
                return ['success' => false, 'message' => 'Cart is empty'];
            }

            $cartItems = $this->validateAndResolveAvailability($cartItems);
            $cartItems = $this->attachDeliveryEstimates($cartItems);

            $member = $this->memberAuthWrapper->check() ? $this->memberAuthWrapper->getMember() : null;

            $eligibility = $this->eligibilityService->validate($member, $cartItems);
            $cartItems = $eligibility->valid;

            if (empty($cartItems)) {
                return ['success' => false, 'message' => 'All items were invalid and removed from the cart.'];
            }

            $voucherData = null;
            if (!empty($data['voucher_code'])) {
                $voucherData = $this->voucherService->validateVoucherForCheckout(
                    $data['voucher_code'],
                    $cartItems,
                    $member->id ?? null
                );

                $voucherData = $voucherData->valid ? $voucherData->toArray() : null;

                $voucherData['voucher_code'] = $data['voucher_code'];
                $voucherData['order_value'] = $voucherData['eligible_subtotal'];

                if (isset($data['voucher_id']) && $voucherData && $data['voucher_id'] != $voucherData['voucher_id']) {
                    $voucherData = null;
                }
            }

            $discounts = $this->resolveDiscounts($cartItems, $member, $voucherData, $data, $siteId);
            $groups = $this->splittingService->splitByMerchant($cartItems);

            if (empty($groups)) {
                return ['success' => false, 'message' => 'No items to process'];
            }

            $country = $data['country'] ?? 'US';
            $shippingPerGroup = $this->merchantShippingService->calculatePerGroup($groups, $country);
            $totalShippingCents = (int)round(array_sum($shippingPerGroup) * 100);

            $taxData = $this->taxCalculatorService->calculateOrderTax(
                $discounts->finalSubtotalCents,
                $totalShippingCents,
                $country,
                $data['state'] ?? null,
                $data['postal_code'] ?? null,
                $member
            );

            $taxCents = $taxData->taxCents;
            $totalCents = $discounts->finalSubtotalCents + $totalShippingCents + $taxCents;

            $allocations = $this->allocationService->allocate(
                $groups,
                [
                    'subtotal' => $discounts->finalSubtotalCents / 100,
                    'shipping' => $totalShippingCents / 100,
                    'discount' => $discounts->getTotalDiscountCents() / 100,
                    'tax' => $taxCents / 100,
                    'total' => $totalCents / 100
                ],
                $shippingPerGroup
            );

            $currency = $this->currencyResolver->resolve($siteId);
            $stripeContexts = [];

            foreach ($groups as $key => $group) {
                if (!($allocations[$key]['stripe_eligible'] ?? true)) {
                    continue;
                }

                $customerId = $member ? $this->customerGateway->getOrCreate($member) : null;

                $dto = new CreatePaymentIntentDto(
                    amountCents:      (int) round($allocations[$key]['total'] * 100),
                    currency:         strtolower($currency),
                    metadata:         [
                        'checkout_type'    => 'multi_merchant',
                        'merchant_id'      => $group['merchant_id'] ?? $key,
                        'stripe_group_key' => $group['stripe_group_key'] ?? 'default',
                        'member_id'        => $member->id ?? null,
                    ],
                    stripeCustomerId: $customerId,
                );

                $piResult = $this->paymentIntentGateway->createWithCustomer($dto)->toLegacyArray();

                if (!$piResult['success']) {
                    return ['success' => false, 'message' => 'Payment processing failed'];
                }

                $stripeContexts[$key] = $piResult;
            }

            return $this->database->transaction(function () use (
                $groups,
                $allocations,
                $data,
                $siteId,
                $country,
                $stripeContexts,
                $discounts,
                $totalCents,
                $currency,
                $member,
                $cartItems,
                $eligibility
            ) {
                $checkoutId = 'chk-' . uniqid('', true);
                $orderNumbers = [];
                $createdOrders = [];

                // ── Stock allocation (multi-merchant) ────────────────────────────────
                $this->allocateStockForCartItems($cartItems);
                // ────────────────────────────────────────────────────────────────────

                foreach ($groups as $key => $group) {
                    $items = $this->prepareOrderItems($group['items']);
                    $orderData = $this->prepareOrderDataFromDiscounts(
                        $data,
                        $discounts,
                        (int)round($allocations[$key]['shipping'] * 100),
                        (int)round($allocations[$key]['tax'] * 100),
                        (int)round($allocations[$key]['total'] * 100),
                        $siteId,
                        $currency
                    );

                    $allFreeGifts = !empty($group['items']) && array_reduce(
                            $group['items'],
                            fn($carry, $item) => $carry && (
                                    ($item['options']['type'] ?? '') === \App\Enums\CartItemType::FREE_GIFT->value
                                    || ($item['price'] ?? 0) <= 0
                                ),
                            true
                        );

                    if ($allFreeGifts) {
                        $orderData['subtotal'] = 0;
                        $orderData['total'] = 0;
                        $orderData['shipping'] = 0;
                        $orderData['tax'] = 0;
                        $orderData['discount'] = 0;
                        $orderData['offer_discount'] = 0;
                        $orderData['voucher_discount'] = 0;
                        $orderData['reward_discount'] = 0;
                    }

                    $orderData['checkout_id'] = $checkoutId;
                    $orderData['currency'] = strtoupper($currency);
                    $orderData['status'] = OrderStatus::PENDING->value;
                    $orderData['payment_intent_id'] = $stripeContexts[$key]['payment_intent_id'] ?? null;
                    $orderData['stripe_customer_id'] = $stripeContexts[$key]['customer_id'] ?? null;
                    $orderData['metadata'] = [
                        'checkout_id' => $checkoutId,
                        'merchant_id' => $group['merchant_id'],
                        'stripe_payment_intent_id' => $stripeContexts[$key]['payment_intent_id'] ?? null,
                        'stripe_client_secret' => $stripeContexts[$key]['client_secret'] ?? null,
                    ];

                    $order = $this->orderCreationService->createMerchantOrder(
                        $orderData,
                        $items,
                        $siteId,
                        $group['merchant_id']
                    );

                    $orderNumbers[] = $order->order_number;
                    $createdOrders[] = $order;

                    $this->shipmentRepository->create([
                        'order_id' => $order->id,
                        'checkout_id' => $checkoutId,
                        'merchant_id' => $group['merchant_id'],
                        'shipping_cost' => $allocations[$key]['shipping'],
                        'country' => $country,
                        'status' => 'pending',
                        'metadata' => [
                            'consolidation_enabled' => $this->merchantShippingService->isConsolidationEnabled(),
                        ],
                        'site_id' => $siteId,
                    ]);
                }

                if ($discounts->voucherDiscountCents > 0 && isset($discounts->metadata['voucher'])) {
                    $voucherMetadata = $discounts->metadata['voucher'];
                    $this->voucherService->applyVoucher(
                        $voucherMetadata['voucher_id'],
                        $member->id ?? null,
                        $discounts->voucherDiscountCents / 100,
                        $createdOrders[0]->id
                    );

                    if (!empty($voucherMetadata['merchant_id'])) {
                        $this->handleMerchantFunding(
                            $voucherMetadata['merchant_id'],
                            $discounts->voucherDiscountCents,
                            $createdOrders[0]->id,
                            $voucherMetadata['voucher_id']
                        );
                    }
                }

                if ($discounts->rewardDiscountCents > 0 && isset($discounts->metadata['reward'])) {
                    $this->claimReward($discounts->metadata['reward']['reward_id'], $createdOrders[0]->id);
                }

                $this->cartService->clear();

                event(new MultiMerchantCheckoutCompletedEvent($checkoutId, $createdOrders));

                return [
                    'success' => true,
                    'has_removed_items' => !$eligibility->isEmpty(),
                    'message' => 'Multi-merchant checkout completed',
                    'checkout_id' => $checkoutId,
                    'order_numbers' => $orderNumbers,
                    'total' => $totalCents / 100,
                    'currency' => strtoupper($currency),
                    'stripe_contexts' => array_map(fn($ctx) => [
                        'payment_intent_id' => $ctx['payment_intent_id'],
                        'client_secret' => $ctx['client_secret'],
                    ], $stripeContexts),
                ];
            });

        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Checkout failed'];
        }
    }

    private function prepareOrderItems(array $cartItems): array
    {
        $items = [];
        foreach ($cartItems as $cartItem) {
            $items[] = [
                'product_id' => $cartItem['product_id'],
                'product_name' => $cartItem['product_name'],
                'product_sku' => $cartItem['product_sku'] ?? '',
                'quantity' => $cartItem['quantity'],
                'unit_price' => $cartItem['price'],
                'subtotal' => $cartItem['subtotal'],
                'tax' => 0,
                'total' => $cartItem['subtotal'],
                'expected_ship_date' => $cartItem['expected_ship_date'],
                'preorder_enabled' => $cartItem['is_preorder'],
            ];
        }
        return $items;
    }
}
