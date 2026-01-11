<?php

namespace App\Tests\Unit\Repositories;

use App\Models\PageTerritory;
use App\Repositories\Cms\PageTerritoryRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class PageTerritoryRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private PageTerritoryRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new PageTerritoryRepository();
    }

    public function test_sync_territories_removes_existing_associations(): void
    {
        // Arrange
        $page = $this->createPage();
        $oldTerritory = $this->createTerritory();
        $newTerritory = $this->createTerritory();

        // Create old association
        PageTerritory::create([
            'page_id' => $page->id,
            'territory_id' => $oldTerritory->id,
            'site_id' => $this->siteId,
        ]);

        // Act
        $this->repository->syncTerritories($page->id, [$newTerritory->id], $this->siteId);

        // Assert
        $this->assertDatabaseMissing('page_territories', [
            'page_id' => $page->id,
            'territory_id' => $oldTerritory->id,
        ]);
    }

    public function test_sync_territories_creates_new_associations(): void
    {
        // Arrange
        $page = $this->createPage();
        $territory1 = $this->createTerritory();
        $territory2 = $this->createTerritory();

        // Act
        $this->repository->syncTerritories($page->id, [$territory1->id, $territory2->id], $this->siteId);

        // Assert
        $this->assertDatabaseHas('page_territories', [
            'page_id' => $page->id,
            'territory_id' => $territory1->id,
            'site_id' => $this->siteId,
        ]);

        $this->assertDatabaseHas('page_territories', [
            'page_id' => $page->id,
            'territory_id' => $territory2->id,
            'site_id' => $this->siteId,
        ]);
    }

    public function test_sync_territories_handles_empty_array(): void
    {
        // Arrange
        $page = $this->createPage();
        $territory = $this->createTerritory();

        PageTerritory::create([
            'page_id' => $page->id,
            'territory_id' => $territory->id,
            'site_id' => $this->siteId,
        ]);

        // Act
        $this->repository->syncTerritories($page->id, [], $this->siteId);

        // Assert
        $count = $this->countRecords('page_territories', ['page_id' => $page->id]);
        $this->assertEquals(0, $count);
    }

    public function test_assign_pages_creates_associations(): void
    {
        // Arrange
        $territory = $this->createTerritory();
        $page1 = $this->createPage();
        $page2 = $this->createPage();

        // Act
        $this->repository->assignPages($territory->id, [$page1->id, $page2->id], $this->siteId);

        // Assert
        $this->assertDatabaseHas('page_territories', [
            'page_id' => $page1->id,
            'territory_id' => $territory->id,
            'site_id' => $this->siteId,
        ]);

        $this->assertDatabaseHas('page_territories', [
            'page_id' => $page2->id,
            'territory_id' => $territory->id,
            'site_id' => $this->siteId,
        ]);
    }

    public function test_assign_pages_skips_existing_associations(): void
    {
        // Arrange
        $territory = $this->createTerritory();
        $page = $this->createPage();

        // Create existing association
        PageTerritory::create([
            'page_id' => $page->id,
            'territory_id' => $territory->id,
            'site_id' => $this->siteId,
        ]);

        $initialCount = $this->countRecords('page_territories', [
            'page_id' => $page->id,
            'territory_id' => $territory->id,
        ]);

        // Act
        $this->repository->assignPages($territory->id, [$page->id], $this->siteId);

        // Assert - count should not increase
        $finalCount = $this->countRecords('page_territories', [
            'page_id' => $page->id,
            'territory_id' => $territory->id,
        ]);

        $this->assertEquals($initialCount, $finalCount);
    }

    public function test_unassign_pages_removes_associations(): void
    {
        // Arrange
        $territory = $this->createTerritory();
        $page1 = $this->createPage();
        $page2 = $this->createPage();
        $page3 = $this->createPage();

        PageTerritory::create([
            'page_id' => $page1->id,
            'territory_id' => $territory->id,
            'site_id' => $this->siteId,
        ]);

        PageTerritory::create([
            'page_id' => $page2->id,
            'territory_id' => $territory->id,
            'site_id' => $this->siteId,
        ]);

        PageTerritory::create([
            'page_id' => $page3->id,
            'territory_id' => $territory->id,
            'site_id' => $this->siteId,
        ]);

        // Act
        $deleted = $this->repository->unassignPages($territory->id, [$page1->id, $page2->id]);

        // Assert
        $this->assertEquals(2, $deleted);

        $this->assertDatabaseMissing('page_territories', [
            'page_id' => $page1->id,
            'territory_id' => $territory->id,
        ]);

        $this->assertDatabaseMissing('page_territories', [
            'page_id' => $page2->id,
            'territory_id' => $territory->id,
        ]);

        $this->assertDatabaseHas('page_territories', [
            'page_id' => $page3->id,
            'territory_id' => $territory->id,
        ]);
    }

    public function test_unassign_pages_returns_zero_when_no_matches(): void
    {
        // Arrange
        $territory = $this->createTerritory();
        $page = $this->createPage();

        // Act
        $deleted = $this->repository->unassignPages($territory->id, [$page->id]);

        // Assert
        $this->assertEquals(0, $deleted);
    }
}