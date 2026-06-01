<?php

namespace App\Tests\Unit\Repositories\Subscription;

use App\Enums\Subscriptions\CommunicationDeliveryStatus;
use App\Models\Member;
use App\Models\Subscription;
use App\Models\SubscriptionCommunication;
use App\Models\SubscriptionCommunicationDelivery;
use App\Models\SubscriptionCommunicationSchedule;
use App\Repositories\Subscriptions\SubscriptionCommunicationDeliveryRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class SubscriptionCommunicationDeliveryRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private SubscriptionCommunicationDeliveryRepository $repository;
    private Member       $member;
    private Subscription $subscription;
    private SubscriptionCommunication $communication;
    private SubscriptionCommunicationSchedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new SubscriptionCommunicationDeliveryRepository();
        $this->member     = $this->createMember();

        $this->subscription = Subscription::create([
            'member_id'  => $this->member->id,
            'site_id'    => $this->siteId,
            'plan_name'  => 'Premium',
            'status'     => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price'      => 29.99,
            'currency'   => 'USD',
        ]);

        $this->communication = SubscriptionCommunication::create([
            'key'        => 'renewal_90_' . uniqid(),
            'name'       => 'Renewal 90 Day',
            'type'       => 'renewal_reminder',
            'template'   => \App\Mail\Subscriptions\RenewalReminderMail::class,
            'channels'   => ['email'],
            'is_active'  => true,
            'sort_order' => 0,
        ]);

        $this->schedule = SubscriptionCommunicationSchedule::create([
            'name'         => '90 day reminder',
            'trigger_type' => 'relative',
            'offset_days'  => -90,
            'relative_to'  => 'renewal_date',
            'is_active'    => true,
            'sort_order'   => 1,
            'subscription_communication_id' => $this->communication->id,
        ]);
    }

    public function test_has_already_sent_returns_false_when_no_delivery_exists(): void
    {
        $result = $this->repository->hasAlreadySent(
            $this->subscription->id,
            $this->communication->id,
            $this->schedule->id,
        );

        $this->assertFalse($result);
    }

    public function test_has_already_sent_returns_true_for_sent_delivery(): void
    {
        SubscriptionCommunicationDelivery::create($this->deliveryAttributes([
            'status' => CommunicationDeliveryStatus::SENT->value,
        ]));

        $result = $this->repository->hasAlreadySent(
            $this->subscription->id,
            $this->communication->id,
            $this->schedule->id,
        );

        $this->assertTrue($result);
    }

    public function test_has_already_sent_returns_true_for_pending_delivery(): void
    {
        SubscriptionCommunicationDelivery::create($this->deliveryAttributes([
            'status' => CommunicationDeliveryStatus::PENDING->value,
        ]));

        $result = $this->repository->hasAlreadySent(
            $this->subscription->id,
            $this->communication->id,
            $this->schedule->id,
        );

        $this->assertTrue($result);
    }

    public function test_has_already_sent_returns_false_for_failed_delivery(): void
    {
        SubscriptionCommunicationDelivery::create($this->deliveryAttributes([
            'status' => CommunicationDeliveryStatus::FAILED->value,
        ]));

        $result = $this->repository->hasAlreadySent(
            $this->subscription->id,
            $this->communication->id,
            $this->schedule->id,
        );

        $this->assertFalse($result);
    }

    public function test_record_pending_creates_delivery_with_pending_status(): void
    {
        $delivery = $this->repository->recordPending(
            subscriptionId:  $this->subscription->id,
            memberId:        $this->member->id,
            communicationId: $this->communication->id,
            scheduleId:      $this->schedule->id,
            channel:         'email',
            recipientEmail:  'test@example.com',
        );

        $this->assertNotNull($delivery->id);
        $this->assertEquals(CommunicationDeliveryStatus::PENDING->value, $delivery->status);
        $this->assertEquals($this->subscription->id, $delivery->subscription_id);
        $this->assertEquals($this->communication->id, $delivery->subscription_communication_id);
        $this->assertEquals($this->schedule->id, $delivery->subscription_communication_schedule_id);
    }

    public function test_record_pending_generates_unique_token(): void
    {
        $delivery1 = $this->repository->recordPending(
            subscriptionId:  $this->subscription->id,
            memberId:        $this->member->id,
            communicationId: $this->communication->id,
            scheduleId:      null,
            channel:         'email',
        );

        $delivery2 = $this->repository->recordPending(
            subscriptionId:  $this->subscription->id,
            memberId:        $this->member->id,
            communicationId: $this->communication->id,
            scheduleId:      null,
            channel:         'in_app',
        );

        $this->assertNotEmpty($delivery1->token);
        $this->assertNotEmpty($delivery2->token);
        $this->assertNotEquals($delivery1->token, $delivery2->token);
    }

    public function test_mark_sent_updates_status_and_sets_sent_at(): void
    {
        $delivery = SubscriptionCommunicationDelivery::create($this->deliveryAttributes([
            'status' => CommunicationDeliveryStatus::PENDING->value,
        ]));

        $this->repository->markSent($delivery->id);

        $delivery->refresh();

        $this->assertEquals(CommunicationDeliveryStatus::SENT->value, $delivery->status);
        $this->assertNotNull($delivery->sent_at);
    }

    public function test_mark_failed_updates_status_sets_failed_at_and_records_reason(): void
    {
        $delivery = SubscriptionCommunicationDelivery::create($this->deliveryAttributes([
            'status' => CommunicationDeliveryStatus::PENDING->value,
        ]));

        $this->repository->markFailed($delivery->id, 'SMTP connection refused');

        $delivery->refresh();

        $this->assertEquals(CommunicationDeliveryStatus::FAILED->value, $delivery->status);
        $this->assertNotNull($delivery->failed_at);
        $this->assertEquals('SMTP connection refused', $delivery->failure_reason);
    }

    public function test_get_for_subscription_returns_all_deliveries_newest_first(): void
    {
        SubscriptionCommunicationDelivery::create($this->deliveryAttributes([
            'status'     => CommunicationDeliveryStatus::SENT->value,
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
        ]));

        SubscriptionCommunicationDelivery::create($this->deliveryAttributes([
            'status'     => CommunicationDeliveryStatus::FAILED->value,
            'created_at' => date('Y-m-d H:i:s'),
        ]));

        $result = $this->repository->getForSubscription($this->subscription->id);

        $this->assertCount(2, $result);
        $this->assertEquals(
            CommunicationDeliveryStatus::FAILED->value,
            $result->first()->status
        );
    }

    public function test_get_for_subscription_only_returns_deliveries_for_that_subscription(): void
    {
        $otherSubscription = Subscription::create([
            'member_id'  => $this->member->id,
            'site_id'    => $this->siteId,
            'plan_name'  => 'Other Plan',
            'status'     => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price'      => 9.99,
            'currency'   => 'USD',
        ]);

        SubscriptionCommunicationDelivery::create($this->deliveryAttributes([
            'subscription_id' => $otherSubscription->id,
            'status'          => CommunicationDeliveryStatus::SENT->value,
        ]));

        SubscriptionCommunicationDelivery::create($this->deliveryAttributes([
            'status' => CommunicationDeliveryStatus::SENT->value,
        ]));

        $result = $this->repository->getForSubscription($this->subscription->id);

        $this->assertCount(1, $result);
        $this->assertEquals($this->subscription->id, $result->first()->subscription_id);
    }

    public function test_get_for_subscription_returns_empty_when_no_deliveries(): void
    {
        $result = $this->repository->getForSubscription($this->subscription->id);

        $this->assertCount(0, $result);
    }

    public function test_find_by_token_returns_delivery_for_valid_token(): void
    {
        $delivery = SubscriptionCommunicationDelivery::create($this->deliveryAttributes([
            'token'  => 'findable-token-abc123',
            'status' => CommunicationDeliveryStatus::SENT->value,
        ]));

        $result = $this->repository->findByToken('findable-token-abc123');

        $this->assertNotNull($result);
        $this->assertEquals($delivery->id, $result->id);
    }

    public function test_find_by_token_returns_null_for_unknown_token(): void
    {
        $result = $this->repository->findByToken('does-not-exist');

        $this->assertNull($result);
    }

    public function test_mark_opened_at_sets_timestamp_on_first_call(): void
    {
        $delivery = SubscriptionCommunicationDelivery::create($this->deliveryAttributes([
            'status'    => CommunicationDeliveryStatus::SENT->value,
            'opened_at' => null,
        ]));

        $this->repository->markOpenedAt($delivery->id);

        $delivery->refresh();

        $this->assertNotNull($delivery->opened_at);
    }

    public function test_mark_opened_at_does_not_overwrite_existing_timestamp(): void
    {
        $originalTime = date('Y-m-d H:i:s', strtotime('-1 hour'));

        $delivery = SubscriptionCommunicationDelivery::create($this->deliveryAttributes([
            'status'    => CommunicationDeliveryStatus::SENT->value,
            'opened_at' => $originalTime,
        ]));

        $this->repository->markOpenedAt($delivery->id);

        $delivery->refresh();

        $this->assertEquals(
            $originalTime,
            $delivery->opened_at->format('Y-m-d H:i:s')
        );
    }

    public function test_mark_clicked_at_sets_timestamp_on_first_call(): void
    {
        $delivery = SubscriptionCommunicationDelivery::create($this->deliveryAttributes([
            'status'     => CommunicationDeliveryStatus::SENT->value,
            'clicked_at' => null,
        ]));

        $this->repository->markClickedAt($delivery->id);

        $delivery->refresh();

        $this->assertNotNull($delivery->clicked_at);
    }

    public function test_mark_clicked_at_does_not_overwrite_existing_timestamp(): void
    {
        $originalTime = date('Y-m-d H:i:s', strtotime('-1 hour'));

        $delivery = SubscriptionCommunicationDelivery::create($this->deliveryAttributes([
            'status'     => CommunicationDeliveryStatus::SENT->value,
            'clicked_at' => $originalTime,
        ]));

        $this->repository->markClickedAt($delivery->id);

        $delivery->refresh();

        $this->assertEquals(
            $originalTime,
            $delivery->clicked_at->format('Y-m-d H:i:s')
        );
    }

    private function deliveryAttributes(array $overrides = []): array
    {
        return array_merge([
            'subscription_id'                        => $this->subscription->id,
            'member_id'                              => $this->member->id,
            'subscription_communication_id'          => $this->communication->id,
            'subscription_communication_schedule_id' => $this->schedule->id,
            'channel'                                => 'email',
            'status'                                 => CommunicationDeliveryStatus::PENDING->value,
            'token'                                  => bin2hex(random_bytes(16)),
            'recipient_email'                        => 'test@example.com',
        ], $overrides);
    }
}