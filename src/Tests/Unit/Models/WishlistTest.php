<?php

namespace App\Tests\Unit\Models;

use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class WishlistTest extends FunctionalTestCase
{
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
        $product = Product::create(['name' => 'Test Product', 'price' => 99.99, 'site_id' => 1]);;
        $user = User::create(['name' => 'John', 'email' => '<EMAIL>', 'password' => '<PASSWORD>', 'site_id' => 1]);;

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
}