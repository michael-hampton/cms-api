<?php

namespace App\Tests\Unit\Services;

use App\Framework\Support\Collection;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Services\MenuRenderer;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use PHPUnit\Framework\TestCase;

class MenuRendererTest extends FunctionalTestCase
{
    private $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new MenuRenderer();
    }

    public function testRenderHeaderHorizontal()
    {
        $menu = Mockery::mock(Menu::class)->makePartial();
        $menu->layout_config = ['type' => 'horizontal'];

        $item = new MenuItem([
            'id' => 1,
            'label' => 'Home',
            'target_type' => 'custom',
            'custom_url' => '/',
            'is_active' => true
        ]);

        $menu->shouldReceive('getAttribute')
            ->with('layout_config')
            ->andReturn(['type' => 'horizontal']);

        $menu->shouldReceive('getActiveItemsAttribute')
            ->andReturn(new Collection([$item]));

        $html = $this->renderer->render($menu, ['layout' => 'horizontal']);

        $this->assertStringContainsString('Home', $html);
        $this->assertStringContainsString('menu-horizontal', $html);
    }
}