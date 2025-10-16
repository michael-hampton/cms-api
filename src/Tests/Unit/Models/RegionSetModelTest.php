<?php

namespace App\Tests\Unit\Models;

use App\Models\Page;
use App\Models\RegionSet;
use App\Models\Territory;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class RegionSetModelTest extends FunctionalTestCase
{
    public function testTerritoriesRelationship()
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
            'is_active' => true,
            'sort_order' => 1,
            'site_id' => $this->siteId
        ]);

        $territories = $regionSet->territories();

        $this->assertCount(1, $territories);
        $this->assertEquals($territory->id, $territories->first()->id);
    }

    public function testPagesRelationship()
    {
        $regionSet = RegionSet::create([
            'name' => 'Test Region Set',
            'slug' => 'test-region-set',
            'is_active' => true,
            'sort_order' => 1,
            'site_id' => $this->siteId
        ]);

        $page = Page::create([
            'title' => 'Test Page',
            'slug' => 'test-page',
            'status' => 'published',
            'region_set_id' => $regionSet->id,
            'site_id' => $this->siteId
        ]);

        $pages = $regionSet->pages();

        $this->assertCount(1, $pages);
        $this->assertEquals($page->id, $pages->first()->id);
    }

    public function testActiveScope()
    {
        RegionSet::create([
            'name' => 'Active Region Set',
            'slug' => 'active-region-set',
            'is_active' => true,
            'sort_order' => 1,
            'site_id' => $this->siteId
        ]);

        RegionSet::create([
            'name' => 'Inactive Region Set',
            'slug' => 'inactive-region-set',
            'is_active' => false,
            'sort_order' => 2,
            'site_id' => $this->siteId
        ]);

        $activeRegionSets = RegionSet::active()->get();

        $this->assertCount(1, $activeRegionSets);
        $this->assertEquals('Active Region Set', $activeRegionSets->first()->name);
    }

    public function testOrderedScope()
    {
        RegionSet::create([
            'name' => 'Region Set B',
            'slug' => 'region-set-b',
            'is_active' => true,
            'sort_order' => 2,
            'site_id' => $this->siteId
        ]);

        RegionSet::create([
            'name' => 'Region Set A',
            'slug' => 'region-set-a',
            'is_active' => true,
            'sort_order' => 1,
            'site_id' => $this->siteId
        ]);

        $orderedRegionSets = RegionSet::ordered()->get();

        $this->assertEquals(1, $orderedRegionSets->first()->sort_order);
        $this->assertEquals(2, $orderedRegionSets->last()->sort_order);
    }

    public function testGetTerritoryCount()
    {
        $regionSet = RegionSet::create([
            'name' => 'Test Region Set',
            'slug' => 'test-region-set',
            'is_active' => true,
            'sort_order' => 1,
            'site_id' => $this->siteId
        ]);

        Territory::create([
            'name' => 'Territory 1',
            'code' => 'T1',
            'region_set_id' => $regionSet->id,
            'is_active' => true,
            'sort_order' => 1,
            'site_id' => $this->siteId
        ]);

        Territory::create([
            'name' => 'Territory 2',
            'code' => 'T2',
            'region_set_id' => $regionSet->id,
            'is_active' => true,
            'sort_order' => 2,
            'site_id' => $this->siteId
        ]);

        $this->assertEquals(2, $regionSet->getTerritoryCount());
    }

    public function testGetPageCount()
    {
        $regionSet = RegionSet::create([
            'name' => 'Test Region Set',
            'slug' => 'test-region-set',
            'is_active' => true,
            'sort_order' => 1,
            'site_id' => $this->siteId
        ]);

        Page::create([
            'title' => 'Page 1',
            'slug' => 'page-1',
            'status' => 'published',
            'region_set_id' => $regionSet->id,
            'site_id' => $this->siteId
        ]);

        Page::create([
            'title' => 'Page 2',
            'slug' => 'page-2',
            'status' => 'published',
            'region_set_id' => $regionSet->id,
            'site_id' => $this->siteId
        ]);

        $this->assertEquals(2, $regionSet->getPageCount());
    }

    public function testToArrayWithRelations()
    {
        $regionSet = RegionSet::create([
            'name' => 'Test Region Set',
            'slug' => 'test-region-set',
            'description' => 'Test Description',
            'is_active' => true,
            'sort_order' => 1,
            'site_id' => $this->siteId
        ]);

        Territory::create([
            'name' => 'Territory 1',
            'code' => 'T1',
            'region_set_id' => $regionSet->id,
            'is_active' => true,
            'sort_order' => 1,
            'site_id' => $this->siteId
        ]);

        $array = $regionSet->toArrayWithRelations();

        $this->assertArrayHasKey('territories', $array);
        $this->assertArrayHasKey('territory_count', $array);
        $this->assertArrayHasKey('page_count', $array);
        $this->assertCount(1, $array['territories']);
    }

    public function testCasts()
    {
        $regionSet = RegionSet::create([
            'name' => 'Test Region Set',
            'slug' => 'test-region-set',
            'is_active' => 1,
            'sort_order' => '5',
            'site_id' => $this->siteId
        ]);

        $this->assertIsBool($regionSet->is_active);
        $this->assertIsInt($regionSet->sort_order);
    }
}