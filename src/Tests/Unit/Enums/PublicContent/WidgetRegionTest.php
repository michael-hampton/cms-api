<?php

namespace App\Tests\Unit\Enums\PublicContent;

use App\Enums\PublicContent\WidgetRegion;
use PHPUnit\Framework\TestCase;

final class WidgetRegionTest extends TestCase
{
    public function test_config_editor_options_use_friendly_placement_names(): void
    {
        $options = WidgetRegion::configEditorOptions();
        $values = array_column($options, 'value');
        $topAliases = $options[0]['aliases'];

        self::assertSame(['top', 'middle', 'bottom', 'sidebar', 'notices', 'modals'], $values);
        self::assertContains('header', $topAliases);
        self::assertContains('top', $topAliases);
    }

    public function test_editor_choice_maps_legacy_slots_onto_friendly_names(): void
    {
        self::assertSame(WidgetRegion::Top, WidgetRegion::Header->editorChoice());
        self::assertSame(WidgetRegion::Middle, WidgetRegion::AfterContent->editorChoice());
        self::assertSame(WidgetRegion::Bottom, WidgetRegion::BelowContent->editorChoice());
        self::assertSame(WidgetRegion::Sidebar, WidgetRegion::Sidebar->editorChoice());
    }

    public function test_unknown_config_values_are_rejected(): void
    {
        self::assertNull(WidgetRegion::tryFromConfig('footer-rail'));
        self::assertNull(WidgetRegion::tryFromConfig(null));
    }
}
