<?php

namespace App\Tests\Unit\Services\Offers;

use App\Framework\Authorization\AuthenticationService;
use App\Models\ProductOffer;
use App\Models\ProductOfferBundle;
use App\Models\ProductOfferBundleItem;
use App\Repositories\Offers\ProductOfferBundleRepository;
use App\Repositories\Offers\ProductOfferRepository;
use App\Services\Offers\ProductOfferBundleService;
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
    private ProductOfferRepository $offerRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticationService = Mockery::mock(AuthenticationService::class);
        $this->repository = Mockery::mock(ProductOfferBundleRepository::class);
        $this->offerRepository = Mockery::mock(ProductOfferRepository::class);
        $this->service = new ProductOfferBundleService(
            $this->repository,
            $this->authenticationService,
            $this->offerRepository
        );
    }

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

        $this->offerRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn(new ProductOffer(['sale_price' => 110]));

        $this->offerRepository->shouldReceive('find')
            ->once()
            ->with(2)
            ->andReturn(new ProductOffer(['sale_price' => 110]));

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

        $this->offerRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn(new ProductOffer(['sale_price' => 110]));

        $this->offerRepository->shouldReceive('find')
            ->once()
            ->with(2)
            ->andReturn(new ProductOffer(['sale_price' => 110]));

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

        $this->offerRepository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn(new ProductOffer(['sale_price' => 110]));

        $this->offerRepository->shouldReceive('find')
            ->once()
            ->with(2)
            ->andReturn(new ProductOffer(['sale_price' => 110]));

        $this->repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($arg) {
                return $arg['discount_percentage'] == 32
                    && $arg['bundle_price'] == 150
                    && $arg['total_price'] == 220;
            }))
            ->andReturn(new ProductOfferBundle($data));

        $result = $this->service->createBundle($data);

        $this->assertNotNull($result);
    }

    public function testCreateBundleValidatesDates(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('End date must be after start date');

        $data = [
            'name' => 'Test Bundle',
            'slug' => 'test-bundle',
            'total_price' => 100,
            'bundle_price' => 80,
            'start_date' => '2024-02-01 00:00:00',
            'end_date' => '2024-01-01 00:00:00', // Before start date
            'items' => [
                ['product_offer_id' => 1, 'quantity' => 1],
                ['product_offer_id' => 2, 'quantity' => 1],
            ]
        ];

        $this->service->createBundle($data);
    }

    public function testCreateBundleRequiresMinimumTwoItems(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Bundle must contain at least two items');

        $data = [
            'name' => 'Test Bundle',
            'slug' => 'test-bundle',
            'total_price' => 100,
            'bundle_price' => 80,
            'start_date' => '2024-01-01 00:00:00',
            'end_date' => '2024-02-01 00:00:00',
            'items' => [
                ['product_offer_id' => 1, 'quantity' => 1],
            ]
        ];

        $this->service->createBundle($data);
    }

    public function testCreateBundleCalculatesDiscount(): void
    {
        $data = [
            'name' => 'Test Bundle',
            'slug' => 'test-bundle',
            'total_price' => 90,
            'bundle_price' => 70,
            'start_date' => '2024-01-01 00:00:00',
            'end_date' => '2024-02-01 00:00:00',
            'items' => [
                ['product_offer_id' => 1, 'quantity' => 1],
                ['product_offer_id' => 1, 'quantity' => 1],
            ],
            'discount_percentage' => 22,
        ];

        $this->offerRepository->shouldReceive('find')
            ->twice()
            ->with(1)
            ->andReturn(new ProductOffer(['sale_price' => 45]));

        $this->repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($arg) {
                return $arg['discount_percentage'] == 22
                    && $arg['bundle_price'] == 70
                    && $arg['total_price'] == 90;
            }))
            ->andReturn(new ProductOfferBundle($data));

        $bundle = $this->service->createBundle($data);

        $this->assertEquals(22, $bundle->discount_percentage); // (90-70)/90 * 100 = 22%
    }

    public function testPublishBundleRequiresPendingStatus(): void
    {
        $userId = 1;

        $this->repository->shouldReceive('publish')
            ->once()
            ->with(1, $userId)
            ->andReturnNull();

        $result = $this->service->publish(1, $userId);

        $this->assertNull($result);
    }

    public function testRejectBundleRequiresReason(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Rejection reason is required');


        $this->service->reject(1, 1, '');
    }

    public function testCreateBundleWithProductsFromSameMerchantAllowed(): void
    {
        $merchant = $this->createMerchant();
        $product1 = $this->createProduct(['merchant_id' => $merchant->id, 'price' => 100.00]);
        $product2 = $this->createProduct(['merchant_id' => $merchant->id, 'price' => 100.00]);

        $data = [
            'name' => 'Test Bundle',
            'slug' => 'test-bundle',
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'items' => [
                ['product_id' => $product1->id, 'quantity' => 1],
                ['product_id' => $product2->id, 'quantity' => 1],
            ],
        ];

        $this->repository->shouldReceive('create')
            ->once()
            ->andReturn(new ProductOfferBundle($data));

        $bundle = $this->service->createBundle($data);

        $this->assertEquals('Test Bundle', $bundle->name);
    }

//    public function testCreateBundleWithProductsFromDifferentMerchantsDenied(): void
//    {
//        $this->service->allowMultiMerchant = false;
//
//        $merchant1 = $this->createMerchant();
//        $merchant2 = $this->createMerchant();
//        $product1 = $this->createProduct(['merchant_id' => $merchant1->id, 'price' => 100.00]);
//        $product2 = $this->createProduct(['merchant_id' => $merchant2->id, 'price' => 100.00]);
//
//        $this->expectException(Exception::class);
//        $this->expectExceptionMessage('Multi-merchant bundles are not allowed');
//
//        $data = [
//            'name' => 'Test Bundle',
//            'slug' => 'test-bundle',
//            'bundle_price' => 150.00,
//            'start_date' => date('Y-m-d H:i:s'),
//            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
//            'items' => [
//                ['product_id' => $product1->id, 'quantity' => 1],
//                ['product_id' => $product2->id, 'quantity' => 1],
//            ],
//        ];
//
//        $this->service->createBundle($data);
//
//        $this->service->allowMultiMerchant = true;
//    }

    public function testCreateBundleWithProductsFromDifferentMerchantsAllowed(): void
    {
        $merchant1 = $this->createMerchant();
        $merchant2 = $this->createMerchant();
        $product1 = $this->createProduct(['merchant_id' => $merchant1->id, 'price' => 100.00]);
        $product2 = $this->createProduct(['merchant_id' => $merchant2->id, 'price' => 100.00]);

        $data = [
            'name' => 'Test Bundle',
            'slug' => 'test-bundle',
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'items' => [
                ['product_id' => $product1->id, 'quantity' => 1],
                ['product_id' => $product2->id, 'quantity' => 1],
            ],
        ];

        $this->repository->shouldReceive('create')
            ->once()
            ->andReturn(new ProductOfferBundle($data));

        $bundle = $this->service->createBundle($data);

        $this->assertEquals('Test Bundle', $bundle->name);
    }

    public function testCalculateBundlePricingWithProducts(): void
    {
        $product1 = $this->createProduct(['price' => 100.00]);
        $product2 = $this->createProduct(['price' => 50.00]);

        $data = [
            'name' => 'Test Bundle',
            'slug' => 'test-bundle',
            'bundle_price' => 120.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'items' => [
                ['product_id' => $product1->id, 'quantity' => 1],
                ['product_id' => $product2->id, 'quantity' => 1],
            ],
        ];

        $this->repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($arg) {
                return $arg['total_price'] === 150.00
                    && $arg['discount_percentage'] === 20; // (150-120)/150 * 100
            }))
            ->andReturn(new ProductOfferBundle($data));

        $bundle = $this->service->createBundle($data);

        $this->assertNotNull($bundle);
    }

    public function testGetActiveBundlesForWeb(): void
    {
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();
        $offer1 = $this->createProductOffer($product1->id);
        $offer2 = $this->createProductOffer($product2->id);

        $bundle = ProductOfferBundle::create([
            'name' => 'Test Bundle',
            'slug' => 'test-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'discount_percentage' => 25,
            'start_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'is_active' => true,
            'status' => 'published',
        ]);

        ProductOfferBundleItem::create([
            'bundle_id' => $bundle->id,
            'product_offer_id' => $offer1->id,
            'quantity' => 1,
        ]);

        ProductOfferBundleItem::create([
            'bundle_id' => $bundle->id,
            'product_offer_id' => $offer2->id,
            'quantity' => 1,
        ]);

        $this->repository->shouldReceive('getActiveBundles')
            ->once()
            ->andReturn(collect([$bundle->load(['items.productOffer.product'])]));

        $result = $this->service->getActiveBundlesForWeb(10);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals($bundle->id, $result[0]['bundle_id']);
        $this->assertEquals($bundle->name, $result[0]['name']);
        $this->assertEquals(150.00, $result[0]['bundle_price']);
    }

    public function testGetActiveBundlesForWebLimitsResults(): void
    {
        $bundles = collect();

        for ($i = 0; $i < 15; $i++) {
            $product1 = $this->createProduct();
            $product2 = $this->createProduct();
            $offer1 = $this->createProductOffer($product1->id);
            $offer2 = $this->createProductOffer($product2->id);

            $bundle = ProductOfferBundle::create([
                'name' => "Bundle $i",
                'slug' => "bundle-$i",
                'total_price' => 200.00,
                'bundle_price' => 150.00,
                'discount_percentage' => 25,
                'start_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
                'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
                'is_active' => true,
                'status' => 'published',
            ]);

            ProductOfferBundleItem::create([
                'bundle_id' => $bundle->id,
                'product_offer_id' => $offer1->id,
                'quantity' => 1,
            ]);

            $bundles->push($bundle->load(['items.productOffer.product']));
        }

        $this->repository->shouldReceive('getActiveBundles')
            ->once()
            ->andReturn($bundles);

        $result = $this->service->getActiveBundlesForWeb(5);

        $this->assertCount(5, $result);
    }


    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}