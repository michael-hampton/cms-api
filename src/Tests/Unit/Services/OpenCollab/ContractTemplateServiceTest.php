<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Enums\OpenCollab\ContractStatus;
use App\Events\OpenCollab\ContractDraftCreatedEvent;
use App\Framework\Container;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Framework\Http\UploadedFile;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\OpenCollabDocument;
use App\Repositories\OpenCollab\ContractRepository;
use App\Repositories\OpenCollab\ContractTemplateRepository;
use App\Services\OpenCollab\ContractTemplateService;
use App\Services\OpenCollab\OpenCollabDocumentService;
use App\Tests\Support\CapturingEventDispatcher;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class ContractTemplateServiceTest extends TestCase
{
    private ContractTemplateService $service;

    /** @var ContractTemplateRepository&MockInterface */
    private ContractTemplateRepository $templateRepository;

    /** @var ContractRepository&MockInterface */
    private ContractRepository $contractRepository;
    private Database $databaseMock;
    private CapturingEventDispatcher $eventDispatcher;
    private OpenCollabDocumentService $documentService;

    // ── createTemplate ────────────────────────────────────────────────────────

    public function test_create_template_persists_with_correct_data(): void
    {
        $template = $this->makeTemplate(['id' => 1, 'name' => 'Standard', 'is_active' => true]);

        $this->templateRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::subset([
                'name' => 'Standard',
                'slug' => 'standard',
                'is_active' => true,
                'created_by' => 99,
                'updated_by' => 99,
            ]))
            ->andReturn($template);

        $result = $this->runInFakeTransaction(
            fn() => $this->service->createTemplate('Standard', 'standard', str_repeat('x', 60), 99)
        );

        $this->assertSame($template, $result);
    }

    // ── updateTemplate ────────────────────────────────────────────────────────

    public function test_update_template_does_not_affect_existing_contracts(): void
    {
        $template = $this->makeTemplate(['id' => 1, 'content' => 'old content']);
        $updated = $this->makeTemplate(['id' => 1, 'content' => 'new content']);

        // update() is called on the template object — not on any contract
        $template->shouldReceive('update')->once()->with(Mockery::subset(['content' => 'new content']));
        $template->shouldReceive('fresh')->once()->andReturn($updated);

        // contractRepository must NOT be called
        $this->contractRepository->shouldNotReceive('create');
        $this->contractRepository->shouldNotReceive('update');

        $result = $this->runInFakeTransaction(
            fn() => $this->service->updateTemplate($template, 'Updated', 'new content', 99)
        );

        $this->assertSame($updated, $result);
    }

    // ── deactivate ────────────────────────────────────────────────────────────

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

    // ── createDraftFromTemplate ───────────────────────────────────────────────

    public function test_draft_from_template_copies_content_snapshot(): void
    {
        $template = $this->makeTemplate(['id' => 7, 'content' => 'template body content here']);
        $draft = $this->makeContract([
            'id' => 1,
            'site_id' => 10,
            'version' => 1,
            'content' => 'template body content here',
            'status' => ContractStatus::Draft,
            'source_template_id' => 7,
        ]);

        $this->contractRepository
            ->shouldReceive('nextVersionNumber')
            ->once()
            ->with(10)
            ->andReturn(1);

        $this->contractRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::subset([
                'content' => 'template body content here',
                'status' => ContractStatus::Draft->value,
                'source_template_id' => 7,
            ]))
            ->andReturn($draft);

        $result = $this->runInFakeTransaction(
            fn() => $this->service->createDraftFromTemplate($template, 10, 99)
        );

        $this->assertSame($draft, $result);
        $this->assertContractEventDispatched(ContractDraftCreatedEvent::class, $draft);
    }

    public function test_draft_from_template_does_not_mutate_template(): void
    {
        $template = $this->makeTemplate(['id' => 7, 'content' => 'original template content']);

        $this->contractRepository->shouldReceive('nextVersionNumber')->andReturn(1);
        $this->contractRepository->shouldReceive('create')
            ->andReturn($this->makeContract(['id' => 1, 'status' => ContractStatus::Draft]));

        $this->runInFakeTransaction(
            fn() => $this->service->createDraftFromTemplate($template, 10, 99)
        );

        // Template content must remain unchanged
        $this->assertEquals('original template content', $template->content);
    }

    public function test_create_template_sets_manual_source_metadata(): void
    {
        $template = $this->makeTemplate(['id' => 1]);

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
            fn () => $this->service->createTemplate(
                'Standard',
                'standard',
                'Template content',
                99
            )
        );

        $this->assertSame($template, $result);
    }

    public function test_update_template_preserves_existing_source_metadata(): void
    {
        $template = $this->makeTemplate([
            'id' => 1,
            'content' => 'old content',
            'source_type' => 'document_import',
            'content_format' => 'html',
        ]);

        $updated = $this->makeTemplate([
            'id' => 1,
            'content' => 'new content',
            'source_type' => 'document_import',
            'content_format' => 'html',
        ]);

        $template
            ->shouldReceive('update')
            ->once()
            ->with(Mockery::subset([
                'name' => 'Updated',
                'content' => 'new content',
                'source_type' => 'document_import',
                'content_format' => 'html',
                'updated_by' => 99,
            ]));

        $template->shouldReceive('fresh')->once()->andReturn($updated);

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

    public function test_import_from_document_creates_document_import_template(): void
    {
        $file = Mockery::mock(UploadedFile::class);

        $document = Mockery::mock(OpenCollabDocument::class)->makePartial();
        $document->id = 55;
        $document->metadata_json = [
            'extraction' => [
                'content' => '<p>Imported contract template</p>',
                'format' => 'html',
                'status' => 'completed',
                'error' => null,
            ],
        ];

        $template = $this->makeTemplate([
            'id' => 12,
            'name' => 'Imported Contract',
            'slug' => 'imported-contract',
            'content' => '<p>Imported contract template</p>',
        ]);

        $this->documentService
            ->shouldReceive('store')
            ->once()
            ->with($file, 10, 'contract_template_source', 99, 'contract_template')
            ->andReturn($document);

        $this->documentService
            ->shouldReceive('attach')
            ->once()
            ->with($document, 'contract_template', 12)
            ->andReturn($document);

        $this->templateRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::subset([
                'name' => 'Imported Contract',
                'slug' => 'imported-contract',
                'description' => 'Imported description',
                'content' => '<p>Imported contract template</p>',
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
                name: 'Imported Contract',
                slug: 'imported-contract',
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
            'name' => 'PDF Contract',
        ]);

        $this->documentService
            ->shouldReceive('store')
            ->once()
            ->with($file, 10, 'contract_template_source', 99, 'contract_template')
            ->andReturn($document);

        $this->documentService
            ->shouldReceive('attach')
            ->once()
            ->with($document, 'contract_template', 13)
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
            fn() => $this->service->importFromDocument($file, 10, 'PDF Contract', 'pdf-contract', 99)
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
            'name' => 'Broken Contract',
        ]);

        $this->documentService
            ->shouldReceive('store')
            ->once()
            ->with($file, 10, 'contract_template_source', 99, 'contract_template')
            ->andReturn($document);

        $this->documentService
            ->shouldReceive('attach')
            ->once()
            ->with($document, 'contract_template', 14)
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
            fn() => $this->service->importFromDocument($file, 10, 'Broken Contract', 'broken-contract', 99)
        );

        $this->assertSame($template, $result);
    }

    public function test_draft_from_template_copies_document_source_metadata(): void
    {
        $template = $this->makeTemplate([
            'id' => 7,
            'content' => '<p>Imported content</p>',
            'content_format' => 'html',
            'extraction_status' => 'needs_review',
            'extraction_error' => 'Check formatting',
        ]);

        $draft = $this->makeContract([
            'id' => 1,
            'site_id' => 10,
            'version' => 1,
            'status' => ContractStatus::Draft->value,
        ]);

        $this->contractRepository
            ->shouldReceive('nextVersionNumber')
            ->once()
            ->with(10)
            ->andReturn(1);

        $this->contractRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::subset([
                'site_id' => 10,
                'version' => 1,
                'content' => '<p>Imported content</p>',
                'template_id' => 7,
                'source_type' => 'template',
                'content_format' => 'html',
                'extraction_status' => 'needs_review',
                'extraction_error' => 'Check formatting',
                'status' => ContractStatus::Draft->value,
                'source_template_id' => 7,
            ]))
            ->andReturn($draft);

        $this->runInFakeTransaction(
            fn () => $this->service->createDraftFromTemplate($template, 10, 99)
        );

        $this->assertTrue(true);
    }

    // ── Setup ─────────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        $this->templateRepository = Mockery::mock(ContractTemplateRepository::class);
        $this->contractRepository = Mockery::mock(ContractRepository::class);
        $this->databaseMock = Mockery::mock(Database::class);
        $this->eventDispatcher = new CapturingEventDispatcher();
        $this->documentService = Mockery::mock(OpenCollabDocumentService::class);

        Container::getInstance()->instance(EventDispatcher::class, $this->eventDispatcher);

        $this->service = new ContractTemplateService(
            $this->templateRepository,
            $this->contractRepository,
            $this->databaseMock,
            $this->documentService,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** @return ContractTemplate&MockInterface */
    private function makeTemplate(array $attributes): ContractTemplate
    {
        $mock = Mockery::mock(ContractTemplate::class)->makePartial();
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

    /** @return Contract&MockInterface */
    private function makeContract(array $attributes): Contract
    {
        $mock = Mockery::mock(Contract::class)->makePartial();
        foreach ($attributes as $key => $value) {
            $mock->{$key} = $value;
        }
        return $mock;
    }

    private function assertContractEventDispatched(string $eventClass, Contract $contract): void
    {
        $matches = array_values(array_filter(
            $this->eventDispatcher->dispatched,
            fn(object $event): bool => $event instanceof $eventClass
        ));

        $this->assertNotEmpty($matches, sprintf('Expected event [%s] to be dispatched.', $eventClass));
        $this->assertSame($contract, $matches[0]->contract);
    }
}
