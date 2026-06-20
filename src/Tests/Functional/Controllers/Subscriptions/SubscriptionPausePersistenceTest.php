<?php

namespace App\Tests\Functional\Controllers\Subscriptions;

use App\Models\Subscription;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

final class SubscriptionPausePersistenceTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function test_pause_is_indefinite_and_stores_enabled_renewal_preference(): void
    {
        $member = $this->createMember();
        $subscription = $this->createSubscription([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'status' => 'active',
            'auto_renew' => true,
        ]);
        $this->actingAsMember($member);

        $response = $this->post(
            "/press-stack/account/subscriptions/{$subscription->id}/pause",
            [],
        );

        $this->assertResponseStatus(200, $response);
        $stored = Subscription::find($subscription->id);
        self::assertSame('paused', $stored->status);
        self::assertFalse((bool) $stored->auto_renew);
        self::assertTrue((bool) $stored->auto_renew_before_pause);
        self::assertNull($stored->pause_until);
        self::assertNotNull($stored->paused_at);
    }

    public function test_resume_restores_enabled_renewal_and_clears_pause_metadata(): void
    {
        $member = $this->createMember();
        $subscription = $this->createSubscription([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'status' => 'active',
            'auto_renew' => true,
        ]);
        $this->actingAsMember($member);
        $this->post("/press-stack/account/subscriptions/{$subscription->id}/pause", []);

        $response = $this->post(
            "/press-stack/account/subscriptions/{$subscription->id}/resume",
            [],
        );

        $this->assertResponseStatus(200, $response);
        $stored = Subscription::find($subscription->id);
        self::assertSame('active', $stored->status);
        self::assertTrue((bool) $stored->auto_renew);
        self::assertNull($stored->auto_renew_before_pause);
        self::assertNull($stored->paused_at);
        self::assertNull($stored->pause_until);
        self::assertNotNull($stored->next_billing_date);
    }

    public function test_resume_does_not_enable_renewal_when_it_was_disabled_before_pause(): void
    {
        $member = $this->createMember();
        $subscription = $this->createSubscription([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'status' => 'active',
            'auto_renew' => false,
        ]);
        $this->actingAsMember($member);
        $this->post("/press-stack/account/subscriptions/{$subscription->id}/pause", []);

        $response = $this->post(
            "/press-stack/account/subscriptions/{$subscription->id}/resume",
            [],
        );

        $this->assertResponseStatus(200, $response);
        $stored = Subscription::find($subscription->id);
        self::assertFalse((bool) $stored->auto_renew);
        self::assertNull($stored->auto_renew_before_pause);
    }

    public function test_member_pause_route_enforces_site_scope_and_persists_state(): void
    {
        $member = $this->createMember();
        $subscription = $this->createSubscription([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'status' => 'active',
            'auto_renew' => true,
        ]);
        $this->actingAsMember($member);

        $response = $this->post(
            "/{$this->siteSlug}/member/subscriptions/unified/{$subscription->id}/pause",
            [],
        );

        $this->assertResponseStatus(200, $response);
        self::assertSame('paused', Subscription::find($subscription->id)->status);
    }

    public function test_invalid_status_and_wrong_member_are_rejected_without_mutation(): void
    {
        $member = $this->createMember();
        $otherMember = $this->createMember();
        $paused = $this->createSubscription([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'status' => 'paused',
        ]);
        $ownedByOther = $this->createSubscription([
            'member_id' => $otherMember->id,
            'site_id' => $this->siteId,
            'status' => 'active',
        ]);
        $this->actingAsMember($member);

        $invalidStatus = $this->post(
            "/press-stack/account/subscriptions/{$paused->id}/pause",
            [],
        );
        $wrongMember = $this->post(
            "/press-stack/account/subscriptions/{$ownedByOther->id}/pause",
            [],
        );

        $this->assertResponseStatus(422, $invalidStatus);
        $this->assertResponseStatus(422, $wrongMember);
        self::assertSame('paused', Subscription::find($paused->id)->status);
        self::assertSame('active', Subscription::find($ownedByOther->id)->status);
    }
}
