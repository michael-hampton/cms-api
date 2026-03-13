<?php

namespace App\Tests\Functional\Controllers\Product;

use App\Models\MerchantProductFeed;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MerchantProductFeedControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    // =========================================================================
    // GET /api/merchants/{id}/feeds
    // =========================================================================

    public function testIndexReturnsFeedsForMerchant(): void
    {
        $merchant = $this->createMerchant();
        $otherMerchant = $this->createMerchant();

        $this->createMerchantFeed(['merchant_id' => $merchant->id]);
        $this->createMerchantFeed(['merchant_id' => $merchant->id]);
        $this->createMerchantFeed(['merchant_id' => $otherMerchant->id]);

        $response = $this->getForSite("/api/merchants/{$merchant->id}/feeds");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertCount(2, $data['data']);
    }

    // =========================================================================
    // POST /api/merchants/{id}/feeds — CreateMerchantProductFeedRequest validation
    // =========================================================================

    public function testStoreCreatesFeed(): void
    {
        $merchant = $this->createMerchant();

        $response = $this->postForSite("/api/merchants/{$merchant->id}/feeds", [
            'feed_url' => 'https://example.com/feed.xml',
            'feed_type' => 'xml',
            'fetch_frequency' => 'daily',
            'is_active' => true,
        ]);
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertArrayHasKey('feed', $responseData['data']);
        $this->assertEquals('https://example.com/feed.xml', $responseData['data']['feed']['feed_url']);
    }

    public function testStoreValidatesRequiredFeedUrl(): void
    {
        $merchant = $this->createMerchant();

        $response = $this->postForSite("/api/merchants/{$merchant->id}/feeds", [
            'feed_type' => 'xml',
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('feed_url', $data['errors']);
    }

    public function testStoreValidatesFeedUrlIsValidUrl(): void
    {
        $merchant = $this->createMerchant();

        $response = $this->postForSite("/api/merchants/{$merchant->id}/feeds", [
            'feed_url' => 'not-a-valid-url',
            'feed_type' => 'xml',
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('feed_url', $data['errors']);
    }

    public function testStoreValidatesRequiredFeedType(): void
    {
        $merchant = $this->createMerchant();

        $response = $this->postForSite("/api/merchants/{$merchant->id}/feeds", [
            'feed_url' => 'https://example.com/feed.xml',
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('feed_type', $data['errors']);
    }

    public function testStoreValidatesFeedTypeIsString(): void
    {
        $merchant = $this->createMerchant();

        $response = $this->postForSite("/api/merchants/{$merchant->id}/feeds", [
            'feed_url' => 'https://example.com/feed.xml',
            'feed_type' => ['not', 'a', 'string'],
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('feed_type', $data['errors']);
    }

    public function testStoreFailsWhenAllRequiredFieldsMissing(): void
    {
        $merchant = $this->createMerchant();

        $response = $this->postForSite("/api/merchants/{$merchant->id}/feeds", []);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('feed_url', $data['errors']);
        $this->assertArrayHasKey('feed_type', $data['errors']);
    }

    // =========================================================================
    // GET /api/merchants/{merchantId}/feeds/{feedId}
    // =========================================================================

    public function testShowReturnsFeed(): void
    {
        $merchant = $this->createMerchant();
        $feed = $this->createMerchantFeed(['merchant_id' => $merchant->id]);

        $response = $this->getForSite("/api/merchants/{$merchant->id}/feeds/{$feed->id}");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('feed', $data);
        $this->assertEquals($feed->id, $data['feed']['id']);
    }

    public function testShowReturns404WhenFeedBelongsToOtherMerchant(): void
    {
        $merchant1 = $this->createMerchant();
        $merchant2 = $this->createMerchant();
        $feed = $this->createMerchantFeed(['merchant_id' => $merchant2->id]);

        $response = $this->getForSite("/api/merchants/{$merchant1->id}/feeds/{$feed->id}");

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testShowReturns404WhenFeedNotFound(): void
    {
        $merchant = $this->createMerchant();

        $response = $this->getForSite("/api/merchants/{$merchant->id}/feeds/99999");

        $this->assertEquals(404, $response->getStatusCode());
    }

    // =========================================================================
    // PUT /api/merchants/{merchantId}/feeds/{feedId} — UpdateMerchantProductFeedRequest validation
    // =========================================================================

    public function testUpdateModifiesFeed(): void
    {
        $merchant = $this->createMerchant();
        $feed = $this->createMerchantFeed([
            'merchant_id' => $merchant->id,
            'feed_url' => 'https://old-url.com/feed.xml',
        ]);

        $response = $this->putForSite("/api/merchants/{$merchant->id}/feeds/{$feed->id}", [
            'feed_url' => 'https://new-url.com/feed.xml',
            'fetch_frequency' => 'hourly',
            'feed_type' => 'xml',
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('https://new-url.com/feed.xml', $data['feed']['feed_url']);
    }

    public function testUpdateValidatesFeedUrlIsValidUrlWhenProvided(): void
    {
        $merchant = $this->createMerchant();
        $feed = $this->createMerchantFeed(['merchant_id' => $merchant->id]);

        $response = $this->putForSite("/api/merchants/{$merchant->id}/feeds/{$feed->id}", [
            'feed_url' => 'not-a-valid-url',
            'feed_type' => 'xml',
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('feed_url', $data['errors']);
    }

    public function testUpdateReturns404WhenFeedBelongsToOtherMerchant(): void
    {
        $merchant1 = $this->createMerchant();
        $merchant2 = $this->createMerchant();
        $feed = $this->createMerchantFeed(['merchant_id' => $merchant2->id]);

        $response = $this->putForSite("/api/merchants/{$merchant1->id}/feeds/{$feed->id}", [
            'feed_type' => 'xml',
        ]);

        $this->assertEquals(422, $response->getStatusCode());
    }

    // =========================================================================
    // DELETE /api/merchants/{merchantId}/feeds/{feedId}
    // =========================================================================

    public function testDeleteRemovesFeed(): void
    {
        $merchant = $this->createMerchant();
        $feed = $this->createMerchantFeed(['merchant_id' => $merchant->id]);

        $response = $this->deleteForSite("/api/merchants/{$merchant->id}/feeds/{$feed->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertDatabaseMissing('merchant_product_feeds', ['id' => $feed->id]);
    }

    public function testDeleteReturns404WhenFeedBelongsToOtherMerchant(): void
    {
        $merchant1 = $this->createMerchant();
        $merchant2 = $this->createMerchant();
        $feed = $this->createMerchantFeed(['merchant_id' => $merchant2->id]);

        $response = $this->deleteForSite("/api/merchants/{$merchant1->id}/feeds/{$feed->id}");

        $this->assertEquals(404, $response->getStatusCode());
    }

    // =========================================================================
    // POST /api/merchants/{merchantId}/feeds/{feedId}/fetch
    // =========================================================================

    public function testFetchTriggersFeedFetch(): void
    {
        $merchant = $this->createMerchant();
        $feed = $this->createMerchantFeed([
            'merchant_id' => $merchant->id,
            'status' => 'pending',
        ]);

        $response = $this->postForSite("/api/merchants/{$merchant->id}/feeds/{$feed->id}/fetch");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('feed', $data['data']);

        $updated = MerchantProductFeed::find($feed->id);
        $this->assertEquals('success', $updated->status);
        $this->assertNotNull($updated->last_fetched_at);
    }

    public function testFetchReturns404WhenFeedBelongsToOtherMerchant(): void
    {
        $merchant1 = $this->createMerchant();
        $merchant2 = $this->createMerchant();
        $feed = $this->createMerchantFeed(['merchant_id' => $merchant2->id]);

        $response = $this->postForSite("/api/merchants/{$merchant1->id}/feeds/{$feed->id}/fetch");

        $this->assertEquals(404, $response->getStatusCode());
    }

    // =========================================================================
    // GET /api/merchants/{merchantId}/feeds/{feedId}/download
    // =========================================================================

    public function testDownloadReturnsFeedData(): void
    {
        $product = $this->createProduct();
        $merchant = $this->createMerchant();
        $feed = $this->createMerchantFeed([
            'merchant_id' => $merchant->id,
            'feed_type' => 'xml',
        ]);

        $this->createProductMerchant($product->id, ['merchant_id' => $merchant->id]);

        $response = $this->getForSite("/api/merchants/{$merchant->id}/feeds/{$feed->id}/download");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
    }

    public function testDownloadReturns404WhenFeedBelongsToOtherMerchant(): void
    {
        $merchant1 = $this->createMerchant();
        $merchant2 = $this->createMerchant();
        $feed = $this->createMerchantFeed(['merchant_id' => $merchant2->id]);

        $response = $this->getForSite("/api/merchants/{$merchant1->id}/feeds/{$feed->id}/download");

        $this->assertEquals(404, $response->getStatusCode());
    }
}