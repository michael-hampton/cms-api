<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Enums\OpenCollab\GuidelineStatus;
use App\Events\OpenCollab\GuidelineArchivedEvent;
use App\Events\OpenCollab\GuidelineDraftCreatedEvent;
use App\Events\OpenCollab\GuidelinePublishedEvent;
use App\Events\OpenCollab\GuidelinesVersionBumpedEvent;
use App\Exceptions\OpenCollab\GuidelineNotArchivableException;
use App\Exceptions\OpenCollab\GuidelineNotEditableException;
use App\Exceptions\OpenCollab\GuidelineNotPublishableException;
use App\Framework\Container;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Framework\Http\UploadedFile;
use App\Models\Guideline;
use App\Models\OpenCollabDocument;
use App\Models\Site;
use App\Repositories\Cms\SiteRepository;
use App\Repositories\OpenCollab\GuidelinesContentRepository;
use App\Services\OpenCollab\GuidelineService;
use App\Services\OpenCollab\OpenCollabDocumentService;
use App\Tests\Support\CapturingEventDispatcher;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class GuidelineServiceTest extends TestCase
{
    private GuidelineService $service;

    /** @var GuidelinesContentRepository&MockInterface */
    private GuidelinesContentRepository $guidelinesRepository;
    private Database $databaseMock;
    private readonly SiteRepository $siteRepository;
    private CapturingEventDispatcher $eventDispatcher;
    private OpenCollabDocumentService $documentService;

    // ── createDraft ───────────────────────────────────────────────────────────

    public function test_create_draft_persists_and_emits_event(): void
    {
        $guideline = $this->makeGuideline(['id' => 1, 'site_id' => 10, 'version' => 1, 'status' => GuidelineStatus::Draft->value]);

        $this->guidelinesRepository
            ->shouldReceive('createVersion')
            ->once()
            ->with(10, Mockery::type('string'))
            ->andReturn($guideline);

        $result = $this->runInFakeTransaction(
            fn() => $this->service->createDraft(10, str_repeat('a', 60), 99)
        );

        $this->assertSame($guideline, $result);
        $this->assertGuidelineEventDispatched(GuidelineDraftCreatedEvent::class, $guideline);
    }

    // ── updateDraftContent ────────────────────────────────────────────────────

    public function test_update_draft_content_succeeds_for_draft(): void
    {
        $guideline = $this->makeGuideline(['id' => 1, 'status' => GuidelineStatus::Draft->value]);
        $updated = $this->makeGuideline(['id' => 1, 'content' => 'new content', 'status' => GuidelineStatus::Draft->value]);

        $guideline->shouldReceive('update')->once()->with(['content' => 'new content']);
        $guideline->shouldReceive('fresh')->once()->andReturn($updated);

        $result = $this->runInFakeTransaction(
            fn() => $this->service->updateDraftContent($guideline, 'new content')
        );

        $this->assertSame($updated, $result);
    }

    public function test_update_draft_content_throws_for_published_guideline(): void
    {
        $guideline = $this->makeGuideline(['id' => 5, 'status' => GuidelineStatus::Published->value]);

        $this->expectException(GuidelineNotEditableException::class);

        $this->service->updateDraftContent($guideline, 'some content');
    }

    public function test_update_draft_content_throws_for_archived_guideline(): void
    {
        $guideline = $this->makeGuideline(['id' => 5, 'status' => GuidelineStatus::Archived->value]);

        $this->expectException(GuidelineNotEditableException::class);

        $this->service->updateDraftContent($guideline, 'some content');
    }

    // ── publishVersion ────────────────────────────────────────────────────────

    public function test_publish_version_transitions_draft_to_published_and_updates_site(): void
    {
        $draft = $this->makeGuideline(['id' => 1, 'site_id' => 10, 'version' => 2, 'status' => GuidelineStatus::Draft->value]);
        $published = $this->makeGuideline(['id' => 1, 'site_id' => 10, 'version' => 2, 'status' => GuidelineStatus::Published->value]);

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

        $result = $this->runInFakeTransaction(
            fn() => $this->service->publishVersion($draft, 99)
        );

        $this->assertSame($published, $result);
        $this->assertGuidelineEventDispatched(GuidelinePublishedEvent::class, $published);
        $this->assertGuidelineEventDispatched(GuidelinesVersionBumpedEvent::class, $published);
    }

    public function test_publish_auto_archives_previous_published_guideline(): void
    {
        $previous = $this->makeGuideline(['id' => 1, 'site_id' => 10, 'version' => 1, 'status' => GuidelineStatus::Published->value]);
        $draft = $this->makeGuideline(['id' => 2, 'site_id' => 10, 'version' => 2, 'status' => GuidelineStatus::Draft->value]);
        $published = $this->makeGuideline(['id' => 2, 'site_id' => 10, 'version' => 2, 'status' => GuidelineStatus::Published->value]);

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

        $this->runInFakeTransaction(
            fn() => $this->service->publishVersion($draft, 99)
        );

        $this->assertGuidelineEventDispatched(GuidelineArchivedEvent::class, $previous);
        $this->assertGuidelineEventDispatched(GuidelinePublishedEvent::class, $published);
        $this->assertGuidelineEventDispatched(GuidelinesVersionBumpedEvent::class, $published);
    }

    public function test_publish_throws_for_already_published_guideline(): void
    {
        $guideline = $this->makeGuideline(['id' => 1, 'status' => GuidelineStatus::Published->value]);

        $this->expectException(GuidelineNotPublishableException::class);

        $this->service->publishVersion($guideline, 99);
    }

    public function test_publish_throws_for_archived_guideline(): void
    {
        $guideline = $this->makeGuideline(['id' => 1, 'status' => GuidelineStatus::Archived->value]);

        $this->expectException(GuidelineNotPublishableException::class);

        $this->service->publishVersion($guideline, 99);
    }

    // ── archiveVersion ────────────────────────────────────────────────────────

    public function test_archive_version_transitions_published_to_archived(): void
    {
        $published = $this->makeGuideline(['id' => 1, 'site_id' => 10, 'status' => GuidelineStatus::Published->value]);
        $archived = $this->makeGuideline(['id' => 1, 'site_id' => 10, 'status' => GuidelineStatus::Archived->value]);

        $this->guidelinesRepository
            ->shouldReceive('archive')
            ->once()
            ->with($published, 99)
            ->andReturn($archived);

        $result = $this->runInFakeTransaction(
            fn() => $this->service->archiveVersion($published, 99)
        );

        $this->assertSame($archived, $result);
        $this->assertGuidelineEventDispatched(GuidelineArchivedEvent::class, $archived);
    }

    public function test_archive_throws_for_draft_guideline(): void
    {
        $guideline = $this->makeGuideline(['id' => 1, 'status' => GuidelineStatus::Draft->value]);

        $this->expectException(GuidelineNotArchivableException::class);

        $this->service->archiveVersion($guideline, 99);
    }

    public function test_archive_throws_for_already_archived_guideline(): void
    {
        $guideline = $this->makeGuideline(['id' => 1, 'status' => GuidelineStatus::Archived->value]);

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
            'status' => GuidelineStatus::Published->value,
        ]);
        $draft = $this->makeGuideline([
            'id' => 4,
            'site_id' => 10,
            'version' => 4,
            'content' => 'original content',
            'status' => GuidelineStatus::Draft->value,
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

        $result = $this->runInFakeTransaction(
            fn() => $this->service->cloneToDraft($source, 99)
        );

        $this->assertSame($draft, $result);
        $this->assertGuidelineEventDispatched(GuidelineDraftCreatedEvent::class, $draft);
    }

    public function test_clone_does_not_mutate_source(): void
    {
        $source = $this->makeGuideline([
            'id' => 3,
            'site_id' => 10,
            'version' => 3,
            'content' => 'original content',
            'status' => GuidelineStatus::Published->value,
        ]);

        $this->guidelinesRepository->shouldReceive('nextVersionNumber')->andReturn(4);
        $this->guidelinesRepository->shouldReceive('create')
            ->andReturn($this->makeGuideline(['id' => 4, 'status' => GuidelineStatus::Draft->value, 'site_id' => 10]));

        $this->runInFakeTransaction(fn() => $this->service->cloneToDraft($source, 99));

        $this->assertEquals('original content', $source->content);
        $this->assertEquals(GuidelineStatus::Published->value, $source->status);
    }

    // ── Transaction rollback on failure ───────────────────────────────────────

    public function test_publish_rolls_back_on_repository_failure(): void
    {
        $draft = $this->makeGuideline(['id' => 1, 'site_id' => 10, 'status' => GuidelineStatus::Draft->value]);

        $this->guidelinesRepository
            ->shouldReceive('latestPublishedForSite')
            ->andReturn(null);

        $this->guidelinesRepository
            ->shouldReceive('publish')
            ->andThrow(new RuntimeException('DB failure'));

        $this->expectException(RuntimeException::class);

        $this->runInFakeTransaction(
            fn() => $this->service->publishVersion($draft, 99)
        );
    }

    public function test_create_draft_with_metadata_persists_document_metadata(): void
    {
        $guideline = $this->makeGuideline([
            'id' => 1,
            'site_id' => 10,
            'version' => 1,
            'status' => GuidelineStatus::Draft->value,
        ]);

        $this->guidelinesRepository
            ->shouldReceive('createVersion')
            ->once()
            ->with(10, '<p>Extracted guidelines</p>', Mockery::subset([
                'title' => 'Uploaded Guidelines',
                'source_type' => 'document_upload',
                'content_format' => 'html',
                'document_id' => 55,
                'source_document_id' => 55,
                'extraction_status' => 'completed',
                'extraction_error' => null,
            ]))
            ->andReturn($guideline);

        $result = $this->runInFakeTransaction(
            fn () => $this->service->createDraft(
                siteId: 10,
                content: '<p>Extracted guidelines</p>',
                createdByUserId: 99,
                metadata: [
                    'title' => 'Uploaded Guidelines',
                    'source_type' => 'document_upload',
                    'content_format' => 'html',
                    'document_id' => 55,
                    'source_document_id' => 55,
                    'extraction_status' => 'completed',
                    'extraction_error' => null,
                ],
            )
        );

        $this->assertSame($guideline, $result);
    }

    public function test_create_draft_with_metadata_applies_manual_defaults_when_missing(): void
    {
        $guideline = $this->makeGuideline([
            'id' => 1,
            'site_id' => 10,
            'version' => 1,
            'status' => GuidelineStatus::Draft->value,
        ]);

        $this->guidelinesRepository
            ->shouldReceive('createVersion')
            ->once()
            ->with(10, 'manual guideline body', Mockery::subset([
                'title' => 'Manual Guidelines',
                'source_type' => 'manual',
                'content_format' => 'html',
                'extraction_status' => 'not_required',
            ]))
            ->andReturn($guideline);

        $result = $this->runInFakeTransaction(
            fn() => $this->service->createDraft(
                siteId: 10,
                content: 'manual guideline body',
                createdByUserId: 99,
                metadata: [
                    'title' => 'Manual Guidelines',
                ],
            )
        );

        $this->assertSame($guideline, $result);
    }

    public function test_create_draft_from_document_creates_document_upload_guideline(): void
    {
        $file = Mockery::mock(UploadedFile::class);

        $document = Mockery::mock(OpenCollabDocument::class)->makePartial();
        $document->id = 55;
        $document->metadata_json = [
            'extraction' => [
                'content' => '<p>Extracted guidelines</p>',
                'format' => 'html',
                'status' => 'completed',
                'error' => null,
            ],
        ];

        $guideline = $this->makeGuideline([
            'id' => 77,
            'site_id' => 10,
            'version' => 1,
            'content' => '<p>Extracted guidelines</p>',
            'status' => GuidelineStatus::Draft->value,
        ]);

        $guideline->shouldReceive('fresh')->once()->andReturn($guideline);

        $this->documentService
            ->shouldReceive('store')
            ->once()
            ->with($file, 10, 'published_guideline_document', 99, 'guideline')
            ->andReturn($document);

        $this->documentService
            ->shouldReceive('attach')
            ->once()
            ->with($document, 'guideline', 77)
            ->andReturn($document);

        $this->databaseMock
            ->shouldReceive('transaction')
            ->twice()
            ->andReturnUsing(fn(callable $callback) => $callback());

        $this->guidelinesRepository
            ->shouldReceive('createVersion')
            ->once()
            ->with(10, '<p>Extracted guidelines</p>', Mockery::subset([
                'title' => 'Uploaded Guidelines',
                'source_type' => 'document_upload',
                'content_format' => 'html',
                'document_id' => 55,
                'source_document_id' => 55,
                'extraction_status' => 'completed',
                'extraction_error' => null,
            ]))
            ->andReturn($guideline);

        $result = $this->service->createDraftFromDocument(
            file: $file,
            siteId: 10,
            createdByUserId: 99,
            title: 'Uploaded Guidelines'
        );

        $this->assertSame($guideline, $result);
        $this->assertGuidelineEventDispatched(GuidelineDraftCreatedEvent::class, $guideline);
    }

    public function test_create_draft_from_failed_extraction_preserves_error_metadata(): void
    {
        $file = Mockery::mock(UploadedFile::class);

        $document = Mockery::mock(OpenCollabDocument::class)->makePartial();
        $document->id = 57;
        $document->metadata_json = [
            'extraction' => [
                'content' => null,
                'format' => 'document',
                'status' => 'failed',
                'error' => 'Unable to read document.',
            ],
        ];

        $guideline = $this->makeGuideline([
            'id' => 79,
            'site_id' => 10,
            'version' => 1,
            'content' => '',
            'status' => GuidelineStatus::Draft->value,
        ]);

        $guideline->shouldReceive('fresh')->once()->andReturn($guideline);

        $this->documentService
            ->shouldReceive('store')
            ->once()
            ->with($file, 10, 'published_guideline_document', 99, 'guideline')
            ->andReturn($document);

        $this->documentService
            ->shouldReceive('attach')
            ->once()
            ->with($document, 'guideline', 79)
            ->andReturn($document);

        $this->databaseMock
            ->shouldReceive('transaction')
            ->twice()
            ->andReturnUsing(fn(callable $callback) => $callback());

        $this->guidelinesRepository
            ->shouldReceive('createVersion')
            ->once()
            ->with(10, '', Mockery::subset([
                'title' => 'Broken Guidelines',
                'source_type' => 'document_upload',
                'content_format' => 'document',
                'document_id' => 57,
                'source_document_id' => 57,
                'extraction_status' => 'failed',
                'extraction_error' => 'Unable to read document.',
            ]))
            ->andReturn($guideline);

        $result = $this->service->createDraftFromDocument($file, 10, 99, 'Broken Guidelines');

        $this->assertSame($guideline, $result);
    }

    public function test_create_draft_from_pdf_document_creates_empty_content_pdf_guideline(): void
    {
        $file = Mockery::mock(UploadedFile::class);

        $document = Mockery::mock(OpenCollabDocument::class)->makePartial();
        $document->id = 56;
        $document->metadata_json = [
            'extraction' => [
                'content' => null,
                'format' => 'pdf',
                'status' => 'needs_review',
                'error' => null,
            ],
        ];

        $guideline = $this->makeGuideline([
            'id' => 78,
            'site_id' => 10,
            'version' => 1,
            'content' => '',
            'status' => GuidelineStatus::Draft->value,
        ]);

        $guideline->shouldReceive('fresh')->once()->andReturn($guideline);

        $this->documentService
            ->shouldReceive('store')
            ->once()
            ->with($file, 10, 'published_guideline_document', 99, 'guideline')
            ->andReturn($document);

        $this->documentService
            ->shouldReceive('attach')
            ->once()
            ->with($document, 'guideline', 78)
            ->andReturn($document);

        $this->databaseMock
            ->shouldReceive('transaction')
            ->twice()
            ->andReturnUsing(fn(callable $callback) => $callback());

        $this->guidelinesRepository
            ->shouldReceive('createVersion')
            ->once()
            ->with(10, '', Mockery::subset([
                'title' => 'PDF Guidelines',
                'source_type' => 'document_upload',
                'content_format' => 'pdf',
                'document_id' => 56,
                'source_document_id' => 56,
                'extraction_status' => 'needs_review',
                'extraction_error' => null,
            ]))
            ->andReturn($guideline);

        $result = $this->service->createDraftFromDocument($file, 10, 99, 'PDF Guidelines');

        $this->assertSame($guideline, $result);
    }

    public function test_clone_to_draft_copies_document_metadata(): void
    {
        $source = $this->makeGuideline([
            'id' => 3,
            'site_id' => 10,
            'title' => 'Uploaded Guidelines',
            'version' => 3,
            'content' => '',
            'source_type' => 'document_upload',
            'content_format' => 'pdf',
            'template_id' => null,
            'document_id' => 55,
            'source_document_id' => 55,
            'extraction_status' => 'needs_review',
            'extraction_error' => null,
            'status' => GuidelineStatus::Published->value,
        ]);

        $draft = $this->makeGuideline([
            'id' => 4,
            'site_id' => 10,
            'version' => 4,
            'status' => GuidelineStatus::Draft->value,
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
                'title' => 'Uploaded Guidelines',
                'version' => 4,
                'content' => '',
                'source_type' => 'document_upload',
                'content_format' => 'pdf',
                'template_id' => null,
                'document_id' => 55,
                'source_document_id' => 55,
                'extraction_status' => 'needs_review',
                'extraction_error' => null,
                'status' => GuidelineStatus::Draft->value,
                'cloned_from_version_id' => 3,
            ]))
            ->andReturn($draft);

        $result = $this->runInFakeTransaction(
            fn () => $this->service->cloneToDraft($source, 99)
        );

        $this->assertSame($draft, $result);
    }

    // ── Setup ─────────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        $this->guidelinesRepository = Mockery::mock(GuidelinesContentRepository::class);
        $this->databaseMock = Mockery::mock(Database::class);
        $this->siteRepository = Mockery::mock(SiteRepository::class);
        $this->eventDispatcher = new CapturingEventDispatcher();
        $this->documentService = Mockery::mock(OpencollabDocumentService::class);

        Container::getInstance()->instance(EventDispatcher::class, $this->eventDispatcher);

        $this->service = new GuidelineService($this->guidelinesRepository, $this->databaseMock, $this->siteRepository, $this->documentService);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
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

        return $callback();
    }

    private function assertGuidelineEventDispatched(string $eventClass, Guideline $guideline): void
    {
        $matches = array_values(array_filter(
            $this->eventDispatcher->dispatched,
            fn(object $event): bool => $event instanceof $eventClass
        ));

        $this->assertNotEmpty($matches, sprintf('Expected event [%s] to be dispatched.', $eventClass));
        $this->assertSame($guideline, $matches[0]->guideline);
    }
}
