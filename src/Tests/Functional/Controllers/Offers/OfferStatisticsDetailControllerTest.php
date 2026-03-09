<?php

namespace App\Tests\Functional\Controllers\Offers;

use App\Models\OfferClicks;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class OfferStatisticsDetailControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    // =========================================================================
    // GET /api/{site}/offers/statistics/{type} — validation
    // =========================================================================

    public function testReturns422ForUnknownType(): void
    {
        $response = $this->getForSite('/api/offers/statistics/banana');

        $this->assertEquals(422, $response->getStatusCode());
    }

    // =========================================================================
    // total_offers
    // =========================================================================

    public function testTotalOffersReturnsAllOffersForSite(): void
    {
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();

        $this->createProductOffer($product1->id, ['status' => 'published']);
        $this->createProductOffer($product2->id, ['status' => 'pending']);

        $response = $this->getForSite('/api/offers/statistics/total_offers');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertEquals(2, $data['total']);
        $this->assertCount(2, $data['items']);
    }

    public function testTotalOffersRowContainsExpectedFields(): void
    {
        $product = $this->createProduct();
        $merchant = $this->createMerchant();
        $this->createProductOffer($product->id, ['merchant_id' => $merchant->id, 'status' => 'published']);

        $response = $this->getForSite('/api/offers/statistics/total_offers');
        $data = json_decode($response->getContent(), true);

        $item = $data['items'][0];
        $this->assertArrayHasKey('id', $item);
        $this->assertArrayHasKey('product_name', $item);
        $this->assertArrayHasKey('merchant_name', $item);
        $this->assertArrayHasKey('status', $item);
        $this->assertArrayHasKey('sale_price', $item);
        $this->assertArrayHasKey('created_at', $item);
    }

    // =========================================================================
    // pending
    // =========================================================================

    public function testPendingReturnsOnlyPendingOffers(): void
    {
        $product = $this->createProduct();
        $this->createProductOffer($product->id, ['status' => 'pending']);
        $this->createProductOffer($product->id, ['status' => 'published']);
        $this->createProductOffer($product->id, ['status' => 'rejected']);

        $response = $this->getForSite('/api/offers/statistics/pending');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(1, $data['total']);
        $this->assertEquals('pending', $data['items'][0]['status']);
    }

    // =========================================================================
    // published
    // =========================================================================

    public function testPublishedReturnsOnlyPublishedOffers(): void
    {
        $product = $this->createProduct();
        $publisher = $this->createUser();

        $test = $this->createProductOffer($product->id, [
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s'),
            'published_by' => $publisher->id,
        ]);

        $this->createProductOffer($product->id, ['status' => 'pending']);

        $response = $this->getForSite('/api/offers/statistics/published');
        $data = json_decode($response->getContent(), true);

        //dd($data);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(1, $data['total']);
        $this->assertEquals('published', $data['items'][0]['status']);
        $this->assertNotNull($data['items'][0]['published_at']);
        $this->assertNotNull($data['items'][0]['published_by']);
    }

    // =========================================================================
    // rejected
    // =========================================================================

    public function testRejectedReturnsOnlyRejectedOffersWithReason(): void
    {
        $product = $this->createProduct();
        $rejector = $this->createUser();

        $this->createProductOffer($product->id, [
            'status' => 'rejected',
            'rejection_reason' => 'Price too high',
            'rejected_at' => date('Y-m-d H:i:s'),
            'rejected_by' => $rejector->id,
        ]);
        $this->createProductOffer($product->id, ['status' => 'published']);

        $response = $this->getForSite('/api/offers/statistics/rejected');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(1, $data['total']);
        $this->assertEquals('rejected', $data['items'][0]['status']);
        $this->assertEquals('Price too high', $data['items'][0]['rejection_reason']);
        $this->assertNotNull($data['items'][0]['rejected_at']);
        $this->assertNotNull($data['items'][0]['rejected_by']);
    }

    // =========================================================================
    // active
    // =========================================================================

    public function testActiveReturnsOnlyCurrentlyActiveOffers(): void
    {
        $product = $this->createProduct();

        // Currently active
        $this->createProductOffer($product->id, [
            'is_active' => true,
            'start_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
        ]);

        // Expired
        $this->createProductOffer($product->id, [
            'is_active' => true,
            'start_date' => date('Y-m-d H:i:s', strtotime('-3 days')),
            'end_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
        ]);

        // Inactive flag
        $this->createProductOffer($product->id, [
            'is_active' => false,
            'start_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
        ]);

        $response = $this->getForSite('/api/offers/statistics/active');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(1, $data['total']);
        $this->assertTrue($data['items'][0]['is_active']);
    }

    // =========================================================================
    // clicks
    // =========================================================================

    public function testClicksReturnsAllClickRecords(): void
    {
        $member = $this->createMember();
        $product = $this->createProduct();
        $offer = $this->createProductOffer($product->id);

        OfferClicks::create([
            'offer_id' => $offer->id,
            'member_id' => $member->id,
            'action' => 'click',
            'ip_address' => '127.0.0.1',
            'clicked_at' => now(),
        ]);

        OfferClicks::create([
            'offer_id' => $offer->id,
            'member_id' => $member->id,
            'action' => 'copy_code',
            'ip_address' => '127.0.0.1',
            'clicked_at' => now(),
        ]);

        $response = $this->getForSite('/api/offers/statistics/clicks');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(2, $data['total']);
        $this->assertArrayHasKey('action', $data['items'][0]);
        $this->assertArrayHasKey('clicked_at', $data['items'][0]);
        $this->assertArrayHasKey('product_name', $data['items'][0]);
        $this->assertArrayHasKey('member_name', $data['items'][0]);
    }

    // =========================================================================
    // unique_clickers
    // =========================================================================

    public function testUniqueClickersReturnsOneRowPerMember(): void
    {
        $member1 = $this->createMember();
        $member2 = $this->createMember();
        $product = $this->createProduct();
        $offer = $this->createProductOffer($product->id);

        // member1 clicks twice
        foreach (range(1, 2) as $_) {
            OfferClicks::create([
                'offer_id' => $offer->id,
                'member_id' => $member1->id,
                'action' => 'click',
                'ip_address' => '10.0.0.1',
                'clicked_at' => now(),
            ]);
        }

        // member2 clicks once
        OfferClicks::create([
            'offer_id' => $offer->id,
            'member_id' => $member2->id,
            'action' => 'click',
            'ip_address' => '10.0.0.2',
            'clicked_at' => now(),
        ]);

        $response = $this->getForSite('/api/offers/statistics/unique_clickers');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        // Two unique members, despite three total clicks
        $this->assertEquals(2, $data['total']);
    }

    public function testUniqueClickersExcludesGuestClicks(): void
    {
        $member = $this->createMember();
        $product = $this->createProduct();
        $offer = $this->createProductOffer($product->id);

        // Authenticated click
        OfferClicks::create([
            'offer_id' => $offer->id,
            'member_id' => $member->id,
            'action' => 'click',
            'ip_address' => '10.0.0.1',
            'clicked_at' => now(),
        ]);

        // Guest click (no member_id)
        OfferClicks::create([
            'offer_id' => $offer->id,
            'member_id' => null,
            'action' => 'click',
            'ip_address' => '10.0.0.99',
            'clicked_at' => now(),
        ]);

        $response = $this->getForSite('/api/offers/statistics/unique_clickers');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(1, $data['total']);
    }
}