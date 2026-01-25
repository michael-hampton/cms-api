<?php

namespace App\Tests\Unit\Services\Product;

use App\Framework\Authorization\AuthenticationService;
use App\Models\ProductOffer;
use App\Repositories\Product\ProductOfferRepository;
use App\Services\Product\ProductOfferService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use Mockery;

class ProductOfferServiceTest extends FunctionalTestCase
{
    use CreatesTestData;

    private $repository;
    private ProductOfferService $service;
    private $authenticationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticationService = Mockery::mock(AuthenticationService::class);
        $this->repository = Mockery::mock(ProductOfferRepository::class);
        $this->service = new ProductOfferService($this->repository, $this->authenticationService);
    }

    public function testGetOffer(): void
    {
        $offer = new ProductOffer(['id' => 1, 'sale_price' => 79.99]);

        $this->repository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($offer);

        $result = $this->service->getOffer(1);

        $this->assertEquals($offer, $result);
    }

    public function testGetActiveOffersForProduct(): void
    {
        $offers = collect([new ProductOffer(['id' => 1])]);

        $this->repository->shouldReceive('getActiveOffersForProduct')
            ->once()
            ->with(1)
            ->andReturn($offers);

        $result = $this->service->getActiveOffersForProduct(1);

        $this->assertEquals($offers, $result);
    }

    public function testGetActiveOffersForCategory(): void
    {
        $offers = collect([new ProductOffer(['id' => 1])]);

        $this->repository->shouldReceive('getActiveOffersForCategory')
            ->once()
            ->with(1)
            ->andReturn($offers);

        $result = $this->service->getActiveOffersForCategory(1);

        $this->assertEquals($offers, $result);
    }

    public function testCreateOffer(): void
    {
        $data = [
            'product_id' => 1,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
        ];

        $offer = new ProductOffer($data);

        $this->repository->shouldReceive('create')
            ->once()
            ->with($data)
            ->andReturn($offer);

        $result = $this->service->createOffer($data);

        $this->assertEquals($offer, $result);
    }

    public function testCreateOfferThrowsExceptionForInvalidDates(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('End date must be after start date');

        $data = [
            'product_id' => 1,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'end_date' => date('Y-m-d H:i:s'),
        ];

        $this->service->createOffer($data);
    }

    public function testUpdateOffer(): void
    {
        $data = [
            'sale_price' => 69.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
        ];

        $this->repository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn(new ProductOffer(['id' => 1]));

        $offer = new ProductOffer($data);

        $this->repository->shouldReceive('update')
            ->once()
            ->with(1, $data)
            ->andReturn($offer);

        $result = $this->service->updateOffer(1, $data);

        $this->assertEquals($offer, $result);
    }

    public function testUpdateOfferThrowsExceptionForInvalidDates(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('End date must be after start date');

        $data = [
            'start_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'end_date' => date('Y-m-d H:i:s'),
        ];

        $this->service->updateOffer(1, $data);
    }

    public function testDeleteOffer(): void
    {
        $this->repository->shouldReceive('delete')
            ->once()
            ->with(1)
            ->andReturn(true);

        $result = $this->service->deleteOffer(1);

        $this->assertTrue($result);
    }

    public function testHasActiveOffer(): void
    {
        $this->repository->shouldReceive('hasActiveOffer')
            ->once()
            ->with(1)
            ->andReturn(true);

        $result = $this->service->hasActiveOffer(1);

        $this->assertTrue($result);
    }


    public function testCreateOfferWithPublishedStatus(): void
    {
        $user = $this->createUser();

        $this->actingAs($user);

        $this->authenticationService->shouldReceive('getUserId')
            ->once()
            ->andReturn($user->id);

        $product = $this->createProduct();

        $data = [
            'product_id' => $product->id,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'status' => 'published',
        ];

        $this->repository->shouldReceive('create')
            ->once()
            ->with(\Mockery::on(function ($arg) {
                return $arg['status'] === 'published'
                    && isset($arg['published_by'])
                    && isset($arg['published_at']);
            }))
            ->andReturn(new ProductOffer($data));

        $offer = $this->service->createOffer($data);

        $this->assertEquals('published', $offer->status);
    }

    public function testUpdateOfferToPublished(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $this->authenticationService->shouldReceive('getUserId')
            ->once()
            ->andReturn($user->id);

        $product = $this->createProduct();
        $existingOffer = new ProductOffer([
            'id' => 1,
            'product_id' => $product->id,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'status' => 'pending',
        ]);

        $this->repository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($existingOffer);

        $this->repository->shouldReceive('update')
            ->once()
            ->with(1, \Mockery::on(function ($arg) use ($user) {
                return $arg['status'] === 'published'
                    && $arg['published_by'] === $user->id
                    && isset($arg['published_at']);
            }))
            ->andReturn($existingOffer);

        $result = $this->service->updateOffer(1, ['status' => 'published']);
        $this->assertInstanceOf(ProductOffer::class, $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}