<?php

namespace App\Tests\Functional\Controllers\Cms;

use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class PageHistoryControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testIndexReturnsPageHistory()
    {
        $page = $this->createPage();
        $this->createPageHistory($page->id);
        $this->createPageHistory($page->id);

        $response = $this->getForSite("/api/pages/{$page->id}/history");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('history', $data['data']);
        $this->assertCount(2, $data['data']['history']);
    }

    public function testShowReturnsHistoryEntry()
    {
        $page = $this->createPage();

        $history = $this->createPageHistory($page->id);

        $response = $this->getForSite("/api/history/{$history->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals($history->id, $data['data']['history']['id']);
        $this->assertEquals('created', $data['data']['history']['action']);
    }

    public function testRecentReturnsRecentHistory()
    {
        $page1 = $this->createPage();

        $page2 = $this->createPage();
        $this->createPageHistory($page1->id);
        $this->createPageHistory($page2->id);

        $response = $this->getForSite('/api/history/recent');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('history', $data['data']);
        $this->assertGreaterThanOrEqual(2, count($data['data']['history']));
    }

    public function testRestoreFromHistory()
    {
        $page = $this->createPage();

        $snapshot = [
            'id' => $page->id,
            'title' => 'Original Title',
            'slug' => 'original-slug',
            'status' => 'published'
        ];

        $history = $this->createPageHistory($page->id, null, ['snapshot' => $snapshot]);

        // Update page to different values
        $page->title = 'Modified Title';
        $page->save();

        $response = $this->postForSite("/api/history/{$history->id}/restore");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('Page restored successfully', $data['data']['message']);
        $this->assertArrayHasKey('page', $data['data']);
    }
}