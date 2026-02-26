<?php

namespace App\Tests\Unit\Services\Adverts;

use App\Enums\Adverts\SuppressionReason;
use App\Repositories\Product\ProductRepository;
use App\Services\Adverts\DealVisibilityResolver;
use App\Services\Adverts\EligibilityRuleFactory;
use App\Services\Adverts\MemberSegmentChecker;
use App\Services\Adverts\RenderContext;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class DealVisibilityResolverTest extends FunctionalTestCase
{
    use CreatesTestData;

    private DealVisibilityResolver $resolver;
    private ProductRepository $repository;

    public function testResolvesActiveDealWithValidSale(): void
    {
        $product = $this->createProduct([
            'price' => 100.00,
            'sale_price' => 79.99,
            'is_active' => true,
        ]);

        $context = RenderContext::forNewsletter(1, null);
        $decision = $this->resolver->resolve($product, $context);

        $this->assertTrue($decision->shouldRender);
        $this->assertEquals($product->id, $decision->metadata['product_id']);
        $this->assertEquals($product->name, $decision->metadata['product_name']);
        $this->assertEquals(100.00, $decision->metadata['original_price']);
        $this->assertEquals(79.99, $decision->metadata['sale_price']);
        $this->assertEquals(20, $decision->metadata['discount_percentage']);
    }

    public function testHidesInactiveProduct(): void
    {
        $product = $this->createProduct([
            'price' => 100.00,
            'sale_price' => 79.99,
            'is_active' => false,
        ]);

        $context = RenderContext::forNewsletter(1, null);
        $decision = $this->resolver->resolve($product, $context);

        $this->assertFalse($decision->shouldRender);
        $this->assertEquals(SuppressionReason::DEAL_INACTIVE, $decision->reason);
    }

    public function testHidesProductWithNoSalePrice(): void
    {
        $product = $this->createProduct([
            'price' => 100.00,
            'sale_price' => 0,
            'is_active' => true,
        ]);

        $context = RenderContext::forNewsletter(1, null);
        $decision = $this->resolver->resolve($product, $context);

        $this->assertFalse($decision->shouldRender);
        $this->assertEquals(SuppressionReason::NO_ACTIVE_SALE, $decision->reason);
    }

    public function testHidesProductWithZeroSalePrice(): void
    {
        $product = $this->createProduct([
            'price' => 100.00,
            'sale_price' => 0,
            'is_active' => true,
        ]);

        $context = RenderContext::forNewsletter(1, null);
        $decision = $this->resolver->resolve($product, $context);

        $this->assertFalse($decision->shouldRender);
        $this->assertEquals(SuppressionReason::NO_ACTIVE_SALE, $decision->reason);
    }

    public function testHidesProductWhenSalePriceEqualsOrExceedsPrice(): void
    {
        $product1 = $this->createProduct([
            'price' => 100.00,
            'sale_price' => 100.00,
            'is_active' => true,
        ]);

        $context = RenderContext::forNewsletter(1, null);
        $decision1 = $this->resolver->resolve($product1, $context);

        $this->assertFalse($decision1->shouldRender);
        $this->assertEquals(SuppressionReason::NO_ACTIVE_SALE, $decision1->reason);

        $product2 = $this->createProduct([
            'price' => 100.00,
            'sale_price' => 110.00,
            'is_active' => true,
        ]);

        $decision2 = $this->resolver->resolve($product2, $context);

        $this->assertFalse($decision2->shouldRender);
        $this->assertEquals(SuppressionReason::NO_ACTIVE_SALE, $decision2->reason);
    }

    public function testResolveMultipleFiltersIneligible(): void
    {
        $activeDeal = $this->createProduct([
            'price' => 100.00,
            'sale_price' => 79.99,
            'is_active' => true,
        ]);

        $inactiveDeal = $this->createProduct([
            'price' => 100.00,
            'sale_price' => 79.99,
            'is_active' => false,
        ]);

        $noSaleDeal = $this->createProduct([
            'price' => 100.00,
            'sale_price' => 100.00,
            'is_active' => true,
        ]);

        $context = RenderContext::forNewsletter(1, null);
        $decisions = $this->resolver->resolveMultiple(
            [$activeDeal->id, $inactiveDeal->id, $noSaleDeal->id],
            $context
        );

        $this->assertCount(1, $decisions);
        $this->assertEquals($activeDeal->id, $decisions[0]['product']->id);
    }

    public function testCalculatesCorrectDiscountPercentage(): void
    {
        $product = $this->createProduct([
            'price' => 200.00,
            'sale_price' => 150.00,
            'is_active' => true,
        ]);

        $context = RenderContext::forNewsletter(1, null);
        $decision = $this->resolver->resolve($product, $context);

        $this->assertTrue($decision->shouldRender);
        $this->assertEquals(25, $decision->metadata['discount_percentage']);
    }

    public function testHandlesSmallDiscounts(): void
    {
        $product = $this->createProduct([
            'price' => 100.00,
            'sale_price' => 99.50,
            'is_active' => true,
        ]);

        $context = RenderContext::forNewsletter(1, null);
        $decision = $this->resolver->resolve($product, $context);

        $this->assertTrue($decision->shouldRender);
        $this->assertEquals(1, $decision->metadata['discount_percentage']); // Rounded
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(ProductRepository::class);
        $ruleFactory = new EligibilityRuleFactory(new MemberSegmentChecker());
        $this->resolver = new DealVisibilityResolver($this->repository, $ruleFactory);
    }
}