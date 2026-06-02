<?php

namespace App\Tests\Functional\Controllers\Subscriptions;

use App\Enums\Subscriptions\CommunicationDeliveryStatus;
use App\Models\Subscription;
use App\Models\SubscriptionCommunication;
use App\Models\SubscriptionCommunicationDelivery;
use App\Models\SubscriptionCommunicationSchedule;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class SubscriptionCommunicationControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testApiResourceRoutesManageSubscriptionCommunications(): void
    {
        $response = $this->postForSite('/api/subscription-communications', $this->communicationPayload([
            'key' => 'renewal-7',
            'name' => 'Renewal 7 days',
        ]));

        $this->assertEquals(201, $response->getStatusCode());
        $created = json_decode($response->getContent(), true);
        $communicationId = $created['data']['communication']['id'];

        $indexResponse = $this->getForSite('/api/subscription-communications');
        $this->assertEquals(200, $indexResponse->getStatusCode());
        $index = json_decode($indexResponse->getContent(), true);
        $this->assertCount(1, $index['communications']);

        $showResponse = $this->getForSite('/api/subscription-communications/' . $communicationId);
        $this->assertEquals(200, $showResponse->getStatusCode());
        $show = json_decode($showResponse->getContent(), true);
        $this->assertEquals('Renewal 7 days', $show['data']['communication']['name']);

        $updateResponse = $this->putForSite(
            '/api/subscription-communications/' . $communicationId,
            $this->communicationPayload([
                'key' => 'renewal-7',
                'name' => 'Updated renewal',
            ])
        );
        $this->assertEquals(200, $updateResponse->getStatusCode());
        $updated = json_decode($updateResponse->getContent(), true);
        $this->assertEquals('Updated renewal', $updated['data']['communication']['name']);

        $deleteResponse = $this->deleteForSite('/api/subscription-communications/' . $communicationId);
        $this->assertEquals(200, $deleteResponse->getStatusCode());
        $this->assertNull(SubscriptionCommunication::find($communicationId));
    }

    public function testItManagesCommunicationSchedules(): void
    {
        $communication = $this->createCommunication();

        $createResponse = $this->postForSite(
            '/api/subscription-communications/' . $communication->id . '/schedules',
            $this->schedulePayload(['name' => 'Initial schedule'])
        );

        $this->assertEquals(201, $createResponse->getStatusCode());
        $created = json_decode($createResponse->getContent(), true);
        $createdSchedule = $created['data']['schedule'] ?? $created['schedule'];
        $scheduleId = $createdSchedule['id'];

        $indexResponse = $this->getForSite('/api/subscription-communications/' . $communication->id . '/schedules');
        $this->assertEquals(200, $indexResponse->getStatusCode());
        $index = json_decode($indexResponse->getContent(), true);
        $this->assertCount(1, $index['schedules']);
        $this->assertEquals('Initial schedule', $index['schedules'][0]['name']);

        $updateResponse = $this->putForSite(
            '/api/subscription-communication-schedules/' . $scheduleId,
            $this->schedulePayload(['name' => 'Updated schedule', 'offset_days' => 14])
        );

        $this->assertEquals(200, $updateResponse->getStatusCode());
        $updated = json_decode($updateResponse->getContent(), true);
        $updatedSchedule = $updated['data']['schedule'] ?? $updated['schedule'];
        $this->assertEquals('Updated schedule', $updatedSchedule['name']);
        $this->assertEquals(14, (int) $updatedSchedule['offset_days']);

        $deleteResponse = $this->deleteForSite('/api/subscription-communication-schedules/' . $scheduleId);
        $this->assertEquals(200, $deleteResponse->getStatusCode());
        $this->assertNull(SubscriptionCommunicationSchedule::find($scheduleId));
    }

    public function testItReturnsSubscriptionCommunicationHistory(): void
    {
        $member = $this->createMember();
        $subscription = Subscription::create([
            'member_id'  => $member->id,
            'site_id'    => $this->siteId,
            'plan_name'  => 'Digital',
            'status'     => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'type'       => 'paid',
        ]);
        $communication = $this->createCommunication(['name' => 'Acknowledgement']);

        SubscriptionCommunicationDelivery::create([
            'subscription_communication_id' => $communication->id,
            'subscription_id'               => $subscription->id,
            'member_id'                     => $member->id,
            'channel'                       => 'email',
            'status'                        => CommunicationDeliveryStatus::SENT->value,
            'sent_at'                       => date('Y-m-d H:i:s'),
        ]);

        $response = $this->getForSite('/api/subscriptions/' . $subscription->id . '/communication-history');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data['data']['history']);
        $this->assertEquals('Acknowledgement', $data['data']['history'][0]['communication']);
        $this->assertEquals('sent', $data['data']['history'][0]['status']);
    }

    public function testItReturnsCommunicationHistoryForCommunicationModal(): void
    {
        $member = $this->createMember();
        $subscription = Subscription::create([
            'member_id'  => $member->id,
            'site_id'    => $this->siteId,
            'plan_name'  => 'Digital',
            'status'     => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'type'       => 'paid',
        ]);
        $communication = $this->createCommunication(['name' => 'Renewal reminder']);

        SubscriptionCommunicationDelivery::create([
            'subscription_communication_id' => $communication->id,
            'subscription_id'               => $subscription->id,
            'member_id'                     => $member->id,
            'channel'                       => 'in_app',
            'status'                        => CommunicationDeliveryStatus::SENT->value,
            'sent_at'                       => date('Y-m-d H:i:s'),
        ]);

        $response = $this->getForSite('/api/subscription-communications/' . $communication->id . '/history');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data['data']['history']);
        $this->assertEquals($subscription->id, $data['data']['history'][0]['subscription_id']);
        $this->assertEquals('Renewal reminder', $data['data']['history'][0]['communication']);
    }

    private function createCommunication(array $overrides = []): SubscriptionCommunication
    {
        return SubscriptionCommunication::create(array_merge($this->communicationPayload(), $overrides));
    }

    private function communicationPayload(array $overrides = []): array
    {
        return array_merge([
            'key' => 'acknowledgement-' . uniqid(),
            'name' => 'Acknowledgement',
            'description' => 'Sent when a subscription is created.',
            'type' => 'acknowledgement',
            'template' => 'subscriptions.acknowledgement',
            'channels' => ['email'],
            'is_active' => true,
            'sort_order' => 1,
        ], $overrides);
    }

    private function schedulePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Default schedule',
            'trigger_type' => 'relative',
            'offset_days' => 7,
            'relative_to' => 'start_date',
            'is_active' => true,
            'sort_order' => 1,
        ], $overrides);
    }
}
