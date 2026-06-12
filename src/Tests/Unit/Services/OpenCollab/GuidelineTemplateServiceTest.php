<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Enums\OpenCollab\GuidelineStatus;
use App\Events\OpenCollab\GuidelineDraftCreatedEvent;
use App\Framework\Container;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Framework\Http\UploadedFile;
use App\Models\Guideline;
use App\Models\GuidelineTemplate;
use App\Models\OpenCollabDocument;
use App\Repositories\OpenCollab\GuidelineTemplateRepository;
use App\Repositories\OpenCollab\GuidelinesContentRepository;
use App\Services\OpenCollab\GuidelineTemplateService;
use App\Services\OpenCollab\OpenCollabDocumentService;
use App\Tests\Support\CapturingEventDispatcher;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class GuidelineTemplateServiceTest extends TestCase
{
    private GuidelineTemplateService $service;

    /** @var GuidelineTemplateRepository&MockInterface */
    private GuidelineTemplateRepository $templateRepository;

    /** @var GuidelinesContentRepository&MockInterface */
    private GuidelinesContentRepository $guidelinesRepository;

    private Database $databaseMock;
    private CapturingEventDispatcher $eventDispatcher;
    private OpenCollabDocumentService $documentService;

    public function test_create_template_persists_with_correct_data(): void
    {
        $template = $this->makeTemplate(['id' => 1, 'name' => 'Standard', 'is_active' => true]);

        $this->templateRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::subset([
                'name' => 'Standard',
                'slug' => 'standard',
                'content' => 'Template content',
                'source_type' => 'manual',
                'content_format' => 'html',
                'extraction_status' => 'not_required',
                'is_active' => true,
                'created_by' => 99,
                'updated_by' => 99,
            ]))
            ->andReturn($template);

        $result = $this->runInFakeTransaction(
            fn() => $this->service->createTemplate(
                'Standard',
                'standard',
                'Template content',
                99
            )
        );

        $this->assertSame($template, $result);
    }

    public function test_update_template_does_not_affect_existing_guidelines(): void
    {
        $template = $this->makeTemplate([
            'id' => 1,
            'content' => 'old content',
            'source_type' => 'manual',
            'content_format' => 'html',
        ]);

        $updated = $this->makeTemplate([
            'id' => 1,
            'content' => 'new content',
            'source_type' => 'manual',
            'content_format' => 'html',
        ]);

        $template
            ->shouldReceive('update')
            ->once()
            ->with(Mockery::subset([
                'name' => 'Updated',
                'content' => 'new content',
                'source_type' => 'manual',
                'content_format' => 'html',
                'updated_by' => 99,
            ]));

        $template->shouldReceive('fresh')->once()->andReturn($updated);

        $this->guidelinesRepository->shouldNotReceive('create');
        $this->guidelinesRepository->shouldNotReceive('update');

        $result = $this->runInFakeTransaction(
            fn() => $this->service->updateTemplate($template, 'Updated', 'new content', 99)
        );

        $this->assertSame($updated, $result);
    }

    public function test_update_template_defaults_missing_source_metadata(): void
    {
        $template = $this->makeTemplate([
            'id' => 1,
            'content' => 'old content',
            'source_type' => null,
            'content_format' => null,
        ]);

        $updated = $this->makeTemplate([
            'id' => 1,
            'content' => 'new content',
            'source_type' => 'manual',
            'content_format' => 'html',
        ]);

        $template
            ->shouldReceive('update')
            ->once()
            ->with(Mockery::subset([
                'source_type' => 'manual',
                'content_format' => 'html',
            ]));

        $template->shouldReceive('fresh')->once()->andReturn($updated);

        $result = $this->runInFakeTransaction(
            fn() => $this->service->updateTemplate($template, 'Updated', 'new content', 99)
        );

        $this->assertSame($updated, $result);
    }

    public function test_deactivate_sets_is_active_false(): void
    {
        $template = $this->makeTemplate(['id' => 1, 'is_active' => true]);
        $deactivated = $this->makeTemplate(['id' => 1, 'is_active' => false]);

        $template->shouldReceive('update')->once()->with(Mockery::subset(['is_active' => false]));
        $template->shouldReceive('fresh')->once()->andReturn($deactivated);

        $result = $this->runInFakeTransaction(
            fn() => $this->service->deactivate($template, 99)
        );

        $this->assertFalse($result->is_active);
    }

    public function test_draft_from_template_copies_content_snapshot(): void
    {
        $template = $this->makeTemplate([
            'id' => 7,
            'content' => 'template guideline body',
            'content_format' => 'html',
            'extraction_status' => 'not_required',
            'extraction_error' => null,
        ]);

        $draft = $this->makeGuideline([
            'id' => 1,
            'site_id' => 10,
            'version' => 1,
            'content' => 'template guideline body',
            'status' => GuidelineStatus::Draft->value,
            'source_template_id' => 7,
        ]);

        $this->guidelinesRepository
            ->shouldReceive('nextVersionNumber')
            ->once()
            ->with(10)
            ->andReturn(1);

        $this->guidelinesRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::subset([
                'site_id' => 10,
                'version' => 1,
                'content' => 'template guideline body',
                'template_id' => 7,
                'source_type' => 'template',
                'content_format' => 'html',
                'extraction_status' => 'not_required',
                'extraction_error' => null,
                'status' => GuidelineStatus::Draft->value,
                'source_template_id' => 7,
            ]))
            ->andReturn($draft);

        $result = $this->runInFakeTransaction(
            fn() => $this->service->createDraftFromTemplate($template, 10, 99)
        );

        $this->assertSame($draft, $result);
        $this->assertGuidelineEventDispatched(GuidelineDraftCreatedEvent::class, $draft);
    }

    public function test_draft_from_template_copies_document_source_metadata(): void
    {
        $template = $this->makeTemplate([
            'id' => 7,
            'content' => '<p>Imported guideline content</p>',
            'content_format' => 'html',
            'extraction_status' => 'needs_review',
            'extraction_error' => 'Check formatting',
        ]);

        $draft = $this->makeGuideline([
            'id' => 1,
            'site_id' => 10,
            'version' => 1,
            'content' => '<p>Imported guideline content</p>',
            'status' => GuidelineStatus::Draft->value,
        ]);

        $this->guidelinesRepository
            ->shouldReceive('nextVersionNumber')
            ->once()
            ->with(10)
            ->andReturn(1);

        $this->guidelinesRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::subset([
                'site_id' => 10,
                'version' => 1,
                'content' => '<p>Imported guideline content</p>',
                'template_id' => 7,
                'source_type' => 'template',
                'content_format' => 'html',
                'extraction_status' => 'needs_review',
                'extraction_error' => 'Check formatting',
                'status' => GuidelineStatus::Draft->value,
                'source_template_id' => 7,
            ]))
            ->andReturn($draft);

        $result = $this->runInFakeTransaction(
            fn() => $this->service->createDraftFromTemplate($template, 10, 99)
        );

        $this->assertSame($draft, $result);
    }

    public function test_draft_from_template_does_not_mutate_template(): void
    {
        $template = $this->makeTemplate([
            'id' => 7,
            'content' => 'original template content',
            'content_format' => 'html',
            'extraction_status' => 'not_required',
            'extraction_error' => null,
        ]);

        $this->guidelinesRepository->shouldReceive('nextVersionNumber')->andReturn(1);
        $this->guidelinesRepository
            ->shouldReceive('create')
            ->andReturn($this->makeGuideline(['id' => 1, 'status' => GuidelineStatus::Draft->value]));

        $this->runInFakeTransaction(
            fn() => $this->service->createDraftFromTemplate($template, 10, 99)
        );

        $this->assertEquals('original template content', $template->content);
    }

    public function test_import_from_document_creates_document_import_template(): void
    {
        $file = Mockery::mock(UploadedFile::class);

        $document = Mockery::mock(OpenCollabDocument::class)->makePartial();
        $document->id = 55;
        $document->metadata_json = [
            'extraction' => [
                'content' => '<p>Imported guideline template</p>',
                'format' => 'html',
                'status' => 'completed',
                'error' => null,
            ],
        ];

        $template = $this->makeTemplate([
            'id' => 12,
            'name' => 'Imported Guidelines',
            'slug' => 'imported-guidelines',
            'content' => '<p>Imported guideline template</p>',
        ]);

        $this->documentService
            ->shouldReceive('store')
            ->once()
            ->with($file, 10, 'guideline_template_source', 99, 'guideline_template')
            ->andReturn($document);

        $this->documentService
            ->shouldReceive('attach')
            ->once()
            ->with($document, 'guideline_template', 12)
            ->andReturn($document);

        $this->templateRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::subset([
                'name' => 'Imported Guidelines',
                'slug' => 'imported-guidelines',
                'description' => 'Imported description',
                'content' => '<p>Imported guideline template</p>',
                'source_document_id' => 55,
                'source_type' => 'document_import',
                'content_format' => 'html',
                'extraction_status' => 'completed',
                'extraction_error' => null,
                'is_active' => true,
                'created_by' => 99,
                'updated_by' => 99,
            ]))
            ->andReturn($template);

        $template->shouldReceive('fresh')->once()->andReturn($template);

        $result = $this->runInFakeTransaction(
            fn() => $this->service->importFromDocument(
                file: $file,
                siteId: 10,
                name: 'Imported Guidelines',
                slug: 'imported-guidelines',
                createdByUserId: 99,
                description: 'Imported description'
            )
        );

        $this->assertSame($template, $result);
    }

    public function test_import_from_pdf_creates_template_with_pdf_metadata(): void
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

        $template = $this->makeTemplate([
            'id' => 13,
            'name' => 'PDF Guidelines',
        ]);

        $this->documentService
            ->shouldReceive('store')
            ->once()
            ->with($file, 10, 'guideline_template_source', 99, 'guideline_template')
            ->andReturn($document);

        $this->documentService
            ->shouldReceive('attach')
            ->once()
            ->with($document, 'guideline_template', 13)
            ->andReturn($document);

        $this->templateRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::subset([
                'content' => '',
                'source_document_id' => 56,
                'source_type' => 'document_import',
                'content_format' => 'pdf',
                'extraction_status' => 'needs_review',
                'extraction_error' => null,
            ]))
            ->andReturn($template);

        $template->shouldReceive('fresh')->once()->andReturn($template);

        $result = $this->runInFakeTransaction(
            fn() => $this->service->importFromDocument($file, 10, 'PDF Guidelines', 'pdf-guidelines', 99)
        );

        $this->assertSame($template, $result);
    }

    public function test_import_from_failed_extraction_sets_failed_metadata(): void
    {
        $file = Mockery::mock(UploadedFile::class);

        $document = Mockery::mock(OpenCollabDocument::class)->makePartial();
        $document->id = 57;
        $document->metadata_json = [
            'extraction' => [
                'content' => null,
                'format' => 'document',
                'status' => 'failed',
                'error' => 'Unable to extract.',
            ],
        ];

        $template = $this->makeTemplate([
            'id' => 14,
            'name' => 'Broken Guidelines',
        ]);

        $this->documentService
            ->shouldReceive('store')
            ->once()
            ->with($file, 10, 'guideline_template_source', 99, 'guideline_template')
            ->andReturn($document);

        $this->documentService
            ->shouldReceive('attach')
            ->once()
            ->with($document, 'guideline_template', 14)
            ->andReturn($document);

        $this->templateRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::subset([
                'content' => '',
                'source_document_id' => 57,
                'source_type' => 'document_import',
                'content_format' => 'document',
                'extraction_status' => 'failed',
                'extraction_error' => 'Unable to extract.',
            ]))
            ->andReturn($template);

        $template->shouldReceive('fresh')->once()->andReturn($template);

        $result = $this->runInFakeTransaction(
            fn() => $this->service->importFromDocument($file, 10, 'Broken Guidelines', 'broken-guidelines', 99)
        );

        $this->assertSame($template, $result);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->templateRepository = Mockery::mock(GuidelineTemplateRepository::class);
        $this->guidelinesRepository = Mockery::mock(GuidelinesContentRepository::class);
        $this->databaseMock = Mockery::mock(Database::class);
        $this->eventDispatcher = new CapturingEventDispatcher();
        $this->documentService = Mockery::mock(OpenCollabDocumentService::class);

        Container::getInstance()->instance(EventDispatcher::class, $this->eventDispatcher);

        $this->service = new GuidelineTemplateService(
            $this->templateRepository,
            $this->guidelinesRepository,
            $this->databaseMock,
            $this->documentService,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    /** @return GuidelineTemplate&MockInterface */
    private function makeTemplate(array $attributes): GuidelineTemplate
    {
        $mock = Mockery::mock(GuidelineTemplate::class)->makePartial();

        foreach ($attributes as $key => $value) {
            $mock->{$key} = $value;
        }

        return $mock;
    }

    /** @return Guideline&MockInterface */
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