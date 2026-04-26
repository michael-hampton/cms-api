<?php

namespace App\Tests\Unit\Services\Newsletters;

use App\Framework\Database\Database;
use App\Models\NewsletterLayout;
use App\Repositories\Newsletters\EmailTemplateRepository;
use App\Repositories\Newsletters\EmailTemplateVersionRepository;
use App\Repositories\Newsletters\EmailThemeRepository;
use App\Services\Newsletter\EmailTemplateRenderer;
use App\Services\Newsletter\EmailTemplateService;
use App\Services\Newsletter\PreviewDataFactory;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;

/**
 * Unit tests for EmailTemplateService.
 *
 * All infrastructure is mocked — no database calls are made.
 * Tests verify business rules: slug generation, duplicate detection,
 * block sanitisation, preview orchestration.
 */
class EmailTemplateServiceTest extends FunctionalTestCase
{
    private MockInterface $db;
    private MockInterface $repository;
    private MockInterface $themeRepository;
    private MockInterface $renderer;
    private PreviewDataFactory $previewDataFactory;
    private EmailTemplateService $service;
    private EmailTemplateVersionRepository $emailTemplateVersionRepository;

    // ── Create ─────────────────────────────────────────────────────────────────

    public function test_create_generates_unique_slug_and_sanitises_blocks(): void
    {
        $this->db->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());
        $this->repository->shouldReceive('slugExistsForSite')
            ->with('welcome-email', 1, null)
            ->once()
            ->andReturn(false);
        $this->repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $data): bool {
                return $data['slug'] === 'welcome-email'
                    && $data['site_id'] === 1
                    && $data['layout_definition_json']['blocks'][0]['type'] === 'text'
                    && $data['layout_definition_json']['blocks'][0]['visible'] === true;
            }))
            ->andReturn(new NewsletterLayout(['id' => 10]));

        $result = $this->service->create([
            'name' => 'Welcome Email',
            'category' => 'transactional',
            'blocks' => [['data' => ['content' => 'Hello']]],
        ], 1);

        $this->assertInstanceOf(NewsletterLayout::class, $result);
    }

    public function test_create_uses_provided_slug_when_present(): void
    {
        $this->db->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());
        $this->repository->shouldReceive('slugExistsForSite')
            ->with('custom-slug', 1, null)
            ->once()
            ->andReturn(false);
        $this->repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn(array $d): bool => $d['slug'] === 'custom-slug'))
            ->andReturn(new NewsletterLayout(['id' => 11]));

        $this->service->create([
            'name' => 'Something',
            'slug' => 'custom-slug',
            'category' => 'marketing',
            'blocks' => [],
        ], 1);

        $this->assertTrue(true);
    }

    public function test_create_increments_slug_when_collision_exists(): void
    {
        $this->db->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());

        $this->repository->shouldReceive('slugExistsForSite')
            ->with('my-email', 1, null)->once()->andReturn(true);
        $this->repository->shouldReceive('slugExistsForSite')
            ->with('my-email-1', 1, null)->once()->andReturn(true);
        $this->repository->shouldReceive('slugExistsForSite')
            ->with('my-email-2', 1, null)->once()->andReturn(false);

        $this->repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn(array $d): bool => $d['slug'] === 'my-email-2'))
            ->andReturn(new NewsletterLayout(['id' => 12]));

        $this->service->create(['name' => 'My Email', 'category' => 'system', 'blocks' => []], 1);

        $this->assertTrue(true);
    }

//    public function test_create_strips_unknown_block_fields_and_defaults_visible_to_true(): void
//    {
//        $capturedData = null;
//
//        $this->db->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());
//        $this->repository->shouldReceive('slugExistsForSite')->andReturn(false);
//        $this->repository->shouldReceive('create')
//            ->once()
//            ->with(Mockery::on(function (array $data) use (&$capturedData): bool {
//                $capturedData = $data;
//                return true;
//            }))
//            ->andReturn(new NewsletterLayout(['id' => 13]));
//
//        $this->emailTemplateVersionRepository->shouldReceive('maxVersionNumber');
//        $this->emailTemplateVersionRepository->shouldReceive('createVersion');
//
//        $this->service->create([
//            'name' => 'Test',
//            'category' => 'transactional',
//            'blocks' => [
//                // 'visible' not provided — should default to true
//                ['type' => 'text', 'data' => ['content' => 'Hi']],
//                // 'visible' explicitly false — should be preserved
//                ['type' => 'button', 'data' => ['label' => 'Click'], 'visible' => false],
//            ],
//        ], 2);
//
//        $this->assertTrue($capturedData['blocks'][0]['visible']);
//        $this->assertFalse($capturedData['blocks'][1]['visible']);
//    }

    // ── Update ─────────────────────────────────────────────────────────────────

    public function test_update_regenerates_slug_when_name_changes(): void
    {
        $template = new NewsletterLayout([
            'id' => 5,
            'name' => 'Old Name',
            'slug' => 'old-name',
            'site_id' => 3,
        ]);

        $this->db->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());
        $this->repository->shouldReceive('find')->with(5)->once()->andReturn($template);
        $this->repository->shouldReceive('slugExistsForSite')
            ->with('new-name', 3, 5)->once()->andReturn(false);
        $this->repository->shouldReceive('update')
            ->with(5, Mockery::on(fn(array $d): bool => $d['slug'] === 'new-name'))
            ->once()
            ->andReturn($template);

        $this->service->update(5, ['name' => 'New Name']);

        $this->assertTrue(true);
    }

    public function test_update_does_not_regenerate_slug_when_name_unchanged(): void
    {
        $template = new NewsletterLayout([
            'id' => 6,
            'name' => 'Same Name',
            'slug' => 'same-name',
            'site_id' => 3,
        ]);

        $this->db->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());
        $this->repository->shouldReceive('find')->with(6)->once()->andReturn($template);
        $this->repository->shouldReceive('slugExistsForSite')->never();
        $this->repository->shouldReceive('update')
            ->once()
            ->with(6, Mockery::on(fn(array $d): bool => !isset($d['slug'])))
            ->andReturn($template);

        $this->service->update(6, ['description' => 'Updated description']);

        $this->assertTrue(true);
    }

    public function test_update_throws_when_template_not_found(): void
    {
        $this->db->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());
        $this->repository->shouldReceive('find')->with(999)->once()->andReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Email template 999 not found');

        $this->service->update(999, ['name' => 'Ghost']);

        $this->assertTrue(true);
    }

    // ── Delete ─────────────────────────────────────────────────────────────────

    public function test_delete_removes_template(): void
    {
        $template = Mockery::mock(NewsletterLayout::class);
        $template->shouldReceive('getAttribute')->with('id')->andReturn(7);
        $template->shouldReceive('delete')->once()->andReturn(true);

        $this->repository->shouldReceive('find')->with(7)->andReturn($template);

        $this->assertTrue($this->service->delete(7));
    }

    public function test_delete_throws_when_template_not_found(): void
    {
        $this->repository->shouldReceive('find')->with(404)->andReturn(null);

        $this->expectException(\RuntimeException::class);

        $this->service->delete(404);

        $this->assertTrue(true);
    }

    // ── Duplicate ──────────────────────────────────────────────────────────────

    public function test_duplicate_creates_inactive_copy_with_new_name(): void
    {
        $source = new NewsletterLayout([
            'id' => 8,
            'site_id' => 1,
            'theme_id' => null,
            'name' => 'Original',
            'slug' => 'original',
            'description' => 'Desc',
            'category' => 'transactional',
            'layout_definition_json' => ['blocks' => [['type' => 'text', 'data' => ['content' => 'Hi'], 'visible' => true]]],
        ]);

        $this->db->shouldReceive('transaction')->once()->andReturnUsing(fn($cb) => $cb());
        $this->repository->shouldReceive('find')->with(8)->once()->andReturn($source);
        $this->repository->shouldReceive('slugExistsForSite')
            ->with('copy', 1, null)->once()->andReturn(false);
        $this->repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $data): bool {
                return $data['name'] === 'Copy'
                    && $data['is_active'] === false
                    && $data['site_id'] === 1;
            }))
            ->andReturn(new NewsletterLayout(['id' => 9]));

        $this->service->duplicate(8, 'Copy');

        $this->assertTrue(true);
    }

    // ── Preview ────────────────────────────────────────────────────────────────

    public function test_preview_saved_returns_html_plain_text_and_unresolved_tokens(): void
    {
        $template = new NewsletterLayout([
            'id' => 9,
            'site_id' => 1,
            'theme_id' => null,
            'name' => 'Order Template',
            'layout_definition_json' => ['blocks' => [['type' => 'text', 'data' => ['content' => 'Hi {{ user.first_name }}']]]],
        ]);

        $this->repository->shouldReceive('find')->with(9)->once()->andReturn($template);
        $this->renderer->shouldReceive('render')->once()
            ->andReturn('<div>Hello {{ missing.value }}</div>');

        $result = $this->service->previewSaved(9, 'mock_user');

        $this->assertArrayHasKey('html', $result);
        $this->assertSame('Hello {{ missing.value }}', $result['plain_text']);
        $this->assertSame(['missing.value'], $result['unresolved_tokens']);
    }

    public function test_preview_saved_throws_when_template_not_found(): void
    {
        $this->repository->shouldReceive('find')->with(0)->andReturn(null);

        $this->expectException(\RuntimeException::class);

        $this->service->previewSaved(0, 'mock_order');

        $this->assertTrue(true);
    }

    public function test_preview_live_renders_blocks_without_saving(): void
    {
        $this->renderer->shouldReceive('renderPreview')
            ->once()
            ->with(
                Mockery::type('array'),
                Mockery::type('array'),
                1,
                null,
            )
            ->andReturn('<p>Preview content</p>');

        $result = $this->service->previewLive(
            blocks: [['type' => 'text', 'data' => ['content' => 'Hello'], 'visible' => true]],
            dataset: 'mock_user',
            siteId: 1,
        );

        $this->assertArrayHasKey('html', $result);
        $this->assertStringContainsString('Preview content', $result['html']);
        $this->assertArrayHasKey('plain_text', $result);
        $this->assertArrayHasKey('unresolved_tokens', $result);
        $this->assertEmpty($result['unresolved_tokens']);
    }

    public function test_preview_live_detects_unresolved_tokens(): void
    {
        $this->renderer->shouldReceive('renderPreview')
            ->once()
            ->andReturn('<p>Dear {{ user.first_name }}, your {{ order.id }} is ready.</p>');

        $result = $this->service->previewLive([], 'mock_order', 1);

        $this->assertSame(
            ['user.first_name', 'order.id'],
            $result['unresolved_tokens'],
        );
    }

    public function test_preview_produces_plain_text_fallback(): void
    {
        $html = '<h1>Hello</h1><p>Your <strong>order</strong> is confirmed.</p><br>';

        $this->repository->shouldReceive('find')->with(15)->andReturn(
            new NewsletterLayout(['id' => 15, 'site_id' => 1, 'theme_id' => null, 'name' => 'T', 'layout_definition_json' => []])
        );
        $this->renderer->shouldReceive('render')->once()->andReturn($html);

        $result = $this->service->previewSaved(15, 'mock_order');

        $this->assertStringContainsString('Hello', $result['plain_text']);
        $this->assertStringContainsString('order', $result['plain_text']);
        $this->assertStringNotContainsString('<h1>', $result['plain_text']);
        $this->assertStringNotContainsString('<strong>', $result['plain_text']);
    }

    // ── PreviewDataFactory integration ─────────────────────────────────────────

    public function test_preview_data_factory_builds_mock_order_dataset(): void
    {
        $factory = new PreviewDataFactory();
        $data = $factory->build('mock_order');

        $this->assertArrayHasKey('user.first_name', $data);
        $this->assertArrayHasKey('order.number', $data);
        $this->assertArrayHasKey('site.name', $data);
        $this->assertSame('Michael', $data['user.first_name']);
    }

    public function test_preview_data_factory_builds_mock_user_dataset(): void
    {
        $factory = new PreviewDataFactory();
        $data = $factory->build('mock_user');

        $this->assertArrayHasKey('user.first_name', $data);
        $this->assertSame('Sarah', $data['user.first_name']);
    }

    public function test_preview_data_factory_falls_back_to_mock_order_for_unknown_dataset(): void
    {
        $factory = new PreviewDataFactory();
        $data = $factory->build('nonexistent_dataset');

        // Should fall back to mock_order
        $this->assertArrayHasKey('user.first_name', $data);
        $this->assertArrayHasKey('order.number', $data);
    }

    // ── setUp ──────────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Mockery::mock(Database::class);
        $this->repository = Mockery::mock(EmailTemplateRepository::class);
        $this->themeRepository = Mockery::mock(EmailThemeRepository::class);
        $this->renderer = Mockery::mock(EmailTemplateRenderer::class);
        $this->previewDataFactory = new PreviewDataFactory();
        $this->emailTemplateVersionRepository = Mockery::mock(EmailTemplateVersionRepository::class);

        $this->service = new EmailTemplateService(
            $this->db,
            $this->repository,
            $this->emailTemplateVersionRepository,
            $this->themeRepository,
            $this->renderer,
            $this->previewDataFactory,
        );

        $this->emailTemplateVersionRepository->shouldReceive('maxVersionNumber')->byDefault();
        $this->emailTemplateVersionRepository->shouldReceive('createVersion')->byDefault();
    }
}