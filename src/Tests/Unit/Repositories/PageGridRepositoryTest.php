<?php

namespace App\Tests\Unit\Repositories;

use App\Models\PageGrid;
use App\Repositories\PageGridRepository;
use App\Search\SearchCriteria;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class PageGridRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private PageGridRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new PageGridRepository();
    }

    /**
     * Create a test page grid
     */
    protected function createPageGrid(array $overrides = []): PageGrid
    {
        return PageGrid::create(array_merge([
            'site_id' => $this->siteId,
            'slug' => 'test-grid-' . uniqid(),
            'title' => 'Test Grid',
            'is_active' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $overrides));
    }

    /** @test */
    public function test_find_by_slug_returns_active_grid(): void
    {
        // Arrange
        $grid = $this->createPageGrid(['slug' => 'test-grid-slug']);

        // Act
        $found = $this->repository->findBySlug('test-grid-slug');

        // Assert
        $this->assertNotNull($found);
        $this->assertEquals($grid->id, $found->id);
        $this->assertEquals('test-grid-slug', $found->slug);
    }

    /** @test */
    public function test_find_by_slug_returns_null_for_deleted_grid(): void
    {
        // Arrange
        $grid = $this->createPageGrid(['slug' => 'deleted-grid']);
        $grid->delete(); // Soft delete

        // Act
        $found = $this->repository->findBySlug('deleted-grid');

        // Assert
        $this->assertNull($found);
    }

    /** @test */
    public function test_find_by_slug_returns_null_when_not_found(): void
    {
        // Act
        $found = $this->repository->findBySlug('non-existent-slug');

        // Assert
        $this->assertNull($found);
    }

    /** @test */
    public function test_restore_brings_back_deleted_grid(): void
    {
        // Arrange
        $grid = $this->createPageGrid();
        $gridId = $grid->id;
        $grid->delete();

        // Verify it's deleted
        $this->assertNotNull(PageGrid::withTrashed()->find($gridId)->deleted_at);

        // Act
        $result = $this->repository->restore($gridId);

        // Assert
        $this->assertTrue($result);
        $restored = PageGrid::find($gridId);
        $this->assertNotNull($restored);
        $this->assertNull($restored->deleted_at);
    }

    /** @test */
    public function test_restore_returns_false_when_grid_not_found(): void
    {
        // Act
        $result = $this->repository->restore(99999);

        // Assert
        $this->assertFalse($result);
    }

    /** @test */
    public function test_force_delete_permanently_removes_grid(): void
    {
        // Arrange
        $grid = $this->createPageGrid();
        $gridId = $grid->id;
        $grid->delete(); // Soft delete first

        // Act
        $result = $this->repository->forceDelete($gridId);

        // Assert
        $this->assertTrue($result);
        $this->assertNull(PageGrid::withTrashed()->find($gridId));
    }

    /** @test */
    public function test_force_delete_returns_false_when_not_found(): void
    {
        // Act
        $result = $this->repository->forceDelete(99999);

        // Assert
        $this->assertFalse($result);
    }

    /** @test */
    public function test_get_active_returns_only_active_non_deleted_grids(): void
    {
        // Arrange
        $activeGrid = $this->createPageGrid(['is_active' => true, 'title' => 'Active']);
        $inactiveGrid = $this->createPageGrid(['is_active' => false, 'title' => 'Inactive']);
        $deletedGrid = $this->createPageGrid(['is_active' => true, 'title' => 'Deleted']);
        $deletedGrid->delete();

        // Act
        $grids = $this->repository->getActive();

        // Assert
        $this->assertGreaterThanOrEqual(1, $grids->count());

        foreach ($grids as $grid) {
            $this->assertTrue((bool) $grid->is_active);
            $this->assertNull($grid->deleted_at);
        }

        // Verify specific grids
        $titles = $grids->pluck('title')->toArray();
        $this->assertContains('Active', $titles);
        $this->assertNotContains('Inactive', $titles);
        $this->assertNotContains('Deleted', $titles);
    }

    /** @test */
    public function test_duplicate_creates_copy_with_modified_title_and_slug(): void
    {
        // Arrange
        $original = $this->createPageGrid([
            'slug' => 'original-slug',
            'title' => 'Original Title',
            'is_active' => true
        ]);

        // Act
        $duplicate = $this->repository->duplicate($original->id);

        // Assert
        $this->assertNotNull($duplicate);
        $this->assertNotEquals($original->id, $duplicate->id);
        $this->assertEquals('Original Title (Copy)', $duplicate->title);
        $this->assertEquals('original-slug-copy', $duplicate->slug);
        $this->assertEquals($original->is_active, $duplicate->is_active);
    }

    /** @test */
    public function test_duplicate_returns_null_when_original_not_found(): void
    {
        // Act
        $duplicate = $this->repository->duplicate(99999);

        // Assert
        $this->assertNull($duplicate);
    }

    /** @test */
    public function test_slug_exists_returns_true_when_slug_exists(): void
    {
        // Arrange
        $this->createPageGrid(['slug' => 'existing-slug']);

        // Act
        $exists = $this->repository->slugExists('existing-slug');

        // Assert
        $this->assertTrue($exists);
    }

    /** @test */
    public function test_slug_exists_returns_false_when_slug_not_found(): void
    {
        // Act
        $exists = $this->repository->slugExists('non-existent-slug');

        // Assert
        $this->assertFalse($exists);
    }

    /** @test */
    public function test_slug_exists_excludes_specific_id(): void
    {
        // Arrange
        $grid = $this->createPageGrid(['slug' => 'test-slug']);

        // Act
        $exists = $this->repository->slugExists('test-slug', $grid->id);

        // Assert - Should return false because we're excluding this grid's ID
        $this->assertFalse($exists);
    }

    /** @test */
    public function test_slug_exists_with_exclude_id_still_finds_other_matches(): void
    {
        // Arrange
        $grid1 = $this->createPageGrid(['slug' => 'shared-slug']);
        $grid2 = $this->createPageGrid(['slug' => 'shared-slug']);

        // Act
        $exists = $this->repository->slugExists('shared-slug', $grid1->id);

        // Assert - Should return true because grid2 exists with same slug
        $this->assertTrue($exists);
    }

    /** @test */
    public function test_search_returns_paginated_results(): void
    {
        // Arrange
        $this->createPageGrid(['title' => 'Grid 1']);
        $this->createPageGrid(['title' => 'Grid 2']);
        $this->createPageGrid(['title' => 'Grid 3']);

        // Act
        $criteria = new SearchCriteria();
        $criteria->setPerPage(2);
        $result = $this->repository->search($criteria);

        // Assert
        $this->assertInstanceOf(\App\Search\PaginatedResult::class, $result);
        $this->assertGreaterThanOrEqual(3, $result->getTotal());
        $this->assertLessThanOrEqual(2, count($result->getData()));
    }

    /** @test */
    public function test_log_history_creates_history_record(): void
    {
        // Arrange
        $grid = $this->createPageGrid();
        $changes = ['title' => ['old' => 'Old Title', 'new' => 'New Title']];

        // Act
        $this->repository->logHistory($grid->id, 'updated', null, $changes);

        // Assert
        $this->assertDatabaseHas('page_grid_history', [
            'page_grid_id' => $grid->id,
            'action' => 'updated'
        ]);
    }

    /** @test */
    public function test_log_history_stores_changes_as_json(): void
    {
        // Arrange
        $grid = $this->createPageGrid();
        $changes = [
            'title' => ['old' => 'Old', 'new' => 'New'],
            'is_active' => ['old' => false, 'new' => true]
        ];

        // Act
        $this->repository->logHistory($grid->id, 'updated', null, $changes);

        // Assert
        $history = $this->database->query(
            "SELECT * FROM page_grid_history WHERE page_grid_id = :id ORDER BY created_at DESC LIMIT 1",
            ['id' => $grid->id]
        )->fetch();

        $this->assertNotNull($history);
        $storedChanges = json_decode($history['changes'], true);
        $this->assertEquals($changes, $storedChanges);
    }

    /** @test */
    public function test_get_history_returns_all_history_for_grid(): void
    {
        // Arrange
        $grid = $this->createPageGrid();

        $this->repository->logHistory($grid->id, 'created', null, []);
        $this->repository->logHistory($grid->id, 'updated', null, ['title' => 'changed']);
        $this->repository->logHistory($grid->id, 'deleted', null, []);

        // Act
        $history = $this->repository->getHistory($grid->id);

        // Assert
        $this->assertCount(3, $history);

        // Should be ordered by created_at desc (newest first)
        $actions = $history->pluck('action')->toArray();
        $this->assertEquals('created', $actions[0]);
        $this->assertEquals('updated', $actions[1]);
        $this->assertEquals('deleted', $actions[2]);
    }

    /** @test */
    public function test_get_history_returns_empty_collection_when_no_history(): void
    {
        // Arrange
        $grid = $this->createPageGrid();

        // Act
        $history = $this->repository->getHistory($grid->id);

        // Assert
        $this->assertInstanceOf(\App\Framework\Support\Collection::class, $history);
        $this->assertCount(0, $history);
    }

    public function test_find_loads_pages_relationship(): void
    {
        $grid = $this->createPageGrid();
        $page = $this->createPage(['title' => 'Test Page']);

        $grid->pages(true)->attach($page->id);

        $found = $this->repository->find($grid->id, ['pages']);

        $this->assertNotNull($found);
        $this->assertTrue($found->relationLoaded('pages'));
        $this->assertCount(1, $found->pages);
    }

    public function test_duplicate_does_not_copy_page_assignments(): void
    {
        $original = $this->createPageGrid();
        $page = $this->createPage(['title' => 'Test Page']);

        $original->pages(true)->attach($page->id);

        $duplicate = $this->repository->duplicate($original->id);

        $this->assertNotNull($duplicate);
        $this->assertCount(0, $duplicate->pages);
    }

    public function test_get_active_grid_for_page_returns_grid_when_no_dates_set(): void
    {
        // Arrange
        $grid = $this->createPageGrid(['start_date' => null, 'end_date' => null]);
        $page = $this->createPage(['title' => 'Test Page']);
        $grid->pages(true)->attach($page->id);

        // Act
        $found = $this->repository->getActiveGridForPage($page->id);

        // Assert
        $this->assertNotNull($found);
        $this->assertEquals($grid->id, $found->id);
    }

    public function test_get_active_grid_for_page_returns_grid_when_within_date_range(): void
    {
        // Arrange
        $yesterday = date('Y-m-d H:i:s', strtotime('-1 day'));
        $tomorrow = date('Y-m-d H:i:s', strtotime('+1 day'));

        $grid = $this->createPageGrid([
            'start_date' => $yesterday,
            'end_date' => $tomorrow
        ]);
        $page = $this->createPage(['title' => 'Test Page']);
        $grid->pages(true)->attach($page->id);

        // Act
        $found = $this->repository->getActiveGridForPage($page->id);

        // Assert
        $this->assertNotNull($found);
        $this->assertEquals($grid->id, $found->id);
    }

    public function test_get_active_grid_for_page_returns_null_when_before_start_date(): void
    {
        // Arrange
        $tomorrow = date('Y-m-d H:i:s', strtotime('+1 day'));
        $nextWeek = date('Y-m-d H:i:s', strtotime('+7 days'));

        $grid = $this->createPageGrid([
            'start_date' => $tomorrow,
            'end_date' => $nextWeek
        ]);
        $page = $this->createPage(['title' => 'Test Page']);
        $grid->pages(true)->attach($page->id);

        // Act
        $found = $this->repository->getActiveGridForPage($page->id);

        // Assert
        $this->assertNull($found);
    }

    public function test_get_active_grid_for_page_returns_null_when_after_end_date(): void
    {
        // Arrange
        $lastWeek = date('Y-m-d H:i:s', strtotime('-7 days'));
        $yesterday = date('Y-m-d H:i:s', strtotime('-1 day'));

        $grid = $this->createPageGrid([
            'start_date' => $lastWeek,
            'end_date' => $yesterday
        ]);
        $page = $this->createPage(['title' => 'Test Page']);
        $grid->pages(true)->attach($page->id);

        // Act
        $found = $this->repository->getActiveGridForPage($page->id);

        // Assert
        $this->assertNull($found);
    }

    public function test_get_active_grid_for_page_returns_grid_with_only_start_date(): void
    {
        // Arrange
        $yesterday = date('Y-m-d H:i:s', strtotime('-1 day'));

        $grid = $this->createPageGrid([
            'start_date' => $yesterday,
            'end_date' => null
        ]);
        $page = $this->createPage(['title' => 'Test Page']);
        $grid->pages(true)->attach($page->id);

        // Act
        $found = $this->repository->getActiveGridForPage($page->id);

        // Assert
        $this->assertNotNull($found);
        $this->assertEquals($grid->id, $found->id);
    }

    public function test_get_active_grid_for_page_returns_grid_with_only_end_date(): void
    {
        // Arrange
        $tomorrow = date('Y-m-d H:i:s', strtotime('+1 day'));

        $grid = $this->createPageGrid([
            'start_date' => null,
            'end_date' => $tomorrow
        ]);
        $page = $this->createPage(['title' => 'Test Page']);
        $grid->pages(true)->attach($page->id);

        // Act
        $found = $this->repository->getActiveGridForPage($page->id);

        // Assert
        $this->assertNotNull($found);
        $this->assertEquals($grid->id, $found->id);
    }

    public function test_get_active_grid_for_territory_returns_grid_when_no_dates_set(): void
    {
        // Arrange
        $grid = $this->createPageGrid(['start_date' => null, 'end_date' => null]);
        $territory = $this->createTerritory(['name' => 'Test Territory']);
        $grid->territories(true)->attach($territory->id);

        // Act
        $found = $this->repository->getActiveGridForTerritory($territory->id);

        // Assert
        $this->assertNotNull($found);
        $this->assertEquals($grid->id, $found->id);
    }

    public function test_get_active_grid_for_territory_returns_grid_when_within_date_range(): void
    {
        // Arrange
        $yesterday = date('Y-m-d H:i:s', strtotime('-1 day'));
        $tomorrow = date('Y-m-d H:i:s', strtotime('+1 day'));

        $grid = $this->createPageGrid([
            'start_date' => $yesterday,
            'end_date' => $tomorrow
        ]);
        $territory = $this->createTerritory(['name' => 'Test Territory']);
        $grid->territories(true)->attach($territory->id);

        // Act
        $found = $this->repository->getActiveGridForTerritory($territory->id);

        // Assert
        $this->assertNotNull($found);
        $this->assertEquals($grid->id, $found->id);
    }

    public function test_get_active_grid_for_territory_returns_null_when_outside_date_range(): void
    {
        // Arrange
        $lastWeek = date('Y-m-d H:i:s', strtotime('-7 days'));
        $yesterday = date('Y-m-d H:i:s', strtotime('-1 day'));

        $grid = $this->createPageGrid([
            'start_date' => $lastWeek,
            'end_date' => $yesterday
        ]);
        $territory = $this->createTerritory(['name' => 'Test Territory']);
        $grid->territories(true)->attach($territory->id);

        // Act
        $found = $this->repository->getActiveGridForTerritory($territory->id);

        // Assert
        $this->assertNull($found);
    }
}