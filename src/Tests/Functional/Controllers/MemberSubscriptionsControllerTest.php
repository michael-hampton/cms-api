<?php

namespace App\Tests\Functional\Controllers;

use App\Framework\Authorization\MemberAuth;
use App\Framework\Session\Session;
use App\Models\Member;
use App\Models\MemberSubscriptionPreference;
use App\Models\Subscription;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MemberSubscriptionsControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private Member $member;

    public function testIndexDisplaysSubscriptionInformation(): void
    {
        // Create active subscription
        Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD',
            'auto_renew' => true
        ]);

        // Create preference
        $token = bin2hex(random_bytes(32));
        MemberSubscriptionPreference::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'email_notifications' => true,
            'newsletter_frequency' => 'weekly',
            'unsubscribe_token' => $token,
            'is_active' => true
        ]);

        $response = $this->getForSite('/member/subscriptions');

        $this->assertEquals(200, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertStringContainsString('My Subscriptions', $content);
    }

    public function testPreferencesRedirectsWhenNotAuthenticated(): void
    {
        MemberAuth::setMember(null);
        Session::forget('member_id');

        $response = $this->getForSite('/member/subscriptions/preferences');

        $this->assertEquals(302, $response->getStatusCode());
    }

    public function testPreferencesDisplaysPreferenceForm(): void
    {
        $token = bin2hex(random_bytes(32));
        MemberSubscriptionPreference::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'email_notifications' => true,
            'newsletter_frequency' => 'weekly',
            'content_types' => ['news', 'blog'],
            'unsubscribe_token' => $token,
            'is_active' => true
        ]);

        $response = $this->getForSite('/member/subscriptions/preferences');

        $this->assertEquals(200, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertStringContainsString('Subscription Preferences', $content);
        $this->assertStringContainsString('newsletter_frequency', $content);
    }

    public function testUpdatePreferencesRedirectsForNonAjax(): void
    {
        $token = bin2hex(random_bytes(32));
        MemberSubscriptionPreference::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'email_notifications' => true,
            'newsletter_frequency' => 'weekly',
            'unsubscribe_token' => $token,
            'is_active' => true
        ]);

        $response = $this->postForSite('/member/subscriptions/preferences', [
            'email_notifications' => true,
            'newsletter_frequency' => 'daily'
        ]);

        $this->assertEquals(302, $response->getStatusCode());
    }

//    public function testUpdatePreferencesSuccessfully(): void
//    {
//        $token = bin2hex(random_bytes(32));
//        MemberSubscriptionPreference::create([
//            'member_id' => $this->member->id,
//            'site_id' => $this->siteId,
//            'email_notifications' => true,
//            'newsletter_frequency' => 'weekly',
//            'unsubscribe_token' => $token,
//            'is_active' => true
//        ]);
//
//        $response = $this->postForSite('/member/subscriptions/preferences', [
//            'email_notifications' => true,
//            'newsletter_frequency' => 'daily',
//            'content_types' => ['news', 'blog'],
//            'category_preferences' => [1, 2]
//        ], [], ['X-Requested-With' => 'XMLHttpRequest']);
//
//        $this->assertEquals(200, $response->getStatusCode());
//        $data = json_decode($response->getContent(), true);
//
//        $this->assertTrue($data['success']);
//        $this->assertArrayHasKey('preference', $data['data']);
//        $this->assertEquals('daily', $data['data']['preference']['newsletter_frequency']);
//    }

    public function testUnsubscribeFormDisplaysForValidToken(): void
    {
        $token = bin2hex(random_bytes(32));
        MemberSubscriptionPreference::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'email_notifications' => true,
            'newsletter_frequency' => 'weekly',
            'unsubscribe_token' => $token,
            'is_active' => true
        ]);

        $response = $this->getForSite("/member/subscriptions/unsubscribe/{$token}");

        $this->assertEquals(200, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertStringContainsString('Unsubscribe from Emails', $content);
        $this->assertStringContainsString($token, $content);
    }

//    public function testUpdatePreferencesRequiresAuthentication(): void
//    {
//        MemberAuth::setMember(null);
//        Session::forget('member_id');
//
//        $response = $this->postForSite('/member/subscriptions/preferences', [
//            'email_notifications' => true
//        ], [], ['X-Requested-With' => 'XMLHttpRequest']);
//
//        $this->assertEquals(401, $response->getStatusCode());
//        $data = json_decode($response->getContent(), true);
//        $this->assertFalse($data['success']);
//    }

    public function testUnsubscribeFormDisplaysErrorForInvalidToken(): void
    {
        $response = $this->getForSite('/member/subscriptions/unsubscribe/invalid-token');

        $this->assertEquals(200, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertStringContainsString('Invalid', $content);
    }

    public function testUnsubscribeSuccessfully(): void
    {
        $token = bin2hex(random_bytes(32));
        MemberSubscriptionPreference::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'email_notifications' => true,
            'newsletter_frequency' => 'weekly',
            'unsubscribe_token' => $token,
            'is_active' => true
        ]);

        $response = $this->postForSite("/member/subscriptions/unsubscribe/{$token}");

        $this->assertEquals(200, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertStringContainsString('unsubscribed', $content);

        // Verify preference is now inactive
        $preference = MemberSubscriptionPreference::where('unsubscribe_token', $token)->first();
        $this->assertFalse($preference->is_active);
        $this->assertFalse($preference->email_notifications);
    }

    public function testUnsubscribeWithInvalidTokenDisplaysError(): void
    {
        $response = $this->postForSite('/member/subscriptions/unsubscribe/invalid-token');

        $this->assertEquals(200, $response->getStatusCode());
        $content = $response->getContent();
        $this->assertStringContainsString('Invalid', $content);
    }

    public function testResubscribeSuccessfully(): void
    {
        $token = bin2hex(random_bytes(32));
        MemberSubscriptionPreference::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'email_notifications' => false,
            'newsletter_frequency' => 'weekly',
            'unsubscribe_token' => $token,
            'is_active' => false
        ]);

        $response = $this->postForSite("/member/subscriptions/resubscribe/{$token}");

        $this->assertEquals(302, $response->getStatusCode());

        // Verify preference is now active
        $preference = MemberSubscriptionPreference::where('unsubscribe_token', $token)->first();
        $this->assertTrue($preference->is_active);
        $this->assertTrue($preference->email_notifications);
    }

//    public function testUnsubscribeReturnsJsonForAjax(): void
//    {
//        $token = bin2hex(random_bytes(32));
//        MemberSubscriptionPreference::create([
//            'member_id' => $this->member->id,
//            'site_id' => $this->siteId,
//            'email_notifications' => true,
//            'newsletter_frequency' => 'weekly',
//            'unsubscribe_token' => $token,
//            'is_active' => true
//        ]);
//
//        $response = $this->postForSite(
//            "/member/subscriptions/unsubscribe/{$token}",
//            [],
//            [],
//            ['X-Requested-With' => 'XMLHttpRequest']
//        );
//
//        $this->assertEquals(200, $response->getStatusCode());
//        $data = json_decode($response->getContent(), true);
//
//        $this->assertTrue($data['success']);
//        $this->assertStringContainsString('unsubscribed', $data['message']);
//    }

    public function testCancelSubscriptionSuccessfully(): void
    {
        $subscription = Subscription::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD',
            'auto_renew' => true
        ]);

        $response = $this->postForSite(
            "/member/subscriptions/{$subscription->id}/cancel",
        );

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);

        // Verify subscription is cancelled
        $updated = Subscription::find($subscription->id);
        $this->assertEquals('cancelled', $updated->status);
        $this->assertFalse($updated->auto_renew);
    }

//    public function testResubscribeReturnsJsonForAjax(): void
//    {
//        $token = bin2hex(random_bytes(32));
//        MemberSubscriptionPreference::create([
//            'member_id' => $this->member->id,
//            'site_id' => $this->siteId,
//            'email_notifications' => false,
//            'newsletter_frequency' => 'weekly',
//            'unsubscribe_token' => $token,
//            'is_active' => false
//        ]);
//
//        $response = $this->postForSite(
//            "/member/subscriptions/resubscribe/{$token}",
//            [],
//            [],
//            ['X-Requested-With' => 'XMLHttpRequest']
//        );
//
//        $this->assertEquals(200, $response->getStatusCode());
//        $data = json_decode($response->getContent(), true);
//        $this->assertTrue($data['success']);
//        $this->assertStringContainsString('resubscribed', $data['message']);
//    }

    public function testCancelSubscriptionRequiresAuthentication(): void
    {
        $this->logout();

        $response = $this->postForSite(
            '/member/subscriptions/1/cancel',
        );

        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
    }

    private function logout(): void
    {
        unset($_SESSION['member_id']);
        unset($_SESSION['member_authenticated']);
    }

    public function testCancelSubscriptionReturns404ForNonExistent(): void
    {
        $response = $this->postForSite(
            '/member/subscriptions/99999/cancel'
        );

        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
    }

    public function testCancelSubscriptionReturns404ForOtherMembersSubscription(): void
    {
        $otherMember = $this->createMember(['email' => 'other@example.com']);

        $subscription = Subscription::create([
            'member_id' => $otherMember->id,
            'site_id' => $this->siteId,
            'plan_name' => 'Premium',
            'status' => 'active',
            'start_date' => date('Y-m-d H:i:s'),
            'price' => 29.99,
            'currency' => 'USD',
            'auto_renew' => true
        ]);

        $response = $this->postForSite(
            "/member/subscriptions/{$subscription->id}/cancel"
        );

        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->member = $this->createMember();
        $this->actingAsMember($this->member);
    }

    private function authenticateMember(Member $member): void
    {
        $_SESSION['member_id'] = $member->id;
        $_SESSION['member_authenticated'] = true;
    }
}