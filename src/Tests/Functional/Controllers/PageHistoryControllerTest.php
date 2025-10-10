<?php

namespace App\Tests\Functional\Controllers;

use App\Models\Page;
use App\Models\PageHistory;

class PageHistoryControllerTest extends FunctionalTestCase
{
    public function testIndexReturnsPageHistory()
    {
        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'published',
            'site_id' => $this->siteId
        ]);

        PageHistory::create([
            'page_id' => $page->id,
            'site_id' => $this->siteId,
            'action' => 'created',
            'description' => 'Page created',
            'created_at' => date('Y-m-d H:i:s')
        ]);

        PageHistory::create([
            'page_id' => $page->id,
            'site_id' => $this->siteId,
            'action' => 'updated',
            'description' => 'Page updated',
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $response = $this->getForSite("/api/pages/{$page->id}/history");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('history', $data['data']);
        $this->assertCount(2, $data['data']['history']);
    }

    public function testShowReturnsHistoryEntry()
    {
        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'published',
            'site_id' => $this->siteId
        ]);

        $history = PageHistory::create([
            'page_id' => $page->id,
            'site_id' => $this->siteId,
            'action' => 'created',
            'description' => 'Page created',
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $response = $this->getForSite("/api/history/{$history->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals($history->id, $data['data']['history']['id']);
        $this->assertEquals('created', $data['data']['history']['action']);
    }

    public function testRecentReturnsRecentHistory()
    {
        $page1 = Page::create([
            'title' => 'Page 1',
            'slug' => 'page-1',
            'status' => 'published',
            'site_id' => $this->siteId
        ]);

        $page2 = Page::create([
            'title' => 'Page 2',
            'slug' => 'page-2',
            'status' => 'published',
            'site_id' => $this->siteId
        ]);

        PageHistory::create([
            'page_id' => $page1->id,
            'site_id' => $this->siteId,
            'action' => 'created',
            'description' => 'Page 1 created',
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours'))
        ]);

        PageHistory::create([
            'page_id' => $page2->id,
            'site_id' => $this->siteId,
            'action' => 'created',
            'description' => 'Page 2 created',
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour'))
        ]);

        $response = $this->getForSite('/api/history/recent');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('history', $data['data']);
        $this->assertGreaterThanOrEqual(2, count($data['data']['history']));
    }

    public function testRestoreFromHistory()
    {
        $page = Page::create([
            'title' => 'Original Title',
            'slug' => 'original-slug',
            'status' => 'published',
            'site_id' => $this->siteId
        ]);

        $snapshot = [
            'id' => $page->id,
            'title' => 'Original Title',
            'slug' => 'original-slug',
            'status' => 'published'
        ];

        $history = PageHistory::create([
            'page_id' => $page->id,
            'site_id' => $this->siteId,
            'action' => 'updated',
            'description' => 'Page updated',
            'snapshot' => json_encode($snapshot),
            'created_at' => date('Y-m-d H:i:s')
        ]);

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