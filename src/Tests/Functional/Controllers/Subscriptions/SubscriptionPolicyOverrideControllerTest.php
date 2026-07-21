<?php

namespace App\Tests\Functional\Controllers\Subscriptions;

use App\Enums\Subscriptions\PolicySettingKey;
use App\Models\ReplacementPolicy;
use App\Services\Subscriptions\Policies\StandardConsumerPolicy;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class SubscriptionPolicyOverrideControllerTest extends FunctionalTestCase
{
    private ReplacementPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        // GoodwillPolicy (seeded as default) has no overridable settings.
        $this->policy = ReplacementPolicy::create([
            'site_id' => $this->siteId,
            'is_default' => false,
            'active' => true,
            'name' => 'Standard Consumer',
            'policy_class' => StandardConsumerPolicy::class,
        ]);
    }

    public function test_index_returns_overridable_policy_settings(): void
    {
        $response = $this->getForSite('/api/crm/subscription-policies/overrides');

        $this->assertResponseStatus(200, $response);
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertIsArray($data['policies']);

        $match = null;
        foreach ($data['policies'] as $row) {
            if ((int) $row['policy_id'] === (int) $this->policy->id) {
                $match = $row;
                break;
            }
        }

        $this->assertNotNull($match);
        $this->assertEquals(StandardConsumerPolicy::class, $match['policy_class']);
        $this->assertNotEmpty($match['settings']);
        $this->assertEquals(PolicySettingKey::PAUSE_ALLOWED->value, $match['settings'][0]['key']);
    }

    public function test_store_and_clear_override(): void
    {
        $store = $this->postForSite('/api/crm/subscription-policies/overrides', [
            'policy_class' => StandardConsumerPolicy::class,
            'setting_key' => PolicySettingKey::PAUSE_ALLOWED->value,
            'value' => false,
            'reason' => 'Functional test override',
        ]);

        $this->assertResponseStatus(201, $store);
        $created = json_decode($store->getContent(), true);
        $this->assertEquals(PolicySettingKey::PAUSE_ALLOWED->value, $created['setting_key']);
        $this->assertFalse((bool) $created['value']);
        $this->assertTrue((bool) $created['active']);

        $history = $this->getForSite(
            '/api/crm/subscription-policies/' . rawurlencode(StandardConsumerPolicy::class) . '/overrides/history'
        );
        $this->assertResponseStatus(200, $history);
        $historyData = json_decode($history->getContent(), true);
        $this->assertNotEmpty($historyData['history']);

        $clear = $this->postForSite('/api/crm/subscription-policies/overrides/clear', [
            'policy_class' => StandardConsumerPolicy::class,
            'setting_key' => PolicySettingKey::PAUSE_ALLOWED->value,
            'reason' => 'Clear functional test override',
        ]);
        $this->assertResponseStatus(200, $clear);
        $cleared = json_decode($clear->getContent(), true);
        $this->assertTrue($cleared['cleared']);
    }

    public function test_store_rejects_unknown_setting_key(): void
    {
        $response = $this->postForSite('/api/crm/subscription-policies/overrides', [
            'policy_class' => StandardConsumerPolicy::class,
            'setting_key' => 'not_a_real_setting',
            'value' => true,
            'reason' => 'Should fail',
        ]);

        $this->assertResponseStatus(422, $response);
    }
}
