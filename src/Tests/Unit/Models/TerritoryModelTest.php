<?php

namespace App\Tests\Unit\Models;

use App\Models\Page;
use App\Models\PageTerritory;
use App\Models\RegionSet;
use App\Models\Territory;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class TerritoryModelTest extends FunctionalTestCase
{
    public function testRegionSetRelationship()
    {
        $regionSet = RegionSet::create([
            'name' => 'Test Region Set',
            'slug' => 'test-region-set',
            'is_active' => true,
            'sort_order' => 1,
            'site_id' => $this->siteId
        ]);

        $territory = Territory::create([
            'name' => 'Test Territory',
            'code' => 'TT',
            'slug' => 'test-territory',
            'region_set_id' => $regionSet->id,
            'is_active' => true,
            'sort_order' => 1,
            'site_id' => $this->siteId
        ]);

        $this->assertInstanceOf(RegionSet::class, $territory->regionSet());
        $this->assertEquals($regionSet->id, $territory->regionSet()->id);
    }

    public function testPagesRelationship()
    {
        $regionSet = RegionSet::create(['name' => 'test', 'slug' => 'test', 'is_active' => true, 'sort_order' => 1, 'site_id' => $this->siteId]);;

        $territory = Territory::create([
            'name' => 'Test Territory',
            'code' => 'TT',
            'slug' => 'test-territory',
            'region_set_id' => $regionSet->id,
            'is_active' => true,
            'sort_order' => 1,
            'site_id' => $this->siteId
        ]);

        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'published',
            'territory_id' => $territory->id,
            'site_id' => $this->siteId
        ]);

        $pages = $territory->pages();

        $this->assertCount(1, $pages);
        $this->assertEquals($page->id, $pages->first()->id);
    }

    public function testActiveScope()
    {
        $regionSet = RegionSet::create(['name' => 'test', 'slug' => 'test', 'is_active' => true, 'sort_order' => 1, 'site_id' => $this->siteId]);;

        Territory::create([
            'name' => 'Active Territory',
            'code' => 'AT',
            'slug' => 'test-territory',
            'region_set_id' => $regionSet->id,
            'is_active' => true,
            'sort_order' => 1,
            'site_id' => $this->siteId
        ]);

        Territory::create([
            'name' => 'Inactive Territory',
            'code' => 'IT',
            'slug' => 'test-territory',
            'region_set_id' => 1,
            'is_active' => false,
            'sort_order' => 2,
            'site_id' => $this->siteId
        ]);

        $activeTerritories = Territory::active()->get();

        $this->assertCount(1, $activeTerritories);
        $this->assertEquals('Active Territory', $activeTerritories->first()->name);
    }

    public function testOrderedScope()
    {
        $regionSet = RegionSet::create(['name' => 'test', 'slug' => 'test', 'is_active' => true, 'sort_order' => 1, 'site_id' => $this->siteId]);;

        Territory::create([
            'name' => 'Territory B',
            'code' => 'TB',
            'slug' => 'test-territory',
            'region_set_id' => $regionSet->id,
            'is_active' => true,
            'sort_order' => 2,
            'site_id' => $this->siteId
        ]);

        Territory::create([
            'name' => 'Territory A',
            'code' => 'TA',
            'slug' => 'test-territory',
            'region_set_id' => 1,
            'is_active' => true,
            'sort_order' => 1,
            'site_id' => $this->siteId
        ]);

        $orderedTerritories = Territory::ordered()->get();

        $this->assertEquals(1, $orderedTerritories->first()->sort_order);
        $this->assertEquals(2, $orderedTerritories->last()->sort_order);
    }

    public function testByRegionSetScope()
    {
        $regionSet1 = RegionSet::create([
            'name' => 'Region Set 1',
            'slug' => 'region-set-1',
            'is_active' => true,
            'sort_order' => 1,
            'site_id' => $this->siteId
        ]);

        $regionSet2 = RegionSet::create([
            'name' => 'Region Set 2',
            'slug' => 'region-set-2',
            'is_active' => true,
            'sort_order' => 2,
            'site_id' => $this->siteId
        ]);

        Territory::create([
            'name' => 'Territory 1',
            'code' => 'T1',
            'region_set_id' => $regionSet1->id,
            'slug' => 'test-territory',
            'is_active' => true,
            'sort_order' => 1,
            'site_id' => $this->siteId
        ]);

        Territory::create([
            'name' => 'Territory 2',
            'code' => 'T2',
            'region_set_id' => $regionSet2->id,
            'slug' => 'test-territory',
            'is_active' => true,
            'sort_order' => 2,
            'site_id' => $this->siteId
        ]);

        $territories = Territory::byRegionSet($regionSet1->id)->get();

        $this->assertCount(1, $territories);
        $this->assertEquals('Territory 1', $territories->first()->name);
    }

    public function testGetPageCount()
    {
        $regionSet = RegionSet::create(['name' => 'test', 'slug' => 'test', 'is_active' => true, 'sort_order' => 1, 'site_id' => $this->siteId]);;

        $territory = Territory::create([
            'name' => 'Test Territory',
            'code' => 'TT',
            'region_set_id' => $regionSet->id,
            'slug' => 'test-territory',
            'is_active' => true,
            'sort_order' => 1,
            'site_id' => $this->siteId
        ]);

        $page = Page::create([
            'title' => 'Page 1',
            'slug' => 'page-1',
            'status' => 'published',
            'site_id' => $this->siteId
        ]);

        PageTerritory::create([
            'page_id' => $page->id,
            'territory_id' => $territory->id,
            'site_id' => $this->siteId
        ]);

        $page2 = Page::create([
            'title' => 'Page 2',
            'slug' => 'page-2',
            'status' => 'published',
            'site_id' => $this->siteId
        ]);

        PageTerritory::create([
            'page_id' => $page2->id,
            'territory_id' => $territory->id,
            'site_id' => $this->siteId
        ]);

        $this->assertEquals(2, $territory->getPageCount());
    }

    public function testToArrayWithRelations()
    {
        $regionSet = RegionSet::create([
            'name' => 'Test Region Set',
            'slug' => 'test-region-set',
            'is_active' => true,
            'sort_order' => 1,
            'site_id' => $this->siteId
        ]);

        $territory = Territory::create([
            'name' => 'Test Territory',
            'code' => 'TT',
            'region_set_id' => $regionSet->id,
            'slug' => 'test-territory',
            'is_active' => true,
            'sort_order' => 1,
            'site_id' => $this->siteId
        ]);

        $array = $territory->toArrayWithRelations();

        $this->assertArrayHasKey('region_set', $array);
        $this->assertArrayHasKey('page_count', $array);
        $this->assertNotNull($array['region_set']);
    }

    public function testCasts()
    {
        $regionSet = RegionSet::create(['name' => 'test', 'slug' => 'test', 'is_active' => true, 'sort_order' => 1, 'site_id' => $this->siteId]);;

        $territory = Territory::create([
            'name' => 'Test Territory',
            'code' => 'TT',
            'region_set_id' => $regionSet->id,
            'slug' => 'test-territory',
            'is_active' => 1,
            'sort_order' => '5',
            'site_id' => $this->siteId
        ]);

        $this->assertIsBool($territory->is_active);
        $this->assertIsInt($territory->sort_order);
        $this->assertIsInt($territory->region_set_id);
    }
}