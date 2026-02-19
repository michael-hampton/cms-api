<?php

namespace App\Tests\Unit\Services\Adverts\Boost;

use App\Contracts\Boost\BoostableInterface;
use App\Enums\Boost\BoostableType;
use App\Models\Merchant;
use App\Repositories\Adverts\Boost\BoostRepository;
use App\Repositories\Offers\ProductOfferRepository;
use App\Repositories\Product\MerchantRepository;
use App\Repositories\Product\ProductRepository;
use App\Services\Adverts\Boost\BoostEligibilityService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;

class BoostEligibilityServiceTest extends FunctionalTestCase
{
    private MockInterface $boostRepository;
    private MockInterface $productRepository;
    private MockInterface $offerRepository;
    private BoostEligibilityService $service;
    private MerchantRepository $merchantRepository;

    public function test_passes_for_valid_product(): void
    {
        $target = $this->makeTarget(eligible: true, inStock: true);

        $this->boostRepository
            ->shouldReceive('hasActiveBoost')
            ->once()
            ->andReturn(false);

        $this->merchantRepository->shouldReceive('find')->with(99)->andReturn($this->makeActiveMerchant());

        // Should not throw
        $this->service->assertEligible($target, BoostableType::Product->value, 99);
        $this->assertTrue(true);
    }

    private function makeTarget(bool $eligible = true, bool $inStock = true): BoostableInterface
    {
        $target = Mockery::mock(BoostableInterface::class);
        $target->shouldReceive('getBoostableId')->andReturn(1);
        $target->shouldReceive('getBoostableType')->andReturn(BoostableType::Product->value);
        $target->shouldReceive('isEligibleForBoost')->andReturn($eligible);
        $target->shouldReceive('isInStock')->andReturn($inStock);
        return $target;
    }

    private function makeActiveMerchant(): Merchant
    {
        $merchant = Mockery::mock(Merchant::class)->makePartial();
        $merchant->is_active = true;
        return $merchant;
    }

    public function test_fails_when_target_inactive(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not active or eligible');

        $target = $this->makeTarget(eligible: false);
        $this->service->assertEligible($target, BoostableType::Product->value, 99);
    }

    public function test_fails_when_target_out_of_stock(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('out of stock');

        $target = $this->makeTarget(eligible: true, inStock: false);
        $this->service->assertEligible($target, BoostableType::Product->value, 99);
    }

    public function test_fails_when_merchant_inactive(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Merchant is not active or compliant');

        $target = $this->makeTarget();
        $merchant = Mockery::mock(Merchant::class)->makePartial();
        $merchant->is_active = false;

        $this->merchantRepository->shouldReceive('find')->with(99)->andReturn($merchant);

        $this->service->assertEligible($target, BoostableType::Product->value, 99);
    }

    public function test_fails_when_merchant_not_found(): void
    {
        $this->expectException(\RuntimeException::class);

        $target = $this->makeTarget();
        $this->merchantRepository->shouldReceive('find')->with(99)->andReturn(null);

        $this->service->assertEligible($target, BoostableType::Product->value, 99);
    }

    public function test_fails_when_active_boost_exists(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('active boost already exists');

        $target = $this->makeTarget();
        $this->merchantRepository->shouldReceive('find')->with(99)->andReturn($this->makeActiveMerchant());

        $this->boostRepository
            ->shouldReceive('hasActiveBoost')
            ->andReturn(true);

        $this->service->assertEligible($target, BoostableType::Product->value, 99);
    }

    public function test_fails_for_expired_offer(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Boost target is not active or eligible.');

        $target = Mockery::mock(BoostableInterface::class);
        $target->shouldReceive('getBoostableId')->andReturn(1);
        $target->shouldReceive('isEligibleForBoost')->andReturn(false);
        $target->shouldReceive('isInStock')->andReturn(true);

        $this->merchantRepository->shouldReceive('find')->with(99)->andReturn($this->makeActiveMerchant());
        $this->boostRepository->shouldReceive('hasActiveBoost')->andReturn(false);

        $this->service->assertEligible($target, BoostableType::Offer->value, 99);
    }

    public function test_passes_for_valid_offer(): void
    {
        $target = Mockery::mock(BoostableInterface::class);
        $target->shouldReceive('getBoostableId')->andReturn(1);
        $target->shouldReceive('isEligibleForBoost')->andReturn(true);
        $target->shouldReceive('isInStock')->andReturn(true);

        $this->merchantRepository->shouldReceive('find')->with(99)->andReturn($this->makeActiveMerchant());
        $this->boostRepository->shouldReceive('hasActiveBoost')->andReturn(false);

        $this->service->assertEligible($target, BoostableType::Offer->value, 99);
        $this->assertTrue(true);
    }

    protected function setUp(): void
    {
        $this->boostRepository = Mockery::mock(BoostRepository::class);
        $this->productRepository = Mockery::mock(ProductRepository::class);
        $this->offerRepository = Mockery::mock(ProductOfferRepository::class);
        $this->merchantRepository = Mockery::mock(MerchantRepository::class);

        $this->service = new BoostEligibilityService(
            $this->boostRepository,
            $this->productRepository,
            $this->offerRepository,
            $this->merchantRepository
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }
}