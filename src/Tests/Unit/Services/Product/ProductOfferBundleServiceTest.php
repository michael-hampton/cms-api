<?php

namespace App\Tests\Unit\Services\Product;

use App\Framework\Authorization\AuthenticationService;
use App\Models\ProductOfferBundle;
use App\Repositories\Product\ProductOfferBundleRepository;
use App\Services\Product\ProductOfferBundleService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use Exception;
use Mockery;

class ProductOfferBundleServiceTest extends FunctionalTestCase
{
    use CreatesTestData;

    private $repository;
    private ProductOfferBundleService $service;
    private $authenticationService;

    public function testGetBundle(): void
    {
        $bundle = new ProductOfferBundle(['id' => 1, 'name' => 'Test Bundle']);

        $this->repository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($bundle);

        $result = $this->service->getBundle(1);

        $this->assertEquals($bundle, $result);
    }

    public function testGetActiveBundles(): void
    {
        $bundles = collect([new ProductOfferBundle(['id' => 1])]);

        $this->repository->shouldReceive('getActiveBundles')
            ->once()
            ->andReturn($bundles);

        $result = $this->service->getActiveBundles();

        $this->assertEquals($bundles, $result);
    }

    public function testCreateBundle(): void
    {
        $data = [
            'name' => 'New Bundle',
            'slug' => 'new-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'items' => [
                ['product_offer_id' => 1, 'quantity' => 1],
                ['product_offer_id' => 2, 'quantity' => 1],
            ],
        ];

        $bundle = new ProductOfferBundle($data);

        $this->repository->shouldReceive('create')
            ->once()
            ->andReturn($bundle);

        $result = $this->service->createBundle($data);

        $this->assertEquals($bundle, $result);
    }

    public function testCreateBundleThrowsExceptionForInvalidDates(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('End date must be after start date');

        $data = [
            'name' => 'New Bundle',
            'slug' => 'new-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'end_date' => date('Y-m-d H:i:s'),
            'items' => [
                ['product_offer_id' => 1, 'quantity' => 1],
                ['product_offer_id' => 2, 'quantity' => 1],
            ],
        ];

        $this->service->createBundle($data);
    }

    public function testCreateBundleThrowsExceptionForNoItems(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Bundle must contain at least one item');

        $data = [
            'name' => 'New Bundle',
            'slug' => 'new-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'items' => [],
        ];

        $this->service->createBundle($data);
    }

    public function testCreateBundleThrowsExceptionForSingleItem(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Bundle must contain at least two items');

        $data = [
            'name' => 'New Bundle',
            'slug' => 'new-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'items' => [
                ['product_offer_id' => 1, 'quantity' => 1],
            ],
        ];

        $this->service->createBundle($data);
    }

    public function testUpdateBundle(): void
    {
        $data = [
            'name' => 'Updated Bundle',
            'bundle_price' => 140.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
        ];

        $this->repository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn(new ProductOfferBundle(['id' => 1]));

        $bundle = new ProductOfferBundle($data);

        $this->repository->shouldReceive('update')
            ->once()
            ->with(1, Mockery::any())
            ->andReturn($bundle);

        $result = $this->service->updateBundle(1, $data);

        $this->assertEquals($bundle, $result);
    }

    public function testUpdateBundleThrowsExceptionForInvalidDates(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('End date must be after start date');

        $data = [
            'start_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'end_date' => date('Y-m-d H:i:s'),
        ];

        $this->service->updateBundle(1, $data);
    }

    public function testDeleteBundle(): void
    {
        $this->repository->shouldReceive('delete')
            ->once()
            ->with(1)
            ->andReturn(true);

        $result = $this->service->deleteBundle(1);

        $this->assertTrue($result);
    }

    public function testPublish(): void
    {
        $bundle = new ProductOfferBundle([
            'id' => 1,
            'status' => 'published',
        ]);

        $this->repository->shouldReceive('publish')
            ->once()
            ->with(1, 10)
            ->andReturn($bundle);

        $result = $this->service->publish(1, 10);

        $this->assertEquals($bundle, $result);
    }

    public function testReject(): void
    {
        $bundle = new ProductOfferBundle([
            'id' => 1,
            'status' => 'rejected',
        ]);

        $this->repository->shouldReceive('reject')
            ->once()
            ->with(1, 10, 'Test reason')
            ->andReturn($bundle);

        $result = $this->service->reject(1, 10, 'Test reason');

        $this->assertEquals($bundle, $result);
    }

    public function testRejectThrowsExceptionForEmptyReason(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Rejection reason is required');

        $this->service->reject(1, 10, '');
    }

    public function testGetByStatus(): void
    {
        $bundles = collect([new ProductOfferBundle(['id' => 1])]);

        $this->repository->shouldReceive('getByStatus')
            ->once()
            ->with('published')
            ->andReturn($bundles);

        $result = $this->service->getByStatus('published');

        $this->assertEquals($bundles, $result);
    }

    public function testCreateBundleWithPublishedStatus(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $this->authenticationService->shouldReceive('getUserId')
            ->once()
            ->andReturn($user->id);

        $data = [
            'name' => 'New Bundle',
            'slug' => 'new-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'status' => 'published',
            'items' => [
                ['product_offer_id' => 1, 'quantity' => 1],
                ['product_offer_id' => 2, 'quantity' => 1],
            ],
        ];

        $this->repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($arg) {
                return $arg['status'] === 'published'
                    && isset($arg['published_by'])
                    && isset($arg['published_at']);
            }))
            ->andReturn(new ProductOfferBundle($data));

        $bundle = $this->service->createBundle($data);

        $this->assertEquals('published', $bundle->status);
    }

    public function testUpdateBundleToPublished(): void
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $this->authenticationService->shouldReceive('getUserId')
            ->once()
            ->andReturn($user->id);

        $existingBundle = new ProductOfferBundle([
            'id' => 1,
            'name' => 'Test Bundle',
            'status' => 'pending',
        ]);

        $this->repository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($existingBundle);

        $this->repository->shouldReceive('update')
            ->once()
            ->with(1, Mockery::on(function ($arg) use ($user) {
                return $arg['status'] === 'published'
                    && $arg['published_by'] === $user->id
                    && isset($arg['published_at']);
            }))
            ->andReturn($existingBundle);

        $result = $this->service->updateBundle(1, ['status' => 'published']);
        $this->assertInstanceOf(ProductOfferBundle::class, $result);
    }

    public function testCalculateBundlePricing(): void
    {
        $data = [
            'name' => 'New Bundle',
            'slug' => 'new-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'items' => [
                ['product_offer_id' => 1, 'quantity' => 1],
                ['product_offer_id' => 2, 'quantity' => 1],
            ],
        ];

        $this->repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($arg) {
                return $arg['discount_percentage'] === 25;
            }))
            ->andReturn(new ProductOfferBundle($data));

        $result = $this->service->createBundle($data);

        $this->assertNotNull($result);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticationService = Mockery::mock(AuthenticationService::class);
        $this->repository = Mockery::mock(ProductOfferBundleRepository::class);
        $this->service = new ProductOfferBundleService($this->repository, $this->authenticationService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}