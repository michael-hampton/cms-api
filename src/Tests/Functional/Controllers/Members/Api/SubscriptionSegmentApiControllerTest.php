<?php

namespace App\Tests\Functional\Controllers\Members\Api;

use App\Models\Segment;
use App\Models\SubscriptionSegment;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class SubscriptionSegmentApiControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    // -------------------------------------------------------------------------
    // GET /api/subscriptions/{id}/segment
    // -------------------------------------------------------------------------

    public function test_show_returns_404_when_subscription_not_found(): void
    {
        $response = $this->getForSite('/api/subscriptions/9999/segment');

        $this->assertResponseStatus(404, $response);
        $body = $this->decodeJson($response);
        $this->assertStringContainsString('9999', $body['error']);
    }

    public function test_show_returns_null_segment_when_no_active_assignment_exists(): void
    {
        $subscription = $this->createSubscription();

        $response = $this->getForSite("/api/subscriptions/{$subscription->id}/segment");

        $this->assertResponseOk($response);
        $body = $this->decodeJson($response);
        $this->assertArrayHasKey('segment', $body);
        $this->assertNull($body['segment']);
    }

    public function test_show_returns_active_segment_assignment(): void
    {
        $subscription = $this->createSubscription();
        $segment = $this->createSegment([
            'key'          => 'at-risk',
            'name'         => 'At Risk',
            'subject_type' => 'subscription',
            'is_active'    => true,
        ]);
        $this->createSubscriptionSegmentAssignment($subscription->id, $segment->id, [
            'status' => 'active',
        ]);

        $response = $this->getForSite("/api/subscriptions/{$subscription->id}/segment");

        $this->assertResponseOk($response);
        $body = $this->decodeJson($response);
        $this->assertNotNull($body['segment']);
        $this->assertEquals($segment->id, $body['segment']['id']);
        $this->assertEquals('at-risk', $body['segment']['key']);
        $this->assertEquals('At Risk', $body['segment']['name']);
        $this->assertEquals('active', $body['segment']['status']);
        $this->assertArrayHasKey('assigned_at', $body['segment']);
    }

    public function test_show_returns_assigned_at_as_iso8601_string(): void
    {
        $subscription = $this->createSubscription();
        $segment = $this->createSegment(['subject_type' => 'subscription', 'is_active' => true]);
        $this->createSubscriptionSegmentAssignment($subscription->id, $segment->id, [
            'status'      => 'active',
            'assigned_at' => '2026-01-15 10:00:00',
        ]);

        $response = $this->getForSite("/api/subscriptions/{$subscription->id}/segment");

        $this->assertResponseOk($response);
        $body = $this->decodeJson($response);
        // ISO 8601 format: 2026-01-15T10:00:00+00:00
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/',
            $body['segment']['assigned_at'],
        );
    }

    public function test_show_does_not_return_inactive_assignment(): void
    {
        $subscription = $this->createSubscription();
        $segment = $this->createSegment(['subject_type' => 'subscription', 'is_active' => true]);
        $this->createSubscriptionSegmentAssignment($subscription->id, $segment->id, [
            'status' => 'inactive',
        ]);

        $response = $this->getForSite("/api/subscriptions/{$subscription->id}/segment");

        $this->assertResponseOk($response);
        $body = $this->decodeJson($response);
        $this->assertNull($body['segment']);
    }

    public function test_show_does_not_return_expired_assignment(): void
    {
        $subscription = $this->createSubscription();
        $segment = $this->createSegment(['subject_type' => 'subscription', 'is_active' => true]);
        $this->createSubscriptionSegmentAssignment($subscription->id, $segment->id, [
            'status'     => 'expired',
            'expires_at' => now_datetime()->subDay(),
        ]);

        $response = $this->getForSite("/api/subscriptions/{$subscription->id}/segment");

        $this->assertResponseOk($response);
        $body = $this->decodeJson($response);
        $this->assertNull($body['segment']);
    }

    public function test_show_returns_segment_fields_in_expected_shape(): void
    {
        $subscription = $this->createSubscription();
        $segment = $this->createSegment(['subject_type' => 'subscription', 'is_active' => true]);
        $this->createSubscriptionSegmentAssignment($subscription->id, $segment->id, [
            'status' => 'active',
        ]);

        $response = $this->getForSite("/api/subscriptions/{$subscription->id}/segment");

        $this->assertResponseOk($response);
        $body = $this->decodeJson($response);
        $segmentBody = $body['segment'];

        foreach (['id', 'key', 'name', 'assigned_at', 'status'] as $field) {
            $this->assertArrayHasKey($field, $segmentBody, "Expected field '{$field}' missing from response.");
        }
    }

    private function createSubscriptionSegmentAssignment(mixed $id, mixed $id1, array $array)
    {

        SubscriptionSegment::create( array_merge(
            [
                'subscription_id' => $id,
                'segment_id' => $id1,
                'assigned_at' => now(),
            ],
            $array,

        ));
    }
}
