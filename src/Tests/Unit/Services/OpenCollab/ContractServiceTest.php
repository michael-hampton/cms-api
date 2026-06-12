<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Enums\OpenCollab\ContractStatus;
use App\Events\OpenCollab\ContractArchivedEvent;
use App\Events\OpenCollab\ContractDraftCreatedEvent;
use App\Events\OpenCollab\ContractPublishedEvent;
use App\Exceptions\OpenCollab\ContractNotArchivableException;
use App\Exceptions\OpenCollab\ContractNotEditableException;
use App\Exceptions\OpenCollab\ContractNotPublishableException;
use App\Framework\Container;
use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Framework\Http\UploadedFile;
use App\Models\Contract;
use App\Models\OpenCollabDocument;
use App\Repositories\OpenCollab\ContractRepository;
use App\Services\OpenCollab\ContractService;
use App\Services\OpenCollab\OpenCollabDocumentService;
use App\Tests\Support\CapturingEventDispatcher;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ContractServiceTest extends TestCase
{
    private ContractService $service;

    /** @var ContractRepository&MockInterface */
    private ContractRepository $contractRepository;
    private Database $databaseMock;
    private CapturingEventDispatcher $eventDispatcher;
    private OpenCollabDocumentService $documentService;

    // ── createDraft ───────────────────────────────────────────────────────────

    public function test_create_draft_persists_and_emits_event(): void
    {
        $contract = $this->makeContract(['id' => 1, 'site_id' => 10, 'version' => 1, 'status' => ContractStatus::Draft->value]);

        $this->contractRepository
            ->shouldReceive('nextVersionNumber')
            ->once()
            ->with(10)
            ->andReturn(1);

        $this->contractRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::subset(['site_id' => 10, 'version' => 1, 'status' => ContractStatus::Draft->value]))
            ->andReturn($contract);

        $result = $this->runInFakeTransaction(
            fn() => $this->service->createDraft(10, str_repeat('a', 60), 99)
        );

        $this->assertSame($contract, $result);
        $this->assertContractEventDispatched(ContractDraftCreatedEvent::class, $contract);
    }

    // ── updateDraftContent ────────────────────────────────────────────────────

    public function test_update_draft_content_succeeds_for_draft(): void
    {
        $contract = $this->makeContract(['id' => 1, 'status' => ContractStatus::Draft->value]);
        $updated = $this->makeContract(['id' => 1, 'content' => 'new content', 'status' => ContractStatus::Draft->value]);

        $contract->shouldReceive('update')->once()->with(['content' => 'new content']);
        $contract->shouldReceive('fresh')->once()->andReturn($updated);

        $result = $this->runInFakeTransaction(
            fn() => $this->service->updateDraftContent($contract, 'new content')
        );

        $this->assertSame($updated, $result);
    }

    public function test_update_draft_content_throws_for_published_contract(): void
    {
        $contract = $this->makeContract(['id' => 5, 'status' => ContractStatus::Published->value]);

        $this->expectException(ContractNotEditableException::class);

        $this->service->updateDraftContent($contract, 'some content');
    }

    public function test_update_draft_content_throws_for_archived_contract(): void
    {
        $contract = $this->makeContract(['id' => 5, 'status' => ContractStatus::Archived->value]);

        $this->expectException(ContractNotEditableException::class);

        $this->service->updateDraftContent($contract, 'some content');
    }

    // ── publishVersion ────────────────────────────────────────────────────────

    public function test_publish_version_transitions_draft_to_published(): void
    {
        $draft = $this->makeContract(['id' => 1, 'site_id' => 10, 'version' => 2, 'status' => ContractStatus::Draft->value]);
        $published = $this->makeContract(['id' => 1, 'site_id' => 10, 'version' => 2, 'status' => ContractStatus::Published->value]);

        $this->contractRepository
            ->shouldReceive('latestPublishedForSite')
            ->once()
            ->with(10)
            ->andReturn(null);

        $this->contractRepository
            ->shouldReceive('publish')
            ->once()
            ->with($draft, 99)
            ->andReturn($published);

        $result = $this->runInFakeTransaction(
            fn() => $this->service->publishVersion($draft, 99)
        );

        $this->assertSame($published, $result);
        $this->assertContractEventDispatched(ContractPublishedEvent::class, $published);
    }

    public function test_publish_auto_archives_previous_published_version(): void
    {
        $previous = $this->makeContract(['id' => 1, 'site_id' => 10, 'version' => 1, 'status' => ContractStatus::Published->value]);
        $draft = $this->makeContract(['id' => 2, 'site_id' => 10, 'version' => 2, 'status' => ContractStatus::Draft->value]);
        $published = $this->makeContract(['id' => 2, 'site_id' => 10, 'version' => 2, 'status' => ContractStatus::Published->value]);

        $this->contractRepository
            ->shouldReceive('latestPublishedForSite')
            ->once()
            ->with(10)
            ->andReturn($previous);

        $this->contractRepository
            ->shouldReceive('archive')
            ->once()
            ->with($previous, 99)
            ->andReturn($previous);

        $this->contractRepository
            ->shouldReceive('publish')
            ->once()
            ->with($draft, 99)
            ->andReturn($published);

        $this->runInFakeTransaction(
            fn() => $this->service->publishVersion($draft, 99)
        );

        $this->assertContractEventDispatched(ContractArchivedEvent::class, $previous);
        $this->assertContractEventDispatched(ContractPublishedEvent::class, $published);
    }

    public function test_publish_throws_for_already_published_contract(): void
    {
        $contract = $this->makeContract(['id' => 1, 'status' => ContractStatus::Published->value]);

        $this->expectException(ContractNotPublishableException::class);

        $this->service->publishVersion($contract, 99);
    }

    public function test_publish_throws_for_archived_contract(): void
    {
        $contract = $this->makeContract(['id' => 1, 'status' => ContractStatus::Archived->value]);

        $this->expectException(ContractNotPublishableException::class);

        $this->service->publishVersion($contract, 99);
    }

    // ── archiveVersion ────────────────────────────────────────────────────────

    public function test_archive_version_transitions_published_to_archived(): void
    {
        $published = $this->makeContract(['id' => 1, 'site_id' => 10, 'status' => ContractStatus::Published->value]);
        $archived = $this->makeContract(['id' => 1, 'site_id' => 10, 'status' => ContractStatus::Archived->value]);

        $this->contractRepository
            ->shouldReceive('archive')
            ->once()
            ->with($published, 99)
            ->andReturn($archived);

        $result = $this->runInFakeTransaction(
            fn() => $this->service->archiveVersion($published, 99)
        );

        $this->assertSame($archived, $result);
        $this->assertContractEventDispatched(ContractArchivedEvent::class, $archived);
    }

    public function test_archive_throws_for_draft_contract(): void
    {
        $contract = $this->makeContract(['id' => 1, 'status' => ContractStatus::Draft->value]);

        $this->expectException(ContractNotArchivableException::class);

        $this->service->archiveVersion($contract, 99);
    }

    public function test_archive_throws_for_already_archived_contract(): void
    {
        $contract = $this->makeContract(['id' => 1, 'status' => ContractStatus::Archived->value]);

        $this->expectException(ContractNotArchivableException::class);

        $this->service->archiveVersion($contract, 99);
    }

    // ── cloneToDraft ──────────────────────────────────────────────────────────

    public function test_clone_to_draft_copies_content_and_increments_version(): void
    {
        $source = $this->makeContract([
            'id' => 3,
            'site_id' => 10,
            'version' => 3,
            'content' => 'original content',
            'status' => ContractStatus::Published->value,
        ]);
        $draft = $this->makeContract([
            'id' => 4,
            'site_id' => 10,
            'version' => 4,
            'content' => 'original content',
            'status' => ContractStatus::Draft->value,
        ]);

        $this->contractRepository
            ->shouldReceive('nextVersionNumber')
            ->once()
            ->with(10)
            ->andReturn(4);

        $this->contractRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::subset([
                'site_id' => 10,
                'version' => 4,
                'content' => 'original content',
                'status' => ContractStatus::Draft->value,
                'cloned_from_version_id' => 3,
            ]))
            ->andReturn($draft);

        $result = $this->runInFakeTransaction(
            fn() => $this->service->cloneToDraft($source, 99)
        );

        $this->assertSame($draft, $result);
        $this->assertContractEventDispatched(ContractDraftCreatedEvent::class, $draft);
    }

    public function test_clone_does_not_mutate_source(): void
    {
        $source = $this->makeContract([
            'id' => 3,
            'site_id' => 10,
            'version' => 3,
            'content' => 'original content',
            'status' => ContractStatus::Published->value,
        ]);

        $this->contractRepository
            ->shouldReceive('nextVersionNumber')->andReturn(4);

        $this->contractRepository
            ->shouldReceive('create')
            ->andReturn($this->makeContract(['id' => 4, 'status' => ContractStatus::Draft->value, 'site_id' => 10]));

        $this->runInFakeTransaction(fn() => $this->service->cloneToDraft($source, 99));

        // Source model attributes unchanged (no update() called on it)
        $this->assertEquals('original content', $source->content);
        $this->assertEquals(ContractStatus::Published->value, $source->status);
    }

    // ── assertEditable / assertPublishable / assertArchivable ─────────────────

    public function test_assert_editable_passes_for_draft(): void
    {
        $contract = $this->makeContract(['status' => ContractStatus::Draft->value]);

        $this->service->assertEditable($contract); // no exception

        $this->assertTrue(true); // explicit assertion
    }

    public function test_assert_publishable_passes_for_draft(): void
    {
        $contract = $this->makeContract(['id' => 1, 'status' => ContractStatus::Draft->value]);

        $this->service->assertPublishable($contract);

        $this->assertTrue(true);
    }

    public function test_assert_archivable_passes_for_published(): void
    {
        $contract = $this->makeContract(['id' => 1, 'status' => ContractStatus::Published->value]);

        $this->service->assertArchivable($contract);

        $this->assertTrue(true);
    }

    // ── Transaction rollback on failure ───────────────────────────────────────

    public function test_publish_rolls_back_on_repository_failure(): void
    {
        $draft = $this->makeContract(['id' => 1, 'site_id' => 10, 'status' => ContractStatus::Draft->value]);

        $this->contractRepository
            ->shouldReceive('latestPublishedForSite')
            ->andReturn(null);

        $this->contractRepository
            ->shouldReceive('publish')
            ->andThrow(new RuntimeException('DB failure'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DB failure');

        $this->runInFakeTransaction(
            fn() => $this->service->publishVersion($draft, 99)
        );
    }

    public function test_create_draft_persists_document_metadata(): void
    {
        $contract = $this->makeContract([
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
                'title' => 'Uploaded Contract',
                'version' => 1,
                'content' => '<p>Extracted terms</p>',
                'source_type' => 'document_upload',
                'content_format' => 'html',
                'template_id' => null,
                'document_id' => 55,
                'source_document_id' => 55,
                'extraction_status' => 'completed',
                'extraction_error' => null,
                'status' => ContractStatus::Draft->value,
                'issued_by_user_id' => 99,
            ]))
            ->andReturn($contract);

        $result = $this->runInFakeTransaction(
            fn () => $this->service->createDraft(
                siteId: 10,
                content: '<p>Extracted terms</p>',
                createdByUserId: 99,
                metadata: [
                    'title' => 'Uploaded Contract',
                    'source_type' => 'document_upload',
                    'content_format' => 'html',
                    'document_id' => 55,
                    'source_document_id' => 55,
                    'extraction_status' => 'completed',
                    'extraction_error' => null,
                ],
            )
        );

        $this->assertSame($contract, $result);
    }

    public function test_clone_to_draft_copies_document_metadata(): void
    {
        $source = $this->makeContract([
            'id' => 3,
            'site_id' => 10,
            'title' => 'Uploaded Contract',
            'version' => 3,
            'content' => '',
            'source_type' => 'document_upload',
            'content_format' => 'pdf',
            'template_id' => null,
            'document_id' => 55,
            'source_document_id' => 55,
            'extraction_status' => 'needs_review',
            'extraction_error' => null,
            'status' => ContractStatus::Published->value,
        ]);

        $draft = $this->makeContract([
            'id' => 4,
            'site_id' => 10,
            'version' => 4,
            'status' => ContractStatus::Draft->value,
        ]);

        $this->contractRepository
            ->shouldReceive('nextVersionNumber')
            ->once()
            ->with(10)
            ->andReturn(4);

        $this->contractRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::subset([
                'site_id' => 10,
                'title' => 'Uploaded Contract',
                'version' => 4,
                'content' => '',
                'source_type' => 'document_upload',
                'content_format' => 'pdf',
                'template_id' => null,
                'document_id' => 55,
                'source_document_id' => 55,
                'extraction_status' => 'needs_review',
                'extraction_error' => null,
                'status' => ContractStatus::Draft->value,
                'cloned_from_version_id' => 3,
            ]))
            ->andReturn($draft);

        $result = $this->runInFakeTransaction(
            fn () => $this->service->cloneToDraft($source, 99)
        );

        $this->assertSame($draft, $result);
    }

    public function test_create_draft_from_document_creates_document_upload_contract(): void
    {
        $file = Mockery::mock(\App\Framework\Http\UploadedFile::class);

        $document = Mockery::mock(\App\Models\OpenCollabDocument::class)->makePartial();
        $document->id = 55;
        $document->metadata_json = [
            'extraction' => [
                'content' => '<p>Extracted contract</p>',
                'format' => 'html',
                'status' => 'completed',
                'error' => null,
            ],
        ];

        $contract = $this->makeContract([
            'id' => 77,
            'site_id' => 10,
            'version' => 1,
            'content' => '<p>Extracted contract</p>',
            'status' => ContractStatus::Draft->value,
        ]);

        $contract->shouldReceive('fresh')->once()->andReturn($contract);

        $this->documentService
            ->shouldReceive('store')
            ->once()
            ->with(
                $file,
                10,
                'issued_contract_document',
                99,
                'contract'
            )
            ->andReturn($document);

        $this->documentService
            ->shouldReceive('attach')
            ->once()
            ->with($document, 'contract', 77)
            ->andReturn($document);

        // One transaction for createDraftFromDocument()
        // One nested transaction for createDraft()
        $this->databaseMock
            ->shouldReceive('transaction')
            ->twice()
            ->andReturnUsing(fn (callable $callback) => $callback());

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
                'title' => 'Uploaded Contract',
                'version' => 1,
                'content' => '<p>Extracted contract</p>',
                'source_type' => 'document_upload',
                'content_format' => 'html',
                'document_id' => 55,
                'source_document_id' => 55,
                'extraction_status' => 'completed',
                'extraction_error' => null,
                'status' => ContractStatus::Draft->value,
                'issued_by_user_id' => 99,
            ]))
            ->andReturn($contract);

        $result = $this->service->createDraftFromDocument(
            file: $file,
            siteId: 10,
            createdByUserId: 99,
            title: 'Uploaded Contract',
        );

        $this->assertSame($contract, $result);
        $this->assertContractEventDispatched(ContractDraftCreatedEvent::class, $contract);
    }

    // ── Document/source metadata ─────────────────────────────────────────────────

    public function test_create_draft_sets_manual_source_metadata_defaults(): void
    {
        $contract = $this->makeContract([
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
                'content' => 'manual contract body',
                'source_type' => 'manual',
                'content_format' => 'html',
                'template_id' => null,
                'document_id' => null,
                'source_document_id' => null,
                'extraction_status' => 'not_required',
                'extraction_error' => null,
                'status' => ContractStatus::Draft->value,
                'issued_by_user_id' => 99,
            ]))
            ->andReturn($contract);

        $result = $this->runInFakeTransaction(
            fn() => $this->service->createDraft(10, 'manual contract body', 99)
        );

        $this->assertSame($contract, $result);
    }

    public function test_create_draft_from_pdf_document_creates_empty_content_pdf_contract(): void
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

        $contract = $this->makeContract([
            'id' => 78,
            'site_id' => 10,
            'version' => 1,
            'content' => '',
            'status' => ContractStatus::Draft->value,
        ]);

        $contract->shouldReceive('fresh')->once()->andReturn($contract);

        $this->documentService
            ->shouldReceive('store')
            ->once()
            ->with($file, 10, 'issued_contract_document', 99, 'contract')
            ->andReturn($document);

        $this->documentService
            ->shouldReceive('attach')
            ->once()
            ->with($document, 'contract', 78)
            ->andReturn($document);


        $this->databaseMock
            ->shouldReceive('transaction')
            ->twice()
            ->andReturnUsing(fn(callable $callback) => $callback());

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
                'content' => '',
                'source_type' => 'document_upload',
                'content_format' => 'pdf',
                'document_id' => 56,
                'source_document_id' => 56,
                'extraction_status' => 'needs_review',
                'extraction_error' => null,
                'status' => ContractStatus::Draft->value,
            ]))
            ->andReturn($contract);

        $result = $this->service->createDraftFromDocument($file, 10, 99, 'PDF Contract');

        $this->assertSame($contract, $result);
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

        $contract = $this->makeContract([
            'id' => 79,
            'site_id' => 10,
            'version' => 1,
            'content' => '',
            'status' => ContractStatus::Draft->value,
        ]);

        $contract->shouldReceive('fresh')->once()->andReturn($contract);

        $this->documentService
            ->shouldReceive('store')
            ->once()
            ->with($file, 10, 'issued_contract_document', 99, 'contract')
            ->andReturn($document);

        $this->documentService
            ->shouldReceive('attach')
            ->once()
            ->with($document, 'contract', 79)
            ->andReturn($document);

        Container::getInstance()->instance(OpenCollabDocumentService::class, $documentService);

        $this->databaseMock
            ->shouldReceive('transaction')
            ->twice()
            ->andReturnUsing(fn(callable $callback) => $callback());

        $this->contractRepository
            ->shouldReceive('nextVersionNumber')
            ->once()
            ->with(10)
            ->andReturn(1);

        $this->contractRepository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::subset([
                'content' => '',
                'source_type' => 'document_upload',
                'content_format' => 'document',
                'document_id' => 57,
                'source_document_id' => 57,
                'extraction_status' => 'failed',
                'extraction_error' => 'Unable to read document.',
            ]))
            ->andReturn($contract);

        $result = $this->service->createDraftFromDocument($file, 10, 99, 'Broken Contract');

        $this->assertSame($contract, $result);
    }

    // ── Setup ─────────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        $this->contractRepository = Mockery::mock(ContractRepository::class);
        $this->databaseMock = Mockery::mock(Database::class);
        $this->eventDispatcher = new CapturingEventDispatcher();
        $this->documentService = Mockery::mock(OpenCollabDocumentService::class);

        Container::getInstance()->instance(EventDispatcher::class, $this->eventDispatcher);

        $this->service = new ContractService($this->contractRepository, $this->databaseMock, $this->documentService);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Build a partial mock of Contract with given attribute stubs.
     * @return Contract&MockInterface
     */
    private function makeContract(array $attributes): Contract
    {
        $mock = Mockery::mock(Contract::class)->makePartial();

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
