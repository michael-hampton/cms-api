<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repositories\Subscription;

use App\Enums\Subscriptions\CommunicationDeliveryStatus;
use App\Models\Member;
use App\Models\Site;
use App\Models\Subscription;
use App\Models\SubscriptionCommunication;
use App\Models\SubscriptionCommunicationDelivery;
use App\Models\SubscriptionPlan;
use App\Repositories\Subscriptions\SubscriptionCommunicationDeliveryRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class SubscriptionCommunicationDeliveryRepositoryTest extends FunctionalTestCase
{
    use MockeryPHPUnitIntegration;

    private SubscriptionCommunicationDeliveryRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new SubscriptionCommunicationDeliveryRepository();
    }

    public function test_record_pending_stores_metadata_and_dedupe_key(): void
    {
        [$subscription, $member, $communication] = $this->makeGraph();

        $delivery = $this->repository->recordPending(
            subscriptionId: (int) $subscription->id,
            memberId: (int) $member->id,
            communicationId: (int) $communication->id,
            scheduleId: null,
            channel: 'email',
            segmentId: null,
            subscriptionSegmentId: null,
            recipientEmail: 'test@example.com',
            subject: 'Subject',
            metadata: [
                'letter_code' => 'ITD_DD_PRICE_RISE',
                'pricing_change_id' => 77,
            ],
            dedupeKey: 'pricing-change:77:transition:500:itd',
        );

        $this->assertInstanceOf(SubscriptionCommunicationDelivery::class, $delivery);
        $this->assertSame((int) $subscription->id, (int) $delivery->subscription_id);
        $this->assertSame((int) $member->id, (int) $delivery->member_id);
        $this->assertSame((int) $communication->id, (int) $delivery->subscription_communication_id);
        $this->assertNull($delivery->subscription_communication_schedule_id);
        $this->assertSame('email', $delivery->channel);
        $this->assertSame(CommunicationDeliveryStatus::PENDING->value, $delivery->status);
        $this->assertSame('test@example.com', $delivery->recipient_email);
        $this->assertSame('Subject', $delivery->subject);
        $this->assertSame('pricing-change:77:transition:500:itd', $delivery->dedupe_key);
        $this->assertSame('ITD_DD_PRICE_RISE', $delivery->metadata['letter_code']);
        $this->assertSame(77, $delivery->metadata['pricing_change_id']);
        $this->assertNotEmpty($delivery->token);
    }

    public function test_has_already_sent_returns_true_for_pending_delivery_with_same_dedupe_key(): void
    {
        [$subscription, $member, $communication] = $this->makeGraph();

        $this->makeDelivery(
            subscription: $subscription,
            member: $member,
            communication: $communication,
            status: CommunicationDeliveryStatus::PENDING->value,
            dedupeKey: 'pricing-change:77:transition:500:itd'
        );

        $result = $this->repository->hasAlreadySent(
            subscriptionId: (int) $subscription->id,
            communicationId: (int) $communication->id,
            scheduleId: null,
            dedupeKey: 'pricing-change:77:transition:500:itd',
        );

        $this->assertTrue($result);
    }

    public function test_has_already_sent_returns_true_for_sent_delivery_with_same_dedupe_key(): void
    {
        [$subscription, $member, $communication] = $this->makeGraph();

        $this->makeDelivery(
            subscription: $subscription,
            member: $member,
            communication: $communication,
            status: CommunicationDeliveryStatus::SENT->value,
            dedupeKey: 'pricing-change:77:transition:500:itd'
        );

        $result = $this->repository->hasAlreadySent(
            subscriptionId: (int) $subscription->id,
            communicationId: (int) $communication->id,
            scheduleId: null,
            dedupeKey: 'pricing-change:77:transition:500:itd',
        );

        $this->assertTrue($result);
    }

    public function test_has_already_sent_returns_false_for_failed_delivery_with_same_dedupe_key(): void
    {
        [$subscription, $member, $communication] = $this->makeGraph();

        $this->makeDelivery(
            subscription: $subscription,
            member: $member,
            communication: $communication,
            status: CommunicationDeliveryStatus::FAILED->value,
            dedupeKey: 'pricing-change:77:transition:500:itd'
        );

        $result = $this->repository->hasAlreadySent(
            subscriptionId: (int) $subscription->id,
            communicationId: (int) $communication->id,
            scheduleId: null,
            dedupeKey: 'pricing-change:77:transition:500:itd',
        );

        $this->assertFalse($result);
    }

    public function test_has_already_sent_uses_dedupe_key_when_provided(): void
    {
        [$subscription, $member, $communication] = $this->makeGraph();

        $this->makeDelivery(
            subscription: $subscription,
            member: $member,
            communication: $communication,
            status: CommunicationDeliveryStatus::SENT->value,
            dedupeKey: 'pricing-change:77:transition:500:itd'
        );

        $result = $this->repository->hasAlreadySent(
            subscriptionId: (int) $subscription->id,
            communicationId: (int) $communication->id,
            scheduleId: null,
            dedupeKey: 'pricing-change:77:transition:999:itd',
        );

        $this->assertFalse($result);
    }

    public function test_has_already_sent_preserves_legacy_behaviour_when_dedupe_key_is_null(): void
    {
        [$subscription, $member, $communication] = $this->makeGraph();

        $this->makeDelivery(
            subscription: $subscription,
            member: $member,
            communication: $communication,
            status: CommunicationDeliveryStatus::SENT->value,
            dedupeKey: 'some-context'
        );

        $result = $this->repository->hasAlreadySent(
            subscriptionId: (int) $subscription->id,
            communicationId: (int) $communication->id,
            scheduleId: null,
            dedupeKey: null,
        );

        $this->assertTrue($result);
    }

    /**
     * @return array{0: Subscription, 1: Member, 2: SubscriptionCommunication}
     */
    private function makeGraph(): array
    {
        $site = Site::create([
            'name' => 'Test Site ' . uniqid(),
            'domain' => 'delivery-' . uniqid() . '.test',
            'is_active' => true,
        ]);

        $member = Member::create([
            'email' => 'member-' . uniqid() . '@example.com',
            'first_name' => 'Test',
            'last_name' => 'Member',
            'site_id' => $site->id,
        ]);

        $plan = SubscriptionPlan::create([
            'site_id' => $site->id,
            'name' => 'Test Plan ' . uniqid(),
            'slug' => 'test-plan-' . uniqid(),
            'price' => 9.99,
            'currency' => 'GBP',
            'billing_period' => 'monthly',
            'is_active' => true,
        ]);

        $subscription = Subscription::create([
            'member_id' => $member->id,
            'site_id' => $site->id,
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'status' => 'active',
            'start_date' => now_datetime()->format('Y-m-d H:i:s'),
            'end_date' => (new \DateTime('+1 month'))->format('Y-m-d H:i:s'),
            'next_billing_date' => (new \DateTime('+1 month'))->format('Y-m-d H:i:s'),
            'price' => 9.99,
            'currency' => 'GBP',
            'auto_renew' => true,
            'type' => 'paid',
            'delivery_type' => 'digital',
        ]);

        $communication = SubscriptionCommunication::create([
            'key' => 'test_comm_' . uniqid(),
            'name' => 'Test Communication',
            'description' => 'Test',
            'type' => 'itd',
            'template' => 'FakeTemplate',
            'channels' => ['email'],
            'is_active' => true,
            'sort_order' => 1,
        ]);

        return [$subscription, $member, $communication];
    }

    private function makeDelivery(
        Subscription $subscription,
        Member $member,
        SubscriptionCommunication $communication,
        string $status,
        ?string $dedupeKey
    ): SubscriptionCommunicationDelivery {
        return SubscriptionCommunicationDelivery::create([
            'subscription_id' => $subscription->id,
            'member_id' => $member->id,
            'subscription_communication_id' => $communication->id,
            'subscription_communication_schedule_id' => null,
            'channel' => 'email',
            'status' => $status,
            'token' => 'token-' . uniqid(),
            'dedupe_key' => $dedupeKey,
        ]);
    }
}