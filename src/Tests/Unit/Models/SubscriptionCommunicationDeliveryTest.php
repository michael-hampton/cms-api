<?php

namespace App\Tests\Unit\Models;

use App\Enums\Subscriptions\CommunicationDeliveryStatus;
use App\Models\SubscriptionCommunicationDelivery;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class SubscriptionCommunicationDeliveryTest extends FunctionalTestCase
{
    public function test_status_is_cast_to_enum(): void
    {
        $delivery = new SubscriptionCommunicationDelivery([
            'status' => CommunicationDeliveryStatus::PENDING->value,
        ]);

        $this->assertSame(CommunicationDeliveryStatus::PENDING->value, $delivery->status);
    }

    public function test_datetime_fields_cast_correctly(): void
    {
        $delivery = new SubscriptionCommunicationDelivery([
            'sent_at'    => '2026-06-01 09:00:00',
            'failed_at'  => '2026-06-01 09:01:00',
            'opened_at'  => '2026-06-01 09:05:00',
            'clicked_at' => '2026-06-01 09:06:00',
        ]);

        $this->assertInstanceOf(\DateTime::class, $delivery->sent_at);
        $this->assertInstanceOf(\DateTime::class, $delivery->failed_at);
        $this->assertInstanceOf(\DateTime::class, $delivery->opened_at);
        $this->assertInstanceOf(\DateTime::class, $delivery->clicked_at);
    }

    public function test_metadata_is_cast_to_array(): void
    {
        $delivery = new SubscriptionCommunicationDelivery([
            'metadata' => ['source' => 'nightly_job'],
        ]);

        $this->assertIsArray($delivery->metadata);
        $this->assertSame('nightly_job', $delivery->metadata['source']);
    }
}