<?php

namespace App\Tests\Unit\Resources\PublicContent;

use App\DTO\PublicContent\Widgets\WidgetLayoutOverride;
use App\Enums\PublicContent\WidgetRegion;
use App\Resources\PublicContent\PageWidgetLayoutResource;
use PHPUnit\Framework\TestCase;

final class PageWidgetLayoutResourceTest extends TestCase
{
    public function test_it_serialises_overrides_for_the_page_widget_api(): void
    {
        $payload = (new PageWidgetLayoutResource([
            new WidgetLayoutOverride('comments', WidgetRegion::Sidebar, 20, true, ['variant' => 'compact']),
        ]))->toArray();

        self::assertSame([
            'widgets' => [
                [
                    'widget_key' => 'comments',
                    'region' => 'sidebar',
                    'priority' => 20,
                    'is_enabled' => true,
                    'configuration' => ['variant' => 'compact'],
                ],
            ],
        ], $payload);
    }
}
