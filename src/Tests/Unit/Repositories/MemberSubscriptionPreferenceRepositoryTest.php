<?php

namespace App\Tests\Unit\Repositories;

use App\Models\Member;
use App\Models\MemberSubscriptionPreference;
use App\Repositories\MemberSubscriptionPreferenceRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MemberSubscriptionPreferenceRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private MemberSubscriptionPreferenceRepository $repository;
    private Member $testMember;

    public function test_find_by_member_returns_preference(): void
    {
        $token = bin2hex(random_bytes(32));

        $preference = MemberSubscriptionPreference::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'email_notifications' => true,
            'newsletter_frequency' => 'weekly',
            'unsubscribe_token' => $token,
            'is_active' => true
        ]);

        $result = $this->repository->findByMember($this->testMember->id, $this->siteId);

        $this->assertNotNull($result);
        $this->assertEquals($preference->id, $result->id);
    }

    public function test_find_by_member_returns_null_when_not_found(): void
    {
        $result = $this->repository->findByMember(99999, $this->siteId);

        $this->assertNull($result);
    }

    public function test_find_by_member_filters_by_site(): void
    {
        $otherSite = $this->createSite();
        $token = bin2hex(random_bytes(32));

        MemberSubscriptionPreference::create([
            'member_id' => $this->testMember->id,
            'site_id' => $otherSite->id,
            'email_notifications' => true,
            'newsletter_frequency' => 'weekly',
            'unsubscribe_token' => $token,
            'is_active' => true
        ]);

        $result = $this->repository->findByMember($this->testMember->id, $this->siteId);

        $this->assertNull($result);
    }

    public function test_find_by_token_returns_preference(): void
    {
        $token = bin2hex(random_bytes(32));

        $preference = MemberSubscriptionPreference::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'email_notifications' => true,
            'newsletter_frequency' => 'weekly',
            'unsubscribe_token' => $token,
            'is_active' => true
        ]);

        $result = $this->repository->findByToken($token);

        $this->assertNotNull($result);
        $this->assertEquals($preference->id, $result->id);
    }

    public function test_get_or_create_returns_existing_preference(): void
    {
        $token = bin2hex(random_bytes(32));

        $existing = MemberSubscriptionPreference::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'email_notifications' => true,
            'newsletter_frequency' => 'weekly',
            'unsubscribe_token' => $token,
            'is_active' => true
        ]);

        $result = $this->repository->getOrCreateForMember($this->testMember->id, $this->siteId);

        $this->assertEquals($existing->id, $result->id);
    }

    public function test_get_or_create_creates_new_preference(): void
    {
        $result = $this->repository->getOrCreateForMember($this->testMember->id, $this->siteId);

        $this->assertNotNull($result);
        $this->assertEquals($this->testMember->id, $result->member_id);
        $this->assertEquals($this->siteId, $result->site_id);
        $this->assertTrue($result->is_active);
        $this->assertNotEmpty($result->unsubscribe_token);
    }

    public function test_update_preferences_modifies_existing(): void
    {
        $token = bin2hex(random_bytes(32));

        MemberSubscriptionPreference::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'email_notifications' => true,
            'newsletter_frequency' => 'weekly',
            'content_types' => ['news'],
            'unsubscribe_token' => $token,
            'is_active' => true
        ]);

        $result = $this->repository->updatePreferences($this->testMember->id, [
            'newsletter_frequency' => 'daily',
            'content_types' => ['news', 'blog']
        ], $this->siteId);

        $this->assertNotNull($result);
        $this->assertEquals('daily', $result->newsletter_frequency);
        $this->assertCount(2, $result->content_types);
    }

    public function test_update_preferences_returns_null_when_not_found(): void
    {
        $result = $this->repository->updatePreferences(99999, [
            'newsletter_frequency' => 'daily'
        ], $this->siteId);

        $this->assertNull($result);
    }

    public function test_unsubscribe_sets_inactive(): void
    {
        $token = bin2hex(random_bytes(32));

        MemberSubscriptionPreference::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'email_notifications' => true,
            'newsletter_frequency' => 'weekly',
            'unsubscribe_token' => $token,
            'is_active' => true
        ]);

        $result = $this->repository->unsubscribe($token);

        $this->assertTrue($result);

        $preference = $this->repository->findByToken($token);
        $this->assertFalse($preference->is_active);
        $this->assertFalse($preference->email_notifications);
    }

    public function test_unsubscribe_returns_false_for_invalid_token(): void
    {
        $result = $this->repository->unsubscribe('invalid-token');

        $this->assertFalse($result);
    }

    public function test_resubscribe_sets_active(): void
    {
        $token = bin2hex(random_bytes(32));

        MemberSubscriptionPreference::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'email_notifications' => false,
            'newsletter_frequency' => 'weekly',
            'unsubscribe_token' => $token,
            'is_active' => false
        ]);

        $result = $this->repository->resubscribe($token);

        $this->assertTrue($result);

        $preference = $this->repository->findByToken($token);
        $this->assertTrue($preference->is_active);
        $this->assertTrue($preference->email_notifications);
    }

    public function test_get_active_subscribers_for_site(): void
    {
        $token1 = bin2hex(random_bytes(32));
        $token2 = bin2hex(random_bytes(32));
        $token3 = bin2hex(random_bytes(32));

        $member2 = $this->createMember(['email' => 'member2@example.com']);
        $member3 = $this->createMember(['email' => 'member3@example.com']);

        MemberSubscriptionPreference::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'email_notifications' => true,
            'unsubscribe_token' => $token1,
            'is_active' => true
        ]);

        MemberSubscriptionPreference::create([
            'member_id' => $member2->id,
            'site_id' => $this->siteId,
            'email_notifications' => true,
            'unsubscribe_token' => $token2,
            'is_active' => true
        ]);

        // Inactive preference - should not be returned
        MemberSubscriptionPreference::create([
            'member_id' => $member3->id,
            'site_id' => $this->siteId,
            'email_notifications' => false,
            'unsubscribe_token' => $token3,
            'is_active' => false
        ]);

        $result = $this->repository->getActiveSubscribersForSite($this->siteId);

        $this->assertCount(2, $result);
    }

    public function test_get_subscribers_for_content_type(): void
    {
        $token1 = bin2hex(random_bytes(32));
        $token2 = bin2hex(random_bytes(32));
        $token3 = bin2hex(random_bytes(32));

        $member2 = $this->createMember(['email' => 'member2@example.com']);
        $member3 = $this->createMember(['email' => 'member3@example.com']);

        // No specific content types - should receive all
        MemberSubscriptionPreference::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'email_notifications' => true,
            'content_types' => null,
            'unsubscribe_token' => $token1,
            'is_active' => true
        ]);

        // Has 'news' in preferences
        MemberSubscriptionPreference::create([
            'member_id' => $member2->id,
            'site_id' => $this->siteId,
            'email_notifications' => true,
            'content_types' => ['news', 'blog'],
            'unsubscribe_token' => $token2,
            'is_active' => true
        ]);

        // Does not have 'news' in preferences
        MemberSubscriptionPreference::create([
            'member_id' => $member3->id,
            'site_id' => $this->siteId,
            'email_notifications' => true,
            'content_types' => ['promotions'],
            'unsubscribe_token' => $token3,
            'is_active' => true
        ]);

        $result = $this->repository->getSubscribersForContentType('news', $this->siteId);

        $this->assertCount(2, $result);
    }

    public function test_get_subscribers_for_category(): void
    {
        $token1 = bin2hex(random_bytes(32));
        $token2 = bin2hex(random_bytes(32));
        $token3 = bin2hex(random_bytes(32));

        $member2 = $this->createMember(['email' => 'member2@example.com']);
        $member3 = $this->createMember(['email' => 'member3@example.com']);

        // No specific categories - should receive all
        MemberSubscriptionPreference::create([
            'member_id' => $this->testMember->id,
            'site_id' => $this->siteId,
            'email_notifications' => true,
            'category_preferences' => null,
            'unsubscribe_token' => $token1,
            'is_active' => true
        ]);

        // Has category 1 in preferences
        MemberSubscriptionPreference::create([
            'member_id' => $member2->id,
            'site_id' => $this->siteId,
            'email_notifications' => true,
            'category_preferences' => [1, 2],
            'unsubscribe_token' => $token2,
            'is_active' => true
        ]);

        // Does not have category 1 in preferences
        MemberSubscriptionPreference::create([
            'member_id' => $member3->id,
            'site_id' => $this->siteId,
            'email_notifications' => true,
            'category_preferences' => [3, 4],
            'unsubscribe_token' => $token3,
            'is_active' => true
        ]);

        $result = $this->repository->getSubscribersForCategory(1, $this->siteId);

        $this->assertCount(2, $result);

        $memberIds = $result->pluck('member_id')->toArray();
        $this->assertContains($this->testMember->id, $memberIds);
        $this->assertContains($member2->id, $memberIds);
        $this->assertNotContains($member3->id, $memberIds);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new MemberSubscriptionPreferenceRepository();
        $this->testMember = $this->createMember();
    }
}