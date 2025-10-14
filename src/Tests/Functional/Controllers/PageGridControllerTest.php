<?php

namespace App\Tests\Functional\Controllers;

use App\Models\PageGrid;

class PageGridControllerTest extends FunctionalTestCase
{
    public function test_can_list_page_grids()
    {
        $this->createPageGrid(3);

        $response = $this->getForSite('/api/page-grids');

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('pagination', $data);

        // Check structure of first item if data exists
        if (!empty($data['data'])) {
            $firstItem = $data['data'][0];

            $this->assertArrayHasKey('id', $firstItem);
            $this->assertArrayHasKey('title', $firstItem);
            $this->assertArrayHasKey('slug', $firstItem);
            $this->assertArrayHasKey('layout', $firstItem);
            $this->assertArrayHasKey('columns', $firstItem);
            $this->assertArrayHasKey('is_active', $firstItem);
        }

        $this->assertCount(3, $data['data']);
    }

    public function test_can_list_page_grids_with_search()
    {
        $this->createPageGrid(1, ['title' => 'Featured Properties']);
        $this->createPageGrid(1, ['title' => 'Latest News']);
        $this->createPageGrid(1, ['title' => 'Featured Articles']);

        $response = $this->getForSite('/api/page-grids?search=featured');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data['data']);
    }

    public function test_can_list_page_grids_with_filters()
    {
        PageGrid::create([
            'title' => 'Grid 1',
            'slug' => 'grid-1',
            'layout' => 'grid',
            'columns' => 3,
            'is_active' => true,
            'pages' => [], // FIXED: Array instead of json_encode
            'site_id' => $this->siteId
        ]);

        PageGrid::create([
            'title' => 'Grid 2',
            'slug' => 'grid-2',
            'layout' => 'list',
            'columns' => 1,
            'is_active' => false,
            'pages' => [], // FIXED
            'site_id' => $this->siteId
        ]);

        PageGrid::create([
            'title' => 'Grid 3',
            'slug' => 'grid-3',
            'layout' => 'grid',
            'columns' => 3,
            'is_active' => true,
            'pages' => [], // FIXED
            'site_id' => $this->siteId
        ]);

        $response = $this->getForSite('/api/page-grids?layout=grid&is_active=1');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data['data']);
    }

    public function test_can_create_page_grid()
    {
        $data = [
            'title' => 'Test Grid',
            'subtitle' => 'Test Subtitle',
            'layout' => 'grid',
            'columns' => 3,
            'show_excerpt' => true,
            'show_image' => true,
            'show_features' => true,
            'show_actions' => true,
            'pages' => [ // FIXED: Array instead of json_encode
                [
                    'title' => 'Page 1',
                    'slug' => 'page-1',
                    'excerpt' => 'Test excerpt',
                    'url' => '/page-1',
                ]
            ],
            'is_active' => true,
        ];

        $response = $this->postForSite('/api/page-grids', $data);

        $this->assertEquals(201, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('success', $responseData);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertArrayHasKey('data', $responseData);

        $gridData = $responseData['data'];

        $this->assertArrayHasKey('id', $gridData);
        $this->assertArrayHasKey('title', $gridData);
        $this->assertArrayHasKey('slug', $gridData);
        $this->assertArrayHasKey('layout', $gridData);
        $this->assertArrayHasKey('columns', $gridData);
        $this->assertArrayHasKey('pages', $gridData);

        $this->assertTrue($responseData['success']);
        $this->assertEquals('Test Grid', $gridData['title']);
        $this->assertEquals('grid', $gridData['layout']);

        // Verify in database
        $created = PageGrid::where('title', 'Test Grid')->first();
        $this->assertNotNull($created);
        $this->assertEquals('test-grid', $created->slug);
    }

    public function test_can_create_page_grid_with_auto_generated_slug()
    {
        $data = [
            'title' => 'Test Grid',
            'layout' => 'grid',
            'columns' => 3,
        ];

        $response = $this->postForSite('/api/page-grids', $data);

        $this->assertEquals(201, $response->getStatusCode());

        $created = PageGrid::where('title', 'Test Grid')->first();
        $this->assertNotNull($created);
        $this->assertEquals('test-grid', $created->slug);
    }

    public function test_cannot_create_page_grid_with_invalid_data()
    {
        $data = [
            'title' => 'Test Grid',
            'layout' => 'invalid-layout',
            'columns' => 10, // exceeds max
        ];

        $response = $this->postForSite('/api/page-grids', $data);

        $this->assertEquals(422, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $responseData);
    }

    public function test_can_show_page_grid()
    {
        $pageGrid = PageGrid::create([
            'title' => 'Test Grid',
            'slug' => 'test-grid',
            'layout' => 'grid',
            'columns' => 3,
            'is_active' => true,
            'pages' => [], // FIXED
            'site_id' => $this->siteId
        ]);

        $response = $this->getForSite("/api/page-grids/{$pageGrid->id}");

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals($pageGrid->id, $data['data']['id']);
        $this->assertEquals($pageGrid->title, $data['data']['title']);
        $this->assertEquals($pageGrid->slug, $data['data']['slug']);
    }

    public function test_can_show_page_grid_by_slug()
    {
        PageGrid::create([
            'title' => 'Test Grid',
            'slug' => 'test-grid',
            'layout' => 'grid',
            'columns' => 3,
            'is_active' => true,
            'pages' => [], // FIXED
            'site_id' => $this->siteId
        ]);

        $response = $this->getForSite('/api/page-grids/slug/test-grid');

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('test-grid', $data['data']['slug']);
    }

    public function test_returns_404_when_page_grid_not_found()
    {
        $response = $this->getForSite('/api/page-grids/999');

        $this->assertEquals(404, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('Page grid not found', $data['message']);
    }

    public function test_can_update_page_grid()
    {
        $pageGrid = PageGrid::create([
            'title' => 'Old Title',
            'slug' => 'old-title',
            'layout' => 'grid',
            'columns' => 2,
            'is_active' => true,
            'pages' => [], // FIXED
            'site_id' => $this->siteId
        ]);

        $updateData = [
            'title' => 'New Title',
            'columns' => 4,
        ];

        $response = $this->putForSite("/api/page-grids/{$pageGrid->id}", $updateData);

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('New Title', $data['data']['title']);
        $this->assertEquals(4, $data['data']['columns']);

        // Verify in database
        $updated = PageGrid::find($pageGrid->id);
        $this->assertEquals('New Title', $updated->title);
        $this->assertEquals(4, $updated->columns);
    }

    public function test_can_create_page_grid_with_dates()
    {
        $data = [
            'title' => 'Seasonal Grid',
            'layout' => 'grid',
            'columns' => 3,
            'start_date' => '2025-01-01 00:00:00',
            'end_date' => '2025-12-31 23:59:59',
            'is_active' => true,
            'site_id' => $this->siteId
        ];

        $response = $this->postForSite('/api/page-grids', $data);

        $this->assertEquals(201, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals($data['start_date'], $responseData['data']['start_date']);
        $this->assertEquals($data['end_date'], $responseData['data']['end_date']);
    }

    public function test_cannot_create_page_grid_with_invalid_dates()
    {
        $data = [
            'title' => 'Invalid Grid',
            'layout' => 'grid',
            'columns' => 3,
            'start_date' => '2025-12-31 00:00:00',
            'end_date' => '2025-01-01 00:00:00', // End before start
        ];

        $response = $this->postForSite('/api/page-grids', $data);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function test_can_get_page_grid_history()
    {
        $pageGrid = PageGrid::create([
            'title' => 'Test Grid',
            'slug' => 'test-grid',
            'layout' => 'grid',
            'columns' => 3,
            'is_active' => true,
            'pages' => [],
            'site_id' => $this->siteId
        ]);

        // Update it to create history
        $this->putForSite("/api/page-grids/{$pageGrid->id}", [
            'title' => 'Updated Grid'
        ]);

        $response = $this->getForSite("/api/page-grids/{$pageGrid->id}/history");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('data', $data);
        $this->assertGreaterThan(0, count($data['data']));
    }

    public function test_history_tracks_page_grid_updates()
    {
        $pageGrid = PageGrid::create([
            'title' => 'Original Title',
            'slug' => 'original-title',
            'layout' => 'grid',
            'columns' => 3,
            'is_active' => true,
            'pages' => [],
            'site_id' => $this->siteId
        ]);

        $this->putForSite("/api/page-grids/{$pageGrid->id}", [
            'title' => 'New Title',
            'columns' => 4
        ]);

        $response = $this->getForSite("/api/page-grids/{$pageGrid->id}/history");

        $data = json_decode($response->getContent(), true);

        $latestHistory = $data['data'][0];

        $this->assertEquals('updated', $latestHistory['action']);

        $changes = json_decode($latestHistory['changes'], true);
        $this->assertArrayHasKey('changes', $changes);
        $this->assertEquals('Original Title', $changes['changes']['title']['old']);
        $this->assertEquals('New Title', $changes['changes']['title']['new']);
    }

    public function test_can_delete_page_grid()
    {
        $pageGrid = PageGrid::create([
            'title' => 'Test Grid',
            'slug' => 'test-grid',
            'layout' => 'grid',
            'columns' => 3,
            'is_active' => true,
            'pages' => [], // FIXED
            'site_id' => $this->siteId
        ]);

        $response = $this->deleteForSite("/api/page-grids/{$pageGrid->id}");

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('Page grid deleted successfully', $data['message']);

        // Verify soft delete
        $deleted = PageGrid::find($pageGrid->id);
        $this->assertNull($deleted);
    }

    public function test_can_restore_page_grid()
    {
        $pageGrid = PageGrid::create([
            'title' => 'Test Grid',
            'slug' => 'test-grid',
            'layout' => 'grid',
            'columns' => 3,
            'is_active' => true,
            'pages' => [], // FIXED
            'site_id' => $this->siteId
        ]);

        // Soft delete the grid
        $pageGrid->delete();

        $response = $this->postForSite("/api/page-grids/{$pageGrid->id}/restore");

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('Page grid restored successfully', $data['message']);

        // Verify restoration
        $restored = PageGrid::find($pageGrid->id);
        $this->assertNotNull($restored);
    }

    public function test_can_force_delete_page_grid()
    {
        $pageGrid = PageGrid::create([
            'title' => 'Test Grid',
            'slug' => 'test-grid',
            'layout' => 'grid',
            'columns' => 3,
            'is_active' => true,
            'pages' => [], // FIXED
            'site_id' => $this->siteId
        ]);

        $pageGrid->delete();

        $response = $this->deleteForSite("/api/page-grids/{$pageGrid->id}/force");

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('Page grid permanently deleted', $data['message']);

        // Verify permanent deletion
        $this->assertNull(PageGrid::find($pageGrid->id));
    }

    public function test_can_duplicate_page_grid()
    {
        $pageGrid = PageGrid::create([
            'title' => 'Original Grid',
            'slug' => 'original-grid',
            'layout' => 'grid',
            'columns' => 3,
            'is_active' => true,
            'pages' => [],
            'site_id' => $this->siteId
        ]);

        $response = $this->postForSite("/api/page-grids/{$pageGrid->id}/duplicate");

        $this->assertEquals(201, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('Page grid duplicated successfully', $data['message']);
        $this->assertEquals('Original Grid (Copy)', $data['data']['title']);
        $this->assertEquals('original-grid-copy', $data['data']['slug']);

        // Verify in database
        $duplicate = PageGrid::where('slug', 'original-grid-copy')->first();
        $this->assertNotNull($duplicate);

        // Verify history was created for the duplicate
        $historyResponse = $this->getForSite("/api/page-grids/{$duplicate->id}/history");
        $historyData = json_decode($historyResponse->getContent(), true);

        $this->assertGreaterThan(0, count($historyData['data']));
        $this->assertEquals('created', $historyData['data'][0]['action']);
    }

    public function test_can_toggle_active_status()
    {
        $pageGrid = PageGrid::create([
            'title' => 'Test Grid',
            'slug' => 'test-grid',
            'layout' => 'grid',
            'columns' => 3,
            'is_active' => true,
            'pages' => [], // FIXED
            'site_id' => $this->siteId
        ]);

        $response = $this->postForSite("/api/page-grids/{$pageGrid->id}/toggle-active");

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals(0, $data['data']['is_active']);

        // Verify in database
        $updated = PageGrid::find($pageGrid->id);
        $this->assertFalse($updated->is_active);
    }

    public function test_can_add_page_to_grid()
    {
        $pageGrid = PageGrid::create([
            'title' => 'Test Grid',
            'slug' => 'test-grid',
            'layout' => 'grid',
            'columns' => 3,
            'is_active' => true,
            'pages' => [], // FIXED
            'site_id' => $this->siteId
        ]);

        $pageData = [
            'title' => 'New Page',
            'slug' => 'new-page',
            'excerpt' => 'Page excerpt',
            'url' => '/new-page',
        ];

        $response = $this->postForSite("/api/page-grids/{$pageGrid->id}/pages", $pageData);

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('Page added to grid successfully', $data['message']);

        $pageGrid = PageGrid::find($pageGrid->id);
        $this->assertCount(1, $pageGrid->pages);
        $this->assertEquals('New Page', $pageGrid->pages[0]['title']);
    }

    public function test_can_remove_page_from_grid()
    {
        $pageGrid = PageGrid::create([
            'title' => 'Test Grid',
            'slug' => 'test-grid',
            'layout' => 'grid',
            'columns' => 3,
            'is_active' => true,
            'pages' => [ // FIXED
                ['title' => 'Page 1', 'slug' => 'page-1'],
                ['title' => 'Page 2', 'slug' => 'page-2'],
            ],
            'site_id' => $this->siteId
        ]);

        $response = $this->deleteForSite("/api/page-grids/{$pageGrid->id}/pages/0");

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals('Page removed from grid successfully', $data['message']);

        $pageGrid = PageGrid::find($pageGrid->id);

        $this->assertCount(1, $pageGrid->pages);
        $this->assertEquals('Page 2', $pageGrid->pages[0]['title']);
    }

    public function test_can_update_page_in_grid()
    {
        $pageGrid = PageGrid::create([
            'title' => 'Test Grid',
            'slug' => 'test-grid',
            'layout' => 'grid',
            'columns' => 3,
            'is_active' => true,
            'pages' => [ // FIXED
                ['title' => 'Old Title', 'slug' => 'old-slug'],
            ],
            'site_id' => $this->siteId
        ]);

        $updateData = [
            'title' => 'Updated Title',
            'excerpt' => 'Updated excerpt',
        ];

        $response = $this->putForSite("/api/page-grids/{$pageGrid->id}/pages/0", $updateData);

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('Page updated successfully', $data['message']);

        $pageGrid = PageGrid::find($pageGrid->id);

        $this->assertEquals('Updated Title', $pageGrid->pages[0]['title']);
        $this->assertEquals('Updated excerpt', $pageGrid->pages[0]['excerpt']);
    }

    public function test_can_reorder_pages_in_grid()
    {
        $pageGrid = PageGrid::create([
            'title' => 'Test Grid',
            'slug' => 'test-grid',
            'layout' => 'grid',
            'columns' => 3,
            'is_active' => true,
            'pages' => [ // FIXED
                ['title' => 'Page 1', 'slug' => 'page-1'],
                ['title' => 'Page 2', 'slug' => 'page-2'],
                ['title' => 'Page 3', 'slug' => 'page-3'],
            ],
            'site_id' => $this->siteId
        ]);

        $response = $this->postForSite("/api/page-grids/{$pageGrid->id}/pages/reorder", [
            'order' => [2, 0, 1]
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('Pages reordered successfully', $data['message']);

        $pageGrid = PageGrid::find($pageGrid->id);
        $this->assertEquals('Page 3', $pageGrid->pages[0]['title']);
        $this->assertEquals('Page 1', $pageGrid->pages[1]['title']);
        $this->assertEquals('Page 2', $pageGrid->pages[2]['title']);
    }

    public function test_pagination_works_correctly()
    {
        for ($i = 1; $i <= 25; $i++) {
            PageGrid::create([
                'title' => "Grid $i",
                'slug' => "grid-$i",
                'layout' => 'grid',
                'columns' => 3,
                'is_active' => true,
                'pages' => [], // FIXED
                'site_id' => $this->siteId
            ]);
        }

        $response = $this->getForSite('/api/page-grids?per_page=10');

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('pagination', $data);

        $pagination = $data['pagination'];
        $this->assertArrayHasKey('current_page', $pagination);
        $this->assertArrayHasKey('last_page', $pagination);
        $this->assertArrayHasKey('per_page', $pagination);
        $this->assertArrayHasKey('total', $pagination);
        $this->assertArrayHasKey('from', $pagination);
        $this->assertArrayHasKey('to', $pagination);

        $this->assertCount(10, $data['data']);
        $this->assertEquals(25, $pagination['total']);
        $this->assertEquals(3, $pagination['last_page']);
    }

    public function test_sorting_works_correctly()
    {
        // Create records with explicit created_at timestamps
        $zebra = PageGrid::create([
            'title' => 'Zebra',
            'slug' => 'zebra',
            'layout' => 'grid',
            'columns' => 3,
            'is_active' => true,
            'pages' => [], // FIXED
            'site_id' => $this->siteId
        ]);
        // Manually update created_at to ensure proper ordering
        $this->database->query(
            "UPDATE page_grids SET created_at = ? WHERE id = ?",
            [date('Y-m-d H:i:s', strtotime('-3 days')), $zebra->id]
        );

        $alpha = PageGrid::create([
            'title' => 'Alpha',
            'slug' => 'alpha',
            'layout' => 'grid',
            'columns' => 3,
            'is_active' => true,
            'pages' => [], // FIXED
            'site_id' => $this->siteId
        ]);
        $this->database->query(
            "UPDATE page_grids SET created_at = ? WHERE id = ?",
            [date('Y-m-d H:i:s', strtotime('-2 days')), $alpha->id]
        );

        $beta = PageGrid::create([
            'title' => 'Beta',
            'slug' => 'beta',
            'layout' => 'grid',
            'columns' => 3,
            'is_active' => true,
            'pages' => [], // FIXED
            'site_id' => $this->siteId
        ]);
        $this->database->query(
            "UPDATE page_grids SET created_at = ? WHERE id = ?",
            [date('Y-m-d H:i:s', strtotime('-1 day')), $beta->id]
        );

        // Sort by title ascending
        $response = $this->getForSite('/api/page-grids?sort_by=title&sort_order=asc');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Alpha', $data['data'][0]['title']);
        $this->assertEquals('Beta', $data['data'][1]['title']);
        $this->assertEquals('Zebra', $data['data'][2]['title']);

        // Sort by created_at descending (most recent first)
        $response = $this->getForSite('/api/page-grids?sort_by=created_at&sort_order=desc');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        // Beta was created most recently (-1 day), so it should be first
        $this->assertEquals('Beta', $data['data'][0]['title']);
        $this->assertEquals('Alpha', $data['data'][1]['title']);
        $this->assertEquals('Zebra', $data['data'][2]['title']);
    }

    public function test_can_create_page_grid_with_use_hero()
    {
        $data = [
            'title' => 'Hero Grid',
            'layout' => 'grid',
            'columns' => 3,
            'use_hero' => true,
        ];

        $response = $this->postForSite('/api/page-grids', $data);

        $this->assertEquals(201, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertTrue($responseData['data']['use_hero']);
    }

    public function test_can_update_use_hero_flag()
    {
        $pageGrid = PageGrid::create([
            'title' => 'Test Grid',
            'slug' => 'test-grid',
            'layout' => 'grid',
            'columns' => 3,
            'is_active' => true,
            'pages' => [],
            'use_hero' => false,
            'site_id' => $this->siteId
        ]);

        $response = $this->putForSite("/api/page-grids/{$pageGrid->id}", [
            'use_hero' => true
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals(1, $data['data']['use_hero']);

        $updated = PageGrid::find($pageGrid->id);
        $this->assertTrue($updated->use_hero);
    }

    protected function createPageGrid(int $count = 1, array $data = [])
    {
        for ($i = 1; $i <= $count; $i++) {
            PageGrid::create([
                'title' => $data['title'] ?? "Test Page Grid $i",
                'slug' => isset($data['title']) ? $this->slugify($data['title']) : "test-page-grid-$i",
                'layout' => 'grid',
                'columns' => 3,
                'is_active' => true,
                'pages' => [], // FIXED
                'site_id' => $this->siteId
            ]);
        }
    }

    protected function slugify(string $text): string
    {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);
        return $text;
    }
}