<?php

namespace App\Tests\Functional\Controllers\Members\Api;

use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MemberDashboardApiControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testOverviewReturnsMemberAndOrders(): void
    {
        $member = $this->createAuthenticatedMember();

        $response = $this->getForSite('/api/member/dashboard/overview', [], true);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('member', $data['data']);
        $this->assertArrayHasKey('recent_orders', $data['data']);
        $this->assertArrayHasKey('all_subscriptions', $data['data']);
    }

    private function createAuthenticatedMember(array $overrides = [])
    {
        $member = $this->createMember($overrides);
        $this->actingAsMember($member);
        return $member;
    }

    public function testOverviewReturnsUnauthorizedWhenNotLoggedIn(): void
    {
        $this->unauthenticateMember();

        $response = $this->getForSite('/api/member/dashboard/overview', [], true);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testActivityReturnsProgressAndTrends(): void
    {
        $member = $this->createAuthenticatedMember();
        $this->createMemberActivity(['member_id' => $member->id]);

        $response = $this->getForSite('/api/member/dashboard/activity', [], true);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('progress', $data['data']);
        $this->assertArrayHasKey('activity_trends', $data['data']);
        $this->assertArrayHasKey('recent_activities', $data['data']);
        $this->assertArrayHasKey('badges', $data['data']);
        $this->assertArrayHasKey('unclaimed_rewards', $data['data']);
    }

    public function testActivityFormatsActivityDate(): void
    {
        $member = $this->createAuthenticatedMember();
        $this->createMemberActivity(['member_id' => $member->id]);

        $response = $this->getForSite('/api/member/dashboard/activity', [], true);

        $data = json_decode($response->getContent(), true);
        foreach ($data['data']['recent_activities'] as $activity) {
            if ($activity['activity_date']) {
                $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $activity['activity_date']);
            }
        }
    }

    public function testActivityRequiresAuthentication(): void
    {
        $this->unauthenticateMember();

        $response = $this->getForSite('/api/member/dashboard/activity', [], true);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testDiscoveryReturnsRecommendedContent(): void
    {
        $member = $this->createAuthenticatedMember(['email_verified_at' => date('Y-m-d H:i:s')]);

        $response = $this->getForSite('/api/member/dashboard/discovery', [], true);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('recommended_pages', $data['data']);
        $this->assertArrayHasKey('trending_pages', $data['data']);
        $this->assertArrayHasKey('gifted_articles', $data['data']);
        $this->assertArrayHasKey('grouped_subscriptions', $data['data']);
    }

    public function testDiscoveryRequiresAuthentication(): void
    {
        $this->unauthenticateMember();

        $response = $this->getForSite('/api/member/dashboard/discovery', [], true);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testNewslettersReturnsMemberNewsletterSubscriptions(): void
    {
        $member = $this->createAuthenticatedMember(['email' => 'dash@example.com']);
        $newsletter = $this->createNewsletter();
        $this->createSubscriber(['email' => $member->email, 'newsletter_id' => $newsletter->id]);

        $response = $this->getForSite('/api/member/dashboard/newsletters', [], true);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('newsletters', $data['data']);
    }

    public function testNewslettersRequiresAuthentication(): void
    {
        $this->unauthenticateMember();

        $response = $this->getForSite('/api/member/dashboard/newsletters', [], true);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testNewslettersItemHasExpectedStructure(): void
    {
        $member = $this->createAuthenticatedMember(['email' => 'nl@example.com']);
        $newsletter = $this->createNewsletter();
        $this->createSubscriber(['email' => $member->email, 'newsletter_id' => $newsletter->id]);

        $response = $this->getForSite('/api/member/dashboard/newsletters', [], true);

        $data = json_decode($response->getContent(), true);
        $this->assertNotEmpty($data['data']['newsletters']);
        $item = $data['data']['newsletters'][0];
        $this->assertArrayHasKey('newsletter_id', $item);
        $this->assertArrayHasKey('title', $item);
        $this->assertArrayHasKey('is_active', $item);
        $this->assertArrayHasKey('can_toggle', $item);
    }

    public function testRewardsReturnsUnclaimedRewards(): void
    {
        $member = $this->createAuthenticatedMember();
        $reward = $this->createMemberReward(['member_id' => $member->id]);

        $response = $this->getForSite('/api/member/dashboard/rewards', [], true);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('unclaimed_rewards', $data['data']);
    }

    public function testRewardsRequiresAuthentication(): void
    {
        $this->unauthenticateMember();

        $response = $this->getForSite('/api/member/dashboard/rewards', [], true);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testSubscriptionsReturnsGroupedSubscriptions(): void
    {
        $member = $this->createAuthenticatedMember();
        $this->createSubscription(['member_id' => $member->id, 'status' => 'active']);

        $response = $this->getForSite('/api/member/dashboard/subscriptions', [], true);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('grouped_subscriptions', $data['data']);
        $this->assertArrayHasKey('active', $data['data']['grouped_subscriptions']);
        $this->assertArrayHasKey('expired', $data['data']['grouped_subscriptions']);
    }

    public function testSubscriptionsRequiresAuthentication(): void
    {
        $this->unauthenticateMember();

        $response = $this->getForSite('/api/member/dashboard/subscriptions', [], true);

        $this->assertEquals(401, $response->getStatusCode());
    }
}