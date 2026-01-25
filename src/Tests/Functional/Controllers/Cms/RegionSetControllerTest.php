<?php

namespace App\Tests\Functional\Controllers\Cms;

use App\Models\PageRegionSet;
use App\Models\RegionSet;
use App\Models\Territory;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class RegionSetControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

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
                    'is_active' => true,
                    'slug' => 'united-kingdom'
                ],
                [
                    'name' => 'France',
                    'code' => 'FR',
                    'is_active' => true,
                    'slug' => 'france'
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
        $regionSet = $this->createRegionSet(['name' => 'Europe']);

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
        $regionSet = $this->createRegionSet();

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
        $regionSet = $this->createRegionSet();

        $response = $this->deleteForSite("/api/region-sets/{$regionSet->id}");

        $this->assertResponseOk($response);

        // Verify deletion
        $deleted = RegionSet::find($regionSet->id);
        $this->assertNull($deleted);
    }

    public function testDestroyWithDependenciesRequiresReassignment()
    {
        $regionSet1 = $this->createRegionSet();

        $this->createTerritory(['region_set_id' => $regionSet1->id]);

        $response = $this->deleteForSite("/api/region-sets/{$regionSet1->id}");

        $this->assertResponseStatus(500, $response);
    }

    public function testDestroyWithReassignment()
    {
        $regionSet1 = $this->createRegionSet();
        $regionSet2 = $this->createRegionSet();
        $this->createTerritory(['region_set_id' => $regionSet1->id, 'code' => 'GB']);

        // Add page assignment
        $page = $this->createPage();
        $this->attachRegionSetToPage($page, $regionSet1);

        $response = $this->deleteForSite(
            "/api/region-sets/{$regionSet1->id}?reassign_to_region_set_id={$regionSet2->id}"
        );

        $this->assertResponseOk($response);

        // Verify territory was reassigned
        $territory = Territory::where('code', 'GB')->first();
        $this->assertEquals($regionSet2->id, $territory->region_set_id);

        // Verify page was reassigned via pivot table
        $pageAssignment = PageRegionSet::where('page_id', $page->id)
            ->where('region_set_id', $regionSet2->id)
            ->first();
        $this->assertNotNull($pageAssignment);
    }

    public function testCheckDeletable()
    {
        $regionSet = $this->createRegionSet();
        $this->createTerritory(['region_set_id' => $regionSet->id]);

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
        $regionSet1 = $this->createRegionSet();
        $this->createRegionSet();
        $this->createRegionSet();

        $response = $this->getForSite("/api/region-sets/{$regionSet1->id}/alternatives");

        $this->assertResponseOk($response);

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('region_sets', $data['data']);
        $this->assertCount(2, $data['data']['region_sets']);
    }

    public function testDuplicate()
    {
        $regionSet = $this->createRegionSet();
        $this->createTerritory(['region_set_id' => $regionSet->id]);

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

//    public function testReorder()
//    {
//        $regionSet1 = $this->createRegionSet();
//
//        $regionSet2 = $this->createRegionSet();
//
//        $regionSet3 = $this->createRegionSet();
//
//        $orderedIds = [$regionSet3->id, $regionSet1->id, $regionSet2->id];
//
//        $response = $this->postForSite('/api/region-sets/reorder', [
//            'ordered_ids' => $orderedIds
//        ]);
//
//        $this->assertResponseOk($response);
//
//        // Verify order was updated
//        $regionSet3 = $regionSet3->fresh();
//        $regionSet1 = $regionSet1->fresh();
//        $regionSet2 = $regionSet2->fresh();
//
//        $this->assertEquals(0, $regionSet3->sort_order);
//        $this->assertEquals(1, $regionSet1->sort_order);
//        $this->assertEquals(2, $regionSet2->sort_order);
//    }

    public function testGetActive()
    {
        $this->createRegionSet(['is_active' => true]);
        $this->createRegionSet(['is_active' => false]);
        $this->createRegionSet(['is_active' => true]);

        $response = $this->getForSite('/api/region-sets/active');

        $this->assertResponseOk($response);

        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('region_sets', $data['data']);
        $this->assertCount(2, $data['data']['region_sets']);
    }
    public function testSearchAvailablePagesForRegionSet()
    {
        $regionSet = $this->createRegionSet();
       $this->createPage();
       $this->createPage();

        $response = $this->getForSite("/api/region-sets/{$regionSet->id}/search-pages?q=test");

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertGreaterThanOrEqual(1, count($data['data']));
    }

    public function testAssignPagesToRegionSet()
    {
        $regionSet = $this->createRegionSet();
        $page1 = $this->createPage();
        $page2 = $this->createPage();

        $response = $this->postForSite("/api/region-sets/{$regionSet->id}/assign-pages", [
            'page_ids' => [$page1->id, $page2->id]
        ]);

        $this->assertResponseOk($response);

        // Verify assignments in pivot table
        $assignments = PageRegionSet::where('region_set_id', $regionSet->id)->get();
        $this->assertCount(2, $assignments);
    }

    public function testUnassignPagesFromRegionSet()
    {
        $regionSet = $this->createRegionSet();
        $page = $this->createPage();
        $this->attachRegionSetToPage($page, $regionSet);

        $response = $this->postForSite("/api/region-sets/{$regionSet->id}/unassign-pages", [
            'page_ids' => [$page->id]
        ]);

        $this->assertResponseOk($response);

        // Verify unassignment
        $assignment = PageRegionSet::where('page_id', $page->id)
            ->where('region_set_id', $regionSet->id)
            ->first();
        $this->assertNull($assignment);
    }

    public function testAssignPagesWithEmptyArrayReturnsError()
    {
        $regionSet = $this->createRegionSet();

        $response = $this->postForSite("/api/region-sets/{$regionSet->id}/assign-pages", [
            'page_ids' => []
        ]);

        $this->assertResponseStatus(400, $response);
    }

    public function testGetPagesForRegionSet()
    {
        $regionSet = $this->createRegionSet();
        $page1 = $this->createPage(['title' => 'European Page 1']);
        $page2 = $this->createPage(['title' => 'European Page 2']);;
        $page3 = $this->createPage(['title' => 'Non-European Page']);
        $this->attachRegionSetToPage($page1, $regionSet);
        $this->attachRegionSetToPage($page2, $regionSet);

        $response = $this->getForSite("/api/region-sets/{$regionSet->id}/pages");

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('items', $data);
        $this->assertCount(2, $data['items']); // Should only return 2 pages

        // Verify the correct pages are returned
        $titles = array_column($data['items'], 'title');
        $this->assertContains('European Page 1', $titles);
        $this->assertContains('European Page 2', $titles);
        $this->assertNotContains('Non-European Page', $titles);
    }

    public function testBulkDeleteSuccessfully(): void
    {
        $regionSet1 = $this->createRegionSet();
        $regionSet2 = $this->createRegionSet();

        $response = $this->postForSite('/api/region-sets/bulk-delete', [
            'ids' => [$regionSet1->id, $regionSet2->id]
        ]);

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertCount(2, $data['result']['deleted']);

        // Verify deletion
        $this->assertNull(RegionSet::find($regionSet1->id));
        $this->assertNull(RegionSet::find($regionSet2->id));
    }

    public function testBulkDeleteFailsWhenTerritoriesExist(): void
    {
        $regionSet1 = $this->createRegionSet();
        $regionSet2 = $this->createRegionSet();

        $this->createTerritory(['region_set_id' => $regionSet2->id]);

        $response = $this->postForSite('/api/region-sets/bulk-delete', [
            'ids' => [$regionSet1->id, $regionSet2->id]
        ]);

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertCount(1, $data['result']['deleted']);
        $this->assertCount(1, $data['result']['failed']);
        $this->assertStringContainsString('territories', $data['result']['failed'][0]['reason']);

        // Verify regionSet1 deleted, regionSet2 still exists
        $this->assertNull(RegionSet::find($regionSet1->id));
        $this->assertNotNull(RegionSet::find($regionSet2->id));
    }

    public function testBulkDeleteFailsWhenPagesExist(): void
    {
        $regionSet1 = $this->createRegionSet();
        $regionSet2 = $this->createRegionSet();

        $page = $this->createPage();
        $this->attachRegionSetToPage($page, $regionSet2);

        $response = $this->postForSite('/api/region-sets/bulk-delete', [
            'ids' => [$regionSet1->id, $regionSet2->id]
        ]);

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertCount(1, $data['result']['deleted']);
        $this->assertCount(1, $data['result']['failed']);
        $this->assertStringContainsString('pages', $data['result']['failed'][0]['reason']);
    }

    public function testBulkActivateSuccessfully(): void
    {
        $regionSet1 = $this->createRegionSet(['is_active' => false]);
        $regionSet2 = $this->createRegionSet(['is_active' => false]);

        $response = $this->postForSite('/api/region-sets/bulk-activate', [
            'ids' => [$regionSet1->id, $regionSet2->id]
        ]);

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertCount(2, $data['result']['updated']);

        // Verify activation
        $this->assertTrue((bool)RegionSet::find($regionSet1->id)->is_active);
        $this->assertTrue((bool)RegionSet::find($regionSet2->id)->is_active);
    }

    public function testBulkDeactivateSuccessfully(): void
    {
        $regionSet1 = $this->createRegionSet(['is_active' => true]);
        $regionSet2 = $this->createRegionSet(['is_active' => true]);

        $response = $this->postForSite('/api/region-sets/bulk-deactivate', [
            'ids' => [$regionSet1->id, $regionSet2->id]
        ]);

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertCount(2, $data['result']['updated']);

        // Verify deactivation
        $this->assertFalse((bool)RegionSet::find($regionSet1->id)->is_active);
        $this->assertFalse((bool)RegionSet::find($regionSet2->id)->is_active);
    }

    public function testBulkActivateHandlesNotFound(): void
    {
        $regionSet = $this->createRegionSet(['is_active' => false]);

        $response = $this->postForSite('/api/region-sets/bulk-activate', [
            'ids' => [$regionSet->id, 9999]
        ]);

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertCount(1, $data['result']['updated']);
        $this->assertCount(1, $data['result']['failed']);
        $this->assertEquals('Region set not found', $data['result']['failed'][0]['reason']);
    }

    public function testBulkDeactivateHandlesNotFound(): void
    {
        $regionSet = $this->createRegionSet(['is_active' => true]);

        $response = $this->postForSite('/api/region-sets/bulk-deactivate', [
            'ids' => [$regionSet->id, 9999]
        ]);

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertCount(1, $data['result']['updated']);
        $this->assertCount(1, $data['result']['failed']);
        $this->assertEquals('Region set not found', $data['result']['failed'][0]['reason']);
    }

    public function testBulkDeleteValidation(): void
    {
        $response = $this->postForSite('/api/region-sets/bulk-delete', [
            'ids' => []
        ]);

        $this->assertResponseStatus(422, $response);
    }

    public function testBulkActivateValidation(): void
    {
        $response = $this->postForSite('/api/region-sets/bulk-activate', [
            'ids' => 'not-an-array'
        ]);

        $this->assertResponseStatus(422, $response);
    }

    public function testBulkDeactivateValidation(): void
    {
        $response = $this->postForSite('/api/region-sets/bulk-deactivate', [
            'ids' => ['not', 'integers']
        ]);

        $this->assertResponseStatus(422, $response);
    }
}