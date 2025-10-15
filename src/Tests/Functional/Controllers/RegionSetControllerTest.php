<?php

namespace App\Tests\Functional\Controllers;

use App\Models\Page;
use App\Models\RegionSet;
use App\Models\Territory;

class RegionSetControllerTest extends FunctionalTestCase
{
    public function testIndexReturnsRegionSets()
    {
        $dateStr = (new \DateTime())->format('Y-m-d H:i:s');
        // Create test region sets
        RegionSet::create([
            'name' => 'Europe ' . $dateStr,
            'slug' => 'europe-'.str_replace('-', '', $dateStr),
            'description' => 'European region',
            'site_id' => $this->siteId
        ]);

        RegionSet::create([
            'name' => 'Asia Pacific ' . $dateStr,
            'slug' => 'asia-pacific-' . str_replace('-', '', $dateStr),
            'description' => 'Asia Pacific region',
            'site_id' => $this->siteId
        ]);

        $response = $this->getForSite('/api/region-sets');

        $this->assertResponseOk($response);
        $this->assertJsonResponse($response);

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('items', $data);
        $this->assertCount(2, $data['items']);
    }

    public function testStoreCreatesRegionSet()
    {
        $requestData = [
            'name' => 'North America',
            'description' => 'North American region',
            'is_active' => true,
            'site_id' => $this->siteId
        ];

        $response = $this->postForSite('/api/region-sets', $requestData);

        $this->assertResponseStatus(201, $response);
        $this->assertJsonResponse($response);

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('region_set', $data['data']);
        $this->assertEquals('North America', $data['data']['region_set']['name']);
        $this->assertEquals('north-america', $data['data']['region_set']['slug']);
    }

    public function testStoreCreatesRegionSetWithTerritories()
    {
        $requestData = [
            'name' => 'Europe',
            'description' => 'European region',
            'is_active' => true,
            'site_id' => $this->siteId,
            'territories' => [
                [
                    'name' => 'United Kingdom',
                    'code' => 'GB',
                    'is_active' => true
                ],
                [
                    'name' => 'France',
                    'code' => 'FR',
                    'is_active' => true
                ]
            ]
        ];

        $response = $this->postForSite('/api/region-sets', $requestData);

        $this->assertResponseStatus(201, $response);

        $data = json_decode($response->getContent(), true);
        $regionSetId = $data['data']['region_set']['id'];

        // Verify territories were created
        $territories = Territory::where('region_set_id', $regionSetId)->get();
        $this->assertCount(2, $territories);
    }

    public function testShowReturnsRegionSet()
    {
        $regionSet = RegionSet::create([
            'name' => 'Europe',
            'slug' => 'europe',
            'description' => 'European region',
            'site_id' => $this->siteId
        ]);

        $response = $this->getForSite("/api/region-sets/{$regionSet->id}");

        $this->assertResponseOk($response);
        $this->assertJsonResponse($response);

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('region_set', $data['data']);
        $this->assertEquals('Europe', $data['data']['region_set']['name']);
    }

    public function testShowReturnsNotFoundForInvalidId()
    {
        $response = $this->getForSite('/api/region-sets/999999');

        $this->assertResponseStatus(404, $response);
    }

    public function testUpdateModifiesRegionSet()
    {
        $regionSet = RegionSet::create([
            'name' => 'Europe',
            'slug' => 'europe',
            'description' => 'European region',
            'site_id' => $this->siteId
        ]);

        $updateData = [
            'name' => 'Updated Europe',
            'description' => 'Updated European region',
            'is_active' => false
        ];

        $response = $this->putForSite("/api/region-sets/{$regionSet->id}", $updateData);

        $this->assertResponseOk($response);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals('Updated Europe', $data['data']['region_set']['name']);
        $this->assertEquals('Updated European region', $data['data']['region_set']['description']);
        $this->assertFalse($data['data']['region_set']['is_active']);
    }

    public function testDestroyDeletesRegionSet()
    {
        $regionSet = RegionSet::create([
            'name' => 'Europe',
            'slug' => 'europe',
            'description' => 'European region',
            'site_id' => $this->siteId
        ]);

        $response = $this->deleteForSite("/api/region-sets/{$regionSet->id}");

        $this->assertResponseOk($response);

        // Verify deletion
        $deleted = RegionSet::find($regionSet->id);
        $this->assertNull($deleted);
    }

    public function testDestroyWithDependenciesRequiresReassignment()
    {
        $regionSet1 = RegionSet::create([
            'name' => 'Europe',
            'slug' => 'europe',
            'site_id' => $this->siteId
        ]);

        Territory::create([
            'name' => 'United Kingdom',
            'code' => 'GB',
            'region_set_id' => $regionSet1->id,
            'site_id' => $this->siteId
        ]);

        $response = $this->deleteForSite("/api/region-sets/{$regionSet1->id}");

        $this->assertResponseStatus(500, $response);
    }

    public function testDestroyWithReassignment()
    {
        $regionSet1 = RegionSet::create([
            'name' => 'Europe',
            'slug' => 'europe',
            'site_id' => $this->siteId
        ]);

        $regionSet2 = RegionSet::create([
            'name' => 'Asia',
            'slug' => 'asia',
            'site_id' => $this->siteId
        ]);

        Territory::create([
            'name' => 'United Kingdom',
            'code' => 'GB',
            'region_set_id' => $regionSet1->id,
            'site_id' => $this->siteId
        ]);

        $response = $this->deleteForSite(
            "/api/region-sets/{$regionSet1->id}?reassign_to_region_set_id={$regionSet2->id}"
        );

        $this->assertResponseOk($response);

        // Verify territory was reassigned
        $territory = Territory::where('code', 'GB')->first();
        $this->assertEquals($regionSet2->id, $territory->region_set_id);
    }

    public function testCheckDeletable()
    {
        $regionSet = RegionSet::create([
            'name' => 'Europe',
            'slug' => 'europe',
            'site_id' => $this->siteId
        ]);

        Territory::create([
            'name' => 'United Kingdom',
            'code' => 'GB',
            'region_set_id' => $regionSet->id,
            'site_id' => $this->siteId
        ]);

        $response = $this->getForSite("/api/region-sets/{$regionSet->id}/check-deletable");

        $this->assertResponseOk($response);

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertFalse($data['data']['can_delete']);
        $this->assertEquals(1, $data['data']['territory_count']);
        $this->assertTrue($data['data']['requires_reassignment']);
    }

    public function testGetAlternatives()
    {
        $regionSet1 = RegionSet::create([
            'name' => 'Europe',
            'slug' => 'europe',
            'site_id' => $this->siteId
        ]);

        $regionSet2 = RegionSet::create([
            'name' => 'Asia',
            'slug' => 'asia',
            'site_id' => $this->siteId
        ]);

        $regionSet3 = RegionSet::create([
            'name' => 'North America',
            'slug' => 'north-america',
            'site_id' => $this->siteId
        ]);

        $response = $this->getForSite("/api/region-sets/{$regionSet1->id}/alternatives");

        $this->assertResponseOk($response);

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('region_sets', $data['data']);
        $this->assertCount(2, $data['data']['region_sets']);
    }

    public function testDuplicate()
    {
        $regionSet = RegionSet::create([
            'name' => 'Europe',
            'slug' => 'europe',
            'description' => 'European region',
            'site_id' => $this->siteId
        ]);

        Territory::create([
            'name' => 'United Kingdom',
            'code' => 'GB',
            'region_set_id' => $regionSet->id,
            'site_id' => $this->siteId
        ]);

        $response = $this->postForSite("/api/region-sets/{$regionSet->id}/duplicate", [
            'name' => 'Europe Copy'
        ]);

        $this->assertResponseStatus(201, $response);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals('Europe Copy', $data['data']['region_set']['name']);

        // Verify territories were duplicated
        $newRegionSetId = $data['data']['region_set']['id'];
        $territories = Territory::where('region_set_id', $newRegionSetId)->get();
        $this->assertCount(1, $territories);
    }

    public function testReorder()
    {
        $regionSet1 = RegionSet::create([
            'name' => 'Europe',
            'slug' => 'europe',
            'sort_order' => 0,
            'site_id' => $this->siteId
        ]);

        $regionSet2 = RegionSet::create([
            'name' => 'Asia',
            'slug' => 'asia',
            'sort_order' => 1,
            'site_id' => $this->siteId
        ]);

        $regionSet3 = RegionSet::create([
            'name' => 'North America',
            'slug' => 'north-america',
            'sort_order' => 2,
            'site_id' => $this->siteId
        ]);

        $orderedIds = [$regionSet3->id, $regionSet1->id, $regionSet2->id];

        $response = $this->postForSite('/api/region-sets/reorder', [
            'ordered_ids' => $orderedIds
        ]);

        $this->assertResponseOk($response);

        // Verify order was updated
        $regionSet3 = $regionSet3->fresh();
        $regionSet1 = $regionSet1->fresh();
        $regionSet2 = $regionSet2->fresh();

        $this->assertEquals(0, $regionSet3->sort_order);
        $this->assertEquals(1, $regionSet1->sort_order);
        $this->assertEquals(2, $regionSet2->sort_order);
    }

    public function testGetActive()
    {
        RegionSet::create([
            'name' => 'Europe',
            'slug' => 'europe',
            'is_active' => true,
            'site_id' => $this->siteId
        ]);

        RegionSet::create([
            'name' => 'Asia',
            'slug' => 'asia',
            'is_active' => false,
            'site_id' => $this->siteId
        ]);

        RegionSet::create([
            'name' => 'North America',
            'slug' => 'north-america',
            'is_active' => true,
            'site_id' => $this->siteId
        ]);

        $response = $this->getForSite('/api/region-sets/active');

        $this->assertResponseOk($response);

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('region_sets', $data['data']);
        $this->assertCount(2, $data['data']['region_sets']);
    }

    // tests/Functional/Controllers/RegionSetControllerTest.php - Add these test methods

    public function testGetPagesForRegionSet()
    {
        $regionSet = RegionSet::create([
            'name' => 'Europe',
            'slug' => 'europe',
            'site_id' => $this->siteId
        ]);

        Page::create([
            'title' => 'European Page 1',
            'slug' => 'european-page-1',
            'status' => 'published',
            'region_set_id' => $regionSet->id,
            'site_id' => $this->siteId
        ]);

        Page::create([
            'title' => 'European Page 2',
            'slug' => 'european-page-2',
            'status' => 'published',
            'region_set_id' => $regionSet->id,
            'site_id' => $this->siteId
        ]);

        $response = $this->getForSite("/api/region-sets/{$regionSet->id}/pages");

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('items', $data);
        $this->assertCount(2, $data['items']);
    }

    public function testSearchAvailablePagesForRegionSet()
    {
        $regionSet = RegionSet::create([
            'name' => 'Europe',
            'slug' => 'europe',
            'site_id' => $this->siteId
        ]);

        Page::create([
            'title' => 'Global Test Page',
            'slug' => 'global-test-page',
            'status' => 'published',
            'site_id' => $this->siteId
        ]);

        Page::create([
            'title' => 'Another Page',
            'slug' => 'another-page',
            'status' => 'published',
            'site_id' => $this->siteId
        ]);

        $response = $this->getForSite("/api/region-sets/{$regionSet->id}/search-pages?q=test");

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertGreaterThanOrEqual(1, count($data['data']));
    }

    public function testAssignPagesToRegionSet()
    {
        $regionSet = RegionSet::create([
            'name' => 'Europe',
            'slug' => 'europe',
            'site_id' => $this->siteId
        ]);

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

        $response = $this->postForSite("/api/region-sets/{$regionSet->id}/assign-pages", [
            'page_ids' => [$page1->id, $page2->id]
        ]);

        $this->assertResponseOk($response);

        // Verify pages were assigned
        $page1 = $page1->fresh();
        $page2 = $page2->fresh();

        $this->assertEquals($regionSet->id, $page1->region_set_id);
        $this->assertEquals($regionSet->id, $page2->region_set_id);
    }

    public function testUnassignPagesFromRegionSet()
    {
        $regionSet = RegionSet::create([
            'name' => 'Europe',
            'slug' => 'europe',
            'site_id' => $this->siteId
        ]);

        $page = Page::create([
            'title' => 'European Page',
            'slug' => 'european-page',
            'status' => 'published',
            'region_set_id' => $regionSet->id,
            'site_id' => $this->siteId
        ]);

        $response = $this->postForSite("/api/region-sets/{$regionSet->id}/unassign-pages", [
            'page_ids' => [$page->id]
        ]);

        $this->assertResponseOk($response);

        // Verify page was unassigned
        $page = $page->fresh();
        $this->assertNull($page->region_set_id);
    }

    public function testAssignPagesWithEmptyArrayReturnsError()
    {
        $regionSet = RegionSet::create([
            'name' => 'Europe',
            'slug' => 'europe',
            'site_id' => $this->siteId
        ]);

        $response = $this->postForSite("/api/region-sets/{$regionSet->id}/assign-pages", [
            'page_ids' => []
        ]);

        $this->assertResponseStatus(400, $response);
    }
}