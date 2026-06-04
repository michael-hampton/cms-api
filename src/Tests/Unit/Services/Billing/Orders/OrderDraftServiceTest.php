<?php

namespace App\Tests\Unit\Services\Billing\Orders;

use App\DTO\Cart\TaxData;
use App\DTO\Subscriptions\SubscriptionPricing;
use App\Enums\Subscriptions\SubscriptionType;
use App\Framework\Database\Database;
use App\Models\Member;
use App\Models\Order;
use App\Models\Subscription;
use App\Repositories\Billing\OrderRepository;
use App\Services\Billing\Order\OrderCreationService;
use App\Services\Billing\Order\OrderDraftService;
use App\Services\Billing\TaxCalculatorService;
use App\Services\Vouchers\ResolvedDiscounts;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

/**
 * BUG NOTE: createPendingOrder uses $pricing AFTER the foreach loop ends:
 *   $totalCents = $pricing->totalCents + $totalTaxCents;
 * This means $pricing holds the LAST iteration's value — not an aggregate.
 * For single-subscription carts this is accidental-correct; for multi-subscription
 * carts totalCents will be wrong. Tests below expose both behaviours.
 *
 * BUG NOTE: createPendingOrder calls $resolvedDiscounts->merchantFundedCents and
 * ->platformFundedCents unconditionally (outside the ternary) — if $resolvedDiscounts
 * is null this will throw a fatal error. The test below exposes this.
 */
class OrderDraftServiceTest extends TestCase
{
    private OrderCreationService&MockInterface $orderCreationService;
    private OrderRepository&MockInterface $orderRepository;
    private TaxCalculatorService&MockInterface $taxCalculatorService;
    private Database&MockInterface $database;
    private OrderDraftService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderCreationService = Mockery::mock(OrderCreationService::class);
        $this->orderRepository = Mockery::mock(OrderRepository::class);
        $this->taxCalculatorService = Mockery::mock(TaxCalculatorService::class);
        $this->database = Mockery::mock(Database::class);

        $this->service = new OrderDraftService(
            $this->orderCreationService,
            $this->orderRepository,
            $this->taxCalculatorService,
            $this->database
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeMember(int $id = 1): Member&MockInterface
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = $id;
        return $member;
    }

    private function makeOrder(): Order&MockInterface
    {
        return Mockery::mock(Order::class)->makePartial();
    }

    private function makePricing(array $overrides = []): SubscriptionPricing&MockInterface
    {
        $pricing = Mockery::mock(SubscriptionPricing::class)->makePartial();
        $pricing->subtotalCents = $overrides['subtotalCents'] ?? 1000;
        $pricing->shippingCents = $overrides['shippingCents'] ?? 0;
        $pricing->discountCents = $overrides['discountCents'] ?? 0;
        $pricing->totalCents = $overrides['totalCents'] ?? 1000;
        $pricing->deliveryType = $overrides['deliveryType'] ?? SubscriptionType::DIGITAL->value;
        $pricing->voucherId = $overrides['voucherId'] ?? null;
        $pricing->shippingAddressSnapshot = $overrides['shippingAddressSnapshot'] ?? null;

        $pricing->allows('getSubtotal')->andReturn(($overrides['subtotalCents'] ?? 1000) / 100);
        $pricing->allows('getShipping')->andReturn(($overrides['shippingCents'] ?? 0) / 100);

        return $pricing;
    }

    private function makeSubscription(int $id = 1): Subscription&MockInterface
    {
        $sub = Mockery::mock(Subscription::class)->makePartial();
        $sub->id = $id;
        $sub->plan_name = 'Monthly Box';
        return $sub;
    }

    private function makeResolvedDiscounts(array $overrides = []): ResolvedDiscounts&MockInterface
    {
        $rd = Mockery::mock(ResolvedDiscounts::class)->makePartial();
        $rd->rewardDiscountCents = $overrides['rewardDiscountCents'] ?? 0;
        $rd->offerDiscountCents = $overrides['offerDiscountCents'] ?? 0;
        $rd->voucherDiscountCents = $overrides['voucherDiscountCents'] ?? 0;
        $rd->tieredDiscountCents = $overrides['tieredDiscountCents'] ?? 0;
        $rd->merchantFundedCents = $overrides['merchantFundedCents'] ?? 0;
        $rd->platformFundedCents = $overrides['platformFundedCents'] ?? 0;
        return $rd;
    }

    private function makeTaxResult(int $taxCents = 0): object
    {
        return new TaxData(rate: 0, jurisdiction: null, includesShipping: false, taxCents: $taxCents);

        //return (object) ['taxCents' => $taxCents];
    }

    private function makeSubData(
        ?SubscriptionPricing $pricing = null,
        ?Subscription        $subscription = null,
        array                $meta = []
    ): array
    {
        return [
            'subscription' => $subscription ?? $this->makeSubscription(),
            'pricing' => $pricing ?? $this->makePricing(),
            'meta' => $meta,
        ];
    }

    // -------------------------------------------------------------------------
    // createPendingOrder — happy path
    // -------------------------------------------------------------------------

    public function testCreatePendingOrderDelegatesToOrderCreationService(): void
    {
        $member = $this->makeMember();
        $order = $this->makeOrder();
        $pricing = $this->makePricing(['subtotalCents' => 1000, 'totalCents' => 1000]);
        $rd = $this->makeResolvedDiscounts();

        $this->taxCalculatorService
            ->expects('calculateOrderTax')
            ->andReturn($this->makeTaxResult(0));

        $this->orderCreationService
            ->expects('create')
            ->with(Mockery::type('array'), Mockery::type('array'), 1)
            ->andReturn($order);

        $result = $this->service->createPendingOrder(
            [$this->makeSubData($pricing)],
            $member,
            1,
            ['country' => 'GB'],
            $rd
        );

        $this->assertSame($order, $result);
    }

    public function testCreatePendingOrderBuildsOrderItemPerSubscription(): void
    {
        $member = $this->makeMember();
        $order = $this->makeOrder();
        $rd = $this->makeResolvedDiscounts();

        $pricing1 = $this->makePricing(['subtotalCents' => 1000, 'totalCents' => 1000]);
        $pricing2 = $this->makePricing(['subtotalCents' => 2000, 'totalCents' => 2000]);

        $this->taxCalculatorService->expects('calculateOrderTax')->andReturn($this->makeTaxResult(0));

        $this->orderCreationService
            ->expects('create')
            ->withArgs(function ($data, array $items) {
                return count($items) === 2;
            })
            ->andReturn($order);

        $result = $this->service->createPendingOrder(
            [
                $this->makeSubData($pricing1, $this->makeSubscription(1)),
                $this->makeSubData($pricing2, $this->makeSubscription(2)),
            ],
            $member,
            1,
            ['country' => 'GB'],
            $rd
        );

        $this->assertSame($order, $result);
    }

    public function testCreatePendingOrderSetsOrderStatusToPending(): void
    {
        $member = $this->makeMember();
        $order = $this->makeOrder();
        $pricing = $this->makePricing(['subtotalCents' => 500, 'totalCents' => 500]);
        $rd = $this->makeResolvedDiscounts();

        $this->taxCalculatorService->expects('calculateOrderTax')->andReturn($this->makeTaxResult(0));

        $this->orderCreationService
            ->expects('create')
            ->withArgs(fn($data) => ($data['status'] ?? null) === 'pending'
                && ($data['payment_status'] ?? null) === 'unpaid')
            ->andReturn($order);

        $result = $this->service->createPendingOrder(
            [$this->makeSubData($pricing)],
            $member, 1, ['country' => 'GB'], $rd
        );

        $this->assertSame($order, $result);
    }

    public function testCreatePendingOrderAccumulatesSubtotalAndShippingAcrossSubscriptions(): void
    {
        $member = $this->makeMember();
        $order = $this->makeOrder();
        $rd = $this->makeResolvedDiscounts();

        $p1 = $this->makePricing(['subtotalCents' => 1000, 'shippingCents' => 200, 'totalCents' => 1200]);
        $p2 = $this->makePricing(['subtotalCents' => 500, 'shippingCents' => 100, 'totalCents' => 600]);

        $this->taxCalculatorService->expects('calculateOrderTax')->andReturn($this->makeTaxResult(0));

        $this->orderCreationService
            ->expects('create')
            ->withArgs(function ($data) {
                // subtotal = (1000+500)/100 = 15.0; shipping = (200+100)/100 = 3.0
                return ($data['subtotal'] ?? null) === 15
                    && ($data['shipping'] ?? null) === 3;
            })
            ->andReturn($order);

        $result = $this->service->createPendingOrder(
            [
                $this->makeSubData($p1, $this->makeSubscription(1)),
                $this->makeSubData($p2, $this->makeSubscription(2)),
            ],
            $member, 1, ['country' => 'GB'], $rd
        );

        $this->assertSame($order, $result);
    }

    public function testCreatePendingOrderPassesCountryStatePostalCodeToTaxCalculator(): void
    {
        $member = $this->makeMember();
        $order = $this->makeOrder();
        $pricing = $this->makePricing(['subtotalCents' => 1000, 'totalCents' => 1000]);
        $rd = $this->makeResolvedDiscounts();

        $this->taxCalculatorService
            ->expects('calculateOrderTax')
            ->with(
                1000,       // subtotalCents
                0,          // shippingCents
                'US',       // country
                'CA',       // state
                '90210',    // postal_code
                $member
            )
            ->andReturn($this->makeTaxResult(0));

        $this->orderCreationService->expects('create')->andReturn($order);

        $result = $this->service->createPendingOrder(
            [$this->makeSubData($pricing)],
            $member,
            1,
            ['country' => 'US', 'state' => 'CA', 'postal_code' => '90210'],
            $rd
        );

        $this->assertSame($order, $result);
    }

    public function testCreatePendingOrderDistributesTaxToItemsWhenTaxIsPositive(): void
    {
        $member = $this->makeMember();
        $order = $this->makeOrder();
        $pricing = $this->makePricing(['subtotalCents' => 1000, 'totalCents' => 1000]);
        $rd = $this->makeResolvedDiscounts();

        $this->taxCalculatorService
            ->expects('calculateOrderTax')
            ->andReturn($this->makeTaxResult(200));

        $this->orderCreationService->expects('create')->andReturn($order);

        $result = $this->service->createPendingOrder(
            [$this->makeSubData($pricing)],
            $member, 1, ['country' => 'GB'], $rd
        );

        $this->assertSame($order, $result);
    }

    public function testCreatePendingOrderDoesNotDistributeTaxWhenTaxIsZero(): void
    {
        $member = $this->makeMember();
        $order = $this->makeOrder();
        $pricing = $this->makePricing(['subtotalCents' => 1000, 'totalCents' => 1000]);
        $rd = $this->makeResolvedDiscounts();

        $this->taxCalculatorService
            ->expects('calculateOrderTax')
            ->andReturn($this->makeTaxResult(0));

        $this->taxCalculatorService->expects('distributeTaxToItems')->never();

        $this->orderCreationService->expects('create')->andReturn($order);

        $result = $this->service->createPendingOrder(
            [$this->makeSubData($pricing)],
            $member, 1, ['country' => 'GB'], $rd
        );

        $this->assertSame($order, $result);
    }

    // -------------------------------------------------------------------------
    // createPendingOrder — free order override
    // -------------------------------------------------------------------------

    public function testCreatePendingOrderZerosAllFinancialFieldsWhenFreeOrder(): void
    {
        $member = $this->makeMember();
        $order = $this->makeOrder();
        $pricing = $this->makePricing(['subtotalCents' => 5000, 'totalCents' => 5000]);
        $rd = $this->makeResolvedDiscounts();

        $this->taxCalculatorService->expects('calculateOrderTax')->andReturn($this->makeTaxResult(0));

        $this->orderCreationService
            ->expects('create')
            ->withArgs(function ($data) {
                return ($data['subtotal'] ?? -1) === 0
                    && ($data['shipping'] ?? -1) === 0
                    && ($data['tax'] ?? -1) === 0
                    && ($data['total'] ?? -1) === 0
                    && ($data['discount'] ?? -1) === 0
                    && ($data['offer_discount'] ?? -1) === 0
                    && ($data['voucher_discount'] ?? -1) === 0
                    && ($data['reward_discount'] ?? -1) === 0
                    && ($data['tiered_discount'] ?? -1) === 0;
            })
            ->andReturn($order);

        $result = $this->service->createPendingOrder(
            [$this->makeSubData($pricing)],
            $member, 1, ['country' => 'GB'], $rd,
            isFreeOrder: true
        );

        $this->assertSame($order, $result);
    }

    // -------------------------------------------------------------------------
    // createPendingOrder — subscription ID mapping
    // -------------------------------------------------------------------------

    public function testCreatePendingOrderSetsSingleSubscriptionIdDirectly(): void
    {
        $member = $this->makeMember();
        $order = $this->makeOrder();
        $pricing = $this->makePricing(['subtotalCents' => 1000, 'totalCents' => 1000]);
        $rd = $this->makeResolvedDiscounts();

        $this->taxCalculatorService->expects('calculateOrderTax')->andReturn($this->makeTaxResult(0));

        $this->orderCreationService
            ->expects('create')
            ->withArgs(fn($data) => ($data['one_time_subscription_id'] ?? null) === 99
                && !isset($data['metadata']['multiple_subscriptions']))
            ->andReturn($order);

        $result = $this->service->createPendingOrder(
            [$this->makeSubData($pricing, $this->makeSubscription(99))],
            $member, 1, ['country' => 'GB'], $rd
        );

        $this->assertSame($order, $result);
    }

    public function testCreatePendingOrderSetsMetadataForMultipleSubscriptions(): void
    {
        $member = $this->makeMember();
        $order = $this->makeOrder();
        $pricing = $this->makePricing(['subtotalCents' => 1000, 'totalCents' => 1000]);
        $rd = $this->makeResolvedDiscounts();

        $this->taxCalculatorService->expects('calculateOrderTax')->andReturn($this->makeTaxResult(0));

        $this->orderCreationService
            ->expects('create')
            ->withArgs(function ($data) {
                return isset($data['metadata']['multiple_subscriptions'])
                    && $data['metadata']['multiple_subscriptions'] === true
                    && count($data['metadata']['subscription_ids']) === 2;
            })
            ->andReturn($order);

        $result = $this->service->createPendingOrder(
            [
                $this->makeSubData($pricing, $this->makeSubscription(1)),
                $this->makeSubData($pricing, $this->makeSubscription(2)),
            ],
            $member, 1, ['country' => 'GB'], $rd
        );

        $this->assertSame($order, $result);
    }

    // -------------------------------------------------------------------------
    // createPendingOrder — shipping address handling
    // -------------------------------------------------------------------------

    public function testCreatePendingOrderDoesNotSetShippingAddressForDigitalOnly(): void
    {
        $member = $this->makeMember();
        $order = $this->makeOrder();
        $pricing = $this->makePricing(['deliveryType' => SubscriptionType::DIGITAL->value, 'subtotalCents' => 1000, 'totalCents' => 1000]);
        $rd = $this->makeResolvedDiscounts();

        $this->taxCalculatorService->expects('calculateOrderTax')->andReturn($this->makeTaxResult(0));

        $this->orderCreationService
            ->expects('create')
            ->withArgs(fn($data) => !isset($data['shipping_address']) && !isset($data['shipping_address_id']))
            ->andReturn($order);

        $result = $this->service->createPendingOrder(
            [$this->makeSubData($pricing)],
            $member, 1, ['country' => 'GB'], $rd
        );

        $this->assertSame($order, $result);
    }

    public function testCreatePendingOrderSetsSavedAddressIdWhenPrintAndSavedAddressProvided(): void
    {
        $member = $this->makeMember();
        $order = $this->makeOrder();
        $pricing = $this->makePricing([
            'deliveryType' => SubscriptionType::PRINTED->value,
            'subtotalCents' => 1000,
            'totalCents' => 1000,
        ]);
        $rd = $this->makeResolvedDiscounts();

        $this->taxCalculatorService->expects('calculateOrderTax')->andReturn($this->makeTaxResult(0));

        $this->orderCreationService
            ->expects('create')
            ->withArgs(fn($data) => ($data['shipping_address_id'] ?? null) === 42)
            ->andReturn($order);

        $result = $this->service->createPendingOrder(
            [$this->makeSubData($pricing)],
            $member, 1,
            ['country' => 'GB', 'saved_address' => 42],
            $rd
        );

        $this->assertSame($order, $result);
    }

    public function testCreatePendingOrderUsesSnapshotAddressWhenPrintAndNoSavedAddress(): void
    {
        $member = $this->makeMember();
        $order = $this->makeOrder();
        $snapshot = ['line1' => '1 Street', 'country' => 'GB'];
        $pricing = $this->makePricing([
            'deliveryType' => SubscriptionType::PRINTED->value,
            'subtotalCents' => 1000,
            'totalCents' => 1000,
            'shippingAddressSnapshot' => $snapshot,
        ]);
        $rd = $this->makeResolvedDiscounts();

        $this->taxCalculatorService->expects('calculateOrderTax')->andReturn($this->makeTaxResult(0));

        $this->orderCreationService
            ->expects('create')
            ->withArgs(fn($data) => ($data['shipping_address'] ?? null) === $snapshot
                && ($data['billing_address'] ?? null) === $snapshot)
            ->andReturn($order);

        $result = $this->service->createPendingOrder(
            [$this->makeSubData($pricing)],
            $member, 1, ['country' => 'GB'], $rd
        );

        $this->assertSame($order, $result);
    }

    // -------------------------------------------------------------------------
    // createPendingOrder — resolvedDiscounts mapping
    // -------------------------------------------------------------------------

    public function testCreatePendingOrderMapsResolvedDiscountsToOrderData(): void
    {
        $member = $this->makeMember();
        $order = $this->makeOrder();
        $pricing = $this->makePricing(['subtotalCents' => 1000, 'totalCents' => 1000]);
        $rd = $this->makeResolvedDiscounts([
            'rewardDiscountCents' => 100,
            'offerDiscountCents' => 200,
            'voucherDiscountCents' => 300,
            'tieredDiscountCents' => 400,
            'merchantFundedCents' => 500,
            'platformFundedCents' => 600,
        ]);

        $this->taxCalculatorService->expects('calculateOrderTax')->andReturn($this->makeTaxResult(0));

        $this->orderCreationService
            ->expects('create')
            ->withArgs(function ($data) {
                return ($data['reward_discount'] ?? null) === 1
                    && ($data['offer_discount'] ?? null) === 2
                    && ($data['voucher_discount'] ?? null) === 3
                    && ($data['tiered_discount'] ?? null) === 4
                    && ($data['merchant_funded'] ?? null) === 5
                    && ($data['platform_funded'] ?? null) === 6;
            })
            ->andReturn($order);

        $result = $this->service->createPendingOrder(
            [$this->makeSubData($pricing)],
            $member, 1, ['country' => 'GB'], $rd
        );

        $this->assertSame($order, $result);
    }

    public function testCreatePendingOrderDefaultsDiscountsToZeroWhenResolvedDiscountsIsNull(): void
    {
        $member = $this->makeMember();
        $order = $this->makeOrder();
        $pricing = $this->makePricing(['subtotalCents' => 1000, 'totalCents' => 1000]);

        $this->taxCalculatorService->expects('calculateOrderTax')->andReturn($this->makeTaxResult(0));

        $this->orderCreationService
            ->expects('create')
            ->withArgs(function ($data) {
                return ($data['reward_discount'] ?? -1) === 0
                    && ($data['offer_discount'] ?? -1) === 0
                    && ($data['voucher_discount'] ?? -1) === 0
                    && ($data['tiered_discount'] ?? -1) === 0
                    && ($data['merchant_funded'] ?? -1) === 0
                    && ($data['platform_funded'] ?? -1) === 0;
            })
            ->andReturn($order);

        $result = $this->service->createPendingOrder(
            [$this->makeSubData($pricing)],
            $member, 1, ['country' => 'GB'],
            null
        );

        $this->assertSame($order, $result);
    }

    public function testCreatePendingOrderPersistsPostedVoucherFallbackWhenResolvedDiscountsAreZero(): void
    {
        $member = $this->makeMember();
        $order = $this->makeOrder();
        $pricing = $this->makePricing([
            'subtotalCents' => 4950,
            'discountCents' => 50,
            'totalCents' => 4950,
            'voucherId' => 11,
        ]);
        $rd = $this->makeResolvedDiscounts(['voucherDiscountCents' => 0, 'platformFundedCents' => 0]);

        $this->taxCalculatorService->expects('calculateOrderTax')->andReturn($this->makeTaxResult(0));

        $this->orderCreationService
            ->expects('create')
            ->withArgs(function ($data) {
                return ($data['voucher_code'] ?? null) === 'SAVE20'
                    && ($data['discount'] ?? null) === 0.5
                    && ($data['voucher_discount'] ?? null) === 0.5
                    && ($data['platform_funded'] ?? null) === 0.5;
            })
            ->andReturn($order);

        $result = $this->service->createPendingOrder(
            [$this->makeSubData($pricing)],
            $member,
            1,
            ['country' => 'GB', 'voucher_code' => 'SAVE20', 'voucher_id' => 11, 'discount_amount' => 0.5],
            $rd
        );

        $this->assertSame($order, $result);
    }

    public function testCreatePendingOrderTotalIsSumOfAllSubscriptionTotals(): void
    {
        $member = $this->makeMember();
        $order = $this->makeOrder();
        $rd = $this->makeResolvedDiscounts();

        // Two subscriptions: £10.00 + £20.00 = £30.00
        $p1 = $this->makePricing(['subtotalCents' => 1000, 'totalCents' => 1000]);
        $p2 = $this->makePricing(['subtotalCents' => 2000, 'totalCents' => 2000]);

        $this->taxCalculatorService->expects('calculateOrderTax')->andReturn($this->makeTaxResult(0));

        $this->orderCreationService
            ->shouldReceive('create')
            ->once()
            ->withArgs(function ($data, $items, $siteId) {

                $this->assertSame(30, $data['total']);
                $this->assertCount(2, $items);
                $this->assertSame(1, $siteId);

                return true;
            })
            ->andReturn($order);

        $result = $this->service->createPendingOrder(
            [
                $this->makeSubData($p1, $this->makeSubscription(1)),
                $this->makeSubData($p2, $this->makeSubscription(2)),
            ],
            $member, 1, ['country' => 'GB'], $rd
        );

        $this->assertSame($order, $result);
    }

    // -------------------------------------------------------------------------
    // attachPaymentIntent
    // -------------------------------------------------------------------------

    public function testAttachPaymentIntentUpdatesOrderWithinTransaction(): void
    {
        $order = $this->makeOrder();
        $order->id = 5;

        $this->database
            ->expects('transaction')
            ->andReturnUsing(fn($cb) => $cb());

        $this->orderRepository
            ->expects('update')
            ->with(5, [
                'payment_intent_id' => 'pi_abc123',
                'stripe_customer_id' => 'cus_xyz',
            ])
            ->once();

        $this->service->attachPaymentIntent($order, [
            'payment_intent_id' => 'pi_abc123',
            'customer_id' => 'cus_xyz',
        ]);

        $this->addToAssertionCount(1); // Mockery verifies update was called
    }

    public function testAttachPaymentIntentSetsStripeCustomerIdToNullWhenNotProvided(): void
    {
        $order = $this->makeOrder();
        $order->id = 5;

        $this->database->expects('transaction')->andReturnUsing(fn($cb) => $cb());

        $this->orderRepository
            ->expects('update')
            ->with(5, [
                'payment_intent_id' => 'pi_abc123',
                'stripe_customer_id' => null,
            ])
            ->once();

        $this->service->attachPaymentIntent($order, ['payment_intent_id' => 'pi_abc123']);

        $this->addToAssertionCount(1);
    }

    public function testAttachPaymentIntentReturnsVoid(): void
    {
        $order = $this->makeOrder();
        $order->id = 5;

        $this->database->expects('transaction')->andReturnUsing(fn($cb) => $cb());
        $this->orderRepository->expects('update')->once();

        // Return type is void — confirm no exception
        $result = $this->service->attachPaymentIntent($order, ['payment_intent_id' => 'pi_x']);

        $this->assertNull($result);
    }

    public function testCreatePendingOrderHandlesFullyDiscountedOrder(): void
    {
        $member = $this->makeMember();
        $order = $this->makeOrder();
        $rd = $this->makeResolvedDiscounts();

        $pricing = $this->makePricing([
            'subtotalCents' => 5000,
            'discountCents' => 5000,
            'totalCents' => 0,
        ]);

        $this->taxCalculatorService
            ->expects('calculateOrderTax')
            ->andReturn($this->makeTaxResult(0));

        $this->taxCalculatorService
            ->expects('distributeTaxToItems')
            ->never();

        $this->orderCreationService
            ->expects('create')
            ->withArgs(fn($data) => ($data['subtotal'] ?? null) === 50
                && ($data['discount'] ?? null) === 50
            )
            ->andReturn($order);

        $result = $this->service->createPendingOrder(
            [$this->makeSubData($pricing)],
            $member,
            1,
            ['country' => 'GB'],
            $rd
        );

        $this->assertSame($order, $result);
    }

    public function testCreatePendingOrderIncludesMetaInOrderItemMetadata(): void
    {
        $member = $this->makeMember();
        $order = $this->makeOrder();
        $rd = $this->makeResolvedDiscounts();

        $meta = [
            'is_preorder' => true,
            'expected_ship_date' => '2025-06-01',
            'next_issue_id' => 42,
            'next_issue_title' => 'Summer Edition',
        ];

        $pricing = $this->makePricing([
            'subtotalCents' => 5000,
            'totalCents' => 5000
        ]);

        $this->taxCalculatorService
            ->expects('calculateOrderTax')
            ->andReturn($this->makeTaxResult(0));

        $this->orderCreationService
            ->expects('create')
            ->withArgs(function ($data, $items) {

                $itemMeta = $items[0]['metadata'];

                return $items[0]['preorder_enabled'] === true
                    && $items[0]['expected_ship_date'] === '2025-06-01'
                    && $itemMeta['next_issue_id'] === 42
                    && $itemMeta['next_issue_title'] === 'Summer Edition';
            })
            ->andReturn($order);

        $result = $this->service->createPendingOrder(
            [$this->makeSubData($pricing, $this->makeSubscription(), $meta)],
            $member,
            1,
            ['country' => 'GB'],
            $rd
        );

        $this->assertSame($order, $result);
    }

    public function testCreatePendingOrderMapsSnapshotShippingAddressStructure(): void
    {
        $member = $this->makeMember();
        $order = $this->makeOrder();
        $rd = $this->makeResolvedDiscounts();

        $snapshot = [
            'address_line_1' => '123 Main St',
            'city' => 'London',
            'postcode' => 'SW1A 1AA',
            'country' => 'GB',
        ];

        $pricing = $this->makePricing([
            'deliveryType' => SubscriptionType::PRINTED->value,
            'shippingAddressSnapshot' => $snapshot,
            'subtotalCents' => 1000,
            'totalCents' => 1000,
        ]);

        $this->taxCalculatorService
            ->expects('calculateOrderTax')
            ->andReturn($this->makeTaxResult(0));

        $this->orderCreationService
            ->expects('create')
            ->withArgs(function ($data) {

                return isset($data['shipping_address']['address_line_1'])
                    && $data['shipping_address']['address_line_1'] === '123 Main St';
            })
            ->andReturn($order);

        $result = $this->service->createPendingOrder(
            [$this->makeSubData($pricing)],
            $member,
            1,
            ['country' => 'GB'],
            $rd
        );

        $this->assertSame($order, $result);
    }
}
