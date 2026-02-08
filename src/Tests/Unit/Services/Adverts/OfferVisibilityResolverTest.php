<?php

namespace App\Tests\Unit\Services\Adverts;

use App\Enums\Adverts\SuppressionReason;
use App\Models\ProductOffer;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Repositories\Offers\ProductOfferRepository;
use App\Services\Adverts\EligibilityRuleFactory;
use App\Services\Adverts\MemberSegmentChecker;
use App\Services\Adverts\OfferVisibilityResolver;
use App\Services\Adverts\RenderContext;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class OfferVisibilityResolverTest extends FunctionalTestCase
{
    use CreatesTestData;

    private OfferVisibilityResolver $resolver;
    private ProductOfferRepository $repository;

    public function testResolvesActiveOffer(): void
    {
        $product = $this->createProduct();
        $offer = ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 79.99,
            'original_price' => 99.99,
            'start_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
        ]);

        $context = RenderContext::forNewsletter(1, null);
        $decision = $this->resolver->resolve($offer, $context);

        $this->assertTrue($decision->shouldRender);
        $this->assertEquals($offer->id, $decision->metadata['offer_id']);
    }

    public function testHidesInactiveOffer(): void
    {
        $product = $this->createProduct();
        $offer = ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => false,
        ]);

        $context = RenderContext::forNewsletter(1, null);
        $decision = $this->resolver->resolve($offer, $context);

        $this->assertFalse($decision->shouldRender);
        $this->assertEquals(SuppressionReason::OFFER_INACTIVE, $decision->reason);
    }

    public function testHidesExpiredOffer(): void
    {
        $product = $this->createProduct();
        $offer = ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'end_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'is_active' => true,
        ]);

        $context = RenderContext::forNewsletter(1, null);
        $decision = $this->resolver->resolve($offer, $context);

        $this->assertFalse($decision->shouldRender);
        $this->assertEquals(SuppressionReason::OFFER_EXPIRED, $decision->reason);
    }

    public function testHidesOfferNotYetStarted(): void
    {
        $product = $this->createProduct();
        $offer = ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'end_date' => date('Y-m-d H:i:s', strtotime('+2 days')),
            'is_active' => true,
        ]);

        $context = RenderContext::forNewsletter(1, null);
        $decision = $this->resolver->resolve($offer, $context);

        $this->assertFalse($decision->shouldRender);
        $this->assertEquals(SuppressionReason::OFFER_NOT_STARTED, $decision->reason);
    }

    public function testRespectsRequirePaidEligibility(): void
    {
        $product = $this->createProduct();
        $offer = ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
            'eligibility_rules' => ['require_paid' => true],
        ]);

        // Free member
        $freeMember = $this->createMember(['plan' => 'free']);
        $context = RenderContext::forNewsletter(1, $freeMember);
        $decision = $this->resolver->resolve($offer, $context);

        $this->assertFalse($decision->shouldRender);
        $this->assertEquals(SuppressionReason::REQUIRES_PAID_MEMBERSHIP, $decision->reason);
    }

    public function testAllowsOfferForPaidMember(): void
    {
        $product = $this->createProduct();

        $plan = SubscriptionPlan::create([
            'site_id' => $this->siteId,
            'name' => 'Premium Plan',
            'slug' => 'premium',
            'price' => 99.99,
            'currency' => 'USD',
            'billing_period' => 'yearly',
            'plan_type' => 'recurring',
            'is_active' => true,
        ]);

        $paidMember = $this->createMember();

        Subscription::create([
            'member_id' => $paidMember->id,
            'site_id' => $this->siteId,
            'plan_id' => $plan->id,
            'status' => 'active',
            'type' => 'paid', // This is what makes isPaid() return true
            'start_date' => date('Y-m-d H:i:s'),
            'plan_name' => 'Premium Plan'
        ]);

        $offer = ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
            'eligibility_rules' => ['require_paid' => true],
        ]);

        $context = RenderContext::forNewsletter(1, $paidMember->fresh());
        $decision = $this->resolver->resolve($offer, $context);

        $this->assertTrue($decision->shouldRender);
    }

    public function testResolveMultipleFiltersIneligible(): void
    {
        $product = $this->createProduct();

        $activeOffer = ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
        ]);

        $inactiveOffer = ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 69.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => false,
        ]);

        $context = RenderContext::forNewsletter(1, null);
        $decisions = $this->resolver->resolveMultiple(
            [$activeOffer->id, $inactiveOffer->id],
            $context
        );

        $this->assertCount(1, $decisions);
        $this->assertEquals($activeOffer->id, $decisions[0]['offer']->id);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ProductOfferRepository();
        $ruleFactory = new EligibilityRuleFactory(new MemberSegmentChecker());
        $this->resolver = new OfferVisibilityResolver($this->repository, $ruleFactory);
    }
}