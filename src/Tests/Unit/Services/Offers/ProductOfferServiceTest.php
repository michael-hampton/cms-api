<?php

namespace App\Tests\Unit\Services\Offers;

use App\Framework\Authorization\AuthenticationService;
use App\Models\Model;
use App\Models\OfferClicks;
use App\Models\ProductOffer;
use App\Repositories\Offers\ProductOfferRepository;
use App\Services\Offers\OfferStatusTransitionHandler;
use App\Services\Offers\ProductOfferService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use Mockery;

class ProductOfferServiceTest extends FunctionalTestCase
{
    use CreatesTestData;

    private $repository;
    private ProductOfferService $service;
    private $authenticationService;
    private $statusHandler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authenticationService = Mockery::mock(AuthenticationService::class);
        $this->repository = Mockery::mock(ProductOfferRepository::class);
        $this->statusHandler = Mockery::mock(OfferStatusTransitionHandler::class);
        $this->service = new ProductOfferService(
            $this->repository,
            $this->authenticationService,
            $this->statusHandler
        );
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
        $user = $this->createUser();
        $product = $this->createProduct();

        $data = [
            'product_id' => $product->id,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'status' => 'published',
        ];

        $this->authenticationService->shouldReceive('getUserId')
            ->once()
            ->andReturn($user->id);

        $enrichedData = array_merge($data, [
            'published_by' => $user->id,
            'published_at' => now_datetime()
        ]);

        $this->statusHandler->shouldReceive('fillStatusFields')
            ->once()
            ->with($data, $user->id)
            ->andReturn($enrichedData);

        $offer = new ProductOffer($enrichedData);

        $this->repository->shouldReceive('create')
            ->once()
            ->with($enrichedData)
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

    public function testPublish(): void
    {
        $offer = new ProductOffer(['id' => 1, 'status' => 'published']);

        $this->repository->shouldReceive('publish')
            ->once()
            ->with(1, 10)
            ->andReturn($offer);

        $result = $this->service->publish(1, 10);

        $this->assertEquals($offer, $result);
    }

    public function testRejectThrowsExceptionWhenReasonEmpty(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Rejection reason is required');

        $this->service->reject(1, 10, '');
    }

    public function testReject(): void
    {
        $offer = new ProductOffer(['id' => 1, 'status' => 'rejected']);

        $this->repository->shouldReceive('reject')
            ->once()
            ->with(1, 10, 'Not suitable')
            ->andReturn($offer);

        $result = $this->service->reject(1, 10, 'Not suitable');

        $this->assertEquals($offer, $result);
    }

    public function testCreateOfferWithStatusTransition(): void
    {
        $user = $this->createUser();
        $product = $this->createProduct();

        $data = [
            'product_id' => $product->id,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'status' => 'published',
        ];

        $this->authenticationService->shouldReceive('getUserId')
            ->once()
            ->andReturn($user->id);

        $enrichedData = array_merge($data, [
            'published_by' => $user->id,
            'published_at' => now_datetime()
        ]);

        $this->statusHandler->shouldReceive('fillStatusFields')
            ->once()
            ->with($data, $user->id)
            ->andReturn($enrichedData);

        $offer = new ProductOffer($enrichedData);

        $this->repository->shouldReceive('create')
            ->once()
            ->with($enrichedData)
            ->andReturn($offer);

        $result = $this->service->createOffer($data);

        $this->assertEquals($offer, $result);
    }

    public function testUpdateOfferWithStatusTransition(): void
    {
        $user = $this->createUser();
        $product = $this->createProduct();

        $existingOffer = new ProductOffer([
            'id' => 1,
            'product_id' => $product->id,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'status' => 'pending',
        ]);

        $data = ['status' => 'published'];

        $this->repository->shouldReceive('find')
            ->once()
            ->with(1)
            ->andReturn($existingOffer);

        $this->authenticationService->shouldReceive('getUserId')
            ->once()
            ->andReturn($user->id);

        $enrichedData = array_merge($data, [
            'published_by' => $user->id,
            'published_at' => now_datetime()
        ]);

        $this->statusHandler->shouldReceive('fillStatusFieldsOnUpdate')
            ->once()
            ->with($data, $existingOffer, $user->id)
            ->andReturn($enrichedData);

        $this->repository->shouldReceive('update')
            ->once()
            ->with(1, $enrichedData)
            ->andReturn($existingOffer);

        $result = $this->service->updateOffer(1, $data);

        $this->assertInstanceOf(ProductOffer::class, $result);
    }

    public function testTrackClick(): void
    {
        $this->repository->shouldReceive('trackClick')
            ->once()
            ->with(1, 2, 'click', '127.0.0.1', 'Mozilla/5.0')
            ->andReturn(Mockery::mock(OfferClicks::class));

        $result = $this->service->trackClick(1, 2, 'click', '127.0.0.1', 'Mozilla/5.0');

        $this->assertInstanceOf(Model::class, $result);
    }

    public function testTrackClickThrowsExceptionForInvalidAction(): void
    {
        $this->expectException(\ValueError::class);

        $this->service->trackClick(1, 2, 'invalid_action');
    }

    public function testGetByStatusValidatesEnum(): void
    {
        $offers = collect([new ProductOffer(['id' => 1, 'status' => 'published'])]);

        $this->repository->shouldReceive('getByStatus')
            ->once()
            ->with('published')
            ->andReturn($offers);

        $result = $this->service->getByStatus('published');

        $this->assertEquals($offers, $result);
    }

    public function testGetByStatusThrowsExceptionForInvalidStatus(): void
    {
        $this->expectException(\ValueError::class);

        $this->service->getByStatus('invalid_status');
    }

    public function testGetActiveOffers(): void
    {
        $product = $this->createProduct();

        $offer = ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
            'status' => 'published',
            'original_price' => 100.00,
            'site_id' => $this->siteId
        ]);

        $this->repository->shouldReceive('getActiveOffers')
            ->once()
            ->andReturn(collect([$offer->load(['product'])]));

        $result = $this->service->getActiveOffers(10, null, $this->siteId);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals($offer->id, $result[0]['offer_id']);
        $this->assertEquals($product->id, $result[0]['product_id']);
    }

    public function testGetActiveOffersLimitsResults(): void
    {
        $offers = collect();

        for ($i = 0; $i < 15; $i++) {
            $product = $this->createProduct();

            $offer = ProductOffer::create([
                'product_id' => $product->id,
                'sale_price' => 79.99,
                'start_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
                'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
                'is_active' => true,
                'status' => 'published',
                'original_price' => 100.00,
                'site_id' => $this->siteId
            ]);

            $offers->push($offer->load(['product']));
        }

        $this->repository->shouldReceive('getActiveOffers')
            ->once()
            ->andReturn($offers->slice(0, 5));

        $result = $this->service->getActiveOffers(5, null, $this->siteId);

        $this->assertCount(5, $result);
    }

    public function testTrackClickValidatesAction(): void
    {
        $this->repository->shouldReceive('trackClick')
            ->once()
            ->with(1, 2, 'click', '127.0.0.1', 'Mozilla/5.0');

        $result = $this->service->trackClick(1, 2, 'click', '127.0.0.1', 'Mozilla/5.0');

        $this->assertInstanceOf(Model::class, $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}