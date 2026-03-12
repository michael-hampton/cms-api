<?php

namespace App\Tests\Unit\Services\Offers;

use App\Enums\Offers\BundleStatus;
use App\Exceptions\BundleValidationException;
use App\Framework\Authorization\AuthenticationService;
use App\Framework\Database\Database;
use App\Models\Product;
use App\Models\ProductOfferBundle;
use App\Models\ProductOfferBundleItem;
use App\Repositories\Offers\ProductOfferBundleRepository;
use App\Repositories\Offers\ProductOfferRepository;
use App\Repositories\Product\ProductRepository;
use App\Services\Offers\ProductOfferBundleService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use Exception;
use Mockery;

class ProductOfferBundleServiceTest extends FunctionalTestCase
{
    use CreatesTestData;

    private ProductOfferBundleService $service;
    private ProductOfferBundleRepository $repository;
    private AuthenticationService $authenticationService;
    private ProductOfferRepository $offerRepository;
    private Database $databaseMock;

    private ProductRepository $productRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(ProductOfferBundleRepository::class);
        $this->authenticationService = Mockery::mock(AuthenticationService::class);
        $this->offerRepository = Mockery::mock(ProductOfferRepository::class);
        $this->databaseMock = Mockery::mock(Database::class);
        $this->productRepository = Mockery::mock(ProductRepository::class);

        $this->service = new ProductOfferBundleService(
            $this->repository,
            $this->authenticationService,
            $this->offerRepository,
            $this->productRepository,
            $this->databaseMock
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

    public function testItCreatesBundleWithValidData()
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->setProductSearchExpectations();

        $data = [
            'name' => 'Test Bundle',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'bundle_price' => 100,
            'items' => [
                ['product_id' => 1, 'quantity' => 1],
                ['product_id' => 2, 'quantity' => 1],
            ],
        ];

        // Mock Product model for merchant validation
        $product1 = Mockery::mock(Product::class);
        $product1->shouldReceive('with')->andReturnSelf();
        $product1->shouldReceive('whereIn')->andReturnSelf();
        $product1->shouldReceive('get')->andReturn(collect([
            (object)['id' => 1, 'price' => 50, 'merchants' => collect([(object)['id' => 1]])],
            (object)['id' => 2, 'price' => 75, 'merchants' => collect([(object)['id' => 1]])],
        ])->keyBy('id'));

        $this->authenticationService
            ->shouldReceive('getUserId')
            ->andReturn(null);

        $expectedBundle = new ProductOfferBundle();
        $this->repository
            ->shouldReceive('create')
            ->once()
            ->andReturn($expectedBundle);

        $result = $this->service->createBundle($data);

        $this->assertInstanceOf(ProductOfferBundle::class, $result);
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

    public function testItThrowsExceptionForInvalidDateFormat()
    {
        $this->expectException(BundleValidationException::class);
        $this->expectExceptionMessage('Invalid date format');

        $data = [
            'start_date' => 'invalid-date',
            'end_date' => '2026-12-31',
            'items' => [
                ['product_id' => 1, 'quantity' => 1],
                ['product_id' => 2, 'quantity' => 1],
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

    public function testItUpdatesBundle()
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $data = [
            'name' => 'Updated Bundle',
            'bundle_price' => 150,
        ];

        $currentBundle = Mockery::mock(ProductOfferBundle::class)->makePartial();
        $currentBundle->status = BundleStatus::DRAFT->value;

        $this->repository
            ->shouldReceive('find')
            ->with(1)
            ->andReturn($currentBundle);

        $this->repository
            ->shouldReceive('update')
            ->with(1, Mockery::on(function ($arg) use ($data) {
                return $arg['name'] === 'Updated Bundle'
                    && $arg['bundle_price'] === 150;
            }))
            ->andReturn($currentBundle);

        $result = $this->service->updateBundle(1, $data);

        $this->assertInstanceOf(ProductOfferBundle::class, $result);
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

    public function testItThrowsExceptionForEmptyItems()
    {
        $this->expectException(BundleValidationException::class);
        $this->expectExceptionMessage('Bundle must contain at least one item');

        $data = [
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'items' => [],
        ];

        $this->service->createBundle($data);
    }

    public function testItPreventsItemWithBothProductAndOffer()
    {
        $this->expectException(BundleValidationException::class);
        $this->expectExceptionMessage('Bundle item cannot have both product and product offer');

        $data = [
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'items' => [
                ['product_id' => 1, 'product_offer_id' => 1, 'quantity' => 1],
                ['product_id' => 2, 'quantity' => 1],
            ],
        ];

        $this->service->createBundle($data);
    }

    public function testItRequiresEitherProductOrOffer()
    {
        $this->expectException(BundleValidationException::class);
        $this->expectExceptionMessage('Bundle item must have either product or product offer');

        $data = [
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'items' => [
                ['quantity' => 1],
                ['product_id' => 2, 'quantity' => 1],
            ],
        ];

        $this->service->createBundle($data);
    }

    public function testItOnlySetsStatusTimestampsOnStatusChange()
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $currentBundle = Mockery::mock(ProductOfferBundle::class)->makePartial();
        $currentBundle->status = BundleStatus::PUBLISHED->value;
        $currentBundle->published_at = '2026-01-01 00:00:00';

        $data = [
            'name' => 'Updated Name',
            'status' => BundleStatus::PUBLISHED->value,
        ];

        $this->repository
            ->shouldReceive('find')
            ->with(1)
            ->andReturn($currentBundle);

        $this->authenticationService
            ->shouldReceive('getUserId')
            ->andReturn(123);

        $this->repository
            ->shouldReceive('update')
            ->once()
            ->withArgs(function ($id, $data) {
                return !isset($data['published_by']) && !isset($data['published_at']);
            })
            ->andReturn($currentBundle);

        $result = $this->service->updateBundle(1, $data);
        $this->assertInstanceOf(ProductOfferBundle::class, $result);
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

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

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

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

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

    public function testItCalculatesBundlePricingCorrectly()
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $data = [
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'bundle_price' => 100,
            'items' => [
                ['product_id' => 1, 'quantity' => 2],
                ['product_id' => 2, 'quantity' => 1],
            ],
        ];

        $this->setProductSearchExpectations();

        $this->authenticationService
            ->shouldReceive('getUserId')
            ->andReturn(null);

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->withArgs(function ($data) {
                return $data['total_price'] == 180 && // (50*2) + (80*1)
                    $data['discount_percentage'] == 44; // (180-100)/180 * 100 = 44.44%
            })
            ->andReturn(new ProductOfferBundle());

        $result = $this->service->createBundle($data);
        $this->assertInstanceOf(ProductOfferBundle::class, $result);
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

        $product1 = new Product(['price' => 160.00, 'id' => 1, 'merchants' => collect([$merchant])]);
        $product2 = new Product(['price' => 150, 'id' => 2, 'merchants' => collect([$merchant])]);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->productRepository->shouldReceive('findMany')
            ->with([1, 2], ['merchants'])
            ->andReturn(collect([$product1, $product2]));

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

        $product1 = new Product(['price' => 100.00, 'id' => 1, 'merchants' => collect([$merchant1])]);
        $product2 = new Product(['price' => 100, 'id' => 2, 'merchants' => collect([$merchant2])]);

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->productRepository->shouldReceive('findMany')
            ->with([1, 2], ['merchants'])
            ->andReturn(collect([$product1, $product2]));

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

        $product1 = new Product(['price' => 160.00, 'id' => 1]);
        $product2 = new Product(['price' => 150, 'id' => 2]);

        $this->productRepository->shouldReceive('findMany')
            ->with([1, 2], ['merchants'])
            ->andReturn(collect([$product1, $product2]));

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

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        $this->repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($arg) {
                return $arg['total_price'] == 310
                    && $arg['discount_percentage'] == 61; // (150-120)/150 * 100
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
            'site_id' => $this->siteId
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
                'site_id' => $this->siteId
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


    private function setProductSearchExpectations(): void
    {
        $product = new Product([
            'id' => 1,
            'price' => 50,
            'merchants' => collect([(object)['id' => 1]])
        ]);

        $product2 = new Product([
            'id' => 2,
            'price' => 80,
            'merchants' => collect([(object)['id' => 1]])
        ]);

        $this->productRepository->shouldReceive('findMany')
            ->with([1, 2], ['merchants'])
            ->andReturn(collect([$product, $product2]));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}