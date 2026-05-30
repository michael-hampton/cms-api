<?php

namespace App\Tests\Functional\Controllers\Members\Api;

use App\Models\Segment;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class PlanSegmentApiControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    // =========================================================================
    // assign (POST /api/subscription-plans/{planId}/segments/assign)
    // =========================================================================

    public function test_it_assigns_segment_to_plan(): void
    {
        $plan    = $this->createSubscriptionPlan();
        $segment = $this->createSegment([
            'subject_type' => 'subscription',
            'is_active'    => true,
        ]);

        $response = $this->postForSite("/api/subscription-plans/{$plan->id}/segments/assign", [
            'segment_id' => $segment->id,
        ]);

        $this->assertResponseStatus(201, $response);
        $this->assertDatabaseHas('plan_segment', [
            'plan_id'    => $plan->id,
            'segment_id' => $segment->id,
        ]);
    }

    public function test_it_removes_segment_from_plan(): void
    {
        $plan    = $this->createSubscriptionPlan();
        $segment = $this->createSegment(['subject_type' => 'subscription']);
        $plan->segments(true)->attach($segment->id);

        $response = $this->deleteForSite("/api/subscription-plans/{$plan->id}/segments/{$segment->id}");

        $this->assertResponseOk($response);
        $this->assertDatabaseMissing('plan_segment', [
            'plan_id'    => $plan->id,
            'segment_id' => $segment->id,
        ]);
    }

    public function test_it_supports_multiple_segments_on_same_plan(): void
    {
        $plan     = $this->createSubscriptionPlan();
        $segment1 = $this->createSegment(['subject_type' => 'subscription', 'is_active' => true]);
        $segment2 = $this->createSegment(['subject_type' => 'subscription', 'is_active' => true]);

        $this->postForSite("/api/subscription-plans/{$plan->id}/segments/assign", ['segment_id' => $segment1->id]);
        $response = $this->postForSite("/api/subscription-plans/{$plan->id}/segments/assign", ['segment_id' => $segment2->id]);

        $this->assertResponseStatus(201, $response);
        $this->assertCount(2, $plan->segments(true)->get());
    }

    public function test_it_rejects_duplicate_assignment(): void
    {
        $plan    = $this->createSubscriptionPlan();
        $segment = $this->createSegment(['subject_type' => 'subscription']);
        $plan->segments(true)->attach($segment->id);

        $response = $this->postForSite("/api/subscription-plans/{$plan->id}/segments/assign", [
            'segment_id' => $segment->id,
        ]);

        $this->assertResponseStatus(422, $response);
    }

    public function test_it_rejects_member_segment_assignment_to_plan(): void
    {
        $plan    = $this->createSubscriptionPlan();
        $segment = $this->createSegment(['subject_type' => 'member']);

        $response = $this->postForSite("/api/subscription-plans/{$plan->id}/segments/assign", [
            'segment_id' => $segment->id,
        ]);

        $this->assertResponseStatus(422, $response);
    }

    public function test_assign_returns_404_for_unknown_plan(): void
    {
        $segment = $this->createSegment(['subject_type' => 'subscription']);

        $response = $this->postForSite('/api/subscription-plans/9999/segments/assign', [
            'segment_id' => $segment->id,
        ]);

        $this->assertResponseStatus(404, $response);
    }

    public function test_remove_returns_404_when_assignment_does_not_exist(): void
    {
        $plan    = $this->createSubscriptionPlan();
        $segment = $this->createSegment(['subject_type' => 'subscription']);

        $response = $this->deleteForSite("/api/subscription-plans/{$plan->id}/segments/{$segment->id}");

        $this->assertResponseStatus(404, $response);
    }

    // =========================================================================
    // assignPlansToSegment (POST /api/segments/{segmentId}/subscription-plans/assign)
    // =========================================================================

    public function test_it_assigns_plans_to_segment(): void
    {
        $segment = $this->createSegment(['subject_type' => 'subscription', 'is_active' => true]);
        $plan1   = $this->createSubscriptionPlan();
        $plan2   = $this->createSubscriptionPlan();

        $response = $this->postForSite("/api/segments/{$segment->id}/subscription-plans/assign", [
            'plan_ids' => [$plan1->id, $plan2->id],
        ]);

        $this->assertResponseStatus(201, $response);

        $body = $this->decodeJson($response);
        $this->assertCount(2, $body['assigned']);
        $this->assertEmpty($body['skipped']);

        $this->assertDatabaseHas('plan_segment', ['plan_id' => $plan1->id, 'segment_id' => $segment->id]);
        $this->assertDatabaseHas('plan_segment', ['plan_id' => $plan2->id, 'segment_id' => $segment->id]);
    }

    public function test_assign_plans_response_includes_plan_name(): void
    {
        $segment = $this->createSegment(['subject_type' => 'subscription', 'is_active' => true]);
        $plan    = $this->createSubscriptionPlan(['name' => 'Gold Plan']);

        $response = $this->postForSite("/api/segments/{$segment->id}/subscription-plans/assign", [
            'plan_ids' => [$plan->id],
        ]);

        $this->assertResponseStatus(201, $response);

        $body = $this->decodeJson($response);
        $this->assertSame('Gold Plan', $body['assigned'][0]['plan_name']);
    }

    public function test_assign_plans_skips_already_assigned_plans(): void
    {
        $segment = $this->createSegment(['subject_type' => 'subscription', 'is_active' => true]);
        $plan1   = $this->createSubscriptionPlan();
        $plan2   = $this->createSubscriptionPlan();

        // Pre-assign plan1
        $plan1->segments(true)->attach($segment->id);

        $response = $this->postForSite("/api/segments/{$segment->id}/subscription-plans/assign", [
            'plan_ids' => [$plan1->id, $plan2->id],
        ]);

        $this->assertResponseStatus(201, $response);

        $body = $this->decodeJson($response);
        $this->assertCount(1, $body['assigned']);
        $this->assertSame($plan2->id, $body['assigned'][0]['plan_id']);
        $this->assertContains($plan1->id, $body['skipped']);
    }

    public function test_assign_plans_returns_404_for_unknown_segment(): void
    {
        $plan = $this->createSubscriptionPlan();

        $response = $this->postForSite('/api/segments/9999/subscription-plans/assign', [
            'plan_ids' => [$plan->id],
        ]);

        $this->assertResponseStatus(404, $response);
    }

    public function test_assign_plans_returns_422_for_unknown_plan(): void
    {
        $segment = $this->createSegment(['subject_type' => 'subscription', 'is_active' => true]);

        $response = $this->postForSite("/api/segments/{$segment->id}/subscription-plans/assign", [
            'plan_ids' => [9999],
        ]);

        $this->assertResponseStatus(404, $response);
    }

    // =========================================================================
    // plansForSegment (GET /api/segments/{segmentId}/subscription-plans)
    // =========================================================================

    public function test_it_returns_plans_for_segment(): void
    {
        $segment = $this->createSegment(['subject_type' => 'subscription', 'is_active' => true]);
        $plan1   = $this->createSubscriptionPlan(['name' => 'Silver Plan']);
        $plan2   = $this->createSubscriptionPlan(['name' => 'Gold Plan']);

        $plan1->segments(true)->attach($segment->id, ['priority' => 10]);
        $plan2->segments(true)->attach($segment->id, ['priority' => 20]);

        $response = $this->getForSite("/api/segments/{$segment->id}/subscription-plans");

        $this->assertResponseOk($response);

        $body = $this->decodeJson($response);
        $this->assertArrayHasKey('items', $body);
        $this->assertCount(2, $body['items']);

        // Repository orders by priority asc, so Silver (10) comes first.
        $this->assertSame($plan1->id, $body['items'][0]['plan_id']);
        $this->assertSame('Silver Plan', $body['items'][0]['plan_name']);
        $this->assertSame(10, $body['items'][0]['priority']);

        $this->assertSame($plan2->id, $body['items'][1]['plan_id']);
        $this->assertSame('Gold Plan', $body['items'][1]['plan_name']);
    }

    public function test_plans_for_segment_returns_empty_items_when_no_assignments_exist(): void
    {
        $segment = $this->createSegment(['subject_type' => 'subscription']);

        $response = $this->getForSite("/api/segments/{$segment->id}/subscription-plans");

        $this->assertResponseOk($response);
        $this->assertSame([], $this->decodeJson($response)['items']);
    }

    public function test_plans_for_segment_returns_404_for_unknown_segment(): void
    {
        $response = $this->getForSite('/api/segments/9999/subscription-plans');

        $this->assertResponseStatus(404, $response);
    }

    public function test_plans_for_segment_response_shape_is_complete(): void
    {
        $segment = $this->createSegment(['subject_type' => 'subscription', 'is_active' => true]);
        $plan    = $this->createSubscriptionPlan(['name' => 'Platinum Plan']);
        $plan->segments(true)->attach($segment->id, [
            'priority'  => 5,
            'is_active' => true,
            'starts_at' => '2025-01-01 00:00:00',
            'ends_at'   => '2026-01-01 00:00:00',
        ]);

        $response = $this->getForSite("/api/segments/{$segment->id}/subscription-plans");

        $this->assertResponseOk($response);

        $item = $this->decodeJson($response)['items'][0];

        $this->assertArrayHasKey('id', $item);
        $this->assertArrayHasKey('plan_id', $item);
        $this->assertArrayHasKey('plan_name', $item);
        $this->assertArrayHasKey('segment_id', $item);
        $this->assertArrayHasKey('priority', $item);
        $this->assertArrayHasKey('is_active', $item);
        $this->assertArrayHasKey('starts_at', $item);
        $this->assertArrayHasKey('ends_at', $item);

        $this->assertSame('Platinum Plan', $item['plan_name']);
        $this->assertSame(5, $item['priority']);
        $this->assertSame($segment->id, $item['segment_id']);
    }
}