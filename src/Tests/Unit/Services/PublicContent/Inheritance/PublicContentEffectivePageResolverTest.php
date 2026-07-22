<?php

namespace App\Tests\Unit\Services\PublicContent\Inheritance;

use App\Models\Page;
use App\Models\PageSettings;
use App\Repositories\PublicContent\PublicContentPageRepository;
use App\Services\PublicContent\Inheritance\PublicContentEffectivePageResolver;
use App\Services\PublicContent\Inheritance\PublicContentSettingsMerger;
use Mockery;
use PHPUnit\Framework\TestCase;

final class PublicContentEffectivePageResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_child_settings_win_over_parent(): void
    {
        $parent = $this->page(1, 7, [
            'template' => 'parent-layout',
            'custom_css' => 'body{color:red}',
            'menu_order' => 1,
        ]);
        $child = $this->page(2, 7, [
            'template' => 'child-layout',
            'parent_page' => 1,
            'menu_order' => 9,
        ]);

        $pages = Mockery::mock(PublicContentPageRepository::class);
        $pages->shouldReceive('findPublishedById')
            ->once()
            ->with(1, 7, ['settings'])
            ->andReturn($parent);

        $effective = (new PublicContentEffectivePageResolver($pages))->resolve($child);

        self::assertSame('child-layout', $effective->template());
        self::assertSame('body{color:red}', $effective->settings['custom_css']);
        self::assertSame(9, $effective->settings['menu_order']);
        self::assertSame([1], $effective->ancestorPageIds);
        self::assertSame(1, $effective->depth);
        self::assertFalse($effective->cycleDetected);
        self::assertFalse($effective->truncatedByDepth);
    }

    public function test_cycle_terminates_via_visited_set(): void
    {
        $a = $this->page(10, 3, ['parent_page' => 11, 'template' => 'a']);
        $b = $this->page(11, 3, ['parent_page' => 10, 'template' => 'b']);

        $pages = Mockery::mock(PublicContentPageRepository::class);
        $pages->shouldReceive('findPublishedById')
            ->with(11, 3, ['settings'])
            ->andReturn($b);
        $pages->shouldReceive('findPublishedById')
            ->with(10, 3, ['settings'])
            ->andReturn($a);

        $effective = (new PublicContentEffectivePageResolver($pages))->resolve($a);

        self::assertTrue($effective->cycleDetected);
        self::assertContains($effective->settings['template'], ['a', 'b']);
    }

    public function test_depth_bound_stops_at_five_parents(): void
    {
        $pages = Mockery::mock(PublicContentPageRepository::class);
        $chain = [];

        for ($id = 1; $id <= 8; $id++) {
            $parentId = $id < 8 ? $id + 1 : null;
            $chain[$id] = $this->page($id, 1, [
                'template' => 't' . $id,
                'parent_page' => $parentId,
            ]);
        }

        $pages->shouldReceive('findPublishedById')->andReturnUsing(
            static function (int $id, int $siteId) use ($chain): ?Page {
                self::assertSame(1, $siteId);

                return $chain[$id] ?? null;
            }
        );

        $effective = (new PublicContentEffectivePageResolver($pages))->resolve($chain[1]);

        self::assertTrue($effective->truncatedByDepth);
        self::assertSame(PublicContentEffectivePageResolver::MAX_DEPTH, $effective->depth);
        self::assertSame('t1', $effective->template());
        self::assertCount(PublicContentEffectivePageResolver::MAX_DEPTH, $effective->ancestorPageIds);
    }

    public function test_shared_merger_child_wins(): void
    {
        $merger = new PublicContentSettingsMerger();

        $merged = $merger->merge(
            ['template' => 'parent', 'custom_css' => 'a', 'nested' => ['x' => 1, 'y' => 2]],
            ['template' => 'child', 'custom_css' => '', 'nested' => ['y' => 9]],
        );

        self::assertSame('child', $merged['template']);
        self::assertSame('a', $merged['custom_css']);
        self::assertSame(['x' => 1, 'y' => 9], $merged['nested']);
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function page(int $id, int $siteId, array $settings): Page
    {
        $page = Mockery::mock(Page::class)->makePartial();
        $page->id = $id;
        $page->site_id = $siteId;
        $page->status = 'published';

        $pageSettings = Mockery::mock(PageSettings::class)->makePartial();
        foreach ($settings as $key => $value) {
            $pageSettings->{$key} = $value;
        }
        $pageSettings->shouldReceive('toArray')->andReturn($settings);

        $page->settings = $pageSettings;

        return $page;
    }
}
