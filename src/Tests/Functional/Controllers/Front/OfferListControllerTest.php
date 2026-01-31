<?php

namespace App\Tests\Functional\Controllers\Front;

use App\Models\ProductOffer;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class OfferListControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testShowReturnsViewForValidOffer(): void
    {
        $product = $this->createProduct();

        $offer = ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
            'status' => 'published',
        ]);

        $response = $this->getForSite("/offers/{$offer->id}");

        $this->assertResponseOk($response);
    }

    public function testShowReturns404ForInvalidOffer(): void
    {
        $response = $this->getForSite("/offers/99999");

        $this->assertResponseStatus(404, $response); // Returns 404 view
    }

    public function testShowReturns404ForUnpublishedOffer(): void
    {
        $product = $this->createProduct();

        $offer = ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
            'status' => 'pending',
        ]);

        $response = $this->getForSite("/offers/{$offer->id}");

        $this->assertResponseStatus(404, $response); // Returns 404 view
    }

    public function testShowReturns404ForInactiveOffer(): void
    {
        $product = $this->createProduct();

        $offer = ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => false,
            'status' => 'published',
        ]);

        $response = $this->getForSite("/offers/{$offer->id}");

        $this->assertResponseStatus(404, $response); // Returns 404 view
    }
}