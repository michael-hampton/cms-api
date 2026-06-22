<?php

namespace App\Tests\Functional\Controllers\Subscriptions;

use App\Models\Subscription;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

final class ShopAccountAutoRenewControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function test_member_can_enable_auto_renew_with_consent(): void
    {
        $member = $this->createMember();
        $subscription = $this->createSubscription([
            'member_id' => $member->id,
            'auto_renew' => false,
            'consent_given' => false,
        ]);
        $this->actingAsMember($member);

        $response = $this->post(
            "/press-stack/account/subscriptions/{$subscription->id}/auto-renew",
            [
                'auto_renew' => true,
                'consent_given' => true,
            ]
        );

        $this->assertResponseStatus(200, $response);

        $updated = Subscription::find($subscription->id);
        $this->assertTrue((bool)$updated->auto_renew);
        $this->assertTrue((bool)$updated->consent_given);
    }

    public function test_member_cannot_enable_auto_renew_without_consent(): void
    {
        $member = $this->createMember();
        $subscription = $this->createSubscription([
            'member_id' => $member->id,
            'auto_renew' => false,
            'consent_given' => false,
        ]);
        $this->actingAsMember($member);

        $response = $this->post(
            "/press-stack/account/subscriptions/{$subscription->id}/auto-renew",
            [
                'auto_renew' => true,
                'consent_given' => false,
            ]
        );

        $this->assertResponseStatus(422, $response);

        $updated = Subscription::find($subscription->id);
        $this->assertFalse((bool)$updated->auto_renew);
        $this->assertFalse((bool)$updated->consent_given);
    }

    public function test_member_can_disable_auto_renew_without_new_consent(): void
    {
        $member = $this->createMember();
        $subscription = $this->createSubscription([
            'member_id' => $member->id,
            'auto_renew' => true,
            'consent_given' => true,
        ]);
        $this->actingAsMember($member);

        $response = $this->post(
            "/press-stack/account/subscriptions/{$subscription->id}/auto-renew",
            [
                'auto_renew' => false,
            ]
        );

        $this->assertResponseStatus(200, $response);

        $updated = Subscription::find($subscription->id);
        $this->assertFalse((bool)$updated->auto_renew);
       $this->assertTrue((bool)$updated->consent_given);
    }

    public function test_endpoint_rejects_ambiguous_boolean_values(): void
    {
        $member = $this->createMember();
        $subscription = $this->createSubscription([
            'member_id' => $member->id,
            'auto_renew' => false,
        ]);
        $this->actingAsMember($member);

        $response = $this->post(
            "/press-stack/account/subscriptions/{$subscription->id}/auto-renew",
            [
                'auto_renew' => 'yes',
                'consent_given' => true,
            ]
        );

        $this->assertResponseStatus(422, $response);

        $updated = Subscription::find($subscription->id);
        $this->assertFalse((bool)$updated->auto_renew);
    }

    public function test_member_cannot_update_another_members_subscription(): void
    {
        $member = $this->createMember();
        $otherMember = $this->createMember();
        $subscription = $this->createSubscription([
            'member_id' => $otherMember->id,
            'auto_renew' => false,
        ]);
        $this->actingAsMember($member);

        $response = $this->post(
            "/press-stack/account/subscriptions/{$subscription->id}/auto-renew",
            [
                'auto_renew' => true,
                'consent_given' => true,
            ]
        );

        $this->assertResponseStatus(404, $response);

        $updated = Subscription::find($subscription->id);
        $this->assertFalse((bool)$updated->auto_renew);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $member = $this->createMember();
        $subscription = $this->createSubscription([
            'member_id' => $member->id,
            'auto_renew' => false,
        ]);
        $this->unauthenticateMember();

        $response = $this->post(
            "/press-stack/account/subscriptions/{$subscription->id}/auto-renew",
            [
                'auto_renew' => true,
                'consent_given' => true,
            ],
            [],
            ['Accept' => 'application/json']
        );

        $this->assertResponseStatus(401, $response);

        $updated = Subscription::find($subscription->id);
        $this->assertFalse((bool)$updated->auto_renew);
    }
}
