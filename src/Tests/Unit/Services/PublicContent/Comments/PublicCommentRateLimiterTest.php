<?php

namespace App\Tests\Unit\Services\PublicContent\Comments;

use App\Framework\Support\Cache\Cache;
use App\Services\PublicContent\Comments\PublicCommentRateLimiter;
use PHPUnit\Framework\TestCase;

final class PublicCommentRateLimiterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    public function test_it_allows_the_first_attempt_and_reports_remaining_budget(): void
    {
        $result = (new PublicCommentRateLimiter())->consume(1, 42, '127.0.0.1');

        self::assertTrue($result['allowed']);
        self::assertSame(0, $result['retry_after']);
        self::assertSame(4, $result['remaining']);
    }

    public function test_it_decrements_remaining_attempts_on_each_consume(): void
    {
        $limiter = new PublicCommentRateLimiter();

        $first = $limiter->consume(1, 42, '127.0.0.1');
        $second = $limiter->consume(1, 42, '127.0.0.1');

        self::assertSame(4, $first['remaining']);
        self::assertSame(3, $second['remaining']);
    }

    public function test_it_blocks_once_max_attempts_are_reached(): void
    {
        $limiter = new PublicCommentRateLimiter();

        for ($i = 0; $i < 5; $i++) {
            $limiter->consume(1, 42, '127.0.0.1');
        }

        $blocked = $limiter->consume(1, 42, '127.0.0.1');

        self::assertFalse($blocked['allowed']);
        self::assertSame(0, $blocked['remaining']);
        self::assertGreaterThan(0, $blocked['retry_after']);
    }

    public function test_it_tracks_separate_budgets_per_member(): void
    {
        $limiter = new PublicCommentRateLimiter();

        for ($i = 0; $i < 5; $i++) {
            $limiter->consume(1, 42, '127.0.0.1');
        }

        $otherMember = $limiter->consume(1, 99, '127.0.0.1');

        self::assertTrue($otherMember['allowed']);
    }

    public function test_it_tracks_separate_budgets_per_site(): void
    {
        $limiter = new PublicCommentRateLimiter();

        for ($i = 0; $i < 5; $i++) {
            $limiter->consume(1, 42, '127.0.0.1');
        }

        $otherSite = $limiter->consume(2, 42, '127.0.0.1');

        self::assertTrue($otherSite['allowed']);
    }

    public function test_it_falls_back_to_ip_identity_when_no_member(): void
    {
        $limiter = new PublicCommentRateLimiter();

        $byIp = $limiter->consume(1, null, '10.0.0.5');
        $byMember = $limiter->consume(1, 7, '10.0.0.5');

        // Different identities (anonymous IP vs member) get independent budgets.
        self::assertSame(4, $byIp['remaining']);
        self::assertSame(4, $byMember['remaining']);
    }
}