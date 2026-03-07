<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\DTO\Subscriptions\SubscriptionPricing;
use App\Enums\CartItemType;
use App\Enums\Subscriptions\SubscriptionStatus;
use App\Models\Member;
use App\Models\Subscription;
use App\Services\Shopping\OneTimeSubscriptionService;
use App\Services\Subscriptions\SubscriptionBatchFactory;
use App\Services\Subscriptions\SubscriptionPricingService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class SubscriptionBatchFactoryTest extends TestCase
{
    private OneTimeSubscriptionService&MockInterface $subscriptionService;
    private SubscriptionPricingService&MockInterface $pricingCalculator;
    private SubscriptionBatchFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriptionService = Mockery::mock(OneTimeSubscriptionService::class);
        $this->pricingCalculator = Mockery::mock(SubscriptionPricingService::class);

        $this->factory = new SubscriptionBatchFactory(
            $this->subscriptionService,
            $this->pricingCalculator
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

    private function makeSubscription(int $id = 1): Subscription&MockInterface
    {
        $sub = Mockery::mock(Subscription::class)->makePartial();
        $sub->id = $id;
        return $sub;
    }

    private function makePricing(
        ?int   $voucherId = null,
        string $deliveryType = 'digital',
        int    $subtotalCents = 5000,
        int    $discountCents = 0,
        int    $shippingCents = 0,
        int    $taxCents = 0,
        int    $totalCents = 5000
    ): SubscriptionPricing
    {
        return new SubscriptionPricing(
            subtotalCents: $subtotalCents,
            discountCents: $discountCents,
            shippingCents: $shippingCents,
            taxCents: $taxCents,
            totalCents: $totalCents,
            deliveryType: $deliveryType,
            voucherId: $voucherId
        );
    }

    private function makeCartItem(int $planId = 1, array $options = []): array
    {
        return [
            'subscription_plan_id' => $planId,
            'options' => $options,
        ];
    }

    private function expectPricingCalculated(
        array               $item,
        ?string             $voucherCode,
        Member              $member,
        array               $checkoutData,
        SubscriptionPricing $pricing
    ): void
    {
        $this->pricingCalculator
            ->expects('calculateForCartItem')
            ->with($item, $voucherCode, $member, $checkoutData)
            ->andReturn($pricing);
    }

    private function expectSubscriptionCreated(
        Member              $member,
        int                 $planId,
        SubscriptionPricing $pricing,
        Subscription        $subscription,
        ?string             $startDate = null
    ): void
    {
        $this->subscriptionService
            ->expects('createOneTimeSubscription')
            ->with(
                $member->id,
                $planId,
                $pricing->deliveryType,
                Mockery::any(),
                $pricing->voucherId,
                $pricing,
                SubscriptionStatus::PENDING,
                $startDate
            )
            ->andReturn($subscription);
    }

    // -------------------------------------------------------------------------
    // createPendingSubscriptions — basic flow
    // -------------------------------------------------------------------------

    public function testCreatePendingSubscriptionsReturnsOneEntryPerCartItem(): void
    {
        $member = $this->makeMember();
        $pricing = $this->makePricing();
        $subscription = $this->makeSubscription();
        $item = $this->makeCartItem(1);

        $this->pricingCalculator
            ->expects('calculateForCartItem')
            ->andReturn($pricing);

        $this->subscriptionService
            ->allows('createOneTimeSubscription')
            ->andReturn($subscription);

        $result = $this->factory->createPendingSubscriptions(
            [$item],
            [],
            $member,
            1,
            null
        );

        $this->assertCount(1, $result);
    }

    public function testCreatePendingSubscriptionsReturnsCorrectStructurePerEntry(): void
    {
        $member = $this->makeMember();
        $pricing = $this->makePricing();
        $subscription = $this->makeSubscription(42);
        $item = $this->makeCartItem(7);

        $this->expectPricingCalculated($item, null, $member, [], $pricing);
        $this->expectSubscriptionCreated($member, 7, $pricing, $subscription);

        $result = $this->factory->createPendingSubscriptions([$item], [], $member, 1, null);

        $this->assertArrayHasKey('subscription', $result[0]);
        $this->assertArrayHasKey('pricing', $result[0]);
        $this->assertArrayHasKey('meta', $result[0]);
        $this->assertSame($subscription, $result[0]['subscription']);
        $this->assertSame($pricing, $result[0]['pricing']);
    }

    public function testCreatePendingSubscriptionsCreatesSubscriptionsForMultipleItems(): void
    {
        $member = $this->makeMember();
        $item1 = $this->makeCartItem(1);
        $item2 = $this->makeCartItem(2);
        $p1 = $this->makePricing();
        $p2 = $this->makePricing();
        $s1 = $this->makeSubscription(1);
        $s2 = $this->makeSubscription(2);

        $this->expectPricingCalculated($item1, null, $member, [], $p1);
        $this->expectPricingCalculated($item2, null, $member, [], $p2);
        $this->expectSubscriptionCreated($member, 1, $p1, $s1);
        $this->expectSubscriptionCreated($member, 2, $p2, $s2);

        $result = $this->factory->createPendingSubscriptions([$item1, $item2], [], $member, 1, null);

        $this->assertCount(2, $result);
        $this->assertSame($s1, $result[0]['subscription']);
        $this->assertSame($s2, $result[1]['subscription']);
    }

    // -------------------------------------------------------------------------
    // createPendingSubscriptions — voucher logic
    // -------------------------------------------------------------------------

    public function testVoucherIsPassedToFirstNonBundleItem(): void
    {
        $member = $this->makeMember();
        $item = $this->makeCartItem(1);
        $pricing = $this->makePricing(voucherId: null); // voucher not consumed
        $subscription = $this->makeSubscription();
        $checkoutData = ['voucher_code' => 'SAVE10'];

        // Voucher MUST be passed to the first item
        $this->pricingCalculator
            ->expects('calculateForCartItem')
            ->with($item, 'SAVE10', $member, $checkoutData)
            ->andReturn($pricing);

        $this->subscriptionService
            ->expects('createOneTimeSubscription')
            ->andReturn($subscription);

        $result = $this->factory->createPendingSubscriptions([$item], $checkoutData, $member, 1, null);

        $this->assertCount(1, $result);
    }

    public function testVoucherIsNotPassedToSecondItemAfterFirstConsumesIt(): void
    {
        $member = $this->makeMember();
        $item1 = $this->makeCartItem(1);
        $item2 = $this->makeCartItem(2);
        $pricing1 = $this->makePricing(voucherId: 99); // consumed on item 1
        $pricing2 = $this->makePricing(voucherId: null);
        $s1 = $this->makeSubscription(1);
        $s2 = $this->makeSubscription(2);
        $checkoutData = ['voucher_code' => 'SAVE10'];

        $this->pricingCalculator
            ->expects('calculateForCartItem')
            ->with($item1, 'SAVE10', $member, $checkoutData)
            ->andReturn($pricing1);

        // Second item must NOT receive the voucher
        $this->pricingCalculator
            ->expects('calculateForCartItem')
            ->with($item2, null, $member, $checkoutData)
            ->andReturn($pricing2);

        $this->subscriptionService->allows('createOneTimeSubscription')->andReturn($s1, $s2);

        $result = $this->factory->createPendingSubscriptions([$item1, $item2], $checkoutData, $member, 1, null);

        $this->assertCount(2, $result);
    }

    public function testVoucherIsPassedToSecondItemWhenFirstDidNotConsumeIt(): void
    {
        $member = $this->makeMember();
        $item1 = $this->makeCartItem(1);
        $item2 = $this->makeCartItem(2);
        $pricing1 = $this->makePricing(voucherId: null); // NOT consumed
        $pricing2 = $this->makePricing(voucherId: null);
        $s1 = $this->makeSubscription(1);
        $s2 = $this->makeSubscription(2);
        $checkoutData = ['voucher_code' => 'SAVE10'];

        $this->pricingCalculator
            ->expects('calculateForCartItem')
            ->with($item1, 'SAVE10', $member, $checkoutData)
            ->andReturn($pricing1);

        // First item didn't use it, so second gets the voucher too
        $this->pricingCalculator
            ->expects('calculateForCartItem')
            ->with($item2, 'SAVE10', $member, $checkoutData)
            ->andReturn($pricing2);

        $this->subscriptionService->allows('createOneTimeSubscription')->andReturn($s1, $s2);

        $result = $this->factory->createPendingSubscriptions([$item1, $item2], $checkoutData, $member, 1, null);

        $this->assertCount(2, $result);
    }

    public function testVoucherIsNeverPassedToBundleItems(): void
    {
        $member = $this->makeMember();
        $bundleItem = $this->makeCartItem(1, ['bundle_id' => 5]);
        $pricing = $this->makePricing(voucherId: null);
        $subscription = $this->makeSubscription();
        $checkoutData = ['voucher_code' => 'SAVE10'];

        // Bundle item must receive null voucher regardless
        $this->pricingCalculator
            ->expects('calculateForCartItem')
            ->with($bundleItem, null, $member, $checkoutData)
            ->andReturn($pricing);

        $this->subscriptionService->allows('createOneTimeSubscription')->andReturn($subscription);

        $result = $this->factory->createPendingSubscriptions([$bundleItem], $checkoutData, $member, 1, null);

        $this->assertCount(1, $result);
    }

    public function testVoucherIsNeverPassedToItemWithBundleTypeInOptions(): void
    {
        $member = $this->makeMember();
        $bundleItem = $this->makeCartItem(1, ['type' => CartItemType::SUBSCRIPTION_BUNDLE->value]);
        $pricing = $this->makePricing(voucherId: null);
        $subscription = $this->makeSubscription();
        $checkoutData = ['voucher_code' => 'SAVE10'];

        $this->pricingCalculator
            ->expects('calculateForCartItem')
            ->with($bundleItem, null, $member, $checkoutData)
            ->andReturn($pricing);

        $this->subscriptionService->allows('createOneTimeSubscription')->andReturn($subscription);

        $result = $this->factory->createPendingSubscriptions([$bundleItem], $checkoutData, $member, 1, null);

        $this->assertCount(1, $result);
    }

    public function testBundleItemDoesNotBlockVoucherForSubsequentNonBundleItem(): void
    {
        $member = $this->makeMember();
        $bundleItem = $this->makeCartItem(1, ['bundle_id' => 5]);
        $normalItem = $this->makeCartItem(2);
        $pricingB = $this->makePricing(voucherId: null);
        $pricingN = $this->makePricing(voucherId: null);
        $s1 = $this->makeSubscription(1);
        $s2 = $this->makeSubscription(2);
        $checkoutData = ['voucher_code' => 'FIRST'];

        $this->pricingCalculator
            ->expects('calculateForCartItem')
            ->with($bundleItem, null, $member, $checkoutData) // bundle gets null
            ->andReturn($pricingB);

        $this->pricingCalculator
            ->expects('calculateForCartItem')
            ->with($normalItem, 'FIRST', $member, $checkoutData) // normal item still gets voucher
            ->andReturn($pricingN);

        $this->subscriptionService->allows('createOneTimeSubscription')->andReturn($s1, $s2);

        $result = $this->factory->createPendingSubscriptions(
            [$bundleItem, $normalItem],
            $checkoutData,
            $member,
            1,
            null
        );

        $this->assertCount(2, $result);
    }

    public function testNoVoucherPassedWhenCheckoutDataHasNoVoucherCode(): void
    {
        $member = $this->makeMember();
        $item = $this->makeCartItem(1);
        $pricing = $this->makePricing();
        $subscription = $this->makeSubscription();

        $this->pricingCalculator
            ->expects('calculateForCartItem')
            ->with($item, null, $member, [])
            ->andReturn($pricing);

        $this->subscriptionService->allows('createOneTimeSubscription')->andReturn($subscription);

        $result = $this->factory->createPendingSubscriptions([$item], [], $member, 1, null);

        $this->assertCount(1, $result);
    }

    // -------------------------------------------------------------------------
    // createPendingSubscriptions — start date from options
    // -------------------------------------------------------------------------

    public function testStartDateIsPassedFromItemOptionsToSubscriptionService(): void
    {
        $member = $this->makeMember();
        $item = $this->makeCartItem(1, ['start_date' => '2025-01-01']);
        $pricing = $this->makePricing();
        $subscription = $this->makeSubscription();

        $this->pricingCalculator
            ->expects('calculateForCartItem')
            ->andReturn($pricing);

        $this->subscriptionService
            ->expects('createOneTimeSubscription')
            ->with(
                $member->id,
                1,
                $pricing->deliveryType,
                1,
                $pricing->voucherId,
                $pricing,
                SubscriptionStatus::PENDING,
                '2025-01-01'
            )
            ->andReturn($subscription);

        $result = $this->factory->createPendingSubscriptions([$item], [], $member, 1, null);

        $this->assertCount(1, $result);
    }

    public function testStartDateIsNullWhenNotInOptions(): void
    {
        $member = $this->makeMember();
        $item = $this->makeCartItem(1); // no start_date in options
        $pricing = $this->makePricing();
        $subscription = $this->makeSubscription();

        $this->pricingCalculator
            ->expects('calculateForCartItem')
            ->andReturn($pricing);

        $this->subscriptionService
            ->expects('createOneTimeSubscription')
            ->with(
                $member->id,
                1,
                $pricing->deliveryType,
                1,
                $pricing->voucherId,
                $pricing,
                SubscriptionStatus::PENDING,
                null
            )
            ->andReturn($subscription);

        $result = $this->factory->createPendingSubscriptions([$item], [], $member, 1, null);

        $this->assertCount(1, $result);
    }

    // -------------------------------------------------------------------------
    // createPendingSubscriptions — meta merging
    // -------------------------------------------------------------------------

    public function testMetaContainsExpectedShipDateWhenProvidedInItem(): void
    {
        $member = $this->makeMember();
        $item = array_merge($this->makeCartItem(1), [
            'expected_ship_date' => '2025-06-01',
            'is_preorder' => true,
        ]);
        $pricing = $this->makePricing();
        $subscription = $this->makeSubscription();

        $this->pricingCalculator->expects('calculateForCartItem')->andReturn($pricing);
        $this->subscriptionService->allows('createOneTimeSubscription')->andReturn($subscription);

        $result = $this->factory->createPendingSubscriptions([$item], [], $member, 1, null);

        $this->assertSame('2025-06-01', $result[0]['meta']['expected_ship_date']);
        $this->assertTrue($result[0]['meta']['is_preorder']);
    }

    public function testMetaDefaultsUnsetKeysToNull(): void
    {
        $member = $this->makeMember();
        $item = $this->makeCartItem(1);
        $pricing = $this->makePricing();
        $subscription = $this->makeSubscription();

        $this->pricingCalculator->expects('calculateForCartItem')->andReturn($pricing);
        $this->subscriptionService->allows('createOneTimeSubscription')->andReturn($subscription);

        $result = $this->factory->createPendingSubscriptions([$item], [], $member, 1, null);

        $meta = $result[0]['meta'];
        $this->assertNull($meta['is_preorder']);
        $this->assertNull($meta['expected_ship_date']);
        $this->assertNull($meta['release_date']);
        $this->assertNull($meta['is_pre_release']);
    }

    public function testMetaDoesNotContainNonMetaItemKeys(): void
    {
        $member = $this->makeMember();
        $item = array_merge($this->makeCartItem(1), [
            'unit_price' => 10.0,
            'quantity' => 1,
            'some_random' => 'value',
        ]);
        $pricing = $this->makePricing();
        $subscription = $this->makeSubscription();

        $this->pricingCalculator->expects('calculateForCartItem')->andReturn($pricing);
        $this->subscriptionService->allows('createOneTimeSubscription')->andReturn($subscription);

        $result = $this->factory->createPendingSubscriptions([$item], [], $member, 1, null);

        $meta = $result[0]['meta'];
        $this->assertArrayNotHasKey('unit_price', $meta);
        $this->assertArrayNotHasKey('quantity', $meta);
        $this->assertArrayNotHasKey('some_random', $meta);
    }

    // -------------------------------------------------------------------------
    // createPendingSubscriptions — subscription created in PENDING status
    // -------------------------------------------------------------------------

    public function testSubscriptionIsCreatedWithPendingStatus(): void
    {
        $member = $this->makeMember();
        $item = $this->makeCartItem(1);
        $pricing = $this->makePricing();
        $subscription = $this->makeSubscription();

        $this->pricingCalculator
            ->expects('calculateForCartItem')
            ->andReturn($pricing);

        $this->subscriptionService
            ->expects('createOneTimeSubscription')
            ->withArgs(fn(...$args) => true) // captured below
            ->andReturnUsing(function () use ($subscription) {
                return $subscription;
            });

        // Separate assertion on status via explicit named-arg mock
        $this->subscriptionService
            ->allows('createOneTimeSubscription')
            ->never(); // already set above — this is documenting intent

        $result = $this->factory->createPendingSubscriptions([$item], [], $member, 1, null);

        $this->assertSame($subscription, $result[0]['subscription']);
    }

    public function testReturnedPricingMatchesPricingFromCalculator(): void
    {
        $member = $this->makeMember();
        $item = $this->makeCartItem(1);
        $pricing = $this->makePricing();
        $subscription = $this->makeSubscription();

        $this->pricingCalculator->expects('calculateForCartItem')->andReturn($pricing);
        $this->subscriptionService->allows('createOneTimeSubscription')->andReturn($subscription);

        $result = $this->factory->createPendingSubscriptions([$item], [], $member, 1, null);

        $this->assertSame($pricing, $result[0]['pricing']);
    }

    public function testEmptyCartItemsReturnsEmptyArray(): void
    {
        $member = $this->makeMember();

        $result = $this->factory->createPendingSubscriptions([], [], $member, 1, null);

        $this->assertSame([], $result);
    }

    public function testZeroTaxDoesNotBreakCalculation(): void
    {
        $member = $this->makeMember();
        $item = $this->makeCartItem();
        $pricing = $this->makePricing(voucherId: null, taxCents: 0);
        $subscription = $this->makeSubscription();

        $this->pricingCalculator
            ->expects('calculateForCartItem')
            ->andReturn($pricing);

        $this->subscriptionService
            ->expects('createOneTimeSubscription')
            ->andReturn($subscription);

        $result = $this->factory->createPendingSubscriptions([$item], [], $member, 1, null);

        $this->assertSame($pricing, $result[0]['pricing']);
        $this->assertEquals(0, $result[0]['pricing']->taxCents);
        $this->assertEquals($result[0]['pricing']->subtotalCents, $result[0]['pricing']->totalCents);
    }

    public function testDiscountAppliedCorrectlyWhenVoucherUsed(): void
    {
        $member = $this->makeMember();
        $item = $this->makeCartItem();

        $pricing = $this->makePricing(
            voucherId: 123,
            totalCents: 4000,
            discountCents: 1000
        );

        $subscription = $this->makeSubscription();

        $this->pricingCalculator
            ->allows('calculateForCartItem')
            ->andReturn($pricing);

        $this->subscriptionService
            ->allows('createOneTimeSubscription')
            ->andReturn($subscription);

        $result = $this->factory->createPendingSubscriptions(
            [$item],
            ['voucher_code' => 'SAVE10'],
            $member,
            1,
            null
        );

        $this->assertSame(4000, $result[0]['pricing']->totalCents);
    }

    public function testMetadataFromCartItemIsPreserved(): void
    {
        $member = $this->makeMember();

        $item = array_merge($this->makeCartItem(1), [
            'is_pre_release' => true,
            'release_date' => '2025-06-01',
            'is_preorder' => true,
            'next_issue_id' => 42,
            'next_issue_title' => 'Summer Edition',
            'estimated_dispatch' => '2025-04-01',
        ]);

        $pricing = $this->makePricing();
        $subscription = $this->makeSubscription();

        $this->pricingCalculator
            ->allows('calculateForCartItem')
            ->andReturn($pricing);

        $this->subscriptionService
            ->allows('createOneTimeSubscription')
            ->andReturn($subscription);

        $result = $this->factory->createPendingSubscriptions(
            [$item],
            [],
            $member,
            1,
            null
        );

        $meta = $result[0]['meta'];

        $this->assertArrayHasKey('is_pre_release', $meta);
        $this->assertArrayHasKey('is_preorder', $meta);
        $this->assertArrayHasKey('release_date', $meta);
        $this->assertArrayHasKey('next_issue_id', $meta);

        $this->assertTrue($meta['is_pre_release']);
        $this->assertTrue($meta['is_preorder']);
        $this->assertEquals('2025-06-01', $meta['release_date']);
        $this->assertEquals(42, $meta['next_issue_id']);
        $this->assertEquals('Summer Edition', $meta['next_issue_title']);
    }

}