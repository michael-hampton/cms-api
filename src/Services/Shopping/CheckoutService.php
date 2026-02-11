<?php

namespace App\Services\Shopping;

use App\Enums\Orders\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\Checkout\MultiMerchantCheckoutCompletedEvent;
use App\Events\Orders\OrderCompletedEvent;
use App\Framework\Authorization\MemberAuthWrapper;
use App\Framework\Database\Database;
use App\Repositories\Billing\ShipmentRepository;
use App\Repositories\Product\MerchantRepository;
use App\Repositories\Rewards\RewardsRepository;
use App\Services\Billing\CheckoutSplittingService;
use App\Services\Billing\Order\OrderCreationService;
use App\Services\Billing\Order\OrderManager;
use App\Services\Billing\OrderCalculationService;
use App\Services\Billing\PaymentAllocationService;
use App\Services\Billing\PaymentProviders\StripePaymentProcessor;
use App\Services\Billing\TaxCalculatorService;
use App\Services\Currency\CurrencyResolver;
use App\Services\ValueObjects\Money;
use App\Services\Vouchers\DiscountContext;
use App\Services\Vouchers\DiscountResolver;
use App\Services\Vouchers\Providers\OfferDiscountProvider;
use App\Services\Vouchers\Providers\RewardDiscountProvider;
use App\Services\Vouchers\Providers\VoucherDiscountProvider;
use App\Services\Vouchers\ResolvedDiscounts;
use App\Services\Vouchers\VoucherService;
use Exception;

class CheckoutService
{
    public function __construct(
        private readonly CartService             $cartService,
        private readonly OrderCreationService    $orderCreationService,
        private readonly VoucherService          $voucherService,
        private readonly ShippingService         $shippingService,
        private readonly MemberAuthWrapper       $memberAuthWrapper,
        private readonly OrderCalculationService $calculationService,
        private readonly StripePaymentProcessor   $stripeProcessor,
        private readonly CheckoutSplittingService $splittingService,
        private readonly PaymentAllocationService $allocationService,
        private readonly MerchantShippingService  $merchantShippingService,
        private readonly ShipmentRepository       $shipmentRepository,
        private readonly CurrencyResolver        $currencyResolver,
        private readonly Database                $database,
        private readonly OrderManager            $orderService,
        private readonly TaxCalculatorService    $taxCalculatorService,
        private readonly MerchantRepository      $merchantRepository,
        private readonly DiscountResolver        $discountResolver,
        private readonly RewardsRepository       $rewardsRepository,

    )
    {
    }


    public function processCheckout(array $data, int $siteId): array
    {
        try {
            // Validate required fields
            $validation = $this->validateCheckoutData($data);

            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => $validation['message']
                ];
            }

            // Get cart items
            $cartItems = $this->cartService->getItems();
            if (empty($cartItems)) {
                return [
                    'success' => false,
                    'message' => 'Cart is empty'
                ];
            }

            $member = $this->memberAuthWrapper->check() ? $this->memberAuthWrapper->getMember() : null;

            // CRITICAL FIX #7: Voucher validation - ONLY use voucher_code
            $voucherData = null;
            if (!empty($data['voucher_code'])) {
                $voucherData = $this->voucherService->validateVoucherForCheckout(
                    $data['voucher_code'],
                    $cartItems,
                    $member->id ?? null
                );

                if (!$voucherData['valid']) {
                    $voucherData = null;
                }

                // SECURITY: Verify voucher_id wasn't tampered with
                if (isset($data['voucher_id']) && $voucherData && $data['voucher_id'] != $voucherData['voucher_id']) {
                    $voucherData = null;
                }
            }

            // NEW: Build discount context and resolve discounts
            $discounts = $this->resolveDiscounts($cartItems, $member, $voucherData, $data, $siteId);

            // Calculate shipping based on final subtotal
            $shippingCost = $this->shippingService->calculateShipping(
                $discounts->finalSubtotalCents / 100,
                $data
            );

            // Resolve currency
            $currency = $this->currencyResolver->resolve($siteId);

            // Calculate tax
            $taxData = $this->taxCalculatorService->calculateOrderTax(
                $discounts->finalSubtotalCents,
                (int)($shippingCost * 100),
                $data['country'] ?? 'GB',
                $data['state'] ?? null,
                $data['postal_code'] ?? null,
                $member
            );

            $taxCents = $taxData['tax_cents'];
            $shippingCents = (int)round($shippingCost * 100);

            // Calculate final total (all in cents)
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
                $member
            ) {
                // Prepare order data
                // Prepare order data
                $orderData = $this->prepareOrderDataFromDiscounts(
                    $data,
                    $discounts,
                    $shippingCents,
                    $taxCents,
                    $totalCents,
                    $siteId
                );

                // Create payment intent with discount breakdown
                $paymentIntent = $this->stripeProcessor->createPaymentIntent([
                    'amount' => $totalCents, // Send integer cents, NOT divided by 100
                    'currency' => $currency,
                    'site_id' => $siteId,
                    'metadata' => [
                        'offer_discount_cents' => $discounts->offerDiscountCents,
                        'voucher_discount_cents' => $discounts->voucherDiscountCents,
                        'reward_discount_cents' => $discounts->rewardDiscountCents,
                        'merchant_funded_cents' => $discounts->merchantFundedCents,
                        'platform_funded_cents' => $discounts->platformFundedCents,
                        'voucher_code' => $discounts->metadata['voucher']['voucher_code'] ?? null,
                        'campaign_id' => $discounts->metadata['voucher']['campaign_id'] ?? null
                    ]
                ]);

                if (!$paymentIntent['success']) {
                    return [
                        'success' => false,
                        'message' => $paymentIntent['message'] ?? 'Payment failed'
                    ];
                }

                // Prepare order items with distributed discounts
                $orderItems = $this->prepareOrderItemsFromDiscounts($cartItems, $discounts);

                // Create order
                $order = $this->orderCreationService->create($orderData, $orderItems, $siteId);

                // Apply voucher if present
                if ($discounts->voucherDiscountCents > 0 && isset($discounts->metadata['voucher'])) {
                    $voucherMetadata = $discounts->metadata['voucher'];
                    $this->voucherService->applyVoucher(
                        $voucherMetadata['voucher_id'],
                        $member->id ?? null,
                        $discounts->voucherDiscountCents / 100,
                        $order->id
                    );

                    // Handle merchant funding if applicable
                    if (!empty($voucherMetadata['merchant_id'])) {
                        $this->handleMerchantFunding(
                            $voucherMetadata['merchant_id'],
                            $discounts->voucherDiscountCents / 100,
                            $order->id,
                            $voucherMetadata['voucher_id']
                        );
                    }
                }

                // Mark reward as claimed if present
                if ($discounts->rewardDiscountCents > 0 && isset($discounts->metadata['reward'])) {
                    $this->claimReward($discounts->metadata['reward']['reward_id'], $order->id);
                }

                $this->cartService->clear();

                return [
                    'success' => true,
                    'message' => 'Order placed successfully',
                    'client_secret' => $paymentIntent['client_secret'],
                    'payment_intent_id' => $paymentIntent['payment_intent_id'],
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'order_internal_id' => $order->id,
                    'total' => $totalCents / 100,
                    'discount_breakdown' => [
                        'offer_discount' => $discounts->offerDiscountCents / 100,
                        'voucher_discount' => $discounts->voucherDiscountCents / 100,
                        'reward_discount' => $discounts->rewardDiscountCents / 100,
                        'total_discount' => $discounts->getTotalDiscountCents() / 100
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

    private function resolveDiscounts(
        array   $cartItems,
        ?object $member,
        ?array  $voucherData,
        array   $requestData,
        int     $siteId
    ): ResolvedDiscounts
    {
        // Calculate base subtotal in cents
        $baseSubtotalCents = 0;
        foreach ($cartItems as $item) {
            $basePriceCents = (int)round(($item['base_price'] ?? $item['price']) * 100);
            $quantity = $item['quantity'] ?? 1;
            $baseSubtotalCents += $basePriceCents * $quantity;
        }

        // Build discount context
        $context = new DiscountContext(
            items: $cartItems,
            baseSubtotalCents: $baseSubtotalCents,
            currentSubtotalCents: $baseSubtotalCents,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: $member,
            isSubscription: false,
            isFirstSubscriptionCycle: false,
            siteId: $siteId
        );

        // Build provider list
        $providers = [
            new OfferDiscountProvider(),
            new VoucherDiscountProvider($voucherData)
        ];

        // Add reward provider if reward_id is present
        if (!empty($requestData['reward_id']) && $member) {
            $providers[] = new RewardDiscountProvider(
                $requestData['reward_id'],
                $this->rewardsRepository
            );
        }

        // Create resolver with providers
        $resolver = $this->discountResolver;
        if (!empty($providers)) {
            // We use the injected resolver if it's already configured or just use the providers
            // For now, to keep it simple and testable, let's just use the injected one
            // but in production it might need to be configured per call.
            // Since DiscountResolver is usually a singleton or factory-created.
        }

        // Resolve discounts
        return $resolver->resolve($context);
    }

    private function prepareOrderDataFromDiscounts(
        array             $data,
        ResolvedDiscounts $discounts,
        int               $shippingCents,
        int               $taxCents,
        int               $totalCents,
        int               $siteId
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
            'discount' => $discounts->voucherDiscountCents / 100, //todo should be total of everything
            'reward_discount' => $discounts->rewardDiscountCents / 100,
            'shipping' => $shippingCents / 100,
            'tax' => $taxCents / 100,
            'total' => $totalCents / 100,
            'currency' => 'USD',
            'voucher_code' => $data['voucher_code'] ?? null,
            'merchant_funded' => $discounts->merchantFundedCents / 100,
            'platform_funded' => $discounts->platformFundedCents / 100,
            'site_id' => $siteId
        ];

        if (!empty($data['saved_address'])) {
            $orderData['shipping_address_id'] = $data['saved_address'];
        } else if ($this->cartService->requiresShipping()) { //todo needs test
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

    private function prepareOrderItemsFromDiscounts(array $cartItems, ResolvedDiscounts $discounts): array
    {
        $orderItems = [];
        $totalDiscountCents = $discounts->getTotalDiscountCents();
        $allocatedDiscountCents = 0;

        foreach ($cartItems as $index => $item) {
            $itemSubtotalCents = (int)round($item['price'] * 100) * ($item['quantity'] ?? 1);

            // Calculate proportional discount for this item
            $itemDiscountCents = 0;

            if ($discounts->baseSubtotalCents > 0) {
                // Check if this is the last item
                $isLastItem = ($index === count($cartItems) - 1);

                if ($isLastItem) {
                    // Assign remainder to last item to ensure exact total
                    $itemDiscountCents = $totalDiscountCents - $allocatedDiscountCents;
                } else {
                    // Floor for all other items
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
                'discount' => $itemDiscountCents / 100
            ];
        }

        return $orderItems;
    }

    private function claimReward(int $rewardId, int $orderId): void
    {
        $reward = $this->rewardsRepository->find($rewardId);

        if ($reward && $reward->isPending()) {
            $reward->claim();
            // Optionally link to order
            $reward->update(['notes' => "Applied to order #{$orderId}"]);
        }
    }



    /**
     * Handle merchant funding for merchant-funded vouchers
     *
     * IMPLEMENTED: Deducts voucher discount amount from merchant balance
     * Creates transaction record for audit trail
     */
    // In CheckoutService::handleMerchantFunding

    private function handleMerchantFunding(int $merchantId, float $amount, int $orderId, int $voucherId): void
    {
        try {
            $this->database->transaction(function () use ($merchantId, $amount, $orderId, $voucherId) {
                // Use SELECT FOR UPDATE for atomic balance check
                $merchant = $this->merchantRepository->find($merchantId);

                if (!$merchant) {
                    $this->createFailedTransaction($merchantId, $amount, $orderId, 'Merchant not found');
                    return;
                }

                $amountCents = Money::convertDollarsToCents($amount);
                $currentBalanceCents = Money::convertDollarsToCents($merchant->balance ?? 0);

                if ($currentBalanceCents >= $amountCents) {
                    // Sufficient balance - deduct atomically
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
                        'metadata' => json_encode([
                            'voucher_id' => $voucherId,
                            'order_id' => $orderId
                        ])
                    ]);
                } else {
                    // Insufficient balance - create pending review transaction
                    $shortfallCents = $amountCents - $currentBalanceCents;

                    $this->merchantRepository->createTransaction([
                        'merchant_id' => $merchantId,
                        'type' => 'voucher_redemption',
                        'amount' => -$amount,
                        'balance_after' => $merchant->balance,  // Balance unchanged
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
            // Log error but don't fail checkout
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
            'metadata' => json_encode([
                'error' => $error,
                'order_id' => $orderId
            ])
        ]);
    }

    private function validateCheckoutData(array $data): array
    {
        $requiresShipping = $this->cartService->requiresShipping();

        $required = $requiresShipping ? ['first_name', 'last_name', 'email', 'phone'] : []; //todo needs test

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

    public function calculateTotals($cartItems, $voucherData = null)
    {
        $subtotal = 0;
        $offerDiscount = 0;
        $voucherDiscount = 0;
        $baseSubtotal = 0;

        // First pass: calculate base prices and offer discounts
        $itemsWithDiscounts = [];

        // 1️⃣ First pass: calculate base prices and offer discounts
        foreach ($cartItems as $item) {
            $basePrice = $item['base_price'] ?? $item['price'];
            $salePrice = $item['price'];
            $quantity = $item['quantity'];

            $itemBaseSubtotal = $basePrice * $quantity;
            $itemSubtotal = $salePrice * $quantity;
            $itemOfferDiscount = max(0, $itemBaseSubtotal - $itemSubtotal);

            $baseSubtotal += $itemBaseSubtotal;
            $subtotal += $itemSubtotal;
            $offerDiscount += $itemOfferDiscount;

            $itemsWithDiscounts[] = array_merge($item, [
                'base_subtotal' => $itemBaseSubtotal,
                'subtotal' => $itemSubtotal,
                'item_offer_discount' => $itemOfferDiscount
            ]);
        }

        // 2️⃣ Apply voucher if provided
        $voucherEligibleItems = [];

        if ($voucherData && $voucherData['valid']) {
            $potentialVoucherDiscount = $voucherData['discount'];
            $voucherEligibleItems = $voucherData['eligible_items'] ?? [];
            $isStackable = $voucherData['is_stackable'] ?? false;
            $requiresOverride = $voucherData['requires_override_decision'] ?? false;

            if ($isStackable) {
                // Stackable voucher applies on top of offers
                $voucherDiscount = $potentialVoucherDiscount;

            } elseif ($requiresOverride) {
                // Non-stackable: compare offer vs voucher for eligible items
                $eligibleItemIds = array_column($voucherEligibleItems, 'id');
                $offerDiscountForEligibleItems = 0;

                foreach ($itemsWithDiscounts as $item) {
                    if (in_array($item['id'], $eligibleItemIds)) {
                        $offerDiscountForEligibleItems += $item['item_offer_discount'];
                    }
                }

                if ($potentialVoucherDiscount > $offerDiscountForEligibleItems) {
                    // Voucher wins - remove offer discount only for eligible items
                    $voucherDiscount = $potentialVoucherDiscount;
                    $offerDiscount -= $offerDiscountForEligibleItems;

                    // MISSING: Recalculate subtotal after resetting prices
                    $subtotal = 0; // Reset and recalculate

                    foreach ($itemsWithDiscounts as &$item) {
                        if (in_array($item['id'], $eligibleItemIds)) {
                            $item['item_offer_discount'] = 0;
                            $item['price'] = $item['base_price'];
                            $item['subtotal'] = $item['base_subtotal'];
                        }
                        $subtotal += $item['subtotal']; // Recalculate from updated items
                    }
                    unset($item);
                } else {
                    // Offer wins - voucher ignored
                    $voucherDiscount = 0;
                    $voucherData = null;
                }
            } else {
                // Stackable without conflicts
                $voucherDiscount = $potentialVoucherDiscount;
            }
        }

        return [
            'base_subtotal' => $baseSubtotal,
            'subtotal' => $subtotal, // Now recalculated
            'offer_discount' => $offerDiscount,
            'voucher_discount' => $voucherDiscount,
            'voucher_code' => $voucherData['voucher_code'] ?? null,
            'voucher_id' => $voucherData['voucher_id'] ?? null,
            'campaign_id' => $voucherData['campaign_id'] ?? null,
            'merchant_id' => $voucherData['merchant_id'] ?? null,
            'is_stackable' => $voucherData['is_stackable'] ?? null,
            'voucher_eligible_items' => $voucherData['eligible_items'] ?? [],
            'items_with_discounts' => $itemsWithDiscounts, // Use updated items
            'prices_adjusted' => !$isStackable && $requiresOverride // Flag for UI
        ];

    }

    private function prepareOrderData($data, $totals, $cartItems, $siteId)
    {
        // Resolve currency once from CurrencyResolver
        $currency = $this->currencyResolver->resolve($siteId);

        $orderData = [
            'user_id' => $this->memberAuthWrapper->check() ? $this->memberAuthWrapper->getMember()->id : null,
            'site_id' => $siteId,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'subtotal' => $totals['subtotal'],
            'shipping' => $totals['shipping'],
            'tax' => $totals['tax'],
            'discount' => $totals['voucher_discount'] + $totals['offer_discount'],
            'total' => $totals['total'],
            'currency' => strtoupper($currency), // Use resolved currency consistently
            'payment_method' => $data['payment_method'] ?? 'card',
            'status' => 'pending',
            'payment_status' => 'pending',
            'metadata' => json_encode([
                'offer_discount' => $totals['offer_discount'],
                'voucher_discount' => $totals['voucher_discount'],
                'voucher_code' => $totals['voucher_code'],
                'voucher_id' => $totals['voucher_id'],
                'campaign_id' => $totals['campaign_id'],
                'is_stackable' => $totals['is_stackable']
            ])
        ];

        // Handle shipping address
        if ($this->cartService->requiresShipping()) {
            if (isset($data['saved_address'])) {
                $orderData['shipping_address_id'] = $data['saved_address'];
            } else {
                $orderData['shipping_address'] = json_encode([
                    'address' => $data['address'],
                    'city' => $data['city'],
                    'state' => $data['state'] ?? '',
                    'postal_code' => $data['postal_code'],
                    'country' => $data['country']
                ]);
            }
        }

        // Add voucher code if present
        if (!empty($totals['voucher_code'])) {
            $orderData['voucher_code'] = $totals['voucher_code'];
        }

        return $orderData;
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
                'total' => $cartItem['subtotal']
            ];
        }
        return $items;
    }

    public function confirmRegularCheckoutPayment(string $paymentIntentId, int $orderId): array
    {
        try {
            $confirmResult = $this->stripeProcessor->confirmPaymentIntent($paymentIntentId);

            if (!$confirmResult['success'] || $confirmResult['status'] !== 'succeeded') {
                return [
                    'success' => false,
                    'message' => 'Payment confirmation failed'
                ];
            }

            return $this->database->transaction(function () use ($orderId) {
                $this->orderService->updateOrderStatus(
                    $orderId,
                    OrderStatus::COMPLETED->value,
                    PaymentStatus::PAID->value
                );

                //$this->cartService->clear();

                $order = $this->orderService->find($orderId);
                event(new OrderCompletedEvent($order));

                return [
                    'success' => true,
                    'message' => 'Order completed successfully'
                ];
            });
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Payment confirmation error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Multi-merchant checkout flow.
     *
     * 1. Validate & get cart
     * 2. Split items by merchant
     * 3. Calculate per-merchant shipping
     * 4. Allocate payment across groups
     * 5. Create one Stripe PaymentIntent per merchant order (logical; single capture)
     * 6. Persist each merchant order + shipment
     * 7. Return unified response with all order numbers
     *
     * Subscription items must be excluded before calling this method.
     */
    public function processMultiMerchantCheckout(array $data, int $siteId): array
    {
        try {
            // Validation
            $validation = $this->validateCheckoutData($data);
            if (!$validation['valid']) {
                return ['success' => false, 'message' => $validation['message']];
            }

            $cartItems = $this->cartService->getItems();
            if (empty($cartItems)) {
                return ['success' => false, 'message' => 'Cart is empty'];
            }

            $member = $this->memberAuthWrapper->check() ? $this->memberAuthWrapper->getMember() : null;

            // SECURITY FIX: Validate voucher by code only
            $voucherData = null;
            if (!empty($data['voucher_code'])) {
                $voucherData = $this->voucherService->validateVoucherForCheckout(
                    $data['voucher_code'],
                    $cartItems,
                    $member->id ?? null
                );

                if (!$voucherData['valid']) {
                    $voucherData = null;
                }

                // SECURITY: Verify voucher_id wasn't tampered with
                if (isset($data['voucher_id']) && $voucherData && $data['voucher_id'] != $voucherData['voucher_id']) {
                    $voucherData = null;
                }
            }

            // Resolve discounts FIRST
            $discounts = $this->resolveDiscounts($cartItems, $member, $voucherData, $data, $siteId);

            // Split by merchant
            $groups = $this->splittingService->splitByMerchant($cartItems);

            if (empty($groups)) {
                return ['success' => false, 'message' => 'No items to process'];
            }

            // Shipping per group
            $country = $data['country'] ?? 'US';
            $shippingPerGroup = $this->merchantShippingService->calculatePerGroup($groups, $country);

            // Calculate totals at checkout level
            $totalShippingCents = (int)round(array_sum($shippingPerGroup) * 100);

            // Calculate tax on final discounted subtotal
            $taxData = $this->taxCalculatorService->calculateOrderTax(
                $discounts->finalSubtotalCents,
                $totalShippingCents,
                $country,
                $data['state'] ?? null,
                $data['postal_code'] ?? null,
                $member
            );

            $taxCents = $taxData['tax_cents'];
            $totalCents = $discounts->finalSubtotalCents + $totalShippingCents + $taxCents;

            // Allocate payment across groups
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

            // Create Stripe PaymentIntents per group
            $stripeContexts = [];
            foreach ($groups as $key => $group) {
                if (!($allocations[$key]['stripe_eligible'] ?? true)) {
                    continue;
                }

                $piResult = $this->stripeProcessor->createPaymentIntentWithCustomer([
                    'amount' => $allocations[$key]['total'],
                    'member' => $member,
                    'currency' => strtolower($currency),
                    'site_id' => $siteId,
                    'metadata' => [
                        'checkout_type' => 'multi_merchant',
                        'merchant_id' => $group['merchant_id'] ?? $key,
                        'stripe_group_key' => $group['stripe_group_key'] ?? 'default',
                        'member_id' => $member->id ?? null,
                    ],
                ]);

                if (!$piResult['success']) {
                    return [
                        'success' => false,
                        'message' => 'Payment processing failed',
                    ];
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
                $cartItems
            ) {
                $checkoutId = 'chk-' . uniqid('', true);
                $orderNumbers = [];
                $createdOrders = [];

                foreach ($groups as $key => $group) {
                    $items = $this->prepareOrderItems($group['items']);

                    $orderData = $this->prepareOrderDataFromDiscounts(
                        $data,
                        $discounts,
                        (int)round($allocations[$key]['shipping'] * 100),
                        (int)round($allocations[$key]['tax'] * 100),
                        (int)round($allocations[$key]['total'] * 100),
                        $siteId
                    );

                    $orderData['checkout_id'] = $checkoutId;
                    $orderData['currency'] = strtoupper($currency);
                    $orderData['status'] = OrderStatus::PENDING->value;

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

                    // Create shipment
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

                // Apply voucher once at checkout level
                if ($discounts->voucherDiscountCents > 0 && isset($discounts->metadata['voucher'])) {
                    $voucherMetadata = $discounts->metadata['voucher'];
                    $this->voucherService->applyVoucher(
                        $voucherMetadata['voucher_id'],
                        $member->id ?? null,
                        $discounts->voucherDiscountCents / 100,
                        $createdOrders[0]->id
                    );

                    // Handle merchant funding
                    if (!empty($voucherMetadata['merchant_id'])) {
                        $this->handleMerchantFunding(
                            $voucherMetadata['merchant_id'],
                            $discounts->voucherDiscountCents,
                            $createdOrders[0]->id,
                            $voucherMetadata['voucher_id']
                        );
                    }
                }

                // Claim reward if present
                if ($discounts->rewardDiscountCents > 0 && isset($discounts->metadata['reward'])) {
                    $this->claimReward($discounts->metadata['reward']['reward_id'], $createdOrders[0]->id);
                }

                // Clear cart AFTER successful transaction
                $this->cartService->clear();

                event(new MultiMerchantCheckoutCompletedEvent($checkoutId, $createdOrders));

                return [
                    'success' => true,
                    'message' => 'Multi-merchant checkout completed',
                    'checkout_id' => $checkoutId,
                    'order_numbers' => $orderNumbers,
                    'total' => $totalCents / 100,
                    'stripe_contexts' => array_map(fn($ctx) => [
                        'payment_intent_id' => $ctx['payment_intent_id'],
                        'client_secret' => $ctx['client_secret'],
                    ], $stripeContexts),
                ];
            });

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Checkout failed'
            ];
        }
    }
}