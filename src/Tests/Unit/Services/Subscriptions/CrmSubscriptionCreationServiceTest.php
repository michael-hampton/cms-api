<?php

namespace App\Tests\Unit\Services\Subscriptions;

use App\Exceptions\Checkout\CheckoutException;
use App\Framework\Authorization\MemberAuthWrapper;
use App\Models\Address;
use App\Models\Member;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Repositories\Members\AddressRepository;
use App\Repositories\Members\MemberRepository;
use App\Repositories\Subscriptions\SubscriptionPlanRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Shopping\CartService;
use App\Services\Shopping\OneTimeSubscriptionCheckoutService;
use App\Services\Subscriptions\CrmSubscriptionCreationService;
use App\Services\Subscriptions\SubscriptionPaymentService;
use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class CrmSubscriptionCreationServiceTest extends TestCase
{
    private MemberRepository&MockInterface $memberRepository;
    private SubscriptionPlanRepository&MockInterface $planRepository;
    private SubscriptionRepository&MockInterface $subscriptionRepository;
    private CartService&MockInterface $cartService;
    private OneTimeSubscriptionCheckoutService&MockInterface $checkoutService;
    private MemberAuthWrapper&MockInterface $memberAuth;
    private SubscriptionPaymentService&MockInterface $subscriptionPaymentService;
    private AddressRepository&MockInterface $addressRepository;

    private CrmSubscriptionCreationService $service;

    public function test_throws_when_member_not_found(): void
    {
        $this->memberRepository->expects('find')->with(99)->andReturnNull();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Member not found.');

        $this->service->createSubscription(99, 1, 'pm_test', 1);
    }

    public function test_throws_when_member_belongs_to_different_site(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->site_id = 2;

        $this->memberRepository->expects('find')->with(1)->andReturn($member);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Member does not belong to this site.');

        $this->service->createSubscription(1, 1, 'pm_test', siteId: 1);
    }

    // ─── Validation ───────────────────────────────────────────────────────────

    public function test_throws_when_plan_not_found(): void
    {
        $member = $this->makeMember(siteId: 1);

        $this->memberRepository->expects('find')->with(1)->andReturn($member);
        $this->planRepository->expects('find')->with(5)->andReturnNull();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Subscription plan not found.');

        $this->service->createSubscription(1, 5, 'pm_test', 1);
    }

    private function makeMember(int $siteId): Member&MockInterface
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;
        $member->site_id = $siteId;
        $member->first_name = 'Jane';
        $member->last_name = 'Doe';
        $member->email = 'jane@example.com';
        $member->phone = null;
        return $member;
    }

    public function test_throws_when_plan_is_inactive(): void
    {
        $member = $this->makeMember(siteId: 1);
        $plan = $this->makePlan(siteId: 1, isActive: false, name: 'Basic');

        $this->memberRepository->expects('find')->with(1)->andReturn($member);
        $this->planRepository->expects('find')->with(5)->andReturn($plan);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Plan "Basic" is not currently active.');

        $this->service->createSubscription(1, 5, 'pm_test', 1);
    }

    private function makePlan(int $siteId, bool $isActive, string $name = 'Pro'): SubscriptionPlan&MockInterface
    {
        $plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        $plan->id = 5;
        $plan->site_id = $siteId;
        $plan->is_active = $isActive;
        $plan->name = $name;
        return $plan;
    }

    public function test_throws_when_plan_belongs_to_different_site(): void
    {
        $member = $this->makeMember(siteId: 1);
        $plan = $this->makePlan(siteId: 2, isActive: true);

        $this->memberRepository->expects('find')->with(1)->andReturn($member);
        $this->planRepository->expects('find')->with(5)->andReturn($plan);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Plan does not belong to this site.');

        $this->service->createSubscription(1, 5, 'pm_test', 1);
    }

    public function test_throws_when_member_already_has_active_subscription(): void
    {
        $member = $this->makeMember(siteId: 1);
        $plan = $this->makePlan(siteId: 1, isActive: true);

        $this->memberRepository->expects('find')->with(1)->andReturn($member);
        $this->planRepository->expects('find')->with(5)->andReturn($plan);
        $this->subscriptionRepository->expects('hasActiveSubscriptionToPlan')->with(1, 5)->andReturnTrue();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Member already has an active subscription on this plan.');

        $this->service->createSubscription(1, 5, 'pm_test', 1);
    }

    // ─── Happy path ───────────────────────────────────────────────────────────

    public function test_creates_subscription_successfully(): void
    {
        $member = $this->makeMember(siteId: 1);
        $plan = $this->makePlan(siteId: 1, isActive: true);
        $subscription = $this->makeSubscription();

        $plan->shouldReceive('getDeliveryOptions')
            ->once()
            ->andReturn(['print']);

        $this->memberRepository->expects('find')->with(1)->andReturn($member);
        $this->planRepository->expects('find')->with(5)->andReturn($plan);
        $this->subscriptionRepository->expects('hasActiveSubscriptionToPlan')->with(1, 5)->andReturnFalse();

        $this->memberAuth->expects('login')->with($member)->once();
        $this->cartService->expects('addSubscriptionToCart')->with(5, 'print', [])->once();
        $this->cartService->expects('clear')->once();

        $checkoutResult = ['subscription_id' => 42, 'order_id' => 7];
        $this->checkoutService->expects('processCheckout')
            ->withArgs(fn($data, $siteId) => $data['payment_method_id'] === 'pm_test' &&
                $data['one_time_subscription'] === false &&
                $data['admin_created'] === true &&
                $siteId === 1
            )
            ->andReturn($checkoutResult);

        $this->subscriptionRepository->expects('find')->with(42)->andReturn($subscription);

        $this->subscriptionPaymentService->expects('processStripeSubscriptionPayment')
            ->once()
            ->andReturn(['subscription_id' => 'sub_stripe_123']);

        $subscription->expects('update')->with(['payment_subscription_id' => 'sub_stripe_123'])->once();

        // resolveSubscription path
        $this->subscriptionRepository->expects('find')->with(42)->andReturn($subscription);

        $result = $this->service->createSubscription(1, 5, 'pm_test', 1);

        $this->assertTrue($result['success']);
        $this->assertSame($subscription, $result['subscription']);
    }

    public function test_forwards_pricing_tier_and_offer_type_to_cart(): void
    {
        $member = $this->makeMember(siteId: 1);
        $plan = $this->makePlan(siteId: 1, isActive: true);
        $subscription = $this->makeSubscription();

        $plan->shouldReceive('getDeliveryOptions')
            ->once()
            ->andReturn(['digital']);

        $this->memberRepository->expects('find')->with(1)->andReturn($member);
        $this->planRepository->expects('find')->with(5)->andReturn($plan);
        $this->subscriptionRepository->expects('hasActiveSubscriptionToPlan')->with(1, 5)->andReturnFalse();

        $this->memberAuth->expects('login')->with($member)->once();
        $this->cartService->expects('addSubscriptionToCart')
            ->with(5, 'digital', [
                'pricing_tier_id' => 123,
                'offer_type' => 'digital',
            ])
            ->once();
        $this->cartService->expects('clear')->once();

        $checkoutResult = ['subscription_id' => 42, 'order_id' => 7];
        $this->checkoutService->expects('processCheckout')->andReturn($checkoutResult);
        $this->subscriptionRepository->expects('find')->with(42)->andReturn($subscription);
        $this->subscriptionPaymentService->expects('processStripeSubscriptionPayment')
            ->once()
            ->andReturn(['subscription_id' => 'sub_stripe_123']);
        $subscription->expects('update')->with(['payment_subscription_id' => 'sub_stripe_123'])->once();
        $this->subscriptionRepository->expects('find')->with(42)->andReturn($subscription);

        $result = $this->service->createSubscription(
            memberId: 1,
            planId: 5,
            paymentMethodId: 'pm_test',
            siteId: 1,
            pricingId: 123,
            offerType: 'digital',
        );

        $this->assertTrue($result['success']);
    }

    private function makeSubscription(): Subscription&MockInterface
    {
        $sub = Mockery::mock(Subscription::class)->makePartial();
        $sub->id = 42;
        $sub->payment_subscription_id = null;
        $sub->plan = Mockery::mock(SubscriptionPlan::class)->makePartial();
        return $sub;
    }

    // ─── Address resolution ───────────────────────────────────────────────────

    public function test_cart_is_cleared_even_when_checkout_throws(): void
    {
        $member = $this->makeMember(siteId: 1);
        $plan = $this->makePlan(siteId: 1, isActive: true);

        $plan->shouldReceive('getDeliveryOptions')
            ->once()
            ->andReturn(['print']);

        $this->memberRepository->expects('find')->andReturn($member);
        $this->planRepository->expects('find')->andReturn($plan);
        $this->subscriptionRepository->expects('hasActiveSubscriptionToPlan')->andReturnFalse();

        $this->memberAuth->expects('login')->once();
        $this->cartService->expects('addSubscriptionToCart')->once();
        $this->cartService->expects('clear')->once(); // must still be called

        $this->checkoutService->expects('processCheckout')
            ->andThrow(new CheckoutException('Stripe error'));

        $this->expectException(CheckoutException::class);

        $this->service->createSubscription(1, 5, 'pm_test', 1);
    }

    public function test_creates_address_when_no_id_but_address_data_provided(): void
    {
        $member = $this->makeMember(siteId: 1);
        $plan = $this->makePlan(siteId: 1, isActive: true);
        $plan->shouldReceive('getDeliveryOptions')
            ->once()
            ->andReturn(['print']);
        $subscription = $this->makeSubscription();
        $addressData = ['line1' => '1 Test St', 'city' => 'London'];

        $address = Mockery::mock(Address::class)->makePartial();
        $address->id = 99;

        $this->memberRepository->expects('find')->andReturn($member);
        $this->planRepository->expects('find')->andReturn($plan);
        $this->subscriptionRepository->expects('hasActiveSubscriptionToPlan')->andReturnFalse();

        $this->addressRepository->expects('createAddressForMember')
            ->with(1, $addressData, 1)
            ->andReturn($address);

        $this->memberAuth->expects('login')->once();
        $this->cartService->expects('addSubscriptionToCart')->once();
        $this->cartService->expects('clear')->once();

        $checkoutResult = ['subscription_id' => 42, 'order_id' => 7];
        $this->checkoutService->expects('processCheckout')
            ->withArgs(fn($data) => $data['saved_address'] === 99)
            ->andReturn($checkoutResult);

        $this->subscriptionRepository->expects('find')->with(42)->andReturn($subscription);
        $this->subscriptionPaymentService->expects('processStripeSubscriptionPayment')
            ->andReturn(['subscription_id' => 'sub_stripe_123']);
        $subscription->expects('update')->once();
        $this->subscriptionRepository->expects('find')->with(42)->andReturn($subscription);

        $result = $this->service->createSubscription(1, 5, 'pm_test', 1, null, $addressData);

        $this->assertTrue($result['success']);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function test_skips_address_creation_when_id_already_provided(): void
    {
        $member = $this->makeMember(siteId: 1);
        $plan = $this->makePlan(siteId: 1, isActive: true);
        $plan->shouldReceive('getDeliveryOptions')
            ->once()
            ->andReturn(['print']);
        $subscription = $this->makeSubscription();

        $this->memberRepository->expects('find')->andReturn($member);
        $this->planRepository->expects('find')->andReturn($plan);
        $this->subscriptionRepository->expects('hasActiveSubscriptionToPlan')->andReturnFalse();

        $this->addressRepository->shouldNotReceive('createAddressForMember');

        $this->memberAuth->expects('login')->once();
        $this->cartService->expects('addSubscriptionToCart')->once();
        $this->cartService->expects('clear')->once();

        $checkoutResult = ['subscription_id' => 42, 'order_id' => 7];
        $this->checkoutService->expects('processCheckout')
            ->withArgs(fn($data) => $data['saved_address'] === 77)
            ->andReturn($checkoutResult);

        $this->subscriptionRepository->expects('find')->with(42)->andReturn($subscription);
        $this->subscriptionPaymentService->expects('processStripeSubscriptionPayment')
            ->andReturn(['subscription_id' => 'sub_stripe_123']);
        $subscription->expects('update')->once();
        $this->subscriptionRepository->expects('find')->with(42)->andReturn($subscription);

        $result = $this->service->createSubscription(1, 5, 'pm_test', 1, deliveryAddressId: 77);

        $this->assertTrue($result['success']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->memberRepository = Mockery::mock(MemberRepository::class);
        $this->planRepository = Mockery::mock(SubscriptionPlanRepository::class);
        $this->subscriptionRepository = Mockery::mock(SubscriptionRepository::class);
        $this->cartService = Mockery::mock(CartService::class);
        $this->checkoutService = Mockery::mock(OneTimeSubscriptionCheckoutService::class);
        $this->memberAuth = Mockery::mock(MemberAuthWrapper::class);
        $this->subscriptionPaymentService = Mockery::mock(SubscriptionPaymentService::class);
        $this->addressRepository = Mockery::mock(AddressRepository::class);

        $this->service = new CrmSubscriptionCreationService(
            memberRepository: $this->memberRepository,
            planRepository: $this->planRepository,
            subscriptionRepository: $this->subscriptionRepository,
            cartService: $this->cartService,
            checkoutService: $this->checkoutService,
            memberAuth: $this->memberAuth,
            subscriptionPaymentService: $this->subscriptionPaymentService,
            addressRepository: $this->addressRepository,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
