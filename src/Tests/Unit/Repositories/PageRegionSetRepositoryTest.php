<?php

namespace App\Tests\Unit\Repositories;

use App\Models\PageRegionSet;
use App\Models\RegionSet;
use App\Repositories\PageRegionSetRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class PageRegionSetRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private PageRegionSetRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new PageRegionSetRepository();
    }

    /**
     * Create a test region set
     */
    protected function createRegionSet(array $overrides = []): RegionSet
    {
        return RegionSet::create(array_merge([
            'site_id' => $this->siteId,
            'slug' => 'region-set-' . uniqid(),
            'name' => 'Test Region Set',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $overrides));
    }

    /**
     * Attach region set to page
     */
    protected function attachRegionSetToPage($page, RegionSet $regionSet): void
    {
        PageRegionSet::create([
            'page_id' => $page->id,
            'region_set_id' => $regionSet->id,
            'site_id' => $this->siteId,
        ]);
    }

    /** @test */
    public function test_sync_region_sets_removes_old_and_adds_new(): void
    {
        // Arrange
        $page = $this->createPage();
        $oldRegionSet = $this->createRegionSet();
        $this->attachRegionSetToPage($page, $oldRegionSet);

        $newRegionSet1 = $this->createRegionSet();
        $newRegionSet2 = $this->createRegionSet();

        // Act
        $this->repository->syncRegionSets(
            $page->id,
            [$newRegionSet1->id, $newRegionSet2->id],
            $this->siteId
        );

        // Assert
        $pageRegionSets = PageRegionSet::where('page_id', $page->id)->get();
        $this->assertCount(2, $pageRegionSets);

        // Old association should be removed
        $this->assertDatabaseMissing('page_region_sets', [
            'page_id' => $page->id,
            'region_set_id' => $oldRegionSet->id
        ]);

        // New associations should exist
        $this->assertDatabaseHas('page_region_sets', [
            'page_id' => $page->id,
            'region_set_id' => $newRegionSet1->id,
            'site_id' => $this->siteId
        ]);
        $this->assertDatabaseHas('page_region_sets', [
            'page_id' => $page->id,
            'region_set_id' => $newRegionSet2->id,
            'site_id' => $this->siteId
        ]);
    }

    /** @test */
    public function test_sync_region_sets_handles_empty_array(): void
    {
        // Arrange
        $page = $this->createPage();
        $regionSet = $this->createRegionSet();
        $this->attachRegionSetToPage($page, $regionSet);

        // Act
        $this->repository->syncRegionSets($page->id, [], $this->siteId);

        // Assert
        $count = $this->countRecords('page_region_sets', ['page_id' => $page->id]);
        $this->assertEquals(0, $count);
    }

    /** @test */
    public function test_sync_region_sets_stores_correct_site_id(): void
    {
        // Arrange
        $page = $this->createPage();
        $regionSet = $this->createRegionSet();

        // Act
        $this->repository->syncRegionSets($page->id, [$regionSet->id], $this->siteId);

        // Assert
        $pageRegionSet = PageRegionSet::where('page_id', $page->id)->first();
        $this->assertEquals($this->siteId, $pageRegionSet->site_id);
    }

    /** @test */
    public function test_sync_region_sets_replaces_all_existing_associations(): void
    {
        // Arrange
        $page = $this->createPage();
        $regionSet1 = $this->createRegionSet();
        $regionSet2 = $this->createRegionSet();
        $regionSet3 = $this->createRegionSet();

        // Add initial associations
        $this->attachRegionSetToPage($page, $regionSet1);
        $this->attachRegionSetToPage($page, $regionSet2);

        // Act - replace with different region sets
        $this->repository->syncRegionSets($page->id, [$regionSet3->id], $this->siteId);

        // Assert
        $count = $this->countRecords('page_region_sets', ['page_id' => $page->id]);
        $this->assertEquals(1, $count);

        $this->assertDatabaseHas('page_region_sets', [
            'page_id' => $page->id,
            'region_set_id' => $regionSet3->id
        ]);
    }

    /** @test */
    public function test_assign_pages_creates_new_associations(): void
    {
        // Arrange
        $regionSet = $this->createRegionSet();
        $page1 = $this->createPage();
        $page2 = $this->createPage();

        // Act
        $this->repository->assignPages($regionSet->id, [$page1->id, $page2->id], $this->siteId);

        // Assert
        $this->assertDatabaseHas('page_region_sets', [
            'page_id' => $page1->id,
            'region_set_id' => $regionSet->id,
            'site_id' => $this->siteId
        ]);
        $this->assertDatabaseHas('page_region_sets', [
            'page_id' => $page2->id,
            'region_set_id' => $regionSet->id,
            'site_id' => $this->siteId
        ]);
    }

    /** @test */
    public function test_assign_pages_does_not_duplicate_existing_associations(): void
    {
        // Arrange
        $regionSet = $this->createRegionSet();
        $page = $this->createPage();

        // Create initial association
        $this->attachRegionSetToPage($page, $regionSet);

        $initialCount = $this->countRecords('page_region_sets', [
            'page_id' => $page->id,
            'region_set_id' => $regionSet->id
        ]);

        // Act - try to assign again
        $this->repository->assignPages($regionSet->id, [$page->id], $this->siteId);

        // Assert - should not create duplicate
        $finalCount = $this->countRecords('page_region_sets', [
            'page_id' => $page->id,
            'region_set_id' => $regionSet->id
        ]);

        $this->assertEquals($initialCount, $finalCount);
    }

    /** @test */
    public function test_assign_pages_handles_empty_array(): void
    {
        // Arrange
        $regionSet = $this->createRegionSet();

        // Act
        $this->repository->assignPages($regionSet->id, [], $this->siteId);

        // Assert - should not throw error
        $count = $this->countRecords('page_region_sets', ['region_set_id' => $regionSet->id]);
        $this->assertEquals(0, $count);
    }

    /** @test */
    public function test_assign_pages_can_assign_multiple_pages(): void
    {
        // Arrange
        $regionSet = $this->createRegionSet();
        $pages = $this->createPages(5);
        $pageIds = array_map(fn($page) => $page->id, $pages);

        // Act
        $this->repository->assignPages($regionSet->id, $pageIds, $this->siteId);

        // Assert
        $count = $this->countRecords('page_region_sets', ['region_set_id' => $regionSet->id]);
        $this->assertEquals(5, $count);
    }

    /** @test */
    public function test_unassign_pages_removes_associations(): void
    {
        // Arrange
        $regionSet = $this->createRegionSet();
        $page1 = $this->createPage();
        $page2 = $this->createPage();
        $page3 = $this->createPage();

        $this->attachRegionSetToPage($page1, $regionSet);
        $this->attachRegionSetToPage($page2, $regionSet);
        $this->attachRegionSetToPage($page3, $regionSet);

        // Act - unassign 2 pages
        $deleted = $this->repository->unassignPages($regionSet->id, [$page1->id, $page2->id]);

        // Assert
        $this->assertEquals(2, $deleted);

        $this->assertDatabaseMissing('page_region_sets', [
            'page_id' => $page1->id,
            'region_set_id' => $regionSet->id
        ]);
        $this->assertDatabaseMissing('page_region_sets', [
            'page_id' => $page2->id,
            'region_set_id' => $regionSet->id
        ]);

        // Page 3 should still be assigned
        $this->assertDatabaseHas('page_region_sets', [
            'page_id' => $page3->id,
            'region_set_id' => $regionSet->id
        ]);
    }

    /** @test */
    public function test_unassign_pages_returns_zero_when_no_matches(): void
    {
        // Arrange
        $regionSet = $this->createRegionSet();
        $page = $this->createPage();

        // Act - try to unassign page that's not assigned
        $deleted = $this->repository->unassignPages($regionSet->id, [$page->id]);

        // Assert
        $this->assertEquals(0, $deleted);
    }

    /** @test */
    public function test_unassign_pages_handles_empty_array(): void
    {
        // Arrange
        $regionSet = $this->createRegionSet();
        $page = $this->createPage();
        $this->attachRegionSetToPage($page, $regionSet);

        // Act
        $deleted = $this->repository->unassignPages($regionSet->id, []);

        // Assert
        $this->assertEquals(0, $deleted);

        // Original assignment should still exist
        $this->assertDatabaseHas('page_region_sets', [
            'page_id' => $page->id,
            'region_set_id' => $regionSet->id
        ]);
    }

    /** @test */
    public function test_unassign_pages_only_affects_specified_region_set(): void
    {
        // Arrange
        $regionSet1 = $this->createRegionSet();
        $regionSet2 = $this->createRegionSet();
        $page = $this->createPage();

        $this->attachRegionSetToPage($page, $regionSet1);
        $this->attachRegionSetToPage($page, $regionSet2);

        // Act - unassign from regionSet1 only
        $this->repository->unassignPages($regionSet1->id, [$page->id]);

        // Assert
        $this->assertDatabaseMissing('page_region_sets', [
            'page_id' => $page->id,
            'region_set_id' => $regionSet1->id
        ]);

        // Assignment to regionSet2 should remain
        $this->assertDatabaseHas('page_region_sets', [
            'page_id' => $page->id,
            'region_set_id' => $regionSet2->id
        ]);
    }
}