<?php

namespace App\Tests\Functional\Controllers\Subscriptions;

use App\Models\MemberSubscriptionPreference;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

/**
 * Functional (real-database) tests for SubscriptionPlanSubscriberController.
 *
 * Routes tested:
 *   GET /api/{site}/subscriptions/plans/{planId}/subscribers
 *   GET /api/{site}/subscriptions/{subscriptionId}
 *   GET /api/{site}/subscriptions/{subscriptionId}/preferences
 */
class SubscriptionPlanSubscriberControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    // =========================================================================
    // GET /api/{site}/subscriptions/plans/{planId}/subscribers
    // =========================================================================

    public function testPlanSubscribersReturnsPaginatedList(): void
    {
        $plan = $this->createSubscriptionPlan();
        $member = $this->createMember();

        $this->createSubscription([
            'plan_id' => $plan->id,
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'status' => 'active',
        ]);

        $response = $this->getForSite("/api/subscriptions/plans/{$plan->id}/subscribers");

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('pagination', $data);
        $this->assertCount(1, $data['items']);
        $this->assertEquals($member->id, $data['items'][0]['member_id']);
    }

    public function testPlanSubscribersReturnsEmptyForUnknownPlan(): void
    {
        $response = $this->getForSite('/api/subscriptions/plans/99999/subscribers');

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertCount(0, $data['items']);
        $this->assertEquals(0, $data['pagination']['total']);
    }

    public function testPlanSubscribersFiltersOnStatus(): void
    {
        $plan = $this->createSubscriptionPlan();
        $active = $this->createMember(['email' => 'active@example.com']);
        $cancelled = $this->createMember(['email' => 'cancelled@example.com']);

        $this->createSubscription([
            'plan_id' => $plan->id,
            'member_id' => $active->id,
            'site_id' => $this->siteId,
            'status' => 'active',
        ]);

        $this->createSubscription([
            'plan_id' => $plan->id,
            'member_id' => $cancelled->id,
            'site_id' => $this->siteId,
            'status' => 'cancelled',
        ]);

        $response = $this->getForSite(
            "/api/subscriptions/plans/{$plan->id}/subscribers?status=active"
        );

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data['items']);
        $this->assertEquals($active->id, $data['items'][0]['member_id']);
    }

    public function testPlanSubscribersRespectsPagination(): void
    {
        $plan = $this->createSubscriptionPlan();

        for ($i = 0; $i < 5; $i++) {
            $member = $this->createMember(['email' => "member{$i}@example.com"]);
            $this->createSubscription([
                'plan_id' => $plan->id,
                'member_id' => $member->id,
                'site_id' => $this->siteId,
            ]);
        }

        $response = $this->getForSite(
            "/api/subscriptions/plans/{$plan->id}/subscribers?per_page=2&page=1"
        );

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data['items']);
        $this->assertEquals(5, $data['pagination']['total']);
        $this->assertEquals(3, $data['pagination']['last_page']);
    }

    public function testPlanSubscribersIncludesMemberData(): void
    {
        $plan = $this->createSubscriptionPlan();
        $member = $this->createMember(['email' => 'detail@example.com']);

        $this->createSubscription([
            'plan_id' => $plan->id,
            'member_id' => $member->id,
            'site_id' => $this->siteId,
        ]);

        $response = $this->getForSite("/api/subscriptions/plans/{$plan->id}/subscribers");

        $data = json_decode($response->getContent(), true);

        // The SubscriptionResource should embed member email
        $this->assertEquals($member->email, $data['items'][0]['member_email']);
    }

    // =========================================================================
    // GET /api/{site}/subscriptions/{subscriptionId}
    // =========================================================================

    public function testShowReturnsSubscriptionDetail(): void
    {
        $plan = $this->createSubscriptionPlan();
        $member = $this->createMember();
        $subscription = $this->createSubscription([
            'plan_id' => $plan->id,
            'member_id' => $member->id,
            'site_id' => $this->siteId,
        ]);

        $response = $this->getForSite("/api/subscriptions/{$subscription->id}");

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals($subscription->id, $data['subscription']['id']);
        $this->assertEquals($member->id, $data['subscription']['member_id']);
        $this->assertEquals($plan->id, $data['subscription']['plan_id']);
    }

    public function testShowReturns404ForUnknownSubscription(): void
    {
        $response = $this->getForSite('/api/subscriptions/99999');

        $this->assertEquals(404, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
    }

    // =========================================================================
    // GET /api/{site}/subscriptions/{subscriptionId}/preferences
    // =========================================================================

    public function testPreferencesReturnsPreferenceForSubscriptionOwner(): void
    {
        $plan = $this->createSubscriptionPlan();
        $member = $this->createMember();
        $subscription = $this->createSubscription([
            'plan_id' => $plan->id,
            'member_id' => $member->id,
            'site_id' => $this->siteId,
        ]);

        MemberSubscriptionPreference::create([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'email_notifications' => true,
            'newsletter_frequency' => 'weekly',
            'unsubscribe_token' => bin2hex(random_bytes(32)),
            'is_active' => true,
        ]);

        $response = $this->getForSite("/api/subscriptions/{$subscription->id}/preferences");

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertNotNull($data['preference']);
        $this->assertEquals($member->id, $data['preference']['member_id']);
        $this->assertTrue($data['preference']['email_notifications']);
        $this->assertEquals('weekly', $data['preference']['newsletter_frequency']);

        // Security: raw token must never be exposed
        $this->assertArrayNotHasKey('unsubscribe_token', $data['preference']);
    }

    public function testPreferencesReturnsNullWhenNoPreferenceExists(): void
    {
        $plan = $this->createSubscriptionPlan();
        $member = $this->createMember();
        $subscription = $this->createSubscription([
            'plan_id' => $plan->id,
            'member_id' => $member->id,
            'site_id' => $this->siteId,
        ]);

        $response = $this->getForSite("/api/subscriptions/{$subscription->id}/preferences");

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertNull($data['preference']);
    }

    public function testPreferencesReturns404WhenSubscriptionNotFound(): void
    {
        $response = $this->getForSite('/api/subscriptions/99999/preferences');

        $this->assertEquals(404, $response->getStatusCode());
    }
}