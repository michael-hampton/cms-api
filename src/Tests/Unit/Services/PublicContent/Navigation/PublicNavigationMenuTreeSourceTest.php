<?php

namespace App\Tests\Unit\Services\PublicContent\Navigation;

use App\Models\Menu;
use App\Repositories\PublicContent\PublicNavigationRepository;
use App\Services\PublicContent\Navigation\PublicNavigationMenuTreeSource;
use Mockery;
use PHPUnit\Framework\TestCase;

final class PublicNavigationMenuTreeSourceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_delegates_to_the_navigation_repository(): void
    {
        $menu = Mockery::mock(Menu::class)->makePartial();

        $navigation = Mockery::mock(PublicNavigationRepository::class);
        $navigation->shouldReceive('findActiveMenu')->once()->with(1, 'header', 5)->andReturn($menu);

        $source = new PublicNavigationMenuTreeSource($navigation);

        self::assertSame($menu, $source->findTree(1, 'header', 5));
    }

    public function test_it_returns_null_when_no_active_menu_is_found(): void
    {
        $navigation = Mockery::mock(PublicNavigationRepository::class);
        $navigation->shouldReceive('findActiveMenu')->once()->with(1, 'footer', null)->andReturn(null);

        $source = new PublicNavigationMenuTreeSource($navigation);

        self::assertNull($source->findTree(1, 'footer'));
    }
}