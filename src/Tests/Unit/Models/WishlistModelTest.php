<?php

namespace App\Tests\Unit\Models;

use App\Models\Product;
use App\Models\ProductOfferBundle;
use App\Models\User;
use App\Models\Wishlist;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class WishlistModelTest extends FunctionalTestCase
{
    use CreatesTestData;

    protected Wishlist $wishlist;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wishlist = new Wishlist([
            'session_id' => 'test_session_123',
            'user_id' => 1,
            'product_id' => 5,
            'site_id' => 1
        ]);
    }

    public function testWishlistCanBeInstantiated()
    {
        $this->assertInstanceOf(Wishlist::class, $this->wishlist);
    }

    public function testWishlistHasCorrectTableName()
    {
        $this->assertEquals('wishlists', $this->wishlist->getTable());
    }


    public function testUserRelationReturnsCorrectType()
    {
        $relation = $this->wishlist->user();
        $this->assertInstanceOf(User::class, $relation);
    }

    public function testSetAndGetSessionId()
    {
        $this->wishlist->session_id = 'new_session_456';
        $this->assertEquals('new_session_456', $this->wishlist->session_id);
    }

    public function testSetAndGetUserId()
    {
        $this->wishlist->user_id = 10;
        $this->assertEquals(10, $this->wishlist->user_id);
    }

    public function testSetAndGetProductId()
    {
        $this->wishlist->product_id = 20;
        $this->assertEquals(20, $this->wishlist->product_id);
    }

    public function testSetAndGetSiteId()
    {
        $this->wishlist->site_id = 3;
        $this->assertEquals(3, $this->wishlist->site_id);
    }

    public function testToArrayIncludesAllAttributes()
    {
        $array = $this->wishlist->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('session_id', $array);
        $this->assertArrayHasKey('user_id', $array);
        $this->assertArrayHasKey('product_id', $array);
        $this->assertArrayHasKey('site_id', $array);
    }

    public function testCreateWishlist()
    {
        $product = $this->createProduct();
        $user =$this->createMember();

        $wishlist = Wishlist::create([
            'session_id' => 'wishlist_session',
            'user_id' => $user->id,
            'product_id' => $product->id,
            'site_id' => 1
        ]);

        $this->assertInstanceOf(Wishlist::class, $wishlist);
        $this->assertEquals('wishlist_session', $wishlist->session_id);
        $this->assertEquals($user->id, $wishlist->user_id);
        $this->assertEquals($product->id, $wishlist->product_id);
    }

    public function testFillMethodPopulatesAttributes()
    {
        $wishlist = new Wishlist();
        $wishlist->fill([
            'session_id' => 'new_session',
            'product_id' => 25
        ]);

        $this->assertEquals('new_session', $wishlist->session_id);
        $this->assertEquals(25, $wishlist->product_id);
    }

    public function testWishlistCanBeCreatedWithoutUserId()
    {
        $product = Product::create(['name' => 'Test Product', 'price' => 99.99, 'site_id' => 1]);;

        $wishlist = new Wishlist([
            'session_id' => 'guest_session',
            'product_id' => $product->id,
            'site_id' => 1
        ]);

        $this->assertEquals('guest_session', $wishlist->session_id);
        $this->assertEquals($product->id, $wishlist->product_id);
        $this->assertNull($wishlist->user_id);
    }

    public function testGetItemTypeReturnsProduct(): void
    {
        $wishlist = new Wishlist([
            'session_id' => 'test_session',
            'user_id' => 1,
            'product_id' => 5,
            'site_id' => 1,
            'item_type' => null
        ]);

        $this->assertEquals('product', $wishlist->getItemType());
    }

    public function testGetItemTypeReturnsOffer(): void
    {
        $wishlist = new Wishlist([
            'session_id' => 'test_session',
            'user_id' => 1,
            'product_id' => 5,
            'site_id' => 1,
            'item_type' => 'offer'
        ]);

        $this->assertEquals('offer', $wishlist->getItemType());
    }

    public function testGetItemTypeReturnsBundle(): void
    {
        $wishlist = new Wishlist([
            'session_id' => 'test_session',
            'user_id' => 1,
            'product_id' => 5,
            'site_id' => 1,
            'item_type' => 'bundle'
        ]);

        $this->assertEquals('bundle', $wishlist->getItemType());
    }

    public function testIsOfferReturnsTrueWhenTypeIsOffer(): void
    {
        $wishlist = new Wishlist([
            'session_id' => 'test_session',
            'user_id' => 1,
            'product_id' => 5,
            'site_id' => 1,
            'item_type' => 'offer'
        ]);

        $this->assertTrue($wishlist->isOffer());
    }

    public function testIsOfferReturnsFalseWhenTypeIsNotOffer(): void
    {
        $wishlist = new Wishlist([
            'session_id' => 'test_session',
            'user_id' => 1,
            'product_id' => 5,
            'site_id' => 1,
            'item_type' => 'bundle'
        ]);

        $this->assertFalse($wishlist->isOffer());
    }

    public function testIsBundleReturnsTrueWhenTypeIsBundle(): void
    {
        $wishlist = new Wishlist([
            'session_id' => 'test_session',
            'user_id' => 1,
            'product_id' => 5,
            'site_id' => 1,
            'item_type' => 'bundle'
        ]);

        $this->assertTrue($wishlist->isBundle());
    }

    public function testIsBundleReturnsFalseWhenTypeIsNotBundle(): void
    {
        $wishlist = new Wishlist([
            'session_id' => 'test_session',
            'user_id' => 1,
            'product_id' => 5,
            'site_id' => 1,
            'item_type' => 'offer'
        ]);

        $this->assertFalse($wishlist->isBundle());
    }

    public function testOfferRelationship(): void
    {
        $offer = $this->createProductOffer($this->createProduct()->id);

        $wishlist = Wishlist::create([
            'session_id' => 'test_session',
            'user_id' => null,
            'product_id' => null,
            'site_id' => 1,
            'item_type' => 'offer',
            'item_id' => $offer->id
        ]);

        $loadedOffer = $wishlist->offer;

        $this->assertNotNull($loadedOffer);
        $this->assertEquals($offer->id, $loadedOffer->id);
    }

    public function testBundleRelationship(): void
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
            'discount_percentage' => 25,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'is_active' => true,
        ]);

        $wishlist = Wishlist::create([
            'session_id' => 'test_session',
            'user_id' => null,
            'product_id' => null,
            'site_id' => 1,
            'item_type' => 'bundle',
            'item_id' => $bundle->id
        ]);

        $loadedBundle = $wishlist->bundle;

        $this->assertNotNull($loadedBundle);
        $this->assertEquals($bundle->id, $loadedBundle->id);
    }

}