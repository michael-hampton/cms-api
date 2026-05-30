<?php

namespace App\Tests\Functional\Controllers\Members\Api;

use App\Models\SubscriptionSegment;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class SubscriptionSegmentOverrideApiControllerTest extends FunctionalTestCase
{
    use CreatesTestData;
    public function test_it_assigns_segment_override_to_subscription(): void
    {
        $subscription = $this->createSubscription(['status' => 'active']);
        $segment      = $this->createSegment([
            'subject_type' => 'subscription',
            'is_active'    => true,
        ]);

        $response = $this->postForSite("/api/subscriptions/{$subscription->id}/segment/assign", [
            'segment_id' => $segment->id,
            'reason'     => 'Manual retention override',
        ]);

        $this->assertResponseStatus(201, $response);
        $this->assertDatabaseHas('subscription_segments', [
            'subscription_id' => $subscription->id,
            'segment_id'      => $segment->id,
            'source'          => 'manual',
            'reason'          => 'Manual retention override',
        ]);
    }

    public function test_it_replaces_existing_active_assignment(): void
    {
        $subscription = $this->createSubscription(['status' => 'active']);
        $oldSegment   = $this->createSegment(['subject_type' => 'subscription', 'is_active' => true]);
        $newSegment   = $this->createSegment(['subject_type' => 'subscription', 'is_active' => true]);

        // Create an existing active assignment
        SubscriptionSegment::create([
            'segment_id'  => $oldSegment->id,
            'assigned_at' => now(),
            'status'      => 'active',
            'source'      => 'rule_based',
            'subscription_id' => $subscription->id,
        ]);

        $this->postForSite("/api/subscriptions/{$subscription->id}/segment/assign", [
            'segment_id' => $newSegment->id,
            'reason'     => 'Upgrade retention path',
        ]);

        // Old assignment is replaced, not deleted.
        $this->assertDatabaseHas('subscription_segments', [
            'subscription_id' => $subscription->id,
            'segment_id'      => $oldSegment->id,
            'status'          => 'replaced',
        ]);

        $this->assertDatabaseHas('subscription_segments', [
            'subscription_id' => $subscription->id,
            'segment_id'      => $newSegment->id,
            'status'          => 'active',
            'source'          => 'manual',
        ]);
    }

    public function test_it_stores_expiry_date(): void
    {
        $subscription = $this->createSubscription(['status' => 'active']);
        $segment      = $this->createSegment(['subject_type' => 'subscription', 'is_active' => true]);

        $response = $this->postForSite("/api/subscriptions/{$subscription->id}/segment/assign", [
            'segment_id' => $segment->id,
            'reason'     => 'Temporary override',
            'expires_at' => now_datetime()->addMonths(1)->format('Y-m-d H:i:s'),
        ]);

        $this->assertResponseStatus(201, $response);
        $body = $this->decodeJson($response);
        $this->assertNotNull($body['expires_at']);
    }

    public function test_it_requires_reason(): void
    {
        $subscription = $this->createSubscription();
        $segment      = $this->createSegment(['subject_type' => 'subscription']);

        $response = $this->postForSite("/api/subscriptions/{$subscription->id}/segment/assign", [
            'segment_id' => $segment->id,
            // reason omitted
        ]);

        $this->assertResponseStatus(422, $response);
    }

    public function test_it_returns_404_for_unknown_subscription(): void
    {
        $segment = $this->createSegment(['subject_type' => 'subscription']);

        $response = $this->postForSite('/api/subscriptions/9999/segment/assign', [
            'segment_id' => $segment->id,
            'reason'     => 'Test',
        ]);

        $this->assertResponseStatus(404, $response);
    }

    public function test_it_returns_404_for_unknown_segment(): void
    {
        $subscription = $this->createSubscription();

        $response = $this->postForSite("/api/subscriptions/{$subscription->id}/segment/assign", [
            'segment_id' => 9999,
            'reason'     => 'Test',
        ]);

        $this->assertResponseStatus(404, $response);
    }

    public function test_expired_override_source_is_stored_correctly(): void
    {
        $subscription = $this->createSubscription(['status' => 'active']);
        $segment      = $this->createSegment(['subject_type' => 'subscription', 'is_active' => true]);

        $this->postForSite("/api/subscriptions/{$subscription->id}/segment/assign", [
            'segment_id' => $segment->id,
            'reason'     => 'Expiring override',
            'expires_at' => now_datetime()->addMonths(1)->format('Y-m-d H:i:s'),
        ]);

        $this->assertDatabaseHas('subscription_segments', [
            'subscription_id' => $subscription->id,
            'source'          => 'manual',
        ]);
    }
}