<?php

namespace App\Tests\Unit\Repositories;

use App\Models\PageRegionSet;
use App\Repositories\RegionSetRepository;
use App\Search\SearchCriteria;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class RegionSetRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private RegionSetRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new RegionSetRepository();
    }

    public function test_search_returns_paginated_results_with_relations(): void
    {
        // Arrange
        $regionSet = $this->createRegionSet();
        $territory = $this->createTerritory(['region_set_id' => $regionSet->id]);

        // Act
        $criteria = new SearchCriteria();
        $criteria->setPerPage(10);
        $result = $this->repository->search($criteria);

        // Assert
        $this->assertInstanceOf(\App\Search\PaginatedResult::class, $result);
        $this->assertGreaterThan(0, count($result->getData()));
    }

    public function test_find_with_relations_loads_territories(): void
    {
        // Arrange
        $regionSet = $this->createRegionSet();
        $territory = $this->createTerritory(['region_set_id' => $regionSet->id]);

        // Act
        $result = $this->repository->findWithRelations($regionSet->id);

        // Assert
        $this->assertNotNull($result);
        $this->assertRelationLoaded($result, 'territories');
    }

    public function test_get_active_returns_only_active_region_sets(): void
    {
        // Arrange
        $active1 = $this->createRegionSet(['name' => 'Active 1', 'is_active' => true]);
        $active2 = $this->createRegionSet(['name' => 'Active 2', 'is_active' => true]);
        $inactive = $this->createRegionSet(['name' => 'Inactive', 'is_active' => false]);

        // Act
        $regionSets = $this->repository->getActive();

        // Assert
        $this->assertGreaterThanOrEqual(2, $regionSets->count());
        foreach ($regionSets as $regionSet) {
            $this->assertEquals(1, $regionSet->is_active);
        }
    }

    public function test_check_deletable_returns_can_delete_when_no_territories_or_pages(): void
    {
        // Arrange
        $regionSet = $this->createRegionSet();

        // Act
        $result = $this->repository->checkDeletable($regionSet->id);

        // Assert
        $this->assertTrue($result['can_delete']);
        $this->assertEquals(0, $result['territory_count']);
        $this->assertEquals(0, $result['page_count']);
        $this->assertFalse($result['requires_reassignment']);
    }

    public function test_check_deletable_returns_requires_reassignment_when_territories_exist(): void
    {
        // Arrange
        $regionSet = $this->createRegionSet();
        $territory = $this->createTerritory(['region_set_id' => $regionSet->id]);

        // Act
        $result = $this->repository->checkDeletable($regionSet->id);

        // Assert
        $this->assertFalse($result['can_delete']);
        $this->assertGreaterThan(0, $result['territory_count']);
        $this->assertTrue($result['requires_reassignment']);
    }

    public function test_check_deletable_returns_requires_reassignment_when_pages_exist(): void
    {
        // Arrange
        $regionSet = $this->createRegionSet();
        $page = $this->createPage();
        $this->attachRegionSetToPage($page, $regionSet);

        // Act
        $result = $this->repository->checkDeletable($regionSet->id);

        // Assert
        $this->assertFalse($result['can_delete']);
        $this->assertGreaterThan(0, $result['page_count']);
        $this->assertTrue($result['requires_reassignment']);
    }

    public function test_check_deletable_throws_exception_when_not_found(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Region set not found');

        $this->repository->checkDeletable(99999);
    }

    public function test_get_alternatives_excludes_specified_region_set(): void
    {
        // Arrange
        $regionSet1 = $this->createRegionSet(['name' => 'Region Set 1']);
        $regionSet2 = $this->createRegionSet(['name' => 'Region Set 2']);
        $regionSet3 = $this->createRegionSet(['name' => 'Region Set 3']);

        // Act
        $alternatives = $this->repository->getAlternatives($regionSet2->id);

        // Assert
        $this->assertGreaterThanOrEqual(2, $alternatives->count());
        $this->assertCollectionDoesNotContain($alternatives, ['id' => $regionSet2->id]);
    }

    public function test_reorder_region_sets_updates_sort_order(): void
    {
        // Arrange
        $regionSet1 = $this->createRegionSet(['sort_order' => 0]);
        $regionSet2 = $this->createRegionSet(['sort_order' => 1]);
        $regionSet3 = $this->createRegionSet(['sort_order' => 2]);

        // Act - reverse the order
        $result = $this->repository->reorderRegionSets([
            $regionSet3->id,
            $regionSet2->id,
            $regionSet1->id
        ]);

        // Assert
        $this->assertTrue($result);

        $fresh1 = $this->fresh($regionSet1);
        $fresh2 = $this->fresh($regionSet2);
        $fresh3 = $this->fresh($regionSet3);

        $this->assertEquals(2, $fresh1->sort_order);
        $this->assertEquals(1, $fresh2->sort_order);
        $this->assertEquals(0, $fresh3->sort_order);
    }

    public function test_reorder_region_sets_handles_transaction_rollback(): void
    {
        // Arrange
        $regionSet1 = $this->createRegionSet(['sort_order' => 0]);

        // Act & Assert - invalid ID should cause rollback
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Region set not found');

        $this->repository->reorderRegionSets([
            $regionSet1->id,
            99999 // This will cause an issue
        ]);
    }

    public function test_search_available_pages_excludes_pages_in_other_region_sets(): void
    {
        // Arrange
        $regionSet1 = $this->createRegionSet();
        $regionSet2 = $this->createRegionSet();

        $page1 = $this->createPage(['title' => 'Available Page', 'site_id' => $this->siteId]);
        $page2 = $this->createPage(['title' => 'Assigned Page', 'site_id' => $this->siteId]);

        $this->attachRegionSetToPage($page2, $regionSet2);

        // Act
        $result = $this->repository->searchAvailablePages($regionSet1->id, '', 20, 1, $this->siteId);

        // Assert
        $this->assertIsArray($result);
        $foundPage1 = false;
        $foundPage2 = false;

        foreach ($result['data'] as $page) {
            if ($page->id === $page1->id) $foundPage1 = true;
            if ($page->id === $page2->id) $foundPage2 = true;
        }

        $this->assertTrue($foundPage1);
        $this->assertFalse($foundPage2);
    }

    public function test_search_available_pages_filters_by_query(): void
    {
        // Arrange
        $regionSet = $this->createRegionSet();
        $page1 = $this->createPage(['title' => 'Laravel Tutorial', 'site_id' => $this->siteId]);
        $page2 = $this->createPage(['title' => 'PHP Guide', 'site_id' => $this->siteId]);

        // Act
        $result = $this->repository->searchAvailablePages($regionSet->id, 'Laravel', 20, 1, $this->siteId);

        // Assert
        $this->assertCount(1, $result['data']);
        $this->assertEquals('Laravel Tutorial', $result['data']->first()->title);
    }

    public function test_search_available_pages_filters_by_site(): void
    {
        // Arrange
        $regionSet = $this->createRegionSet();
        $otherSite = $this->createSite();

        $page1 = $this->createPage(['title' => 'Site 1 Page', 'site_id' => $this->siteId]);
        $page2 = $this->createPage(['title' => 'Site 2 Page', 'site_id' => $otherSite->id]);

        // Act
        $result = $this->repository->searchAvailablePages($regionSet->id, '', 20, 1);

        // Assert
        $foundOtherSitePage = false;
        foreach ($result['data'] as $page) {
            if ($page['id'] === $page2->id) {
                $foundOtherSitePage = true;
                break;
            }
        }

        $this->assertFalse($foundOtherSitePage);
    }

    public function test_search_available_pages_paginates_results(): void
    {
        // Arrange
        $regionSet = $this->createRegionSet();

        for ($i = 1; $i <= 25; $i++) {
            $this->createPage(['title' => "Page $i", 'site_id' => $this->siteId]);
        }

        // Act
        $result = $this->repository->searchAvailablePages($regionSet->id, '', 10, 1, $this->siteId);

        // Assert
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('pagination', $result);
        $this->assertArrayHasKey('per_page', $result['pagination']);
        $this->assertArrayHasKey('total', $result['pagination']);
        $this->assertCount(10, $result['data']);
        $this->assertGreaterThanOrEqual(25, $result['pagination']['total']);
    }

    public function test_reassign_pages_updates_pivot_table(): void
    {
        // Arrange
        $regionSet1 = $this->createRegionSet();
        $regionSet2 = $this->createRegionSet();
        $page1 = $this->createPage();
        $page2 = $this->createPage();

        PageRegionSet::create([
            'page_id' => $page1->id,
            'region_set_id' => $regionSet1->id,
            'site_id' => $this->siteId,
        ]);

        PageRegionSet::create([
            'page_id' => $page2->id,
            'region_set_id' => $regionSet1->id,
            'site_id' => $this->siteId,
        ]);

        // Act
        $result = $this->repository->reassignPages($regionSet1->id, $regionSet2->id);

        // Assert
        $this->assertTrue($result);

        $reassigned = PageRegionSet::where('region_set_id', $regionSet2->id)->count();
        $this->assertEquals(2, $reassigned);

        $oldAssignments = PageRegionSet::where('region_set_id', $regionSet1->id)->count();
        $this->assertEquals(0, $oldAssignments);
    }
}