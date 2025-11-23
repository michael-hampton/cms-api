<?php

namespace App\Tests\Unit\Models;

use App\Models\Badge;
use App\Models\MemberBadge;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class BadgeModelTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testCreateBadge()
    {
        $badge = Badge::create([
            'site_id' => $this->siteId,
            'name' => 'First Comment',
            'slug' => 'first-comment',
            'description' => 'Posted your first comment',
            'icon' => '💬',
            'tier' => 'bronze',
            'category' => 'engagement',
            'criteria' => [
                ['type' => 'comments_count', 'operator' => '>=', 'value' => 1]
            ],
            'points' => 50,
            'is_active' => true
        ]);

        $this->assertInstanceOf(Badge::class, $badge);
        $this->assertEquals('First Comment', $badge->name);
        $this->assertEquals('bronze', $badge->tier);
        $this->assertTrue($badge->is_active);
    }

    public function testBadgeCriteriaCast()
    {
        $criteria = [
            ['type' => 'comments_count', 'operator' => '>=', 'value' => 10]
        ];

        $badge = Badge::create([
            'site_id' => $this->siteId,
            'name' => 'Test Badge',
            'slug' => 'test-badge',
            'tier' => 'silver',
            'category' => 'engagement',
            'criteria' => $criteria,
            'is_active' => true
        ]);

        $this->assertIsArray($badge->criteria);
        $this->assertEquals($criteria, $badge->criteria);
    }

    public function testBadgeHasManyMembers()
    {
        $badge = $this->createBadge();
        $member1 = $this->createMember();
        $member2 = $this->createMember();

        MemberBadge::create([
            'member_id' => $member1->id,
            'badge_id' => $badge->id,
            'earned_at' => now()
        ]);

        MemberBadge::create([
            'member_id' => $member2->id,
            'badge_id' => $badge->id,
            'earned_at' => now()
        ]);

        $members = $badge->members(true)->get();
        $this->assertEquals(2, $members->count());
    }

    public function testCheckCriteriaReturnsTrueWhenAllMet()
    {
        $member = $this->createMember();

        // Create comments to meet criteria
        for ($i = 0; $i < 5; $i++) {
            $this->createComment(['email' => $member->email, 'member_id' => $member->id]);
        }

        $badge = Badge::create([
            'site_id' => $this->siteId,
            'name' => 'Commenter',
            'slug' => 'commenter',
            'tier' => 'bronze',
            'category' => 'engagement',
            'criteria' => [
                ['type' => 'comments_count', 'operator' => '>=', 'value' => 5]
            ],
            'is_active' => true
        ]);

        $this->assertTrue($badge->checkCriteria($member));
    }

    public function testCheckCriteriaReturnsFalseWhenNotMet()
    {
        $member = $this->createMember();

        // Only create 2 comments
        for ($i = 0; $i < 2; $i++) {
            $this->createComment(['email' => $member->email, 'member_id' => $member->id]);
        }

        $badge = Badge::create([
            'site_id' => $this->siteId,
            'name' => 'Super Commenter',
            'slug' => 'super-commenter',
            'tier' => 'gold',
            'category' => 'engagement',
            'criteria' => [
                ['type' => 'comments_count', 'operator' => '>=', 'value' => 10]
            ],
            'is_active' => true
        ]);

        $this->assertFalse($badge->checkCriteria($member));
    }

    public function testCheckCriteriaWithMultipleRules()
    {
        $member = $this->createMember();

        // Create 5 comments
        for ($i = 0; $i < 5; $i++) {
            $this->createComment(['email' => $member->email, 'member_id' => $member->id]);
        }

        // Create 3 likes
        for ($i = 0; $i < 3; $i++) {
            $page = $this->createPage();
            $this->createPageLike(['member_id' => $member->id, 'page_id' => $page->id]);
        }

        $badge = Badge::create([
            'site_id' => $this->siteId,
            'name' => 'Active User',
            'slug' => 'active-user',
            'tier' => 'silver',
            'category' => 'engagement',
            'criteria' => [
                ['type' => 'comments_count', 'operator' => '>=', 'value' => 5],
                ['type' => 'likes_given', 'operator' => '>=', 'value' => 3]
            ],
            'is_active' => true
        ]);

        $this->assertTrue($badge->checkCriteria($member));
    }

    public function testCheckCriteriaPagesRead()
    {
        $member = $this->createMember();

        // Create page views
        for ($i = 0; $i < 10; $i++) {
            $page = $this->createPage();
            $this->createPageView([
                'member_id' => $member->id,
                'page_id' => $page->id
            ]);
        }

        $badge = Badge::create([
            'site_id' => $this->siteId,
            'name' => 'Bookworm',
            'slug' => 'bookworm',
            'tier' => 'bronze',
            'category' => 'content',
            'criteria' => [
                ['type' => 'pages_read', 'operator' => '>=', 'value' => 10]
            ],
            'is_active' => true
        ]);

        $this->assertTrue($badge->checkCriteria($member));
    }

    public function testCheckCriteriaMemberDays()
    {
        $member = $this->createMember([
            'created_at' => now_datetime()->subDays(45)
        ]);

        $member->created_at = now_datetime()->subDays(30);

        $badge = Badge::create([
            'site_id' => $this->siteId,
            'name' => 'Regular',
            'slug' => 'regular',
            'tier' => 'silver',
            'category' => 'loyalty',
            'criteria' => [
                ['type' => 'member_days', 'operator' => '>=', 'value' => 30]
            ],
            'is_active' => true
        ]);

        $this->assertTrue($badge->checkCriteria($member));
    }

    public function testCheckCriteriaOrdersCount()
    {
        $member = $this->createMember();

        // Create completed orders
        for ($i = 0; $i < 3; $i++) {
            $this->createOrder([
                'user_id' => $member->id,
                'status' => 'completed'
            ]);
        }

        $badge = Badge::create([
            'site_id' => $this->siteId,
            'name' => 'Loyal Customer',
            'slug' => 'loyal-customer',
            'tier' => 'silver',
            'category' => 'special',
            'criteria' => [
                ['type' => 'orders_count', 'operator' => '>=', 'value' => 3]
            ],
            'is_active' => true
        ]);

        $this->assertTrue($badge->checkCriteria($member));
    }

    public function testCheckCriteriaTotalSpent()
    {
        $member = $this->createMember();

        $this->createOrder([
            'user_id' => $member->id,
            'status' => 'completed',
            'total' => 500.00
        ]);

        $this->createOrder([
            'user_id' => $member->id,
            'status' => 'completed',
            'total' => 600.00
        ]);

        $badge = Badge::create([
            'site_id' => $this->siteId,
            'name' => 'Big Spender',
            'slug' => 'big-spender',
            'tier' => 'platinum',
            'category' => 'special',
            'criteria' => [
                ['type' => 'total_spent', 'operator' => '>=', 'value' => 1000]
            ],
            'is_active' => true
        ]);

        $this->assertTrue($badge->checkCriteria($member));
    }

    public function testUpdateBadge()
    {
        $badge = $this->createBadge();

        $badge->update([
            'name' => 'Updated Badge',
            'description' => 'Updated description',
            'points' => 100
        ]);

        $fresh = Badge::find($badge->id);
        $this->assertEquals('Updated Badge', $fresh->name);
        $this->assertEquals('Updated description', $fresh->description);
        $this->assertEquals(100, $fresh->points);
    }

    public function testDeleteBadge()
    {
        $badge = $this->createBadge();
        $id = $badge->id;

        $badge->delete();

        $deleted = Badge::find($id);
        $this->assertNull($deleted);
    }

    public function testTimestamps()
    {
        $badge = $this->createBadge();

        $this->assertNotNull($badge->created_at);
        $this->assertNotNull($badge->updated_at);
    }

    public function testInactiveBadge()
    {
        $badge = Badge::create([
            'site_id' => $this->siteId,
            'name' => 'Inactive Badge',
            'slug' => 'inactive-badge',
            'tier' => 'bronze',
            'category' => 'engagement',
            'criteria' => [],
            'is_active' => false
        ]);

        $this->assertFalse($badge->is_active);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
    }
}