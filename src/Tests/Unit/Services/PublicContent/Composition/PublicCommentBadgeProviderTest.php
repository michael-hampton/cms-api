<?php

namespace App\Tests\Unit\Services\PublicContent\Composition;

use App\Framework\Support\Collection;
use App\Models\Badge;
use App\Models\Member;
use App\Repositories\Members\BadgeRepository;
use App\Repositories\PublicContent\PublicBadgeRepository;
use App\Services\Members\BadgeAccessService;
use App\Services\Members\BadgeService;
use App\Services\PublicContent\Composition\PublicCommentBadgeProvider;
use Mockery;
use PHPUnit\Framework\TestCase;

final class PublicCommentBadgeProviderTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_returns_null_when_member_cannot_access_badges(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();

        $badges = Mockery::mock(PublicBadgeRepository::class);
        $earned = Mockery::mock(BadgeRepository::class);
        $badgeService = Mockery::mock(BadgeService::class);

        $access = Mockery::mock(BadgeAccessService::class);
        $access->shouldReceive('canAccessBadges')->with($member, 1)->andReturn(false);

        $provider = new PublicCommentBadgeProvider($badges, $earned, $badgeService, $access);

        self::assertNull($provider->next($member, 1));
    }

    public function test_it_returns_the_lowest_threshold_unearned_comment_badge(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 7;

        $earnedBadge = $this->makeBadge(1, [['type' => 'comments_count', 'value' => 5]]);
        $nearBadge = $this->makeBadge(2, [['type' => 'comments_count', 'value' => 10]]);
        $farBadge = $this->makeBadge(3, [['type' => 'comments_count', 'value' => 50]]);
        $unrelatedBadge = $this->makeBadge(4, [['type' => 'logins_count', 'value' => 3]]);

        $badges = Mockery::mock(PublicBadgeRepository::class);
        $badges->shouldReceive('getActiveEngagementBadges')->with(1)->andReturn(
            new Collection([$farBadge, $earnedBadge, $nearBadge, $unrelatedBadge]),
        );

        $earned = Mockery::mock(BadgeRepository::class);
        $earned->shouldReceive('getEarnedBadges')->with($member)->andReturn(
            new Collection([$earnedBadge]),
        );

        $badgeService = Mockery::mock(BadgeService::class);
        $badgeService->shouldReceive('calculateBadgeProgress')->once()
            ->with($member, $nearBadge)
            ->andReturn(['percentage' => 60]);

        $access = Mockery::mock(BadgeAccessService::class);
        $access->shouldReceive('canAccessBadges')->with($member, 1)->andReturn(true);

        $provider = new PublicCommentBadgeProvider($badges, $earned, $badgeService, $access);
        $result = $provider->next($member, 1);

        self::assertSame($nearBadge, $result['badge']);
        self::assertSame(['percentage' => 60], $result['progress']);
    }

    public function test_it_returns_null_when_all_comment_badges_are_earned(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 7;

        $earnedBadge = $this->makeBadge(1, [['type' => 'comments_count', 'value' => 5]]);

        $badges = Mockery::mock(PublicBadgeRepository::class);
        $badges->shouldReceive('getActiveEngagementBadges')->with(1)->andReturn(
            new Collection([$earnedBadge]),
        );

        $earned = Mockery::mock(BadgeRepository::class);
        $earned->shouldReceive('getEarnedBadges')->with($member)->andReturn(
            new Collection([$earnedBadge]),
        );

        $badgeService = Mockery::mock(BadgeService::class);
        $access = Mockery::mock(BadgeAccessService::class);
        $access->shouldReceive('canAccessBadges')->with($member, 1)->andReturn(true);

        $provider = new PublicCommentBadgeProvider($badges, $earned, $badgeService, $access);

        self::assertNull($provider->next($member, 1));
    }

    public function test_it_ignores_badges_without_a_comment_count_criterion(): void
    {
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 7;

        $unrelatedBadge = $this->makeBadge(4, [['type' => 'logins_count', 'value' => 3]]);

        $badges = Mockery::mock(PublicBadgeRepository::class);
        $badges->shouldReceive('getActiveEngagementBadges')->with(1)->andReturn(
            new Collection([$unrelatedBadge]),
        );

        $earned = Mockery::mock(BadgeRepository::class);
        $earned->shouldReceive('getEarnedBadges')->with($member)->andReturn(new Collection());

        $badgeService = Mockery::mock(BadgeService::class);
        $access = Mockery::mock(BadgeAccessService::class);
        $access->shouldReceive('canAccessBadges')->with($member, 1)->andReturn(true);

        $provider = new PublicCommentBadgeProvider($badges, $earned, $badgeService, $access);

        self::assertNull($provider->next($member, 1));
    }

    private function makeBadge(int $id, array $criteria): Badge
    {
        $badge = Mockery::mock(Badge::class)->makePartial();
        $badge->id = $id;
        $badge->criteria = $criteria;

        return $badge;
    }
}