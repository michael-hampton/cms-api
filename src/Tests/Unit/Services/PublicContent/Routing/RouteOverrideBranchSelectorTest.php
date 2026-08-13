<?php

namespace App\Tests\Unit\Services\PublicContent\Routing;

use App\Services\PublicContent\Routing\RouteOverrideAudience;
use App\Services\PublicContent\Routing\RouteOverrideBranch;
use App\Services\PublicContent\Routing\RouteOverrideBranchSelector;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RouteOverrideBranchSelectorTest extends TestCase
{
    public function test_selects_exact_language_territory_then_subscriber_refinement(): void
    {
        $selector = new RouteOverrideBranchSelector();
        $branches = [
            new RouteOverrideBranch(new RouteOverrideAudience('en', 'GB', null), ['title' => 'anon-gb']),
            new RouteOverrideBranch(new RouteOverrideAudience('en', 'GB', 'subscriber'), ['title' => 'sub-gb']),
            new RouteOverrideBranch(new RouteOverrideAudience('en', 'US', null), ['title' => 'anon-us']),
        ];

        $subscriber = $selector->select($branches, 'en', 'GB', 'subscriber');
        $anon = $selector->select($branches, 'en', 'GB', 'not-connected');
        $blank = $selector->select($branches, 'en', 'GB', '');
        $absent = $selector->select($branches, 'en', 'GB', null);

        self::assertSame('sub-gb', $subscriber?->values['title']);
        self::assertSame('anon-gb', $anon?->values['title']);
        self::assertSame('anon-gb', $blank?->values['title']);
        self::assertSame('anon-gb', $absent?->values['title']);
    }

    public function test_missing_request_info_resolves_without_applying_a_branch(): void
    {
        $selector = new RouteOverrideBranchSelector();
        $branches = [
            new RouteOverrideBranch(new RouteOverrideAudience('en', 'GB'), ['title' => 'gb']),
        ];

        self::assertNull($selector->select($branches, null, 'GB', null));
        self::assertNull($selector->select($branches, 'en', null, null));
        self::assertNull($selector->select($branches, '', 'GB', null));
    }

    /**
     * Documented departure from silent first-wins Flexi behaviour:
     * duplicate audiences are an explicit error.
     */
    public function test_duplicate_audiences_are_an_explicit_error_not_silent_first_wins(): void
    {
        $selector = new RouteOverrideBranchSelector();
        $branches = [
            new RouteOverrideBranch(new RouteOverrideAudience('en', 'GB', 'subscriber'), ['title' => 'first']),
            new RouteOverrideBranch(new RouteOverrideAudience('en', 'GB', 'subscriber'), ['title' => 'second']),
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Duplicate route override audiences');

        $selector->select($branches, 'en', 'GB', 'subscriber');
    }

    public function test_subscriber_status_is_open_text(): void
    {
        $selector = new RouteOverrideBranchSelector();
        $branches = [
            new RouteOverrideBranch(new RouteOverrideAudience('en', 'GB', 'vip-trial'), ['title' => 'vip']),
        ];

        self::assertSame('vip', $selector->select($branches, 'en', 'GB', 'vip-trial')?->values['title']);
        self::assertNull($selector->select($branches, 'en', 'GB', 'other-open-value'));
    }
}
