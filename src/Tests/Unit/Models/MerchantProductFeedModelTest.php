<?php

namespace App\Tests\Unit\Models;

use App\Models\Merchant;
use App\Models\MerchantProductFeed;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MerchantProductFeedModelTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function test_it_has_correct_table_name()
    {
        $model = new MerchantProductFeed();
        $this->assertEquals('merchant_product_feeds', $model->getTable());
    }

    public function test_it_belongs_to_a_merchant()
    {
        $merchant = $this->createMerchant();

        $feed = $this->createMerchantFeed(['merchant_id' => $merchant->id]);

        $this->assertInstanceOf(Merchant::class, $feed->merchant);
        $this->assertEquals($merchant->id, $feed->merchant->id);
    }

    public function test_it_can_scope_active_feeds()
    {
        $this->createMerchantFeed(['is_active' => true]);
        $this->createMerchantFeed(['is_active' => false]);

        $this->assertCount(1, MerchantProductFeed::active()->get());
    }

    public function test_it_can_scope_feeds_due_for_fetch()
    {
// Due now
        $this->createMerchantFeed(['is_active' => true, 'next_fetch_at' => now_datetime()->subDays(1)->format('Y-m-d H:i:s')]);
// Due (null)
        $this->createMerchantFeed(['is_active' => true, 'next_fetch_at' => null]);
// Not due
        $this->createMerchantFeed(['is_active' => true, 'next_fetch_at' => now_datetime()->addDays(1)->format('Y-m-d H:i:s')]);

        $this->assertCount(2, MerchantProductFeed::dueForFetch()->get());
    }
}