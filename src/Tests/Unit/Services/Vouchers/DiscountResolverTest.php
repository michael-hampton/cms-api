<?php

namespace App\Tests\Unit\Services\Vouchers;

use App\Services\Vouchers\Contracts\DiscountProvider;
use App\Services\Vouchers\DiscountApplicationResult;
use App\Services\Vouchers\DiscountContext\DiscountContext;
use App\Services\Vouchers\DiscountProviderRegistry;
use App\Services\Vouchers\DiscountResolver;
use Mockery;
use PHPUnit\Framework\TestCase;

class DiscountResolverTest extends TestCase
{
    private DiscountProviderRegistry $discountProviderRegistry;

    public function setUp(): void
    {
        $this->discountProviderRegistry = new DiscountProviderRegistry();
        parent::setUp();
    }

    public function test_resolve_applies_providers_in_priority_order(): void
    {
        $provider1 = Mockery::mock(DiscountProvider::class);
        $provider1->shouldReceive('priority')->andReturn(20);
        $provider1->shouldReceive('supports')->andReturn(true);
        $provider1->shouldReceive('apply')->andReturn(
            new DiscountApplicationResult(
                discountAmountCents: 1000,
                affectedItemIds: [1],
                stackable: true,
                fundingSource: 'merchant',
                type: 'offer'
            )
        );

        $provider2 = Mockery::mock(DiscountProvider::class);
        $provider2->shouldReceive('priority')->andReturn(10);
        $provider2->shouldReceive('supports')->andReturn(true);
        $provider2->shouldReceive('apply')->andReturn(
            new DiscountApplicationResult(
                discountAmountCents: 500,
                affectedItemIds: [1],
                stackable: true,
                fundingSource: 'platform',
                type: 'voucher'
            )
        );

        $this->discountProviderRegistry->register($provider1);
        $this->discountProviderRegistry->register($provider2);

        $resolver = new DiscountResolver($this->discountProviderRegistry);

        $context = new DiscountContext(
            items: [['id' => 1, 'price' => 100, 'quantity' => 1]],
            baseSubtotalCents: 10000,
            currentSubtotalCents: 10000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: null
        );

        $result = $resolver->resolve($context);

        // Provider2 (priority 10) should apply first, then provider1 (priority 20)
        $this->assertEquals(8500, $result->finalSubtotalCents);
        $this->assertEquals(1500, $result->getTotalDiscountCents());
    }

    public function test_resolve_skips_unsupported_providers(): void
    {
        $provider = Mockery::mock(DiscountProvider::class);
        $provider->shouldReceive('priority')->andReturn(10);
        $provider->shouldReceive('supports')->andReturn(false);
        $provider->shouldNotReceive('apply');

        $this->discountProviderRegistry->register($provider);

        $resolver = new DiscountResolver($this->discountProviderRegistry);

        $context = new DiscountContext(
            items: [],
            baseSubtotalCents: 10000,
            currentSubtotalCents: 10000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: null
        );

        $result = $resolver->resolve($context);

        $this->assertEquals(10000, $result->finalSubtotalCents);
        $this->assertEquals(0, $result->getTotalDiscountCents());
    }

    public function test_resolve_handles_non_stackable_discounts(): void
    {
        $provider1 = Mockery::mock(DiscountProvider::class);
        $provider1->shouldReceive('priority')->andReturn(10);
        $provider1->shouldReceive('supports')->andReturn(true);
        $provider1->shouldReceive('apply')->andReturn(
            new DiscountApplicationResult(
                discountAmountCents: 1000,
                affectedItemIds: [1],
                stackable: true,
                fundingSource: 'merchant',
                type: 'offer'
            )
        );

        $provider2 = Mockery::mock(DiscountProvider::class);
        $provider2->shouldReceive('priority')->andReturn(20);
        $provider2->shouldReceive('supports')->andReturn(true);
        $provider2->shouldReceive('apply')->andReturn(
            new DiscountApplicationResult(
                discountAmountCents: 2000,
                affectedItemIds: [1],
                stackable: false,
                fundingSource: 'platform',
                type: 'voucher'
            )
        );

        $this->discountProviderRegistry->register($provider1);
        $this->discountProviderRegistry->register($provider2);

        $resolver = new DiscountResolver($this->discountProviderRegistry);

        $context = new DiscountContext(
            items: [['id' => 1, 'price' => 100, 'quantity' => 1]],
            baseSubtotalCents: 10000,
            currentSubtotalCents: 10000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: null
        );

        $result = $resolver->resolve($context);

        // Non-stackable discount should override previous
        $this->assertEquals(2000, $result->getTotalDiscountCents());
        $this->assertEquals(0, $result->offerDiscountCents);
        $this->assertEquals(2000, $result->voucherDiscountCents);
    }

    public function test_resolve_tracks_funding_sources(): void
    {
        $provider1 = Mockery::mock(DiscountProvider::class);
        $provider1->shouldReceive('priority')->andReturn(10);
        $provider1->shouldReceive('supports')->andReturn(true);
        $provider1->shouldReceive('apply')->andReturn(
            new DiscountApplicationResult(
                discountAmountCents: 1000,
                affectedItemIds: [1],
                stackable: true,
                fundingSource: 'merchant',
                type: 'offer'
            )
        );

        $provider2 = Mockery::mock(DiscountProvider::class);
        $provider2->shouldReceive('priority')->andReturn(20);
        $provider2->shouldReceive('supports')->andReturn(true);
        $provider2->shouldReceive('apply')->andReturn(
            new DiscountApplicationResult(
                discountAmountCents: 500,
                affectedItemIds: [1],
                stackable: true,
                fundingSource: 'platform',
                type: 'voucher'
            )
        );

        $this->discountProviderRegistry->register($provider1);
        $this->discountProviderRegistry->register($provider2);

        $resolver = new DiscountResolver($this->discountProviderRegistry);

        $context = new DiscountContext(
            items: [['id' => 1, 'price' => 100, 'quantity' => 1]],
            baseSubtotalCents: 10000,
            currentSubtotalCents: 10000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: null
        );

        $result = $resolver->resolve($context);

        $this->assertEquals(1000, $result->merchantFundedCents);
        $this->assertEquals(500, $result->platformFundedCents);
    }

    public function test_resolve_prevents_negative_subtotal(): void
    {
        $provider = Mockery::mock(DiscountProvider::class);
        $provider->shouldReceive('priority')->andReturn(10);
        $provider->shouldReceive('supports')->andReturn(true);
        $provider->shouldReceive('apply')->andReturn(
            new DiscountApplicationResult(
                discountAmountCents: 15000, // More than subtotal
                affectedItemIds: [1],
                stackable: true,
                fundingSource: 'platform',
                type: 'voucher'
            )
        );

        $this->discountProviderRegistry->register($provider);

        $resolver = new DiscountResolver($this->discountProviderRegistry);

        $context = new DiscountContext(
            items: [['id' => 1, 'price' => 100, 'quantity' => 1]],
            baseSubtotalCents: 10000,
            currentSubtotalCents: 10000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: null
        );

        $result = $resolver->resolve($context);

        // Should cap at 0, not go negative
        $this->assertEquals(0, $result->finalSubtotalCents);
    }

    public function test_resolve_stores_metadata(): void
    {
        $provider = Mockery::mock(DiscountProvider::class);
        $provider->shouldReceive('priority')->andReturn(10);
        $provider->shouldReceive('supports')->andReturn(true);
        $provider->shouldReceive('apply')->andReturn(
            new DiscountApplicationResult(
                discountAmountCents: 1000,
                affectedItemIds: [1],
                stackable: true,
                fundingSource: 'platform',
                type: 'voucher',
                metadata: ['voucher_code' => 'TEST123']
            )
        );

        $this->discountProviderRegistry->register($provider);

        $resolver = new DiscountResolver($this->discountProviderRegistry);

        $context = new DiscountContext(
            items: [['id' => 1, 'price' => 100, 'quantity' => 1]],
            baseSubtotalCents: 10000,
            currentSubtotalCents: 10000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: null
        );

        $result = $resolver->resolve($context);

        $this->assertArrayHasKey('voucher', $result->metadata);
        $this->assertEquals('TEST123', $result->metadata['voucher']['voucher_code']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        $this->discountProviderRegistry->clear();
        parent::tearDown();
    }
}