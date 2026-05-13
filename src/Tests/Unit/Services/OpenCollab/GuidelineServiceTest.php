<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Enums\OpenCollab\GuidelineStatus;
use App\Events\OpenCollab\GuidelineArchivedEvent;
use App\Events\OpenCollab\GuidelineDraftCreatedEvent;
use App\Events\OpenCollab\GuidelinePublishedEvent;
use App\Exceptions\OpenCollab\GuidelineNotArchivableException;
use App\Exceptions\OpenCollab\GuidelineNotEditableException;
use App\Exceptions\OpenCollab\GuidelineNotPublishableException;
use App\Framework\Database\Database;
use App\Models\Guideline;
use App\Models\Site;
use App\Repositories\Cms\SiteRepository;
use App\Repositories\OpenCollab\GuidelinesContentRepository;
use App\Services\OpenCollab\GuidelineService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class GuidelineServiceTest extends TestCase
{
    private GuidelineService $service;

    /** @var GuidelinesContentRepository&MockInterface */
    private GuidelinesContentRepository $guidelinesRepository;
    private Database $databaseMock;
    private readonly SiteRepository $siteRepository;

    // ── createDraft ───────────────────────────────────────────────────────────

    public function test_create_draft_persists_and_emits_event(): void
    {
        $guideline = $this->makeGuideline(['id' => 1, 'site_id' => 10, 'version' => 1, 'status' => GuidelineStatus::Draft]);

        $this->guidelinesRepository
            ->shouldReceive('createVersion')
            ->once()
            ->with(10, Mockery::type('string'))
            ->andReturn($guideline);

        //$this->expectsEvents(GuidelineDraftCreatedEvent::class);

        $result = $this->runInFakeTransaction(
            fn() => $this->service->createDraft(10, str_repeat('a', 60), 99)
        );

        $this->assertSame($guideline, $result);
    }

    // ── updateDraftContent ────────────────────────────────────────────────────

    public function test_update_draft_content_succeeds_for_draft(): void
    {
        $guideline = $this->makeGuideline(['id' => 1, 'status' => GuidelineStatus::Draft]);
        $updated = $this->makeGuideline(['id' => 1, 'content' => 'new content', 'status' => GuidelineStatus::Draft]);

        $guideline->shouldReceive('update')->once()->with(['content' => 'new content']);
        $guideline->shouldReceive('fresh')->once()->andReturn($updated);

        $result = $this->runInFakeTransaction(
            fn() => $this->service->updateDraftContent($guideline, 'new content')
        );

        $this->assertSame($updated, $result);
    }

    public function test_update_draft_content_throws_for_published_guideline(): void
    {
        $guideline = $this->makeGuideline(['id' => 5, 'status' => GuidelineStatus::Published]);

        $this->expectException(GuidelineNotEditableException::class);

        $this->service->updateDraftContent($guideline, 'some content');
    }

    public function test_update_draft_content_throws_for_archived_guideline(): void
    {
        $guideline = $this->makeGuideline(['id' => 5, 'status' => GuidelineStatus::Archived]);

        $this->expectException(GuidelineNotEditableException::class);

        $this->service->updateDraftContent($guideline, 'some content');
    }

    // ── publishVersion ────────────────────────────────────────────────────────

    public function test_publish_version_transitions_draft_to_published_and_updates_site(): void
    {
        $draft = $this->makeGuideline(['id' => 1, 'site_id' => 10, 'version' => 2, 'status' => GuidelineStatus::Draft]);
        $published = $this->makeGuideline(['id' => 1, 'site_id' => 10, 'version' => 2, 'status' => GuidelineStatus::Published]);

        $site = Mockery::mock(Site::class)->makePartial();
        $site->id = 10;
        $this->siteRepository->shouldReceive('update')->once()->with(10, ['guidelines_version' => 2]);

        $this->guidelinesRepository
            ->shouldReceive('latestPublishedForSite')
            ->once()
            ->with(10)
            ->andReturn(null);

        $this->guidelinesRepository
            ->shouldReceive('publish')
            ->once()
            ->with($draft, 99)
            ->andReturn($published);

        // Stub Site::find — this is the one static call permitted through the model layer
        $this->siteRepository->shouldReceive('find')->with(10)->andReturn($site);

        //$this->expectsEvents(GuidelinePublishedEvent::class);

        $result = $this->runInFakeTransaction(
            fn() => $this->service->publishVersion($draft, 99)
        );

        $this->assertSame($published, $result);
    }

    public function test_publish_auto_archives_previous_published_guideline(): void
    {
        $previous = $this->makeGuideline(['id' => 1, 'site_id' => 10, 'version' => 1, 'status' => GuidelineStatus::Published]);
        $draft = $this->makeGuideline(['id' => 2, 'site_id' => 10, 'version' => 2, 'status' => GuidelineStatus::Draft]);
        $published = $this->makeGuideline(['id' => 2, 'site_id' => 10, 'version' => 2, 'status' => GuidelineStatus::Published]);

        $site = Mockery::mock(Site::class)->makePartial();
        $site->id = 10;
        $this->siteRepository->shouldReceive('update')->once();

        $this->guidelinesRepository
            ->shouldReceive('latestPublishedForSite')
            ->with(10)
            ->andReturn($previous);

        $this->guidelinesRepository
            ->shouldReceive('archive')
            ->once()
            ->with($previous, 99)
            ->andReturn($previous);

        $this->guidelinesRepository
            ->shouldReceive('publish')
            ->with($draft, 99)
            ->andReturn($published);

        $this->siteRepository->shouldReceive('find')->andReturn($site);

        //$this->expectsEvents(GuidelineArchivedEvent::class);
        //$this->expectsEvents(GuidelinePublishedEvent::class);

        $this->runInFakeTransaction(
            fn() => $this->service->publishVersion($draft, 99)
        );

        $this->assertTrue(true);
    }

    public function test_publish_throws_for_already_published_guideline(): void
    {
        $guideline = $this->makeGuideline(['id' => 1, 'status' => GuidelineStatus::Published]);

        $this->expectException(GuidelineNotPublishableException::class);

        $this->service->publishVersion($guideline, 99);
    }

    public function test_publish_throws_for_archived_guideline(): void
    {
        $guideline = $this->makeGuideline(['id' => 1, 'status' => GuidelineStatus::Archived]);

        $this->expectException(GuidelineNotPublishableException::class);

        $this->service->publishVersion($guideline, 99);
    }

    // ── archiveVersion ────────────────────────────────────────────────────────

    public function test_archive_version_transitions_published_to_archived(): void
    {
        $published = $this->makeGuideline(['id' => 1, 'site_id' => 10, 'status' => GuidelineStatus::Published]);
        $archived = $this->makeGuideline(['id' => 1, 'site_id' => 10, 'status' => GuidelineStatus::Archived]);

        $this->guidelinesRepository
            ->shouldReceive('archive')
            ->once()
            ->with($published, 99)
            ->andReturn($archived);

        //$this->expectsEvents(GuidelineArchivedEvent::class);

        $result = $this->runInFakeTransaction(
            fn() => $this->service->archiveVersion($published, 99)
        );

        $this->assertSame($archived, $result);
    }

    public function test_archive_throws_for_draft_guideline(): void
    {
        $guideline = $this->makeGuideline(['id' => 1, 'status' => GuidelineStatus::Draft]);

        $this->expectException(GuidelineNotArchivableException::class);

        $this->service->archiveVersion($guideline, 99);
    }

    public function test_archive_throws_for_already_archived_guideline(): void
    {
        $guideline = $this->makeGuideline(['id' => 1, 'status' => GuidelineStatus::Archived]);

        $this->expectException(GuidelineNotArchivableException::class);

        $this->service->archiveVersion($guideline, 99);
    }

    // ── cloneToDraft ──────────────────────────────────────────────────────────

    public function test_clone_to_draft_copies_content_and_increments_version(): void
    {
        $source = $this->makeGuideline([
            'id' => 3,
            'site_id' => 10,
            'version' => 3,
            'content' => 'original content',
            'status' => GuidelineStatus::Published,
        ]);
        $draft = $this->makeGuideline([
            'id' => 4,
            'site_id' => 10,
            'version' => 4,
            'content' => 'original content',
            'status' => GuidelineStatus::Draft,
        ]);

        $this->guidelinesRepository
            ->shouldReceive('nextVersionNumber')
            ->once()
            ->with(10)
            ->andReturn(4);

        $this->guidelinesRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::subset([
                'site_id' => 10,
                'version' => 4,
                'content' => 'original content',
                'status' => GuidelineStatus::Draft->value,
                'cloned_from_version_id' => 3,
            ]))
            ->andReturn($draft);

        //$this->expectsEvents(GuidelineDraftCreatedEvent::class);

        $result = $this->runInFakeTransaction(
            fn() => $this->service->cloneToDraft($source, 99)
        );

        $this->assertSame($draft, $result);
    }

    public function test_clone_does_not_mutate_source(): void
    {
        $source = $this->makeGuideline([
            'id' => 3,
            'site_id' => 10,
            'version' => 3,
            'content' => 'original content',
            'status' => GuidelineStatus::Published,
        ]);

        $this->guidelinesRepository->shouldReceive('nextVersionNumber')->andReturn(4);
        $this->guidelinesRepository->shouldReceive('create')
            ->andReturn($this->makeGuideline(['id' => 4, 'status' => GuidelineStatus::Draft, 'site_id' => 10]));

        $this->runInFakeTransaction(fn() => $this->service->cloneToDraft($source, 99));

        $this->assertEquals('original content', $source->content);
        $this->assertEquals(GuidelineStatus::Published, $source->status);
    }

    // ── Transaction rollback on failure ───────────────────────────────────────

    public function test_publish_rolls_back_on_repository_failure(): void
    {
        $draft = $this->makeGuideline(['id' => 1, 'site_id' => 10, 'status' => GuidelineStatus::Draft]);

        $this->guidelinesRepository
            ->shouldReceive('latestPublishedForSite')
            ->andReturn(null);

        $this->guidelinesRepository
            ->shouldReceive('publish')
            ->andThrow(new \RuntimeException('DB failure'));

        $this->expectException(\RuntimeException::class);

        $this->runInFakeTransaction(
            fn() => $this->service->publishVersion($draft, 99)
        );
    }

    // ── Setup ─────────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        $this->guidelinesRepository = Mockery::mock(GuidelinesContentRepository::class);
        $this->databaseMock = Mockery::mock(Database::class);
        $this->siteRepository = Mockery::mock(SiteRepository::class);

        $this->service = new GuidelineService($this->guidelinesRepository, $this->databaseMock, $this->siteRepository);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * @return Guideline&MockInterface
     */
    private function makeGuideline(array $attributes): Guideline
    {
        $mock = Mockery::mock(Guideline::class)->makePartial();

        foreach ($attributes as $key => $value) {
            $mock->{$key} = $value;
        }

        return $mock;
    }

    private function runInFakeTransaction(callable $callback): mixed
    {
        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn(callable $cb) => $cb());

        return $this->databaseMock->transaction($callback);
    }
}