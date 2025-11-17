<?php

namespace App\Tests\Unit\Models;

use App\Models\RegionSet;
use App\Models\Territory;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class TerritoryModelTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testRegionSetRelationship()
    {
        $regionSet = $this->createRegionSet();

        $territory = $this->createTerritory(['region_set_id' => $regionSet->id]);

        $this->assertInstanceOf(RegionSet::class, $territory->regionSet());
        $this->assertEquals($regionSet->id, $territory->regionSet()->id);
    }

    public function testPagesRelationship()
    {
        $regionSet = $this->createRegionSet();

        $territory = $this->createTerritory(['region_set_id' => $regionSet->id]);

        $page = $this->createPage();

        $this->attachTerritoryToPage($page, $territory);

        $pages = $territory->pages();

        $this->assertCount(1, $pages);
        $this->assertEquals($page->id, $pages->first()->id);
    }

    public function testActiveScope()
    {
        $regionSet = $this->createRegionSet();

        $this->createTerritory(['region_set_id' => $regionSet->id, 'is_active' => true, 'name' => 'Active Territory']);
        $this->createTerritory(['region_set_id' => $regionSet->id, 'is_active' => false]);

        $activeTerritories = Territory::active()->get();

        $this->assertCount(1, $activeTerritories);
        $this->assertEquals('Active Territory', $activeTerritories->first()->name);
    }

    public function testOrderedScope()
    {
        $regionSet = $this->createRegionSet();
        $this->createTerritory([
            'name' => 'Territory B',
            'region_set_id' => $regionSet->id,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $this->createTerritory([
            'name' => 'Territory A',
            'region_set_id' => $regionSet->id,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $orderedTerritories = Territory::ordered()->get();

        $this->assertEquals(1, $orderedTerritories->first()->sort_order);
        $this->assertEquals(2, $orderedTerritories->last()->sort_order);
    }

    public function testByRegionSetScope()
    {
        $regionSet1 = $this->createRegionSet();
        $regionSet2 = $this->createRegionSet();

        $this->createTerritory(['region_set_id' => $regionSet1->id, 'name' => 'Territory 1']);
        $this->createTerritory(['region_set_id' => $regionSet2->id, 'name' => 'Territory 2']);;

        $territories = Territory::byRegionSet($regionSet1->id)->get();

        $this->assertCount(1, $territories);
        $this->assertEquals('Territory 1', $territories->first()->name);
    }

    public function testGetPageCount()
    {
        $regionSet = $this->createRegionSet();

        $territory = $this->createTerritory(['region_set_id' => $regionSet->id]);

        $page = $this->createPage();

        $this->attachTerritoryToPage($page, $territory);

        $page2 = $this->createPage();
        $this->attachTerritoryToPage($page2, $territory);

        $this->assertEquals(2, $territory->getPageCount());
    }

    public function testToArrayWithRelations()
    {
        $regionSet = $this->createRegionSet();

        $territory = $this->createTerritory(['region_set_id' => $regionSet->id]);

        $array = $territory->toArrayWithRelations();

        $this->assertArrayHasKey('region_set', $array);
        $this->assertArrayHasKey('page_count', $array);
        $this->assertNotNull($array['region_set']);
    }

    public function testCasts()
    {
        $regionSet = $this->createRegionSet();

        $territory = $this->createTerritory(['region_set_id' => $regionSet->id]);

        $this->assertIsBool($territory->is_active);
        $this->assertIsInt($territory->sort_order);
        $this->assertIsInt($territory->region_set_id);
    }
}