<?php

namespace App\Tests\Unit\Models;

use App\Enums\Subscriptions\CommunicationRelativeTo;
use App\Models\SubscriptionCommunicationSchedule;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use PHPUnit\Framework\TestCase;

class SubscriptionCommunicationScheduleTest extends FunctionalTestCase
{
    public function test_it_creates_with_required_attributes(): void
    {
        $schedule = new SubscriptionCommunicationSchedule([
            'subscription_communication_id' => 1,
            'name'         => '30 days before renewal',
            'trigger_type' => 'relative',
            'offset_days'  => -30,
            'relative_to'  => CommunicationRelativeTo::RENEWAL_DATE->value,
        ]);

        $this->assertSame(-30, $schedule->offset_days);
        $this->assertSame('relative', $schedule->trigger_type);
    }

    public function test_is_active_cast_to_boolean(): void
    {
        $schedule = new SubscriptionCommunicationSchedule(['is_active' => 1]);

        $this->assertTrue($schedule->is_active);
    }

    public function test_fixed_date_cast_to_date(): void
    {
        $schedule = new SubscriptionCommunicationSchedule([
            'fixed_date' => '2026-12-01',
        ]);

        $this->assertInstanceOf(\DateTime::class, $schedule->fixed_date);
    }

    public function test_relative_to_cast_to_enum(): void
    {
        $schedule = new SubscriptionCommunicationSchedule([
            'relative_to' => CommunicationRelativeTo::CCC_EXPIRY_DATE->value,
        ]);

        $this->assertSame(CommunicationRelativeTo::CCC_EXPIRY_DATE->value, $schedule->relative_to);
    }
}