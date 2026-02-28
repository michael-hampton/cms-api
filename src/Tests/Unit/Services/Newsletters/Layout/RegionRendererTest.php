<?php

namespace App\Tests\Unit\Services\Newsletters\Layout;

use App\DTO\Newsletters\Layout\RegionDTO;
use App\Services\Newsletter\Layout\RegionRenderer;
use App\Services\Newsletter\Layout\SlotRenderer;
use App\Services\Newsletter\Renderers\EmailBlockRendererRegistry;
use Mockery;
use PHPUnit\Framework\TestCase;

class RegionRendererTest extends TestCase
{
    private RegionRenderer $renderer;
    private $mockRegistry;

    public function test_empty_region_renders_empty_string(): void
    {
        $region = RegionDTO::fromArray([
            'id' => 'top', 'name' => 'Top', 'order' => 1, 'slots' => [],
        ]);

        $html = $this->renderer->render($region);

        $this->assertSame('', $html);
    }

    public function test_region_wraps_output_with_correct_data_attribute(): void
    {
        $this->mockRegistry->shouldReceive('has')->with('text')->andReturn(true);
        $this->mockRegistry->shouldReceive('render')->andReturn('<p>Hello</p>');

        $region = RegionDTO::fromArray([
            'id' => 'top',
            'name' => 'Top',
            'order' => 1,
            'slots' => [
                ['name' => 'bar', 'blocks' => [['type' => 'text', 'data' => ['paragraphs' => ['Hello']]]]],
            ],
        ]);

        $html = $this->renderer->render($region);

        $this->assertStringContainsString('data-region="top"', $html);
        $this->assertStringContainsString('layout-region--top', $html);
        $this->assertStringContainsString('<p>Hello</p>', $html);
    }

    public function test_regions_render_in_sorted_order(): void
    {
        // Ordering is tested at the VO level; here we just confirm render() uses region id
        $this->mockRegistry->shouldReceive('has')->andReturn(true);
        $this->mockRegistry->shouldReceive('render')->andReturn('<p>Bottom content</p>');

        $region = RegionDTO::fromArray([
            'id' => 'bottom',
            'name' => 'Bottom',
            'order' => 3,
            'slots' => [
                ['name' => 'footer', 'blocks' => [['type' => 'text', 'data' => []]]],
            ],
        ]);

        $html = $this->renderer->render($region);

        $this->assertStringContainsString('data-region="bottom"', $html);
    }

    public function test_slot_with_unknown_block_type_renders_empty(): void
    {
        $this->mockRegistry->shouldReceive('has')->with('unknown_type')->andReturn(false);

        $region = RegionDTO::fromArray([
            'id' => 'top',
            'name' => 'Top',
            'order' => 1,
            'slots' => [
                ['name' => 'bar', 'blocks' => [['type' => 'unknown_type', 'data' => []]]],
            ],
        ]);

        $html = $this->renderer->render($region);

        $this->assertSame('', $html);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockRegistry = Mockery::mock(EmailBlockRendererRegistry::class);
        $slotRenderer = new SlotRenderer($this->mockRegistry);
        $this->renderer = new RegionRenderer($slotRenderer);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}