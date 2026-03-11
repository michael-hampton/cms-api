<?php

namespace App\Tests\Unit\Models;

use App\Models\Member;
use App\Models\ProductOfferBundle;
use App\Models\ProductOfferBundleItem;
use App\Models\ProductOfferBundleRegionSet;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class ProductOfferBundleModelTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testIsCurrentlyActiveReturnsTrueForActiveBundle(): void
    {
        $bundle = ProductOfferBundle::create([
            'name' => 'Test Bundle',
            'slug' => 'test-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
        ]);

        $this->assertTrue($bundle->isCurrentlyActive());
    }

    public function testIsCurrentlyActiveReturnsFalseForInactiveBundle(): void
    {
        $bundle = ProductOfferBundle::create([
            'name' => 'Test Bundle',
            'slug' => 'test-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => false,
        ]);

        $this->assertFalse($bundle->isCurrentlyActive());
    }

    public function testIsCurrentlyActiveReturnsFalseForExpiredBundle(): void
    {
        $bundle = ProductOfferBundle::create([
            'name' => 'Test Bundle',
            'slug' => 'test-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'end_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'is_active' => true,
        ]);

        $this->assertFalse($bundle->isCurrentlyActive());
    }

    public function testIsCurrentlyActiveReturnsFalseForFutureBundle(): void
    {
        $bundle = ProductOfferBundle::create([
            'name' => 'Test Bundle',
            'slug' => 'test-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'end_date' => date('Y-m-d H:i:s', strtotime('+2 days')),
            'is_active' => true,
        ]);

        $this->assertFalse($bundle->isCurrentlyActive());
    }

    public function testScopeActive(): void
    {
        // Active bundle
        ProductOfferBundle::create([
            'name' => 'Active Bundle',
            'slug' => 'active-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
        ]);

        // Expired bundle
        ProductOfferBundle::create([
            'name' => 'Expired Bundle',
            'slug' => 'expired-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'end_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'is_active' => true,
        ]);

        // Inactive bundle
        ProductOfferBundle::create([
            'name' => 'Inactive Bundle',
            'slug' => 'inactive-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => false,
        ]);

        $activeBundles = ProductOfferBundle::active()->get();

        $this->assertCount(1, $activeBundles);
    }

    public function testScopePublished(): void
    {
        ProductOfferBundle::create([
            'name' => 'Published Bundle',
            'slug' => 'published-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
            'status' => 'published',
        ]);

        ProductOfferBundle::create([
            'name' => 'Pending Bundle',
            'slug' => 'pending-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
            'status' => 'pending',
        ]);

        $published = ProductOfferBundle::published()->get();

        $this->assertCount(1, $published);
        $this->assertEquals('published', $published->first()->status);
    }

    public function testScopePending(): void
    {
        ProductOfferBundle::create([
            'name' => 'Pending Bundle',
            'slug' => 'pending-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
            'status' => 'pending',
        ]);

        ProductOfferBundle::create([
            'name' => 'Published Bundle',
            'slug' => 'published-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
            'status' => 'published',
        ]);

        $pending = ProductOfferBundle::pending()->get();

        $this->assertCount(1, $pending);
        $this->assertEquals('pending', $pending->first()->status);
    }

    public function testScopeRejected(): void
    {
        ProductOfferBundle::create([
            'name' => 'Rejected Bundle',
            'slug' => 'rejected-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
            'status' => 'rejected',
        ]);

        ProductOfferBundle::create([
            'name' => 'Pending Bundle',
            'slug' => 'pending-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
            'status' => 'pending',
        ]);

        $rejected = ProductOfferBundle::rejected()->get();

        $this->assertCount(1, $rejected);
        $this->assertEquals('rejected', $rejected->first()->status);
    }

    public function testItemsRelationship(): void
    {
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();
        $offer1 = $this->createProductOffer($product1->id);
        $offer2 = $this->createProductOffer($product2->id);

        $bundle = ProductOfferBundle::create([
            'name' => 'Test Bundle',
            'slug' => 'test-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
        ]);

        ProductOfferBundleItem::create([
            'bundle_id' => $bundle->id,
            'product_offer_id' => $offer1->id,
            'quantity' => 1,
        ]);

        ProductOfferBundleItem::create([
            'bundle_id' => $bundle->id,
            'product_offer_id' => $offer2->id,
            'quantity' => 2,
        ]);

        $bundle = $bundle->fresh(['items']);

        $this->assertNotNull($bundle->items);
        $this->assertCount(2, $bundle->items);
    }

    public function testPublisherRelationship(): void
    {
        $user = $this->createUser();

        $bundle = ProductOfferBundle::create([
            'name' => 'Test Bundle',
            'slug' => 'test-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
            'status' => 'published',
            'published_by' => $user->id,
            'published_at' => now(),
        ]);

        $bundle = $bundle->fresh(['publisher']);

        $this->assertNotNull($bundle->publisher);
        $this->assertEquals($user->id, $bundle->publisher->id);
    }

    public function testRejectorRelationship(): void
    {
        $user = $this->createUser();

        $bundle = ProductOfferBundle::create([
            'name' => 'Test Bundle',
            'slug' => 'test-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
            'status' => 'rejected',
            'rejected_by' => $user->id,
            'rejected_at' => now(),
            'rejection_reason' => 'Test reason',
        ]);

        $bundle = $bundle->fresh(['rejector']);

        $this->assertNotNull($bundle->rejector);
        $this->assertEquals($user->id, $bundle->rejector->id);
    }

    public function testCanBePublished(): void
    {
        $pendingBundle = ProductOfferBundle::create([
            'name' => 'Pending Bundle',
            'slug' => 'pending-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
            'status' => 'pending',
        ]);

        $publishedBundle = ProductOfferBundle::create([
            'name' => 'Published Bundle',
            'slug' => 'published-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
            'status' => 'published',
        ]);

        $expiredBundle = ProductOfferBundle::create([
            'name' => 'Expired Bundle',
            'slug' => 'expired-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'end_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'is_active' => true,
            'status' => 'pending',
        ]);

        $this->assertTrue($pendingBundle->canBePublished());
        $this->assertFalse($publishedBundle->canBePublished());
        $this->assertFalse($expiredBundle->canBePublished());
    }

    public function testCalculateSavings(): void
    {
        $bundle = ProductOfferBundle::create([
            'name' => 'Test Bundle',
            'slug' => 'test-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
        ]);

        $savings = $bundle->calculateSavings();

        $this->assertEquals(50.00, $savings);
    }

    public function testProductOfferWithNoRegionSetsIsVisibleToAnyMember(): void
    {
        $bundle = $this->createProductOfferBundle();

        $member = new Member(['territory_id' => 99]);

        $bundle->setRelation('regionSets', new \App\Framework\Support\Collection([]));

        $this->assertTrue($bundle->isVisibleToMember($member));
    }

    public function testProductOfferIsVisibleToMemberWithMatchingTerritory(): void
    {
        $bundle = $this->createProductOfferBundle();
        $regionSet = $this->createRegionSet();
        $territory = $this->createTerritory(['region_set_id' => $regionSet->id]);

        ProductOfferBundleRegionSet::create([
            'product_offer_bundle_id' => $bundle->id,
            'region_set_id' => $regionSet->id,
        ]);

        $member = $this->createMember(['territory_id' => $territory->id]);

        $this->assertTrue($bundle->isVisibleToMember($member));
    }

    public function testProductOfferIsNotVisibleToMemberWithNonMatchingTerritory(): void
    {
        $bundle = $this->createProductOfferBundle();
        $regionSet = $this->createRegionSet();
        $territory = $this->createTerritory(['region_set_id' => $regionSet->id]);

        ProductOfferBundleRegionSet::create([
            'product_offer_bundle_id' => $bundle->id,
            'region_set_id' => $regionSet->id,
        ]);

        $otherTerritory = $this->createTerritory();
        $member = $this->createMember(['territory_id' => $otherTerritory->id]);

        $this->assertFalse($bundle->isVisibleToMember($member));
    }

    public function testProductOfferIsVisibleToNullMember(): void
    {
        $bundle = $this->createProductOfferBundle();
        $regionSet = $this->createRegionSet();

        ProductOfferBundleRegionSet::create([
            'product_offer_bundle_id' => $bundle->id,
            'region_set_id' => $regionSet->id,
        ]);

        $this->assertTrue($bundle->isVisibleToMember(null));
    }

    public function testProductOfferIsVisibleToMemberWithNoTerritory(): void
    {
        $bundle = $this->createProductOfferBundle();
        $regionSet = $this->createRegionSet();

        ProductOfferBundleRegionSet::create([
            'product_offer_bundle_id' => $bundle->id,
            'region_set_id' => $regionSet->id,
        ]);

        $member = new Member(); // no territory_id

        $this->assertTrue($bundle->isVisibleToMember($member));
    }

    public function testScopeVisibleToMemberFiltersRestrictedOffers(): void
    {
        $bundle1 = $this->createProductOfferBundle();
        $bundle2 = $this->createProductOfferBundle();

        $regionSet = $this->createRegionSet();
        $territory = $this->createTerritory(['region_set_id' => $regionSet->id]);

        ProductOfferBundleRegionSet::create([
            'product_offer_bundle_id' => $bundle1->id,
            'region_set_id' => $regionSet->id,
        ]);

        $otherTerritory = $this->createTerritory();
        $member = $this->createMember(['territory_id' => $otherTerritory->id]);

        $results = ProductOfferBundle::visibleToMember($member)
            ->get();

        $ids = $results->pluck('id')->toArray();

        $this->assertContains($bundle2->id, $ids);
        $this->assertNotContains($bundle1->id, $ids);
    }

    public function testScopeVisibleToMemberShowsAllForNullMember(): void
    {
        $bundle1 = $this->createProductOfferBundle();
        $bundle2 = $this->createProductOfferBundle();

        $regionSet = $this->createRegionSet();
        ProductOfferBundleRegionSet::create([
            'product_offer_bundle_id' => $bundle1->id,
            'region_set_id' => $regionSet->id,
        ]);

        $results = ProductOfferBundle::visibleToMember(null)
            ->get();

        $ids = $results->pluck('id')->toArray();

        $this->assertContains($bundle2->id, $ids);
        $this->assertContains($bundle1->id, $ids);
    }

    public function testRegionSetsRelationshipForProductOffer(): void
    {
        $bundle = $this->createProductOfferBundle();
        $regionSet = $this->createRegionSet();

        ProductOfferBundleRegionSet::create([
            'product_offer_bundle_id' => $bundle->id,
            'region_set_id' => $regionSet->id,
        ]);

        $bundle->load(['regionSets']);

        $this->assertCount(1, $bundle->regionSets);
        $this->assertEquals($regionSet->id, $bundle->regionSets->first()->id);
    }
}