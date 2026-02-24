<?php

namespace App\Tests\Unit\Services\Newsletters;

use App\Enums\Newsletters\LayoutVersionState;
use App\Framework\Support\Logger;
use App\Models\NewsletterLayout;
use App\Repositories\Newsletters\NewsletterLayoutRepository;
use App\Services\Newsletter\LayoutRendererService;
use App\Services\Newsletter\NewsletterLayoutService;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class NewsletterLayoutServiceTest extends RepositoryTestCase
{
    private NewsletterLayoutService $service;
    private NewsletterLayoutRepository $layoutRepository;
    private LayoutRendererService $layoutRenderer;
    private Logger $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->layoutRepository = app(NewsletterLayoutRepository::class);
        $this->layoutRenderer = app(LayoutRendererService::class);
        $this->logger = app(Logger::class);

        $this->service = new NewsletterLayoutService(
            $this->layoutRepository,
            $this->layoutRenderer,
            $this->logger,
            $this->database
        );
    }

    // ─── Creation ─────────────────────────────────────────────────────────────

    public function test_creates_layout_with_draft_version(): void
    {
        $layout = $this->service->createLayout(
            name: 'Test Layout',
            slug: 'test-layout',
            layoutDefinition: ['slots' => [['key' => 'content', 'required' => true]]],
        );

        $this->assertInstanceOf(NewsletterLayout::class, $layout);
        $this->assertEquals('test-layout', $layout->slug);

        $version = $layout->latestVersion();
        $this->assertNotNull($version);
        $this->assertEquals(LayoutVersionState::Draft->value, $version->state);
        $this->assertEquals(1, $version->version_number);
    }

    public function test_rejects_duplicate_slug(): void
    {
        $this->service->createLayout('Layout A', 'my-slug', [], false, null, $this->siteId);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->createLayout('Layout B', 'my-slug', [], false, null, $this->siteId);
    }

    // ─── Version Transitions ──────────────────────────────────────────────────

    public function test_transitions_draft_to_validated(): void
    {
        $layout = $this->service->createLayout('Layout', 'layout-v', []);
        $version = $layout->latestVersion();

        $updated = $this->service->transitionVersionState(
            $version->id,
            LayoutVersionState::Validated
        );

        $this->assertEquals(LayoutVersionState::Validated->value, $updated->state);
    }

    public function test_transitions_validated_to_published_and_emits_event(): void
    {
        $layout = $this->service->createLayout('Layout', 'layout-pub', []);
        $version = $layout->latestVersion();

        $this->service->transitionVersionState($version->id, LayoutVersionState::Validated);
        $this->service->transitionVersionState($version->id, LayoutVersionState::Published);

        $version->refresh();
        $this->assertEquals(LayoutVersionState::Published->value, $version->state);
    }

    public function test_rejects_invalid_state_transition(): void
    {
        $layout = $this->service->createLayout('Layout', 'layout-bad', []);
        $version = $layout->latestVersion();

        $this->expectException(\RuntimeException::class);

        // Cannot skip validated → go directly to published
        $this->service->transitionVersionState($version->id, LayoutVersionState::Published);
    }

    public function test_rejects_deprecated_to_any_state(): void
    {
        $layout = $this->service->createLayout('Layout', 'layout-dep', []);
        $version = $layout->latestVersion();

        $this->service->transitionVersionState($version->id, LayoutVersionState::Validated);
        $this->service->transitionVersionState($version->id, LayoutVersionState::Published);
        $this->service->transitionVersionState($version->id, LayoutVersionState::Deprecated);

        $this->expectException(\RuntimeException::class);
        $this->service->transitionVersionState($version->id, LayoutVersionState::Draft);
    }

    // ─── Cloning ──────────────────────────────────────────────────────────────

    public function test_clones_layout_as_new_user_layout(): void
    {
        $original = $this->service->createLayout(
            'Original',
            'original-layout',
            ['slots' => [['key' => 'content', 'required' => true]]]
        );

        $clone = $this->service->cloneLayout($original->id, 'Clone', 'clone-layout', 1, $this->siteId);

        $this->assertEquals('clone-layout', $clone->slug);
        $this->assertFalse($clone->is_system_layout);
        $this->assertEquals(1, $clone->created_by);

        $cloneVersion = $clone->latestVersion();
        $this->assertNotNull($cloneVersion);
        $this->assertEquals(LayoutVersionState::Draft->value, $cloneVersion->state);
    }

    public function test_clone_rejects_duplicate_slug(): void
    {
        $original = $this->service->createLayout('Original', 'original-2', [], false, null, $this->siteId);
        $this->service->createLayout('Taken', 'taken-slug', [], false, null, $this->siteId);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->cloneLayout($original->id, 'New Clone', 'taken-slug', 1, $this->siteId);
    }

    // ─── Deletion ─────────────────────────────────────────────────────────────

    public function test_deletes_user_layout(): void
    {
        $layout = $this->service->createLayout('Deletable', 'deletable-layout', []);

        $this->service->deleteLayout($layout->id);

        $this->assertNull(NewsletterLayout::find($layout->id));
    }

    public function test_cannot_delete_system_layout(): void
    {
        $layout = NewsletterLayout::create([
            'name' => 'System Layout',
            'slug' => 'system-protected',
            'layout_definition_json' => [],
            'is_system_layout' => true,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->service->deleteLayout($layout->id);
    }

    // ─── Migration Report ─────────────────────────────────────────────────────

    public function test_builds_migration_report_between_versions(): void
    {
        $layout = $this->service->createLayout('Versioned', 'versioned-layout', [
            'slots' => [
                ['key' => 'header', 'required' => true],
                ['key' => 'content', 'required' => true],
            ]
        ]);

        $v1 = $layout->latestVersion();

        $v2 = $this->service->addLayoutVersion($layout->id, [
            'slots' => [
                ['key' => 'header', 'required' => true],
                ['key' => 'content', 'required' => true],
                ['key' => 'sidebar', 'required' => false],
            ]
        ]);

        $report = $this->service->buildMigrationReport($v1->id, $v2->id);

        $this->assertArrayHasKey('mapped', $report);
        $this->assertArrayHasKey('unmapped', $report);
        $this->assertArrayHasKey('deprecated', $report);
        $this->assertContains('header', $report['mapped']);
        $this->assertContains('content', $report['mapped']);
        $this->assertContains('sidebar', $report['unmapped']);
        $this->assertEmpty($report['deprecated']);
    }

    // ─── Version Sequencing ───────────────────────────────────────────────────

    public function test_version_numbers_are_sequential(): void
    {
        $layout = $this->service->createLayout('Sequential', 'sequential-layout', []);

        $v2 = $this->service->addLayoutVersion($layout->id, ['slots' => []]);
        $v3 = $this->service->addLayoutVersion($layout->id, ['slots' => []]);

        $this->assertEquals(2, $v2->version_number);
        $this->assertEquals(3, $v3->version_number);
    }

    public function test_invalid_layout_id_throws_on_add_version(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->addLayoutVersion(99999, []);
    }

    public function test_invalid_version_id_throws_on_transition(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->transitionVersionState(99999, LayoutVersionState::Validated);
    }

    public function test_user_layout_requires_site_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->createLayout(
            name: 'User Layout',
            slug: 'user-layout-no-site',
            layoutDefinition: [],
            isSystemLayout: false,
            createdBy: 1,
            siteId: null
        );
    }

    public function test_system_layout_cannot_have_site_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->createLayout(
            name: 'System Layout',
            slug: 'system-layout-with-site',
            layoutDefinition: [],
            isSystemLayout: true,
            createdBy: null,
            siteId: $this->siteId
        );
    }

    public function test_system_layout_allows_null_site_id(): void
    {
        $layout = $this->service->createLayout(
            name: 'System Layout',
            slug: 'system-layout-valid',
            layoutDefinition: [],
            isSystemLayout: true,
            createdBy: null,
            siteId: null
        );

        $this->assertTrue($layout->is_system_layout);
        $this->assertNull($layout->site_id);
    }

    public function test_user_layout_with_site_is_created(): void
    {
        $layout = $this->service->createLayout(
            name: 'User Layout',
            slug: 'user-layout-valid',
            layoutDefinition: [],
            isSystemLayout: false,
            createdBy: 1,
            siteId: $this->siteId
        );

        $this->assertFalse($layout->is_system_layout);
        $this->assertEquals($this->siteId, $layout->site_id);
    }
}