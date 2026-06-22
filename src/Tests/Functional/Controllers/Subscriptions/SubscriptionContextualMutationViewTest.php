<?php

namespace App\Tests\Functional\Controllers\Subscriptions;

use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

final class SubscriptionContextualMutationViewTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function test_member_cancellation_flow_uses_site_scoped_endpoint(): void
    {
        $member = $this->createMember();
        $subscription = $this->createSubscription([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'status' => 'active',
            'cancel_at_period_end' => false,
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 month')),
        ]);
        $this->actingAsMember($member);

        $content = $this->get(
            "/{$this->siteSlug}/member/subscriptions/unified",
        )->getContent();

        $memberEndpoint = "\\/{$this->siteSlug}\\/member\\/subscriptions\\/unified\\/{$subscription->id}\\/cancel";
        $globalEndpoint = "\\/press-stack\\/account\\/subscriptions\\/{$subscription->id}\\/cancel";

        self::assertStringContainsString($memberEndpoint, $content);
        self::assertStringNotContainsString($globalEndpoint, $content);
    }

    public function test_member_reactivate_action_uses_site_scoped_endpoint(): void
    {
        $member = $this->createMember();
        $subscription = $this->createSubscription([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'status' => 'active',
            'cancel_at_period_end' => true,
            'cancelled_at' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 month')),
        ]);
        $this->actingAsMember($member);

        $content = $this->get(
            "/{$this->siteSlug}/member/subscriptions/unified",
        )->getContent();

        self::assertStringContainsString(
            "/{$this->siteSlug}/member/subscriptions/unified/{$subscription->id}/reactivate",
            $content,
        );
        self::assertStringNotContainsString(
            "/press-stack/account/subscriptions/{$subscription->id}/reactivate",
            $content,
        );
    }
}
