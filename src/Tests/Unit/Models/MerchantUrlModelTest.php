<?php

namespace App\Tests\Unit\Models;

use App\Models\Merchant;
use App\Models\MerchantUrl;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MerchantUrlModelTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function test_it_has_correct_table_name()
    {
        $model = new MerchantUrl();
        $this->assertEquals('merchant_urls', $model->getTable());
    }


    public function test_it_belongs_to_a_merchant()
    {
        $merchant = $this->createMerchant();
        $merchantUrl = $this->createMerchantUrl(['merchant_id' => $merchant->id]);

        $this->assertInstanceOf(Merchant::class, $merchantUrl->merchant);
        $this->assertEquals($merchant->id, $merchantUrl->merchant->id);
    }

    public function test_it_casts_is_primary_to_boolean()
    {
        $merchantUrl = $this->createMerchantUrl(['is_primary' => 1]);

        $this->assertIsBool($merchantUrl->is_primary);
        $this->assertTrue($merchantUrl->is_primary);
    }
}