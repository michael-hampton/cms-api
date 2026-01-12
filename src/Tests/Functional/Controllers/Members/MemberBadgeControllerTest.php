<?php

namespace App\Tests\Functional\Controllers\Members;

use App\Models\Badge;
use App\Models\Member;
use App\Models\MemberBadge;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MemberBadgeControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private Member $member;
    private Badge $badge;

    public function testGetNewBadgesReturnsNullWhenNoBadges(): void
    {
        $response = $this->getForSiteUnauthenticated('/member/new-badges');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertNull($data['badge']);
    }

    public function testGetNewBadgesReturnsNewBadge(): void
    {
        $memberBadge = MemberBadge::create([
            'member_id' => $this->member->id,
            'badge_id' => $this->badge->id,
            'earned_at' => now(),
            'criteria_met' => [],
            'is_visible' => true
        ]);

        $_SESSION['new_badge_earned'] = $memberBadge->id;

        $response = $this->getForSiteUnauthenticated('/member/new-badges');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertNotNull($data['badge']);
        $this->assertEquals($this->badge->name, $data['badge']['name']);
        $this->assertEquals($this->badge->description, $data['badge']['description']);
    }

    public function testGetNewBadgesRequiresAuthentication(): void
    {
        $this->unauthenticateMember();

        $response = $this->getForSiteUnauthenticated('/member/new-badges');

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testMarkBadgeShownClearsSession(): void
    {
        $_SESSION['new_badge_earned'] = 1;

        $response = $this->postForSiteUnauthenticated('/member/badge-shown', [
            'badge_id' => $this->badge->id
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertArrayNotHasKey('new_badge_earned', $_SESSION);
    }

    public function testMarkBadgeShownRequiresAuthentication(): void
    {
        $this->unauthenticateMember();

        $response = $this->postForSiteUnauthenticated('/member/badge-shown', [
            'badge_id' => $this->badge->id
        ]);

        $this->assertEquals(401, $response->getStatusCode());
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->member = $this->createMember();
        $this->actingAsMember($this->member);

        $this->badge = Badge::create([
            'site_id' => $this->siteId,
            'name' => 'Test Badge',
            'slug' => 'test-badge',
            'description' => 'Test Description',
            'icon' => '🏆',
            'points' => 10,
            'criteria' => [],
            'category' => 'achievement',
            'is_active' => true
        ]);
    }
}