<?php

namespace App\Tests\Functional\Controllers\Members\Api;

use App\Models\Segment;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class PlanSegmentApiControllerTest extends FunctionalTestCase
{
    use CreatesTestData;
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
        $plan    = $this->createSubscriptionPlan();
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
}