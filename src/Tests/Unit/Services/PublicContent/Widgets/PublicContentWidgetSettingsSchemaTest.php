<?php

namespace App\Tests\Unit\Services\PublicContent\Widgets;

use App\Services\PublicContent\Widgets\PublicContentWidgetSettingsSchema;
use PHPUnit\Framework\TestCase;

final class PublicContentWidgetSettingsSchemaTest extends TestCase
{
    public function test_activity_feed_exposes_limit_setting(): void
    {
        $schema = (new PublicContentWidgetSettingsSchema())->all();

        self::assertArrayHasKey('activity-feed', $schema);
        self::assertSame('Activity feed', $schema['activity-feed']['label']);
        self::assertSame('limit', $schema['activity-feed']['fields'][0]['key']);
        self::assertSame(10, $schema['activity-feed']['fields'][0]['default']);
    }

    public function test_defaults_for_returns_field_defaults(): void
    {
        $schema = new PublicContentWidgetSettingsSchema();

        self::assertSame(
            [
                'limit' => 8,
                'eyebrow' => 'Reader offers',
                'title' => 'Latest voucher codes',
                'intro' => 'Hand-picked active codes you can reveal before checkout.',
            ],
            $schema->defaultsFor('vouchers'),
        );
        self::assertSame(['frequency' => 'balanced'], $schema->defaultsFor('adverts'));
        self::assertSame([
            'limit' => 12,
            'layout' => 'carousel',
            'title' => 'Explore Categories',
            'subtitle' => 'Discover content by topic',
        ], $schema->defaultsFor('categories-widget'));
        self::assertSame([
            'pages_per_section' => 6,
            'min_pages' => 3,
        ], $schema->defaultsFor('category-pages'));
        self::assertSame([], $schema->defaultsFor('newsletter'));
    }

    public function test_activity_feed_exposes_title_setting(): void
    {
        $fields = (new PublicContentWidgetSettingsSchema())->all()['activity-feed']['fields'];
        $keys = array_column($fields, 'key');

        self::assertContains('limit', $keys);
        self::assertContains('title', $keys);
    }

    public function test_catalog_covers_core_configurable_widgets(): void
    {
        $keys = array_keys((new PublicContentWidgetSettingsSchema())->all());

        foreach ([
            'activity-feed',
            'most-popular-articles',
            'trending',
            'deals',
            'vouchers',
            'adverts',
            'recirculation',
            'guest-contributors',
            'newsletter',
        ] as $expected) {
            self::assertContains($expected, $keys);
        }
    }
}
