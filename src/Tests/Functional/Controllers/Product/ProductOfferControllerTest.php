<?php

namespace App\Tests\Functional\Controllers\Product;

use App\Models\ProductOffer;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class ProductOfferControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testIndexReturnsActiveOffers(): void
    {
        $product = $this->createProduct();

        $activeOffer = ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
        ]);

        // Expired offer
        ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 69.99,
            'start_date' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'end_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'is_active' => false,
        ]);

        $response = $this->getForSite("/api/products/{$product->id}/offers?is_active=true");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertCount(1, $data['offers']['items']);
        $this->assertEquals($activeOffer->id, $data['offers']['items'][0]['id']);
    }

    public function testIndexSupportsSearch(): void
    {
        $product = $this->createProduct(['name' => 'Test Product']);

        ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
        ]);

        $response = $this->getForSite("/api/products/{$product->id}/offers?search=test");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
    }

    public function testIndexSupportsSorting(): void
    {
        $product = $this->createProduct();

        ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 100.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
        ]);

        ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 50.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
        ]);

        $response = $this->getForSite("/api/products/{$product->id}/offers?sort_by=sale_price&sort_order=asc");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(50.00, $data['offers']['items'][0]['sale_price']);
    }

    public function testStoreValidatesEndDateAfterStartDate(): void
    {
        $product = $this->createProduct();

        $data = [
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'end_date' => date('Y-m-d H:i:s'),
        ];

        $response = $this->postForSite("/api/products/{$product->id}/offers", $data);
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('end_date', $responseData['errors']);
    }

    public function testUpdateValidatesEndDateAfterStartDate(): void
    {
        $product = $this->createProduct();

        $offer = ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
        ]);

        $data = [
            'start_date' => date('Y-m-d H:i:s', strtotime('+2 days')),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
        ];

        $response = $this->putForSite("/api/products/{$product->id}/offers/{$offer->id}", $data);
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('end_date', $responseData['errors']);
    }


    public function testCategoryOffersReturnsOffersForCategory(): void
    {
        $category = $this->createCategory();
        $product1 = $this->createProduct(['category_id' => $category->id]);
        $product2 = $this->createProduct(['category_id' => $category->id]);

        ProductOffer::create([
            'product_id' => $product1->id,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
        ]);

        ProductOffer::create([
            'product_id' => $product2->id,
            'sale_price' => 89.99,
            'start_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
        ]);

        $response = $this->getForSite("/api/categories/{$category->id}/offers");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertCount(2, $data['items']);
    }

    public function testStoreCreatesOffer(): void
    {
        $product = $this->createProduct();
        $merchant = $this->createMerchant();

        $data = [
            'merchant_id' => $merchant->id,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
        ];

        $response = $this->postForSite("/api/products/{$product->id}/offers", $data);

        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertTrue($responseData['success']);
        $this->assertEquals('Offer created successfully', $responseData['message']);
        $this->assertArrayHasKey('offer', $responseData);
    }

    public function testStoreValidatesRequiredFields(): void
    {
        $product = $this->createProduct();

        $response = $this->postForSite("/api/products/{$product->id}/offers", []);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertEquals('Validation failed', $data['error']);
    }

    public function testUpdateModifiesOffer(): void
    {
        $product = $this->createProduct();

        $offer = ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
        ]);

        $data = ['sale_price' => 69.99];

        $response = $this->putForSite("/api/products/{$product->id}/offers/{$offer->id}", $data);
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($responseData['success']);
        $this->assertEquals(69.99, $responseData['offer']['sale_price']);
    }

    public function testUpdateReturns404ForNonexistentOffer(): void
    {
        $product = $this->createProduct();

        $response = $this->putForSite("/api/products/{$product->id}/offers/9999", ['sale_price' => 69.99]);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testDestroyDeletesOffer(): void
    {
        $product = $this->createProduct();

        $offer = ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
        ]);

        $response = $this->deleteForSite("/api/products/{$product->id}/offers/{$offer->id}");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertDatabaseMissing('product_offers', ['id' => $offer->id]);
    }

    public function testDestroyReturns404ForNonexistentOffer(): void
    {
        $product = $this->createProduct();

        $response = $this->deleteForSite("/api/products/{$product->id}/offers/9999");

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testPublishOfferSuccessfully(): void
    {
        $product = $this->createProduct();
        $offer = $this->createProductOffer($product->id, [
            'status' => 'pending',
            'start_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
        ]);

        $response = $this->postForSite("/api/products/{$product->id}/offers/{$offer->id}/publish");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertEquals('Offer published successfully', $data['message']);

        $updatedOffer = ProductOffer::find($offer->id);
        $this->assertEquals('published', $updatedOffer->status);
        $this->assertNotNull($updatedOffer->published_at);
    }

    public function testRejectOfferWithReason(): void
    {
        $product = $this->createProduct();
        $offer = $this->createProductOffer($product->id, [
            'status' => 'pending',
        ]);

        $reason = 'Price is too high';
        $response = $this->postForSite(
            "/api/products/{$product->id}/offers/{$offer->id}/reject",
            ['reason' => $reason]
        );
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($data['success']);

        $updatedOffer = ProductOffer::find($offer->id);
        $this->assertEquals('rejected', $updatedOffer->status);
        $this->assertEquals($reason, $updatedOffer->rejection_reason);
        $this->assertNotNull($updatedOffer->rejected_at);
    }

    public function testRejectOfferRequiresReason(): void
    {
        $product = $this->createProduct();
        $offer = $this->createProductOffer($product->id, ['status' => 'pending']);

        $response = $this->postForSite(
            "/api/products/{$product->id}/offers/{$offer->id}/reject",
            []
        );

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testCannotPublishAlreadyPublishedOffer(): void
    {
        $product = $this->createProduct();
        $offer = $this->createProductOffer($product->id, [
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
        ]);

        $response = $this->postForSite("/api/products/{$product->id}/offers/{$offer->id}/publish");

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testFilterOffersByStatus(): void
    {
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();
        $product3 = $this->createProduct();

        $this->createProductOffer($product1->id, ['status' => 'pending']);
        $this->createProductOffer($product2->id, ['status' => 'published']);
        $this->createProductOffer($product3->id, ['status' => 'rejected']);

        $response = $this->getForSite('/api/offers?status=published');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(1, $data['offers']['items']);
        $this->assertEquals('published', $data['offers']['items'][0]['status']);
    }

    public function testFilterOffersByMerchant(): void
    {
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();
        $merchant = $this->createMerchant();

        $this->createProductOffer($product1->id, ['merchant_id' => $merchant->id]);
        $this->createProductOffer($product2->id, ['merchant_id' => null]);

        $response = $this->getForSite("/api/offers?merchant_id={$merchant->id}");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(1, $data['offers']['items']);
        $this->assertEquals($merchant->id, $data['offers']['items'][0]['merchant_id']);
    }

    public function testCreateOfferWithVoucher(): void
    {
        $product = $this->createProduct();
        $voucher = $this->createVoucher();

        $data = [
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'voucher_id' => $voucher->id,
        ];

        $response = $this->postForSite("/api/products/{$product->id}/offers", $data);
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals($voucher->id, $responseData['offer']['voucher_id']);
    }

    public function testIndexFiltersByMerchant(): void
    {
        $product = $this->createProduct();
        $merchant1 = $this->createMerchant();
        $merchant2 = $this->createMerchant();

        ProductOffer::create([
            'product_id' => $product->id,
            'merchant_id' => $merchant1->id,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
        ]);

        ProductOffer::create([
            'product_id' => $product->id,
            'merchant_id' => $merchant2->id,
            'sale_price' => 69.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
        ]);

        $response = $this->getForSite("/api/products/{$product->id}/offers?merchant_id={$merchant1->id}");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(1, $data['offers']['items']);
        $this->assertEquals($merchant1->id, $data['offers']['items'][0]['merchant_id']);
    }

    public function testIndexFiltersByStatus(): void
    {
        $product = $this->createProduct();

        ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
            'status' => 'published',
        ]);

        ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 69.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
            'status' => 'pending',
        ]);

        $response = $this->getForSite("/api/products/{$product->id}/offers?status=published");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(1, $data['offers']['items']);
        $this->assertEquals('published', $data['offers']['items'][0]['status']);
    }

    public function testIndexFiltersByActiveStatus(): void
    {
        $product = $this->createProduct();

        ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
        ]);

        ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 69.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => false,
        ]);

        $response = $this->getForSite("/api/products/{$product->id}/offers?is_active=1");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(1, $data['offers']['items']);
        $this->assertTrue($data['offers']['items'][0]['is_active']);
    }

    public function testIndexFiltersByDateRange(): void
    {
        $product = $this->createProduct();

        ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s', strtotime('-5 days')),
            'end_date' => date('Y-m-d H:i:s', strtotime('-3 days')),
            'is_active' => true,
        ]);

        ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 69.99,
            'start_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'end_date' => date('Y-m-d H:i:s', strtotime('+5 days')),
            'is_active' => true,
        ]);

        $startDate = date('Y-m-d', strtotime('now'));
        $response = $this->getForSite("/api/products/{$product->id}/offers?start_date[from]={$startDate}");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        foreach ($data['offers']['items'] as $item) {
            $this->assertGreaterThanOrEqual(strtotime($startDate), strtotime($item['start_date']));
        }
    }

    public function testGetStatisticsReturnsOfferStats(): void
    {
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();

        $this->createProductOffer($product1->id, ['status' => 'published', 'is_active' => true]);
        $this->createProductOffer($product2->id, ['status' => 'pending', 'is_active' => false]);

        $response = $this->getForSite('/api/offers/statistics');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('stats', $data);
        $this->assertArrayHasKey('total_offers', $data['stats']);
        $this->assertArrayHasKey('active_offers', $data['stats']);
        $this->assertArrayHasKey('published_offers', $data['stats']);
    }

    public function testTrackClickRecordsClickSuccessfully(): void
    {
        $member = $this->createMember();

        $this->actingAsMember($member);

        $product = $this->createProduct();
        $offer = $this->createProductOffer($product->id);

        $response = $this->postForSite(
            "/products/{$product->id}/offers/{$offer->id}/track",
            ['action' => 'click']
        );

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);

        $this->assertDatabaseHas('offer_clicks', [
            'offer_id' => $offer->id,
            'action' => 'click'
        ]);
    }

    public function testTrackClickRecordsViewAction(): void
    {
        $product = $this->createProduct();
        $offer = $this->createProductOffer($product->id);

        $response = $this->postForSite(
            "/products/{$product->id}/offers/{$offer->id}/track",
            ['action' => 'view']
        );

        $this->assertEquals(200, $response->getStatusCode());

        $this->assertDatabaseHas('offer_clicks', [
            'offer_id' => $offer->id,
            'action' => 'view'
        ]);
    }

    public function testTrackClickRecordsCopyCodeAction(): void
    {
        $product = $this->createProduct();
        $voucher = $this->createVoucher();
        $offer = $this->createProductOffer($product->id, ['voucher_id' => $voucher->id]);

        $response = $this->postForSite(
            "/products/{$product->id}/offers/{$offer->id}/track",
            ['action' => 'copy_code']
        );

        $this->assertEquals(200, $response->getStatusCode());

        $this->assertDatabaseHas('offer_clicks', [
            'offer_id' => $offer->id,
            'action' => 'copy_code'
        ]);
    }
}