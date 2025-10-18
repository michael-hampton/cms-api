<?php

namespace App\Tests\Functional\Controllers;

use App\Models\Model;
use App\Models\Page;
use App\Models\PageTerritory;
use App\Models\RegionSet;
use App\Models\Territory;

class TerritoryControllerTest extends FunctionalTestCase
{
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
        Territory::create([
            'name' => 'United Kingdom',
            'code' => 'GB',
            'region_set_id' => $this->regionSet->id,
            'site_id' => $this->siteId
        ]);

        Territory::create([
            'name' => 'France',
            'code' => 'FR',
            'region_set_id' => $this->regionSet->id,
            'site_id' => $this->siteId
        ]);

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
        $territory = Territory::create([
            'name' => 'United Kingdom',
            'code' => 'GB',
            'region_set_id' => $this->regionSet->id,
            'site_id' => $this->siteId
        ]);

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
        $territory = Territory::create([
            'name' => 'United Kingdom',
            'code' => 'GB',
            'region_set_id' => $this->regionSet->id,
            'site_id' => $this->siteId
        ]);

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
        $territory = Territory::create([
            'name' => 'United Kingdom',
            'code' => 'GB',
            'region_set_id' => $this->regionSet->id,
            'site_id' => $this->siteId
        ]);

        $response = $this->deleteForSite("/api/territories/{$territory->id}");

        $this->assertResponseOk($response);

        // Verify deletion
        $deleted = Territory::find($territory->id);
        $this->assertNull($deleted);
    }

    public function testDestroyWithDependenciesRequiresReassignment()
    {
        $territory = Territory::create([
            'name' => 'United Kingdom',
            'code' => 'GB',
            'region_set_id' => $this->regionSet->id,
            'site_id' => $this->siteId
        ]);

        // Create a page assigned to this territory
        Page::create([
            'title' => 'UK Page',
            'slug' => 'uk-page',
            'status' => 'published',
            'territory_id' => $territory->id,
            'site_id' => $this->siteId
        ]);

        $response = $this->deleteForSite("/api/territories/{$territory->id}");

        $this->assertResponseStatus(500, $response);
    }

    public function testDestroyWithReassignment()
    {
        $territory1 = Territory::create([
            'name' => 'United Kingdom',
            'code' => 'GB',
            'region_set_id' => $this->regionSet->id,
            'site_id' => $this->siteId
        ]);

        $territory2 = Territory::create([
            'name' => 'France',
            'code' => 'FR',
            'region_set_id' => $this->regionSet->id,
            'site_id' => $this->siteId
        ]);

        $page = Page::create([
            'title' => 'UK Page',
            'slug' => 'uk-page',
            'status' => 'published',
            'territory_id' => $territory1->id,
            'site_id' => $this->siteId
        ]);

        $response = $this->deleteForSite(
            "/api/territories/{$territory1->id}?reassign_to_territory_id={$territory2->id}"
        );

        $this->assertResponseOk($response);

        // Verify page was reassigned
        $page = $page->fresh();

        $this->assertEquals($territory2->id, $page->territory_id);
    }

    public function testCheckDeletable()
    {
        $territory = Territory::create([
            'name' => 'United Kingdom',
            'code' => 'GB',
            'region_set_id' => $this->regionSet->id,
            'site_id' => $this->siteId
        ]);

        Page::create([
            'title' => 'UK Page',
            'slug' => 'uk-page',
            'status' => 'published',
            'territory_id' => $territory->id,
            'site_id' => $this->siteId
        ]);

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
        $territory1 = Territory::create([
            'name' => 'United Kingdom',
            'code' => 'GB',
            'region_set_id' => $this->regionSet->id,
            'site_id' => $this->siteId
        ]);

        Territory::create([
            'name' => 'France',
            'code' => 'FR',
            'region_set_id' => $this->regionSet->id,
            'site_id' => $this->siteId
        ]);

        Territory::create([
            'name' => 'Germany',
            'code' => 'DE',
            'region_set_id' => $this->regionSet->id,
            'site_id' => $this->siteId
        ]);

        $response = $this->getForSite("/api/territories/{$territory1->id}/alternatives");

        $this->assertResponseOk($response);

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('territories', $data['data']);
        $this->assertCount(2, $data['data']['territories']);
    }

    public function testReorder()
    {
        $territory1 = Territory::create([
            'name' => 'United Kingdom',
            'code' => 'GB',
            'region_set_id' => $this->regionSet->id,
            'sort_order' => 0,
            'site_id' => $this->siteId
        ]);

        $territory2 = Territory::create([
            'name' => 'France',
            'code' => 'FR',
            'region_set_id' => $this->regionSet->id,
            'sort_order' => 1,
            'site_id' => $this->siteId
        ]);

        $territory3 = Territory::create([
            'name' => 'Germany',
            'code' => 'DE',
            'region_set_id' => $this->regionSet->id,
            'sort_order' => 2,
            'site_id' => $this->siteId
        ]);

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
        $regionSet2 = RegionSet::create([
            'name' => 'Asia',
            'slug' => 'asia',
            'site_id' => $this->siteId
        ]);

        $territory1 = Territory::create([
            'name' => 'United Kingdom',
            'code' => 'GB',
            'region_set_id' => $this->regionSet->id,
            'site_id' => $this->siteId
        ]);

        $territory2 = Territory::create([
            'name' => 'France',
            'code' => 'FR',
            'region_set_id' => $this->regionSet->id,
            'site_id' => $this->siteId
        ]);

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
        Territory::create([
            'name' => 'United Kingdom',
            'code' => 'GB',
            'region_set_id' => $this->regionSet->id,
            'site_id' => $this->siteId
        ]);

        Territory::create([
            'name' => 'France',
            'code' => 'FR',
            'region_set_id' => $this->regionSet->id,
            'site_id' => $this->siteId
        ]);

        // Create another region set with a territory
        $regionSet2 = RegionSet::create([
            'name' => 'Asia',
            'slug' => 'asia',
            'site_id' => $this->siteId
        ]);

        Territory::create([
            'name' => 'Japan',
            'code' => 'JP',
            'region_set_id' => $regionSet2->id,
            'site_id' => $this->siteId
        ]);

        $response = $this->getForSite("/api/territories/by-region-set/{$this->regionSet->id}");

        $this->assertResponseOk($response);

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('territories', $data['data']);
        $this->assertCount(2, $data['data']['territories']);
    }

    public function testGetActive()
    {
        Territory::create([
            'name' => 'United Kingdom',
            'code' => 'GB',
            'region_set_id' => $this->regionSet->id,
            'is_active' => true,
            'site_id' => $this->siteId
        ]);

        Territory::create([
            'name' => 'France',
            'code' => 'FR',
            'region_set_id' => $this->regionSet->id,
            'is_active' => false,
            'site_id' => $this->siteId
        ]);

        Territory::create([
            'name' => 'Germany',
            'code' => 'DE',
            'region_set_id' => $this->regionSet->id,
            'is_active' => true,
            'site_id' => $this->siteId
        ]);

        $response = $this->getForSite('/api/territories/active');

        $this->assertResponseOk($response);

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('territories', $data['data']);
        $this->assertCount(2, $data['data']['territories']);
    }

    public function testSearchAvailablePagesForTerritory()
    {
        $territory = Territory::create([
            'name' => 'United Kingdom',
            'code' => 'GB',
            'region_set_id' => $this->regionSet->id,
            'site_id' => $this->siteId
        ]);

        Page::create([
            'title' => 'Regional Test Page',
            'slug' => 'regional-test-page',
            'status' => 'published',
            'region_set_id' => $this->regionSet->id,
            'site_id' => $this->siteId
        ]);

        $response = $this->getForSite("/api/territories/{$territory->id}/search-pages?q=test");

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('data', $data);
    }

    public function testAssignPagesToTerritory()
    {
        $territory = Territory::create([
            'name' => 'United Kingdom',
            'code' => 'GB',
            'region_set_id' => $this->regionSet->id,
            'site_id' => $this->siteId
        ]);

        $page1 = Page::create([
            'title' => 'Regional Page 1',
            'slug' => 'regional-page-1',
            'status' => 'published',
            'site_id' => $this->siteId
        ]);

        $page2 = Page::create([
            'title' => 'Regional Page 2',
            'slug' => 'regional-page-2',
            'status' => 'published',
            'site_id' => $this->siteId
        ]);

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
        $territory = Territory::create([
            'name' => 'United Kingdom',
            'code' => 'GB',
            'region_set_id' => $this->regionSet->id,
            'site_id' => $this->siteId
        ]);

        $page = Page::create([
            'title' => 'UK Page',
            'slug' => 'uk-page',
            'status' => 'published',
            'site_id' => $this->siteId
        ]);

        PageTerritory::create([
            'page_id' => $page->id,
            'territory_id' => $territory->id,
            'site_id' => $this->siteId
        ]);

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
        $territory = Territory::create([
            'name' => 'United Kingdom',
            'code' => 'GB',
            'region_set_id' => $this->regionSet->id,
            'site_id' => $this->siteId
        ]);

        $page1 = Page::create([
            'title' => 'UK Page 1',
            'slug' => 'uk-page-1',
            'status' => 'published',
            'site_id' => $this->siteId
        ]);

        $page2 = Page::create([
            'title' => 'UK Page 2',
            'slug' => 'uk-page-2',
            'status' => 'published',
            'site_id' => $this->siteId
        ]);

        // Create pivot table entries
        PageTerritory::create([
            'page_id' => $page1->id,
            'territory_id' => $territory->id,
            'site_id' => $this->siteId
        ]);

        PageTerritory::create([
            'page_id' => $page2->id,
            'territory_id' => $territory->id,
            'site_id' => $this->siteId
        ]);

        $response = $this->getForSite("/api/territories/{$territory->id}/pages");

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('items', $data);
        $this->assertCount(2, $data['items']);
    }
}