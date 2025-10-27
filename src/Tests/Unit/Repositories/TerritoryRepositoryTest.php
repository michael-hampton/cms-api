<?php

namespace App\Tests\Unit\Repositories;

use App\Models\Page;
use App\Models\PageTerritory;
use App\Models\RegionSet;
use App\Models\Territory;
use App\Repositories\TerritoryRepository;
use App\Search\SearchCriteria;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class TerritoryRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private TerritoryRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new TerritoryRepository();
    }

    public function testSearchReturnsPaginatedResultsWithRelations(): void
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

    public function testFindWithRelationsLoadsRegionSet(): void
    {
        // Arrange
        $regionSet = $this->createRegionSet();
        $territory = $this->createTerritory(['region_set_id' => $regionSet->id]);

        // Act
        $result = $this->repository->findWithRelations($territory->id);

        // Assert
        $this->assertNotNull($result);
        $this->assertRelationLoaded($result, 'regionSet');
    }

    public function testGetByRegionSetReturnsTerritoriesForRegionSet(): void
    {
        // Arrange
        $regionSet1 = $this->createRegionSet();
        $regionSet2 = $this->createRegionSet();

        $territory1 = $this->createTerritory(['region_set_id' => $regionSet1->id, 'name' => 'Territory 1']);
        $territory2 = $this->createTerritory(['region_set_id' => $regionSet1->id, 'name' => 'Territory 2']);
        $territory3 = $this->createTerritory(['region_set_id' => $regionSet2->id, 'name' => 'Territory 3']);

        // Act
        $territories = $this->repository->getByRegionSet($regionSet1->id);

        // Assert
        $this->assertCount(2, $territories);
        $this->assertCollectionContains($territories, ['name' => 'Territory 1']);
        $this->assertCollectionContains($territories, ['name' => 'Territory 2']);
        $this->assertCollectionDoesNotContain($territories, ['name' => 'Territory 3']);
    }

    public function testGetActiveReturnsOnlyActiveTerritories(): void
    {
        // Arrange
        $active1 = $this->createTerritory(['name' => 'Active 1', 'is_active' => true]);
        $active2 = $this->createTerritory(['name' => 'Active 2', 'is_active' => true]);
        $inactive = $this->createTerritory(['name' => 'Inactive', 'is_active' => false]);

        // Act
        $territories = $this->repository->getActive();

        // Assert
        $this->assertGreaterThanOrEqual(2, $territories->count());
        foreach ($territories as $territory) {
            $this->assertEquals(1, $territory->is_active);
        }
    }

    public function testFindByCodeReturnsTerritory(): void
    {
        // Arrange
        $territory = $this->createTerritory(['code' => 'UNIQUE-CODE']);

        // Act
        $found = $this->repository->findByCode('UNIQUE-CODE');

        // Assert
        $this->assertNotNull($found);
        $this->assertEquals($territory->id, $found->id);
        $this->assertEquals('UNIQUE-CODE', $found->code);
    }

    public function testFindByCodeFiltersBySite(): void
    {
        // Arrange
        $otherSite = $this->createSite();
        $this->createTerritory(['code' => 'SAME-CODE', 'site_id' => $otherSite->id]);

        // Act
        $found = $this->repository->findByCode('SAME-CODE');

        // Assert
        $this->assertNotEmpty($found);
    }

    public function testCheckDeletableReturnsCanDeleteWhenNoPages(): void
    {
        // Arrange
        $territory = $this->createTerritory();

        // Act
        $result = $this->repository->checkDeletable($territory->id, $this->siteId);

        // Assert
        $this->assertTrue($result['can_delete']);
        $this->assertEquals(0, $result['page_count']);
        $this->assertFalse($result['requires_reassignment']);
    }

    public function testCheckDeletableReturnsRequiresReassignmentWhenPagesExist(): void
    {
        // Arrange
        $territory = $this->createTerritory();
        $page = $this->createPage();

        PageTerritory::create([
            'page_id' => $page->id,
            'territory_id' => $territory->id,
            'site_id' => $this->siteId,
        ]);

        // Act
        $result = $this->repository->checkDeletable($territory->id, $this->siteId);;

        // Assert
        $this->assertFalse($result['can_delete']);
        $this->assertGreaterThan(0, $result['page_count']);
        $this->assertTrue($result['requires_reassignment']);
    }

    public function testCheckDeletableThrowsExceptionWhenNotFound(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Territory not found');

        $this->repository->checkDeletable(99999);
    }

    public function testGetAlternativesInRegionSetExcludesSpecifiedTerritory(): void
    {
        // Arrange
        $regionSet = $this->createRegionSet();
        $territory1 = $this->createTerritory(['region_set_id' => $regionSet->id, 'name' => 'Territory 1']);
        $territory2 = $this->createTerritory(['region_set_id' => $regionSet->id, 'name' => 'Territory 2']);
        $territory3 = $this->createTerritory(['region_set_id' => $regionSet->id, 'name' => 'Territory 3']);

        // Act
        $alternatives = $this->repository->getAlternativesInRegionSet($territory2->id, $regionSet->id);

        // Assert
        $this->assertGreaterThanOrEqual(2, $alternatives->count());
        $this->assertCollectionDoesNotContain($alternatives, ['id' => $territory2->id]);
    }

    public function testGetAlternativesInRegionSetIncludesNullRegionSet(): void
    {
        // Arrange
        $regionSet = $this->createRegionSet();
        $territory1 = $this->createTerritory(['region_set_id' => $regionSet->id]);
        $territory2 = $this->createTerritory(['region_set_id' => null]);

        // Act
        $alternatives = $this->repository->getAlternativesInRegionSet($territory1->id, $regionSet->id);

        // Assert
        $foundNullRegionSet = false;
        foreach ($alternatives as $alt) {
            if ($alt->id === $territory2->id) {
                $foundNullRegionSet = true;
                break;
            }
        }
        $this->assertTrue($foundNullRegionSet);
    }

    public function testReorderTerritoriesUpdatesSortOrder(): void
    {
        // Arrange
        $territory1 = $this->createTerritory(['sort_order' => 0]);
        $territory2 = $this->createTerritory(['sort_order' => 1]);
        $territory3 = $this->createTerritory(['sort_order' => 2]);

        // Act - reverse the order
        $result = $this->repository->reorderTerritories([
            $territory3->id,
            $territory2->id,
            $territory1->id
        ]);

        // Assert
        $this->assertTrue($result);

        $fresh1 = $this->fresh($territory1);
        $fresh2 = $this->fresh($territory2);
        $fresh3 = $this->fresh($territory3);

        $this->assertEquals(2, $fresh1->sort_order);
        $this->assertEquals(1, $fresh2->sort_order);
        $this->assertEquals(0, $fresh3->sort_order);
    }

    public function testBulkUpdateRegionSetUpdatesAllTerritories(): void
    {
        // Arrange
        $oldRegionSet = $this->createRegionSet();
        $newRegionSet = $this->createRegionSet();

        $territory1 = $this->createTerritory(['region_set_id' => $oldRegionSet->id]);
        $territory2 = $this->createTerritory(['region_set_id' => $oldRegionSet->id]);

        // Act
        $result = $this->repository->bulkUpdateRegionSet(
            [$territory1->id, $territory2->id],
            $newRegionSet->id
        );

        // Assert
        $this->assertTrue($result);

        $fresh1 = $this->fresh($territory1);
        $fresh2 = $this->fresh($territory2);

        $this->assertEquals($newRegionSet->id, $fresh1->region_set_id);
        $this->assertEquals($newRegionSet->id, $fresh2->region_set_id);
    }

    public function testSearchAvailablePagesExcludesPagesInOtherTerritories(): void
    {
        // Arrange
        $territory1 = $this->createTerritory();
        $territory2 = $this->createTerritory();

        $page1 = $this->createPage(['title' => 'Available Page', 'site_id' => $this->siteId]);
        $page2 = $this->createPage(['title' => 'Assigned Page', 'site_id' => $this->siteId]);

        // Assign page2 to territory2
        PageTerritory::create([
            'page_id' => $page2->id,
            'territory_id' => $territory2->id,
            'site_id' => $this->siteId,
        ]);

        // Act
        $result = $this->repository->searchAvailablePages($territory1->id, '', 20, 1, $this->siteId);

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

    public function testSearchAvailablePagesFiltersByQuery(): void
    {
        // Arrange
        $territory = $this->createTerritory();
        $page1 = $this->createPage(['title' => 'Laravel Tutorial', 'site_id' => $this->siteId]);
        $page2 = $this->createPage(['title' => 'PHP Guide', 'site_id' => $this->siteId]);

        // Act
        $result = $this->repository->searchAvailablePages($territory->id, 'Laravel', 20, 1, $this->siteId);;

        // Assert
        $this->assertCount(1, $result['data']);
        $this->assertEquals('Laravel Tutorial', $result['data']->first()->title);;
    }

    public function testSlugExistsReturnsTrueWhenExists(): void
    {
        // Arrange
        $territory = $this->createTerritory(['slug' => 'existing-slug']);

        // Act
        $exists = $this->repository->slugExists('existing-slug');

        // Assert
        $this->assertTrue($exists);
    }

    public function testSlugExistsReturnsFalseWhenNotExists(): void
    {
        // Act
        $exists = $this->repository->slugExists('non-existent-slug');

        // Assert
        $this->assertFalse($exists);
    }

    public function testGenerateUniqueSlugCreatesUniqueSlug(): void
    {
        // Arrange
        $this->createTerritory(['name' => 'United Kingdom', 'slug' => 'united-kingdom']);

        // Act
        $newSlug = $this->repository->generateUniqueSlug('United Kingdom', $this->siteId);

        // Assert
        $this->assertEquals('united-kingdom-1', $newSlug);
    }

    public function testGenerateUniqueSlugIncrementsCounter(): void
    {
        // Arrange
        $this->createTerritory(['name' => 'France', 'slug' => 'france']);
        $this->createTerritory(['name' => 'France', 'slug' => 'france-1']);

        // Act
        $newSlug = $this->repository->generateUniqueSlug('France', $this->siteId);

        // Assert
        $this->assertEquals('france-2', $newSlug);
    }

    public function testGenerateUniqueSlugExcludesId(): void
    {
        // Arrange
        $territory = $this->createTerritory(['name' => 'Germany', 'slug' => 'germany']);

        // Act - excluding the existing territory should return same slug
        $newSlug = $this->repository->generateUniqueSlug('Germany', $this->siteId, $territory->id);

        // Assert
        $this->assertEquals('germany', $newSlug);
    }

    public function test_reassign_pages_updates_pivot_table(): void
    {
        // Arrange
        $territory1 = $this->createTerritory();
        $territory2 = $this->createTerritory();
        $page1 = $this->createPage();
        $page2 = $this->createPage();

        $this->attachTerritoryToPage($page1, $territory1);
        $this->attachTerritoryToPage($page2, $territory1);

        // Act
        $result = $this->repository->reassignPages($territory1->id, $territory2->id);

        // Assert
        $this->assertTrue($result);

        $reassigned = PageTerritory::where('territory_id', $territory2->id)->count();
        $this->assertEquals(2, $reassigned);

        $oldAssignments = PageTerritory::where('territory_id', $territory1->id)->count();
        $this->assertEquals(0, $oldAssignments);
    }
}