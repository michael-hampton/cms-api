<?php

namespace App\Tests\Functional\Controllers\Members\Api;

use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MemberNewslettersApiControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testIndexReturnsNewsletterData(): void
    {
        $member = $this->createAuthenticatedMember(['email' => 'nl@example.com']);
        $newsletter = $this->createNewsletter();
        $this->createSubscriber(['email' => $member->email, 'newsletter_id' => $newsletter->id]);

        $response = $this->getForSite('/api/member/newsletters', [], true);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('newsletters_with_access', $data['data']);
        $this->assertArrayHasKey('available_newsletters', $data['data']);
        $this->assertArrayHasKey('subscriptions', $data['data']);
        $this->assertArrayHasKey('plans', $data['data']);
    }

    private function createAuthenticatedMember(array $overrides = [])
    {
        $member = $this->createMember($overrides);
        $this->actingAsMember($member);
        return $member;
    }

    public function testIndexReturnsUnauthorizedWhenNotLoggedIn(): void
    {
        $this->unauthenticateMember();

        $response = $this->getForSite('/api/member/newsletters', [], true);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testIndexNewsletterItemHasAccessFields(): void
    {
        $member = $this->createAuthenticatedMember(['email' => 'access@example.com']);
        $this->createNewsletter();

        $response = $this->getForSite('/api/member/newsletters', [], true);

        $data = json_decode($response->getContent(), true);
        if (!empty($data['data']['newsletters_with_access'])) {
            $item = $data['data']['newsletters_with_access'][0];
            $this->assertArrayHasKey('has_access', $item);
            $this->assertArrayHasKey('is_subscribed', $item);
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('title', $item);
        }
    }

    public function testSubscribeToNewsletter(): void
    {
        $member = $this->createAuthenticatedMember(['email' => 'sub_123@example.com']);
        $newsletter = $this->createNewsletter();

        $response = $this->postForSite('/api/member/newsletter/signup', [
            'newsletter_id' => $newsletter->id,
        ], [], [], [], true);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['data']['success']);
        $this->assertArrayHasKey('newsletter_id', $data['data']);
        $this->assertArrayHasKey('subscriber_id', $data['data']);
    }

    public function testSubscribeRequiresAuthentication(): void
    {
        $this->unauthenticateMember();
        $newsletter = $this->createNewsletter();

        $response = $this->postForSite('/api/member/newsletter/signup', [
            'newsletter_id' => $newsletter->id,
        ], [], [], [], true);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testUnsubscribeFromNewsletter(): void
    {
        $member = $this->createAuthenticatedMember(['email' => 'unsub@example.com']);
        $newsletter = $this->createNewsletter();
        $subscriber = $this->createSubscriber([
            'email' => $member->email,
            'newsletter_id' => $newsletter->id,
        ]);

        $response = $this->postForSite('/api/member/newsletters/unsubscribe', [
            'subscriber_id' => $subscriber->id,
        ], [], [], [], true);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
    }

    public function testUnsubscribeReturnsForbiddenForOtherMembersSubscription(): void
    {
        $this->createAuthenticatedMember(['email' => 'me@example.com']);
        $otherMember = $this->createMember(['email' => 'other@example.com']);
        $newsletter = $this->createNewsletter();
        $subscriber = $this->createSubscriber([
            'email' => $otherMember->email,
            'newsletter_id' => $newsletter->id,
        ]);

        $response = $this->postForSite('/api/member/newsletters/unsubscribe', [
            'subscriber_id' => $subscriber->id,
        ], [], [], [], true);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testUnsubscribeRequiresAuthentication(): void
    {
        $this->unauthenticateMember();

        $response = $this->postForSite('/api/member/newsletters/unsubscribe', [
            'subscriber_id' => 1,
        ], [], [], [], true);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testBulkSubscribeToMultipleNewsletters(): void
    {
        $member = $this->createAuthenticatedMember(['email' => 'bulk@example.com']);
        $nl1 = $this->createNewsletter();
        $nl2 = $this->createNewsletter();

        $response = $this->postForSite('/api/member/newsletters/bulk-subscribe', [
            'newsletter_ids' => [$nl1->id, $nl2->id],
        ], [], [], [], true);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['data']['success']);
        $this->assertArrayHasKey('count', $data['data']);
    }

    public function testBulkSubscribeRequiresNewsletterIds(): void
    {
        $this->createAuthenticatedMember();

        $response = $this->postForSite('/api/member/newsletters/bulk-subscribe', [
            'newsletter_ids' => [],
        ], [], [], [], true);

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
    }

    public function testBulkSubscribeRequiresAuthentication(): void
    {
        $this->unauthenticateMember();

        $response = $this->postForSite('/api/member/newsletters/bulk-subscribe', [
            'newsletter_ids' => [1],
        ], [], [], [], true);

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testGetUpgradeOptionsRequiresNewsletterIdParam(): void
    {
        $this->createAuthenticatedMember();

        $response = $this->postForSite('/api/member/newsletters/upgrade-options', [], [], [], [], true);

        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['data']['success']);
    }

    public function testGetUpgradeOptionsReturns404ForInvalidNewsletter(): void
    {
        $this->createAuthenticatedMember();

        $response = $this->postForSite('/api/member/newsletters/upgrade-options?newsletter_id=99999&site_id=' . $this->siteId, [], [], [], [], true);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testGetUpgradeOptionsRequiresAuthentication(): void
    {
        $this->unauthenticateMember();

        $response = $this->postForSite('/api/member/newsletters/upgrade-options?newsletter_id=1', [], [], [], [], true);

        $this->assertEquals(401, $response->getStatusCode());
    }
}