<?php

namespace App\Tests\Unit\Models;

use App\Models\Member;
use App\Models\MemberSubscriptionPreference;
use App\Models\Site;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MemberSubscriptionPreferenceTest extends FunctionalTestCase
{
    use CreatesTestData;

    private Member $member;
    private Site $site;

    public function test_can_create_preference(): void
    {
        $token = bin2hex(random_bytes(32));

        $preference = MemberSubscriptionPreference::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'email_notifications' => true,
            'newsletter_frequency' => 'weekly',
            'content_types' => ['news', 'blog'],
            'category_preferences' => [1, 2, 3],
            'unsubscribe_token' => $token,
            'is_active' => true
        ]);

        $this->assertNotNull($preference);
        $this->assertEquals($this->member->id, $preference->member_id);
        $this->assertTrue($preference->email_notifications);
        $this->assertEquals('weekly', $preference->newsletter_frequency);
    }

    public function test_content_types_cast_to_array(): void
    {
        $token = bin2hex(random_bytes(32));

        $preference = MemberSubscriptionPreference::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'email_notifications' => true,
            'newsletter_frequency' => 'weekly',
            'content_types' => ['news', 'blog'],
            'unsubscribe_token' => $token,
            'is_active' => true
        ]);

        $this->assertIsArray($preference->content_types);
        $this->assertContains('news', $preference->content_types);
        $this->assertContains('blog', $preference->content_types);
    }

    public function test_find_by_token_returns_preference(): void
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

        $found = MemberSubscriptionPreference::findByToken($token);

        $this->assertNotNull($found);
        $this->assertEquals($token, $found->unsubscribe_token);
    }

    public function test_find_by_token_returns_null_when_not_found(): void
    {
        $found = MemberSubscriptionPreference::findByToken('invalid-token');

        $this->assertNull($found);
    }

    public function test_unsubscribe_sets_inactive(): void
    {
        $token = bin2hex(random_bytes(32));

        $preference = MemberSubscriptionPreference::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'email_notifications' => true,
            'newsletter_frequency' => 'weekly',
            'unsubscribe_token' => $token,
            'is_active' => true
        ]);

        $result = $preference->unsubscribe();

        $this->assertTrue($result);
        $this->assertFalse($preference->is_active);
        $this->assertFalse($preference->email_notifications);
    }

    public function test_resubscribe_sets_active(): void
    {
        $token = bin2hex(random_bytes(32));

        $preference = MemberSubscriptionPreference::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'email_notifications' => false,
            'newsletter_frequency' => 'weekly',
            'unsubscribe_token' => $token,
            'is_active' => false
        ]);

        $result = $preference->resubscribe();

        $this->assertTrue($result);
        $this->assertTrue($preference->is_active);
        $this->assertTrue($preference->email_notifications);
    }

    public function test_has_preference_for_returns_true_when_active_and_no_specific_types(): void
    {
        $token = bin2hex(random_bytes(32));

        $preference = MemberSubscriptionPreference::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'email_notifications' => true,
            'newsletter_frequency' => 'weekly',
            'content_types' => null,
            'unsubscribe_token' => $token,
            'is_active' => true
        ]);

        $this->assertTrue($preference->hasPreferenceFor('news'));
        $this->assertTrue($preference->hasPreferenceFor('blog'));
    }

    public function test_has_preference_for_returns_true_when_content_type_selected(): void
    {
        $token = bin2hex(random_bytes(32));

        $preference = MemberSubscriptionPreference::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'email_notifications' => true,
            'newsletter_frequency' => 'weekly',
            'content_types' => ['news', 'blog'],
            'unsubscribe_token' => $token,
            'is_active' => true
        ]);

        $this->assertTrue($preference->hasPreferenceFor('news'));
        $this->assertFalse($preference->hasPreferenceFor('promotions'));
    }

    public function test_has_preference_for_returns_false_when_inactive(): void
    {
        $token = bin2hex(random_bytes(32));

        $preference = MemberSubscriptionPreference::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'email_notifications' => true,
            'newsletter_frequency' => 'weekly',
            'content_types' => ['news'],
            'unsubscribe_token' => $token,
            'is_active' => false
        ]);

        $this->assertFalse($preference->hasPreferenceFor('news'));
    }

    public function test_wants_category_returns_true_when_active_and_no_specific_categories(): void
    {
        $token = bin2hex(random_bytes(32));

        $preference = MemberSubscriptionPreference::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'email_notifications' => true,
            'newsletter_frequency' => 'weekly',
            'category_preferences' => null,
            'unsubscribe_token' => $token,
            'is_active' => true
        ]);

        $this->assertTrue($preference->wantsCategory(1));
        $this->assertTrue($preference->wantsCategory(2));
    }

    public function test_wants_category_returns_true_when_category_selected(): void
    {
        $token = bin2hex(random_bytes(32));

        $preference = MemberSubscriptionPreference::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'email_notifications' => true,
            'newsletter_frequency' => 'weekly',
            'category_preferences' => [1, 2],
            'unsubscribe_token' => $token,
            'is_active' => true
        ]);

        $this->assertTrue($preference->wantsCategory(1));
        $this->assertFalse($preference->wantsCategory(3));
    }

    public function test_member_relationship(): void
    {
        $token = bin2hex(random_bytes(32));

        $preference = MemberSubscriptionPreference::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'email_notifications' => true,
            'newsletter_frequency' => 'weekly',
            'unsubscribe_token' => $token,
            'is_active' => true
        ]);

        $member = $preference->member;

        $this->assertNotNull($member);
        $this->assertEquals($this->member->id, $member->id);
    }

    public function test_site_relationship(): void
    {
        $token = bin2hex(random_bytes(32));

        $preference = MemberSubscriptionPreference::create([
            'member_id' => $this->member->id,
            'site_id' => $this->siteId,
            'email_notifications' => true,
            'newsletter_frequency' => 'weekly',
            'unsubscribe_token' => $token,
            'is_active' => true
        ]);

        $site = $preference->site;

        $this->assertNotNull($site);
        $this->assertEquals($this->siteId, $site->id);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->member = $this->createMember();
    }
}