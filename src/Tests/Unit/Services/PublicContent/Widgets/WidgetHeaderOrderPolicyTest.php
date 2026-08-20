<?php

namespace App\Tests\Unit\Services\PublicContent\Widgets;

use App\Enums\PublicContent\WidgetRegion;
use App\Services\PublicContent\Widgets\WidgetHeaderOrderPolicy;
use App\Services\PublicContent\Widgets\WidgetPlacement;
use PHPUnit\Framework\TestCase;

final class WidgetHeaderOrderPolicyTest extends TestCase
{
    private WidgetHeaderOrderPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new WidgetHeaderOrderPolicy();
    }

    public function test_it_pins_hero_block_and_page_title_in_the_header(): void
    {
        $hero = $this->policy->apply(new WidgetPlacement('hero-block', WidgetRegion::Header, 99));
        $title = $this->policy->apply(new WidgetPlacement('page-title', WidgetRegion::Header, 99));

        self::assertSame(1, $hero->priority);
        self::assertSame(10, $title->priority);
    }

    public function test_it_keeps_breadcrumbs_before_page_title(): void
    {
        $breadcrumbs = $this->policy->apply(new WidgetPlacement('breadcrumbs', WidgetRegion::Header, 5));

        self::assertSame(5, $breadcrumbs->priority);
    }

    public function test_it_pushes_other_header_widgets_below_page_title(): void
    {
        $social = $this->policy->apply(new WidgetPlacement('social-links', WidgetRegion::Header, 3));

        self::assertSame(11, $social->priority);
    }

    public function test_it_does_not_change_non_header_placements(): void
    {
        $comments = new WidgetPlacement('comments', WidgetRegion::BelowContent, 40);
        $resolved = $this->policy->apply($comments);

        self::assertSame(40, $resolved->priority);
        self::assertSame('below-content', $resolved->regionName());
    }

    public function test_it_floors_bottom_widgets_so_they_sort_after_middle(): void
    {
        $feed = $this->policy->applyRegionFloor(
            new WidgetPlacement('activity-feed', WidgetRegion::BelowContent, 20),
            WidgetRegion::AfterContent,
        );

        self::assertSame(920, $feed->priority);
        self::assertSame('below-content', $feed->regionName());
    }

    public function test_it_does_not_floor_explicit_page_overrides(): void
    {
        $comments = $this->policy->applyRegionFloor(new WidgetPlacement(
            'comments',
            WidgetRegion::BelowContent,
            40,
            pageOverride: true,
        ));

        self::assertSame(40, $comments->priority);
    }
}
