<?php
// src/Tests/Functional/Controllers/ProductOfferControllerTest.php

namespace App\Tests\Functional\Controllers;

use App\Models\ProductOffer;
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
            'is_active' => true,
        ]);

        $response = $this->getForSite("/api/products/{$product->id}/offers");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertCount(2, $data['items']);
        $this->assertEquals($activeOffer->id, $data['items'][0]['id']);
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

//    public function testStoreValidatesEndDateAfterStartDate(): void
//    {
//        $product = $this->createProduct();
//
//        $data = [
//            'sale_price' => 79.99,
//            'start_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
//            'end_date' => date('Y-m-d H:i:s'),
//        ];
//
//        $response = $this->postForSite("/api/products/{$product->id}/offers", $data);
//        $responseData = json_decode($response->getContent(), true);
//
//        $this->assertEquals(422, $response->getStatusCode());
//    }

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
}