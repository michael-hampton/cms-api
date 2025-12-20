<?php

namespace App\Tests\Unit\Models;

use App\Models\Campaign;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class CampaignModelTest extends FunctionalTestCase
{
    public function test_campaign_is_active_when_conditions_met(): void
    {
        $campaign = new Campaign([
            'is_active' => true,
            'start_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day'))
        ]);

        $this->assertTrue($campaign->isActive());
    }

    public function test_campaign_is_inactive_when_not_started(): void
    {
        $campaign = new Campaign([
            'is_active' => true,
            'start_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'end_date' => null
        ]);

        $this->assertFalse($campaign->isActive());
    }

    public function test_campaign_is_inactive_when_ended(): void
    {
        $campaign = new Campaign([
            'is_active' => true,
            'start_date' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'end_date' => date('Y-m-d H:i:s', strtotime('-1 day'))
        ]);

        $this->assertFalse($campaign->isActive());
    }

    public function test_campaign_is_inactive_when_flag_false(): void
    {
        $campaign = new Campaign([
            'is_active' => false,
            'start_date' => null,
            'end_date' => null
        ]);

        $this->assertFalse($campaign->isActive());
    }

    public function test_has_ended_returns_true_when_past_end_date(): void
    {
        $campaign = new Campaign([
            'end_date' => date('Y-m-d H:i:s', strtotime('-1 day'))
        ]);

        $this->assertTrue($campaign->hasEnded());
    }

    public function test_has_ended_returns_false_with_no_end_date(): void
    {
        $campaign = new Campaign([
            'end_date' => null
        ]);

        $this->assertFalse($campaign->hasEnded());
    }

    public function test_gates_premium_content_returns_correct_value(): void
    {
        $campaign = new Campaign([
            'gates_premium_content' => true
        ]);

        $this->assertTrue($campaign->gatesPremiumContent());
    }

    public function test_tracking_params_can_be_set_and_retrieved(): void
    {
        $campaign = new Campaign([
            'tracking_params' => ['utm_source' => 'test']
        ]);

        $this->assertEquals('test', $campaign->getTrackingParam('utm_source'));
        $this->assertNull($campaign->getTrackingParam('nonexistent'));
        $this->assertEquals('default', $campaign->getTrackingParam('nonexistent', 'default'));
    }
}