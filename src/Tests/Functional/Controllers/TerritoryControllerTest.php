<?php

namespace App\Tests\Functional\Controllers;

use App\Models\Model;
use App\Models\Page;
use App\Models\PageTerritory;
use App\Models\RegionSet;
use App\Models\Territory;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use function PHPUnit\Framework\assertArrayHasKey;

class TerritoryControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    protected Model $regionSet;

    protected function setUp(): void
    {
        parent::setUp();

        $dateStr = (new \DateTime())->format('Y-m-d H:i:s');

        // Create a region set for testing
        $this->regionSet = RegionSet::create([
            'name' => 'Europe ' . $dateStr,
            'slug' => 'europe-'.str_replace('-', '', $dateStr),
            'site_id' => $this->siteId
        ]);
    }

    public function testIndexReturnsTerritories()
    {
        $this->createTerritory();
        $this->createTerritory();

        $response = $this->getForSite('/api/territories');

        $this->assertResponseOk($response);
        $this->assertJsonResponse($response);

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('items', $data);
        $this->assertCount(2, $data['items']);
    }

    public function testStoreCreatesTerritory()
    {
        $requestData = [
            'name' => 'Germany',
            'code' => 'DE',
            'region_set_id' => $this->regionSet->id,
            'is_active' => true,
            'site_id' => $this->siteId
        ];

        $response = $this->postForSite('/api/territories', $requestData);

        $this->assertResponseStatus(201, $response);
        $this->assertJsonResponse($response);

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('territory', $data['data']);
        $this->assertEquals('Germany', $data['data']['territory']['name']);
        $this->assertEquals('DE', $data['data']['territory']['code']);
    }

    public function testShowReturnsTerritory()
    {
        $territory = $this->createTerritory(['name' => 'United Kingdom']);;

        $response = $this->getForSite("/api/territories/{$territory->id}");

        $this->assertResponseOk($response);
        $this->assertJsonResponse($response);

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('territory', $data['data']);
        $this->assertEquals('United Kingdom', $data['data']['territory']['name']);
    }

    public function testShowReturnsNotFoundForInvalidId()
    {
        $response = $this->getForSite('/api/territories/999999');

        $this->assertResponseStatus(404, $response);
    }

    public function testUpdateModifiesTerritory()
    {
        $territory = $this->createTerritory();

        $updateData = [
            'name' => 'United Kingdom (GB)',
            'is_active' => false
        ];

        $response = $this->putForSite("/api/territories/{$territory->id}", $updateData);

        $this->assertResponseOk($response);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals('United Kingdom (GB)', $data['data']['territory']['name']);
        $this->assertFalse($data['data']['territory']['is_active']);
    }

    public function testDestroyDeletesTerritory()
    {
        $territory = $this->createTerritory();

        $response = $this->deleteForSite("/api/territories/{$territory->id}");

        $this->assertResponseOk($response);

        // Verify deletion
        $deleted = Territory::find($territory->id);
        $this->assertNull($deleted);
    }

    public function testDestroyWithDependenciesRequiresReassignment()
    {
        $territory = $this->createTerritory();
        $page = $this->createPage();
        $this->attachTerritoryToPage($page, $territory);

        $response = $this->deleteForSite("/api/territories/{$territory->id}");

        $this->assertResponseStatus(500, $response);
    }

    public function testDestroyWithReassignment()
    {
        $territory1 = $this->createTerritory(['region_set_id' => $this->regionSet->id]);
        $territory2 = $this->createTerritory(['region_set_id' => $this->regionSet->id]);
        $page = $this->createPage();
        $this->attachTerritoryToPage($page, $territory1);

        $response = $this->deleteForSite(
            "/api/territories/{$territory1->id}?reassign_to_territory_id={$territory2->id}"
        );

        $this->assertResponseOk($response);

        // Verify page was reassigned via pivot table
        $pageAssignment = PageTerritory::where('page_id', $page->id)
            ->where('territory_id', $territory2->id)
            ->first();
        $this->assertNotNull($pageAssignment);

        // Verify old assignment is gone
        $oldAssignment = PageTerritory::where('page_id', $page->id)
            ->where('territory_id', $territory1->id)
            ->first();
        $this->assertNull($oldAssignment);
    }

    public function testCheckDeletable()
    {
        $territory = $this->createTerritory();
        $page = $this->createPage();
        $this->attachTerritoryToPage($page, $territory);

        $response = $this->getForSite("/api/territories/{$territory->id}/check-deletable");

        $this->assertResponseOk($response);

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertFalse($data['data']['can_delete']);
        $this->assertEquals(1, $data['data']['page_count']);
        $this->assertTrue($data['data']['requires_reassignment']);
    }

    public function testGetAlternatives()
    {
        $territory1 = $this->createTerritory(['region_set_id' => $this->regionSet->id]);
       $this->createTerritory(['region_set_id' => $this->regionSet->id, 'code' => 'DE']);
        $this->createTerritory(['region_set_id' => $this->regionSet->id, 'code' => 'FR']);

        $response = $this->getForSite("/api/territories/{$territory1->id}/alternatives");

        $this->assertResponseOk($response);

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('territories', $data['data']);
        $this->assertCount(2, $data['data']['territories']);
    }

    public function testReorder()
    {
        $territory1 = $this->createTerritory();

        $territory2 = $this->createTerritory();

        $territory3 = $this->createTerritory();

        $orderedIds = [$territory3->id, $territory1->id, $territory2->id];

        $response = $this->postForSite('/api/territories/reorder', [
            'ordered_ids' => $orderedIds
        ]);

        $this->assertResponseOk($response);

        // Verify order was updated
        $territory3 = $territory3->fresh();
        $territory1 = $territory1->fresh();
        $territory2 = $territory2->fresh();

        $this->assertEquals(0, $territory3->sort_order);
        $this->assertEquals(1, $territory1->sort_order);
        $this->assertEquals(2, $territory2->sort_order);
    }

    public function testBulkUpdateRegionSet()
    {
        $regionSet2 = $this->createRegionSet();

        $territory1 = $this->createTerritory();

        $territory2 = $this->createTerritory();

        $response = $this->postForSite('/api/territories/bulk-update-region-set', [
            'territory_ids' => [$territory1->id, $territory2->id],
            'region_set_id' => $regionSet2->id
        ]);

        $this->assertResponseOk($response);

        // Verify territories were updated
        $territory1 = $territory1->fresh();
        $territory2 = $territory2->fresh();

        $this->assertEquals($regionSet2->id, $territory1->region_set_id);
        $this->assertEquals($regionSet2->id, $territory2->region_set_id);
    }

    public function testGetByRegionSet()
    {
        $this->createTerritory(['region_set_id' => $this->regionSet->id]);
        $this->createTerritory(['region_set_id' => $this->regionSet->id]);

        // Create another region set with a territory
        $regionSet2 = $this->createRegionSet();
        $this->createTerritory(['region_set_id' => $this->regionSet2->id]);

        $response = $this->getForSite("/api/territories/by-region-set/{$this->regionSet->id}");

        $this->assertResponseOk($response);

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('territories', $data['data']);
        $this->assertCount(2, $data['data']['territories']);
    }

    public function testGetActive()
    {
        $this->createTerritory(['is_active' => true]);
        $this->createTerritory(['is_active' => false]);
        $this->createTerritory(['is_active' => true]);

        $response = $this->getForSite('/api/territories/active');

        $this->assertResponseOk($response);

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('territories', $data['data']);
        $this->assertCount(2, $data['data']['territories']);
    }

    public function testSearchAvailablePagesForTerritory()
    {
        $territory = $this->createTerritory();
       $page = $this->createPage();
       $this->attachTerritoryToPage($page, $territory);

        $response = $this->getForSite("/api/territories/{$territory->id}/search-pages?q=test");

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('data', $data);
        assertArrayHasKey('pages', $data['data']);
        self::assertEquals($page->id, $data['data']['pages'][0]['id']);;
    }

    public function testAssignPagesToTerritory()
    {
        $territory = $this->createTerritory();

        $page1 = $this->createPage();

        $page2 = $this->createPage();

        $response = $this->postForSite("/api/territories/{$territory->id}/assign-pages", [
            'page_ids' => [$page1->id, $page2->id]
        ]);

        $this->assertResponseOk($response);

        // Verify assignments
        $assignments = PageTerritory::where('territory_id', $territory->id)->get();
        $this->assertCount(2, $assignments);
    }

    public function testUnassignPagesFromTerritory()
    {
        $territory = $this->createTerritory();
        $page = $this->createPage();
        $this->attachTerritoryToPage($page, $territory);

        $response = $this->postForSite("/api/territories/{$territory->id}/unassign-pages", [
            'page_ids' => [$page->id]
        ]);

        $this->assertResponseOk($response);

        // Verify unassignment
        $assignment = PageTerritory::where('page_id', $page->id)
            ->where('territory_id', $territory->id)
            ->first();
        $this->assertNull($assignment);
    }

    public function testGetPagesForTerritory()
    {
        $territory = $this->createTerritory();
        $page1 = $this->createPage();
        $page2 = $this->createPage();
        $this->attachTerritoryToPage($page1, $territory);
        $this->attachTerritoryToPage($page2, $territory);

        $response = $this->getForSite("/api/territories/{$territory->id}/pages");

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('items', $data);
        $this->assertCount(2, $data['items']);
    }
}