<?php

namespace App\Tests\Functional\Controllers\Subscriptions;

use App\Models\Site;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

final class UnifiedMemberSubscriptionContinuationControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function test_valid_renew_and_resubscribe_use_site_checkout(): void
    {
        $member = $this->createMember();
        $renewable = $this->createSubscription([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'status' => 'active',
            'auto_renew' => false,
            'end_date' => date('Y-m-d H:i:s', strtotime('+10 days')),
        ]);
        $expired = $this->createSubscription([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'status' => 'expired',
            'end_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
        ]);
        $this->actingAsMember($member);

        $renew = $this->get('/' . $this->siteSlug . '/member/subscriptions/unified/' . $renewable->id . '/renew');
        $resubscribe = $this->get('/' . $this->siteSlug . '/member/subscriptions/unified/' . $expired->id . '/resubscribe');

        self::assertSame('/' . $this->siteSlug . '/checkout?subscription_id=' . $renewable->id . '&renew=true', $renew->getHeader('Location'));
        self::assertSame('/' . $this->siteSlug . '/checkout?subscription_id=' . $expired->id . '&resubscribe=true', $resubscribe->getHeader('Location'));
    }

    public function test_disallowed_action_returns_to_unified_page(): void
    {
        $member = $this->createMember();
        $subscription = $this->createSubscription([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'status' => 'active',
            'auto_renew' => true,
            'end_date' => date('Y-m-d H:i:s', strtotime('+6 months')),
        ]);
        $this->actingAsMember($member);

        $response = $this->get('/' . $this->siteSlug . '/member/subscriptions/unified/' . $subscription->id . '/renew');

        $this->assertResponseStatus(302, $response);
        self::assertSame('/' . $this->siteSlug . '/member/subscriptions/unified', $response->getHeader('Location'));
    }

    public function test_wrong_member_wrong_site_missing_and_unauthenticated_are_rejected(): void
    {
        $member = $this->createMember();
        $otherMember = $this->createMember();
        $otherSite = Site::create([
            'name' => 'Other Publication',
            'slug' => 'other-publication-' . uniqid(),
            'is_active' => true,
            'is_default' => false,
        ]);
        $wrongMember = $this->createSubscription(['member_id' => $otherMember->id, 'site_id' => $this->siteId, 'status' => 'expired']);
        $wrongSite = $this->createSubscription(['member_id' => $member->id, 'site_id' => $otherSite->id, 'status' => 'expired']);
        $this->actingAsMember($member);

        foreach ([$wrongMember->id, $wrongSite->id, 999999] as $id) {
            $this->assertResponseStatus(404, $this->get('/' . $this->siteSlug . '/member/subscriptions/unified/' . $id . '/resubscribe'));
        }

        $this->unauthenticateMember();
        $response = $this->get('/' . $this->siteSlug . '/member/subscriptions/unified/42/renew');
        $this->assertResponseStatus(302, $response);
        self::assertStringStartsWith('/' . $this->siteSlug . '/member/login?redirect=', $response->getHeader('Location'));
    }
}
