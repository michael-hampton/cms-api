<?php

namespace App\Tests\Unit\Services\Newsletters\Layout;

use App\DTO\Newsletters\Layout\LayoutRegionValueObject;
use App\Models\Newsletter;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;
use App\Services\Newsletter\Layout\LayoutRenderPipeline;
use App\Services\Newsletter\Layout\RegionRenderer;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for LayoutRenderPipeline.
 *
 * Verifies:
 * - Regions are rendered in ascending order
 * - Empty region HTML is excluded from output
 * - Context is passed through to RegionRenderer
 * - Multiple regions compose correctly
 */
class LayoutRenderPipelineTest extends TestCase
{
    private RegionRenderer $mockRegionRenderer;
    private LayoutRenderPipeline $pipeline;

    public function test_renders_regions_in_ascending_order(): void
    {
        // Intentionally define regions out-of-order; pipeline must sort them.
        $layout = LayoutRegionValueObject::fromArray([
            'schema_version' => 2,
            'regions' => [
                ['id' => 'bottom', 'name' => 'Footer', 'order' => 3, 'slots' => [['name' => 'footer', 'blocks' => [['type' => 'text', 'data' => []]]]]],
                ['id' => 'center', 'name' => 'Content', 'order' => 2, 'slots' => [['name' => 'body', 'blocks' => [['type' => 'text', 'data' => []]]]]],
                ['id' => 'top', 'name' => 'Header', 'order' => 1, 'slots' => [['name' => 'banner', 'blocks' => [['type' => 'text', 'data' => []]]]]],
            ],
        ]);

        $renderOrder = [];

        $this->mockRegionRenderer
            ->shouldReceive('render')
            ->times(3)
            ->andReturnUsing(function ($region, $ctx) use (&$renderOrder) {
                $renderOrder[] = $region->id;
                return "<div>{$region->id}</div>";
            });

        $this->pipeline->renderBody($layout, $this->makeContext());

        $this->assertSame(['top', 'center', 'bottom'], $renderOrder);
    }

    private function makeContext(): NewsletterRenderContext
    {
        $newsletter = Mockery::mock(Newsletter::class)->makePartial();
        $newsletter->id = 1;

        return new NewsletterRenderContext(
            siteId: 1,
            newsletter: $newsletter,
            member: null,
            sendId: null,
            includeTracking: false,
        );
    }

    // ── Ordering ──────────────────────────────────────────────────────────────

    public function test_excludes_empty_region_html_from_output(): void
    {
        $layout = LayoutRegionValueObject::fromArray([
            'schema_version' => 2,
            'regions' => [
                ['id' => 'top', 'name' => 'Header', 'order' => 1, 'slots' => [['name' => 'banner', 'blocks' => [['type' => 'text', 'data' => []]]]]],
                ['id' => 'center', 'name' => 'Content', 'order' => 2, 'slots' => [['name' => 'body', 'blocks' => [['type' => 'text', 'data' => []]]]]],
                ['id' => 'bottom', 'name' => 'Footer', 'order' => 3, 'slots' => [['name' => 'footer', 'blocks' => [['type' => 'text', 'data' => []]]]]],
            ],
        ]);

        $this->mockRegionRenderer
            ->shouldReceive('render')
            ->times(3)
            ->andReturnValues([
                '<div class="layout-region layout-region--top">top content</div>',
                '',   // center returns empty — should be excluded
                '<div class="layout-region layout-region--bottom">footer</div>',
            ]);

        $result = $this->pipeline->renderBody($layout, $this->makeContext());

        $this->assertStringContainsString('top content', $result);
        $this->assertStringContainsString('footer', $result);
        $this->assertStringNotContainsString('center', $result);
    }

    // ── Empty region handling ─────────────────────────────────────────────────

    public function test_passes_context_array_to_region_renderer(): void
    {
        $newsletter = Mockery::mock(Newsletter::class)->makePartial();
        $newsletter->id = 42;

        $layout = LayoutRegionValueObject::fromArray([
            'schema_version' => 2,
            'regions' => [
                ['id' => 'center', 'name' => 'Content', 'order' => 1, 'slots' => [['name' => 's', 'blocks' => [['type' => 'text', 'data' => []]]]]],
            ],
        ]);

        $context = new NewsletterRenderContext(
            siteId: 7,
            newsletter: $newsletter,
            member: null,
            sendId: 99,
            includeTracking: true,
        );

        $this->mockRegionRenderer
            ->shouldReceive('render')
            ->once()
            ->withArgs(function ($region, NewsletterRenderContext $ctx) {

                return $ctx->newsletter->id === 42
                    && $ctx->siteId === 7
                    && $ctx->sendId === 99
                    && $ctx->includeTracking === true;
            })
            ->andReturn('<div>content</div>');

        $this->pipeline->renderBody($layout, $context);
        $this->assertTrue(true);
    }

    // ── Context propagation ───────────────────────────────────────────────────

    public function test_composes_multiple_region_html_with_newlines(): void
    {
        $layout = LayoutRegionValueObject::fromArray([
            'schema_version' => 2,
            'regions' => [
                ['id' => 'top', 'name' => 'Header', 'order' => 1, 'slots' => [['name' => 'banner', 'blocks' => [['type' => 'text', 'data' => []]]]]],
                ['id' => 'center', 'name' => 'Content', 'order' => 2, 'slots' => [['name' => 'body', 'blocks' => [['type' => 'text', 'data' => []]]]]],
            ],
        ]);

        $this->mockRegionRenderer
            ->shouldReceive('render')
            ->times(2)
            ->andReturnValues(['<top/>', '<center/>']);

        $result = $this->pipeline->renderBody($layout, $this->makeContext());

        $this->assertSame("<top/>\n<center/>", $result);
    }

    // ── Full composition ──────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockRegionRenderer = Mockery::mock(RegionRenderer::class);
        $this->pipeline = new LayoutRenderPipeline($this->mockRegionRenderer);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}