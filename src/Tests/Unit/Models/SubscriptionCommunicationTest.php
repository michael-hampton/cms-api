<?php

namespace App\Tests\Unit\Models;

use App\Enums\Subscriptions\CommunicationTypeEnum;
use App\Models\Segment;
use App\Models\SubscriptionCommunication;
use App\Models\SubscriptionCommunicationDelivery;
use App\Models\SubscriptionCommunicationSchedule;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use PHPUnit\Framework\TestCase;

class SubscriptionCommunicationTest extends FunctionalTestCase
{
    public function test_it_creates_with_required_attributes(): void
    {
        $communication = new SubscriptionCommunication([
            'key'      => 'renewal_reminder_90',
            'name'     => '90 Day Renewal Reminder',
            'type'     => CommunicationTypeEnum::RENEWAL_REMINDER->value,
            'template' => \App\Mail\Subscriptions\RenewalReminderMail::class,
            'channels' => ['email'],
        ]);

        $this->assertSame('renewal_reminder_90', $communication->key);
        $this->assertSame('90 Day Renewal Reminder', $communication->name);
    }

    public function test_type_is_cast_to_enum(): void
    {
        $communication = new SubscriptionCommunication([
            'type' => CommunicationTypeEnum::ACKNOWLEDGEMENT->value,
        ]);

        $this->assertSame(CommunicationTypeEnum::ACKNOWLEDGEMENT->value, $communication->type);
    }

    public function test_channels_are_cast_to_array(): void
    {
        $communication = new SubscriptionCommunication([
            'channels' => ['email', 'in_app'],
        ]);

        $this->assertIsArray($communication->channels);
        $this->assertContains('email', $communication->channels);
        $this->assertContains('in_app', $communication->channels);
    }

    public function test_is_active_is_cast_to_boolean(): void
    {
        $communication = new SubscriptionCommunication(['is_active' => 1]);

        $this->assertTrue($communication->is_active);
    }

    public function test_all_enum_cases_are_valid(): void
    {
        $cases = CommunicationTypeEnum::cases();

        $this->assertNotEmpty($cases);

        foreach ($cases as $case) {
            $resolved = CommunicationTypeEnum::from($case->value);
            $this->assertSame($case, $resolved);
        }
    }
}