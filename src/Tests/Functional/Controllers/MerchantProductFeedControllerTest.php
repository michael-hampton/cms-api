<?php

namespace App\Tests\Functional\Controllers;

use App\Models\MerchantProductFeed;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MerchantProductFeedControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testIndexReturnsFeedsForMerchant()
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
        $this->assertCount(2, $data['items']);
    }

    public function testStoreCreatesFeed()
    {
        $merchant = $this->createMerchant();

        $feedData = [
            'feed_url' => 'https://example.com/feed.xml',
            'feed_type' => 'xml',
            'fetch_frequency' => 'daily',
            'is_active' => true
        ];

        $response = $this->postForSite("/api/merchants/{$merchant->id}/feeds", $feedData);

//        echo '<pre>';
//        print_r($response->getContent());
//        die;

        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertArrayHasKey('feed', $responseData['data']);
        $this->assertEquals('https://example.com/feed.xml', $responseData['data']['feed']['feed_url']);
    }

    public function testStoreFeedValidatesRequiredFields()
    {
        $merchant = $this->createMerchant();

        $response = $this->postForSite("/api/merchants/{$merchant->id}/feeds", []);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testStoreFeedValidatesUrl()
    {
        $merchant = $this->createMerchant();

        $response = $this->postForSite("/api/merchants/{$merchant->id}/feeds", [
            'feed_url' => 'not-a-url',
            'feed_type' => 'xml'
        ]);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testShowReturnsFeed()
    {
        $merchant = $this->createMerchant();
        $feed = $this->createMerchantFeed(['merchant_id' => $merchant->id]);

        $response = $this->getForSite("/api/merchants/{$merchant->id}/feeds/{$feed->id}");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('feed', $data['data']);
        $this->assertEquals($feed->id, $data['data']['feed']['id']);
    }

    public function testShowReturns404WhenFeedBelongsToOtherMerchant()
    {
        $merchant1 = $this->createMerchant();
        $merchant2 = $this->createMerchant();
        $feed = $this->createMerchantFeed(['merchant_id' => $merchant2->id]);

        $response = $this->getForSite("/api/merchants/{$merchant1->id}/feeds/{$feed->id}");

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testUpdateModifiesFeed()
    {
        $merchant = $this->createMerchant();
        $feed = $this->createMerchantFeed([
            'merchant_id' => $merchant->id,
            'feed_url' => 'https://old-url.com/feed.xml'
        ]);

        $response = $this->putForSite("/api/merchants/{$merchant->id}/feeds/{$feed->id}", [
            'feed_url' => 'https://new-url.com/feed.xml',
            'fetch_frequency' => 'hourly',
            'feed_type' => 'xml'
        ]);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('https://new-url.com/feed.xml', $data['data']['feed']['feed_url']);
    }

    public function testDeleteRemovesFeed()
    {
        $merchant = $this->createMerchant();
        $feed = $this->createMerchantFeed(['merchant_id' => $merchant->id]);

        $response = $this->deleteForSite("/api/merchants/{$merchant->id}/feeds/{$feed->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertDatabaseMissing('merchant_product_feeds', ['id' => $feed->id]);
    }

    public function testFetchTriggersFeedFetch()
    {
        $merchant = $this->createMerchant();
        $feed = $this->createMerchantFeed([
            'merchant_id' => $merchant->id,
            'status' => 'pending'
        ]);

        $response = $this->postForSite("/api/merchants/{$merchant->id}/feeds/{$feed->id}/fetch");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertArrayHasKey('feed', $data['data']);

        $updated = MerchantProductFeed::find($feed->id);
        $this->assertEquals('success', $updated->status);
        $this->assertNotNull($updated->last_fetched_at);
    }

    public function testDownloadReturnsFeedData()
    {
        $merchant = $this->createMerchant();
        $feed = $this->createMerchantFeed([
            'merchant_id' => $merchant->id,
            'feed_type' => 'xml'
        ]);

        $response = $this->getForSite("/api/merchants/{$merchant->id}/feeds/{$feed->id}/download");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
    }
}