<?php

namespace App\Tests\Unit\Services\Newsletters\Layout;

use App\DTO\Newsletters\Layout\SlotDTO;
use App\Services\Newsletter\DTOs\BlockData\HeadingBlockData;
use App\Services\Newsletter\DTOs\BlockData\TextBlockData;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\DTOs\RenderedBlock;
use App\Services\Newsletter\Layout\SlotRenderer;
use App\Services\Newsletter\Renderers\EmailBlockRendererRegistry;
use App\Services\Newsletter\Services\BlockDataFactory;
use Mockery;
use PHPUnit\Framework\TestCase;

class SlotRendererTest extends TestCase
{
    private EmailBlockRendererRegistry $registry;
    private BlockDataFactory $factory;
    private SlotRenderer $renderer;

    public function test_returns_empty_string_when_slot_is_empty(): void
    {
        $slot = $this->makeSlot('main', [], true);

        $this->registry
            ->shouldNotReceive('has');

        $result = $this->renderer->render($slot);

        $this->assertSame('', $result);
    }

    private function makeSlot(string $name, array $blocks = [], bool $empty = false): SlotDTO
    {
        $slot = Mockery::mock(SlotDTO::class);

        $slot->shouldReceive('isEmpty')->andReturn($empty);
        $slot->name = $name;
        $slot->blocks = $blocks;

        return $slot;
    }

    // Helpers -------------------------------------------------------------

    public function test_skips_block_with_null_type(): void
    {
        $slot = $this->makeSlot('main', [['data' => []]]);

        $this->registry->shouldNotReceive('has');

        $result = $this->renderer->render($slot);

        $this->assertSame('', trim($result));
    }

    public function test_skips_block_type_not_registered(): void
    {
        $slot = $this->makeSlot('main', [
            ['type' => 'unknown', 'data' => []]
        ]);

        $this->registry
            ->shouldReceive('has')
            ->with('unknown')
            ->andReturn(false);

        $this->registry
            ->shouldNotReceive('render');

        $result = $this->renderer->render($slot);

        $this->assertSame('', trim($result));
    }

    // ── isEmpty ───────────────────────────────────────────────────────────────

    public function test_renders_single_block(): void
    {
        $block = ['type' => 'text', 'data' => ['body' => 'Hello']];
        $slot = $this->makeSlot('main', [$block]);

        $dto = TextBlockData::fromArray([]);
        $context = Mockery::mock(NewsletterRenderContext::class);

        $this->registry
            ->shouldReceive('has')
            ->with('text')
            ->andReturn(true);

        $this->factory
            ->shouldReceive('create')
            ->with('text', $block['data'])
            ->andReturn($dto);

        $this->registry
            ->shouldReceive('render')
            ->with('text', $dto, $context)
            ->andReturn($this->renderedBlock('<p>Hello</p>'));

        $result = $this->renderer->render($slot, $context);

        $this->assertStringContainsString('<p>Hello</p>', $result);
        $this->assertStringContainsString('class="layout-slot"', $result);
        $this->assertStringContainsString('data-slot="main"', $result);
    }

    // ── Unknown / missing types ───────────────────────────────────────────────

    private function renderedBlock(string $html): RenderedBlock
    {
        $rb = Mockery::mock(RenderedBlock::class);
        $rb->html = $html;

        return $rb;
    }

    public function test_renders_multiple_blocks_joined_by_newline(): void
    {
        $blocks = [
            ['type' => 'heading', 'data' => ['text' => 'Title']],
            ['type' => 'text', 'data' => ['body' => 'Body']],
        ];

        $slot = $this->makeSlot('main', $blocks);

        $headingDto = HeadingBlockData::fromArray([]);
        $textDto = TextBlockData::fromArray([]);

        $this->registry
            ->shouldReceive('has')
            ->andReturn(true);

        $this->factory
            ->shouldReceive('create')
            ->andReturnValues([
                $headingDto,
                $textDto
            ]);

        $this->registry
            ->shouldReceive('render')
            ->andReturnValues([
                $this->renderedBlock('<h2>Title</h2>'),
                $this->renderedBlock('<p>Body</p>')
            ]);

        $result = $this->renderer->render($slot);

        $this->assertStringContainsString('<h2>Title</h2>', $result);
        $this->assertStringContainsString('<p>Body</p>', $result);
    }

    // ── Successful render ─────────────────────────────────────────────────────

    public function test_escapes_slot_name(): void
    {
        $slot = $this->makeSlot(
            '<script>alert(1)</script>',
            [['type' => 'text', 'data' => []]]
        );

        $this->registry->shouldReceive('has')->andReturn(true);
        $this->factory->shouldReceive('create')->andReturn(TextBlockData::fromArray([]));

        $this->registry
            ->shouldReceive('render')
            ->andReturn($this->renderedBlock('<p>ok</p>'));

        $result = $this->renderer->render($slot);

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    public function test_wraps_output_in_layout_slot_div_with_slot_name(): void
    {
        $slot = $this->makeSlot('sidebar', [
            ['type' => 'text', 'data' => []]
        ]);

        $this->registry->shouldReceive('has')
            ->andReturn(true);

        $this->factory->shouldReceive('create')
            ->andReturn(TextBlockData::fromArray([]));

        $this->registry->shouldReceive('render')
            ->andReturn($this->renderedBlock('<p>Hi</p>'));

        $result = $this->renderer->render($slot);

        $this->assertMatchesRegularExpression(
            '/<div class="layout-slot" data-slot="sidebar">/',
            $result
        );
    }

    public function test_escapes_slot_name_in_data_attribute(): void
    {
        $slot = $this->makeSlot('<script>alert(1)</script>', [
            ['type' => 'text', 'data' => []]
        ]);

        $this->registry->shouldReceive('has')->andReturn(true);
        $this->factory->shouldReceive('create')->andReturn(TextBlockData::fromArray([]));
        $this->registry->shouldReceive('render')
            ->andReturn($this->renderedBlock('<p>ok</p>'));

        $result = $this->renderer->render($slot);

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    public function test_filters_out_empty_html_blocks(): void
    {
        $slot = $this->makeSlot('main', [
            ['type' => 'spacer', 'data' => []],
            ['type' => 'text', 'data' => ['body' => 'Real']]
        ]);

        $this->registry->shouldReceive('has')->andReturn(true);

        $this->factory
            ->shouldReceive('create')
            ->andReturn(TextBlockData::fromArray([]));

        $this->registry
            ->shouldReceive('render')
            ->andReturnValues([
                $this->renderedBlock(''),
                $this->renderedBlock('<p>Real</p>')
            ]);

        $result = $this->renderer->render($slot);

        $this->assertStringContainsString('<p>Real</p>', $result);
    }

    // ── HTML escaping ─────────────────────────────────────────────────────────

    public function test_returns_empty_string_when_all_blocks_empty(): void
    {
        $slot = $this->makeSlot('main', [
            ['type' => 'spacer', 'data' => []]
        ]);

        $this->registry->shouldReceive('has')->andReturn(true);

        $this->factory
            ->shouldReceive('create')
            ->andReturn(TextBlockData::fromArray([]));

        $this->registry
            ->shouldReceive('render')
            ->andReturn($this->renderedBlock(''));

        $this->assertSame('', $this->renderer->render($slot));
    }

    // ── Empty HTML filtering ──────────────────────────────────────────────────

    public function test_returns_empty_when_whitespace_only_html(): void
    {
        $slot = $this->makeSlot('main', [
            ['type' => 'divider', 'data' => []],
        ]);

        $this->registry
            ->shouldReceive('render')
            ->andReturn($this->renderedBlock('   '));

        $this->factory
            ->shouldReceive('create')
            ->andReturn(TextBlockData::fromArray([]));

        $result = $this->renderer->render($slot);

        $this->assertSame('', trim($result));
    }

    public function test_passes_context_to_registry_render(): void
    {
        $block = ['type' => 'text', 'data' => []];
        $slot = $this->makeSlot('main', [$block]);
        $context = Mockery::mock(NewsletterRenderContext::class);

        $dto = TextBlockData::fromArray([]);

        $this->registry->shouldReceive('has')->andReturn(true);
        $this->factory->shouldReceive('create')->andReturn($dto);

        $this->registry
            ->shouldReceive('render')
            ->once()
            ->with('text', $dto, $context)
            ->andReturn($this->renderedBlock('<p>ok</p>'));

        $this->renderer->render($slot, $context);
        $this->assertTrue(true);
    }

    public function test_renders_without_context_passes_null(): void
    {
        $slot = $this->makeSlot('main', [
            ['type' => 'text', 'data' => []]
        ]);

        $dto = TextBlockData::fromArray([]);

        $this->registry->shouldReceive('has')->andReturn(true);
        $this->factory->shouldReceive('create')->andReturn($dto);

        $this->registry
            ->shouldReceive('render')
            ->with('text', $dto, null)
            ->andReturn($this->renderedBlock('<p>ok</p>'))
            ->once();

        $this->renderer->render($slot);
        $this->assertTrue(true);
    }

    // ── Context passthrough ───────────────────────────────────────────────────

    public function test_uses_whole_block_as_data_when_no_data_key(): void
    {
        $block = [
            'type' => 'text',
            'body' => 'Inline'
        ];

        $slot = $this->makeSlot('main', [$block]);

        $dto = TextBlockData::fromArray([]);

        $this->factory
            ->shouldReceive('create')
            ->once()
            ->with('text', [])
            ->andReturn($dto);

        $this->registry
            ->shouldReceive('render')
            ->once()
            ->andReturn($this->renderedBlock('<p>ok</p>'));

        $result = $this->renderer->render($slot);
        $this->assertNotEmpty($result);
        $this->assertStringContainsString('<p>ok</p>', $result);

    }

    public function test_renders_valid_blocks_and_skips_invalid_in_mixed_set(): void
    {
        $blocks = [
            ['type' => 'text', 'data' => ['body' => 'Valid']],
            ['data' => ['body' => 'No type']],
            ['type' => 'ghost', 'data' => []],
        ];

        $slot = $this->makeSlot('main', $blocks);

        $this->factory
            ->shouldReceive('create')
            ->andReturn(new TextBlockData([]));

        $this->registry
            ->shouldReceive('render')
            ->andReturn($this->renderedBlock('<p>Valid</p>'));

        $result = $this->renderer->render($slot);

        $this->assertStringContainsString('<p>Valid</p>', $result);
    }

    // ── Factory fallback ──────────────────────────────────────────────────────

    // ── Block with data at root level (no 'data' key) ─────────────────────────

    public function test_passes_context_to_renderer(): void
    {
        $block = ['type' => 'text', 'data' => []];

        $slot = $this->makeSlot('main', [$block]);
        $context = Mockery::mock(NewsletterRenderContext::class);

        $dto = TextBlockData::fromArray([]);

        $this->registry->shouldReceive('has')->andReturn(true);
        $this->factory->shouldReceive('create')->andReturn($dto);

        $this->registry
            ->shouldReceive('render')
            ->once()
            ->with('text', $dto, $context)
            ->andReturn($this->renderedBlock('<p>ok</p>'));

        $this->renderer->render($slot, $context);
        $this->assertTrue(true);
    }

    // ── Mixed valid/invalid blocks ────────────────────────────────────────────

    public function test_passes_null_context_when_not_provided(): void
    {
        $slot = $this->makeSlot('main', [
            ['type' => 'text', 'data' => []]
        ]);

        $dto = TextBlockData::fromArray([]);

        $this->registry->shouldReceive('has')->andReturn(true);
        $this->factory->shouldReceive('create')->andReturn($dto);

        $this->registry
            ->shouldReceive('render')
            ->with('text', $dto, null)
            ->andReturn($this->renderedBlock('<p>ok</p>'))
            ->once();

        $this->renderer->render($slot);
        $this->assertTrue(true);
    }

    public function test_renders_valid_and_skips_invalid_blocks(): void
    {
        $blocks = [
            ['type' => 'text', 'data' => ['body' => 'Valid']],
            ['data' => ['body' => 'No type']],
            ['type' => 'ghost', 'data' => []]
        ];

        $slot = $this->makeSlot('main', $blocks);

        $this->registry->shouldReceive('has')
            ->andReturnValues([
                true,
                false
            ]);

        $this->factory
            ->shouldReceive('create')
            ->andReturn(new TextBlockData([]));

        $this->registry
            ->shouldReceive('render')
            ->andReturn($this->renderedBlock('<p>Valid</p>'));

        $result = $this->renderer->render($slot);

        $this->assertStringContainsString('<p>Valid</p>', $result);
    }

    protected function setUp(): void
    {
        $this->registry = Mockery::mock(EmailBlockRendererRegistry::class);
        $this->factory = Mockery::mock(BlockDataFactory::class);

        $this->renderer = new SlotRenderer(
            $this->registry,
            $this->factory
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }
}