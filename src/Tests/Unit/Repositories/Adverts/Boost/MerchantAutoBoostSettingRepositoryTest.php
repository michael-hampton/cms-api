<?php

namespace App\Tests\Unit\Repositories\Adverts\Boost;

use App\Models\MerchantAutoBoostSetting;
use App\Repositories\Adverts\Boost\MerchantAutoBoostSettingRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MerchantAutoBoostSettingRepositoryTest extends FunctionalTestCase
{
    use CreatesTestData;

    private MerchantAutoBoostSettingRepository $repository;

    public function test_find_by_merchant_returns_setting(): void
    {
        MerchantAutoBoostSetting::create([
            'merchant_id' => 1, 'monthly_budget' => 200.00,
            'goal' => 'maximise_revenue', 'contexts_allowed' => ['listing'],
            'is_enabled' => true,
        ]);

        $setting = $this->repository->findByMerchant(1);

        $this->assertNotNull($setting);
        $this->assertEquals(200.00, $setting->monthly_budget);
    }

    public function test_find_by_merchant_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repository->findByMerchant(99999));
    }

    public function test_upsert_creates_when_not_exists(): void
    {
        $setting = $this->repository->upsert(1, [
            'merchant_id' => 1,
            'monthly_budget' => 150.00,
            'goal' => 'promote_deals',
            'contexts_allowed' => ['deals'],
            'is_enabled' => true,
        ]);

        $this->assertNotNull($setting->id);
        $this->assertEquals(150.00, $setting->monthly_budget);
    }

    public function test_upsert_updates_when_exists(): void
    {
        MerchantAutoBoostSetting::create([
            'merchant_id' => 1, 'monthly_budget' => 100.00,
            'goal' => 'maximise_revenue', 'contexts_allowed' => ['listing'],
            'is_enabled' => false,
        ]);

        $setting = $this->repository->upsert(1, [
            'merchant_id' => 1,
            'monthly_budget' => 300.00,
            'is_enabled' => true,
        ]);

        $this->assertEquals(300.00, $setting->monthly_budget);
        $this->assertTrue($setting->is_enabled);
        $this->assertCount(1, MerchantAutoBoostSetting::where('merchant_id', 1)->get());
    }

    public function test_get_enabled_settings_returns_only_enabled(): void
    {
        MerchantAutoBoostSetting::create([
            'merchant_id' => 1, 'monthly_budget' => 100.00, 'goal' => 'maximise_revenue',
            'contexts_allowed' => ['listing'], 'is_enabled' => true,
        ]);
        MerchantAutoBoostSetting::create([
            'merchant_id' => 2, 'monthly_budget' => 50.00, 'goal' => 'promote_deals',
            'contexts_allowed' => ['deals'], 'is_enabled' => false,
        ]);

        $enabled = $this->repository->getEnabledSettings();

        $this->assertCount(1, $enabled);
        $this->assertEquals(1, $enabled->first()->merchant_id);
    }

    public function test_increment_budget_used_adds_to_existing_value(): void
    {
        MerchantAutoBoostSetting::create([
            'merchant_id' => 1, 'monthly_budget' => 200.00, 'goal' => 'maximise_revenue',
            'contexts_allowed' => ['listing'], 'is_enabled' => true,
            'budget_used_this_month' => 50.00,
        ]);

        $this->repository->incrementBudgetUsed(1, 35.00);

        $setting = $this->repository->findByMerchant(1);
        $this->assertEquals(85.00, $setting->budget_used_this_month);
    }

    public function test_increment_budget_used_from_zero(): void
    {
        MerchantAutoBoostSetting::create([
            'merchant_id' => 1, 'monthly_budget' => 200.00, 'goal' => 'maximise_revenue',
            'contexts_allowed' => ['listing'], 'is_enabled' => true,
            'budget_used_this_month' => 0,
        ]);

        $this->repository->incrementBudgetUsed(1, 35.00);

        $setting = $this->repository->findByMerchant(1);
        $this->assertEquals(35.00, $setting->budget_used_this_month);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new MerchantAutoBoostSettingRepository();
    }
}