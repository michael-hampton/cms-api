<?php

namespace App\Tests\Unit\Services\PublicContent\Widgets;

use App\Enums\PublicContent\WidgetRegion;
use App\Services\PublicContent\Widgets\WidgetRegionNormaliser;
use PHPUnit\Framework\TestCase;

final class WidgetRegionNormaliserTest extends TestCase
{
    public function test_it_maps_editor_aliases_to_canonical_slots(): void
    {
        $normaliser = new WidgetRegionNormaliser();

        self::assertSame(WidgetRegion::Header, $normaliser->toLayoutSlot('top'));
        self::assertSame(WidgetRegion::AfterContent, $normaliser->toLayoutSlot('middle'));
        self::assertSame(WidgetRegion::BelowContent, $normaliser->toLayoutSlot('bottom'));
        self::assertSame(WidgetRegion::Sidebar, $normaliser->toLayoutSlot('sidebar'));
        self::assertSame(WidgetRegion::Header, $normaliser->toLayoutSlot(WidgetRegion::Header));
    }

    public function test_it_returns_null_for_unknown_regions(): void
    {
        self::assertNull((new WidgetRegionNormaliser())->tryLayoutSlot('footer-rail'));
    }
}
