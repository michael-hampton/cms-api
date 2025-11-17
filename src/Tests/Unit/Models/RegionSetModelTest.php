<?php

namespace App\Tests\Unit\Models;

use App\Models\Page;
use App\Models\RegionSet;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class RegionSetModelTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testTerritoriesRelationship()
    {
        $regionSet = $this->createRegionSet();

        $territory = $this->createTerritory(['region_set_id' => $regionSet->id]);

        $territories = $regionSet->territories();

        $this->assertCount(1, $territories);
        $this->assertEquals($territory->id, $territories->first()->id);
    }

    public function testPagesRelationship()
    {
        $regionSet = $this->createRegionSet();

        $page = $this->createPage();
        $this->attachRegionSetToPage($page, $regionSet);

        $pages = $regionSet->pages();

        $this->assertCount(1, $pages);
        $this->assertEquals($page->id, $pages->first()->id);
    }

    public function testActiveScope()
    {
        $this->createRegionSet(['is_active' => true, 'name' => 'Active Region Set']);
        $this->createRegionSet(['is_active' => false]);;

        $activeRegionSets = RegionSet::active()->get();

        $this->assertCount(1, $activeRegionSets);
        $this->assertEquals('Active Region Set', $activeRegionSets->first()->name);
    }

    public function testOrderedScope()
    {
        $this->createRegionSet(['sort_order' => 2]);
        $this->createRegionSet(['sort_order' => 1]);;

        $orderedRegionSets = RegionSet::ordered()->get();

        $this->assertEquals(1, $orderedRegionSets->first()->sort_order);
        $this->assertEquals(2, $orderedRegionSets->last()->sort_order);
    }

    public function testGetTerritoryCount()
    {
        $regionSet = $regionSet = $this->createRegionSet();

        $this->createTerritory(['region_set_id' => $regionSet->id]);

        $this->createTerritory(['region_set_id' => $regionSet->id]);

        $this->assertEquals(2, $regionSet->getTerritoryCount());
    }

    public function testGetPageCount()
    {
        $regionSet = $regionSet = $this->createRegionSet();

        $page = $this->createPage();

        $this->attachRegionSetToPage($page, $regionSet);

        $page2 = Page::create([
            'title' => 'Page 2',
            'slug' => 'page-2',
            'status' => 'published',
            'region_set_id' => $regionSet->id,
            'site_id' => $this->siteId
        ]);

        $this->attachRegionSetToPage($page2, $regionSet);

        $this->assertEquals(2, $regionSet->getPageCount());
    }

    public function testToArrayWithRelations()
    {
        $regionSet = $regionSet = $this->createRegionSet();

        $this->createTerritory(['region_set_id' => $regionSet->id]);

        $array = $regionSet->toArrayWithRelations();

        $this->assertArrayHasKey('territories', $array);
        $this->assertArrayHasKey('territory_count', $array);
        $this->assertArrayHasKey('page_count', $array);
        $this->assertCount(1, $array['territories']);
    }

    public function testCasts()
    {
        $regionSet = $regionSet = $this->createRegionSet();

        $this->assertIsBool($regionSet->is_active);
        $this->assertIsInt($regionSet->sort_order);
    }
}