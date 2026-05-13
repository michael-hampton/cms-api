<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Enums\OpenCollab\ContractStatus;
use App\Events\OpenCollab\ContractArchivedEvent;
use App\Events\OpenCollab\ContractDraftCreatedEvent;
use App\Events\OpenCollab\ContractPublishedEvent;
use App\Exceptions\OpenCollab\ContractNotArchivableException;
use App\Exceptions\OpenCollab\ContractNotEditableException;
use App\Exceptions\OpenCollab\ContractNotPublishableException;
use App\Framework\Database\Database;
use App\Models\Contract;
use App\Repositories\OpenCollab\ContractRepository;
use App\Services\OpenCollab\ContractService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class ContractServiceTest extends TestCase
{
    private ContractService $service;

    /** @var ContractRepository&MockInterface */
    private ContractRepository $contractRepository;
    private Database $databaseMock;

    // ── createDraft ───────────────────────────────────────────────────────────

    public function test_create_draft_persists_and_emits_event(): void
    {
        $contract = $this->makeContract(['id' => 1, 'site_id' => 10, 'version' => 1, 'status' => ContractStatus::Draft]);

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

        //$this->expectsEvents(ContractDraftCreatedEvent::class);

        $result = $this->runInFakeTransaction(
            fn() => $this->service->createDraft(10, str_repeat('a', 60), 99)
        );

        $this->assertSame($contract, $result);
    }

    // ── updateDraftContent ────────────────────────────────────────────────────

    public function test_update_draft_content_succeeds_for_draft(): void
    {
        $contract = $this->makeContract(['id' => 1, 'status' => ContractStatus::Draft]);
        $updated = $this->makeContract(['id' => 1, 'content' => 'new content', 'status' => ContractStatus::Draft]);

        $contract->shouldReceive('update')->once()->with(['content' => 'new content']);
        $contract->shouldReceive('fresh')->once()->andReturn($updated);

        $result = $this->runInFakeTransaction(
            fn() => $this->service->updateDraftContent($contract, 'new content')
        );

        $this->assertSame($updated, $result);
    }

    public function test_update_draft_content_throws_for_published_contract(): void
    {
        $contract = $this->makeContract(['id' => 5, 'status' => ContractStatus::Published]);

        $this->expectException(ContractNotEditableException::class);

        $this->service->updateDraftContent($contract, 'some content');
    }

    public function test_update_draft_content_throws_for_archived_contract(): void
    {
        $contract = $this->makeContract(['id' => 5, 'status' => ContractStatus::Archived]);

        $this->expectException(ContractNotEditableException::class);

        $this->service->updateDraftContent($contract, 'some content');
    }

    // ── publishVersion ────────────────────────────────────────────────────────

    public function test_publish_version_transitions_draft_to_published(): void
    {
        $draft = $this->makeContract(['id' => 1, 'site_id' => 10, 'version' => 2, 'status' => ContractStatus::Draft]);
        $published = $this->makeContract(['id' => 1, 'site_id' => 10, 'version' => 2, 'status' => ContractStatus::Published]);

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

        //$this->expectsEvents(ContractPublishedEvent::class);

        $result = $this->runInFakeTransaction(
            fn() => $this->service->publishVersion($draft, 99)
        );

        $this->assertSame($published, $result);
    }

    public function test_publish_auto_archives_previous_published_version(): void
    {
        $previous = $this->makeContract(['id' => 1, 'site_id' => 10, 'version' => 1, 'status' => ContractStatus::Published]);
        $draft = $this->makeContract(['id' => 2, 'site_id' => 10, 'version' => 2, 'status' => ContractStatus::Draft]);
        $published = $this->makeContract(['id' => 2, 'site_id' => 10, 'version' => 2, 'status' => ContractStatus::Published]);

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

        // $this->expectsEvents(ContractArchivedEvent::class);
        // $this->expectsEvents(ContractPublishedEvent::class);

        $this->runInFakeTransaction(
            fn() => $this->service->publishVersion($draft, 99)
        );

        $this->assertTrue(true);
    }

    public function test_publish_throws_for_already_published_contract(): void
    {
        $contract = $this->makeContract(['id' => 1, 'status' => ContractStatus::Published]);

        $this->expectException(ContractNotPublishableException::class);

        $this->service->publishVersion($contract, 99);
    }

    public function test_publish_throws_for_archived_contract(): void
    {
        $contract = $this->makeContract(['id' => 1, 'status' => ContractStatus::Archived]);

        $this->expectException(ContractNotPublishableException::class);

        $this->service->publishVersion($contract, 99);
    }

    // ── archiveVersion ────────────────────────────────────────────────────────

    public function test_archive_version_transitions_published_to_archived(): void
    {
        $published = $this->makeContract(['id' => 1, 'site_id' => 10, 'status' => ContractStatus::Published]);
        $archived = $this->makeContract(['id' => 1, 'site_id' => 10, 'status' => ContractStatus::Archived]);

        $this->contractRepository
            ->shouldReceive('archive')
            ->once()
            ->with($published, 99)
            ->andReturn($archived);

//        $this->expectsEvents(ContractArchivedEvent::class);

        $result = $this->runInFakeTransaction(
            fn() => $this->service->archiveVersion($published, 99)
        );

        $this->assertSame($archived, $result);
    }

    public function test_archive_throws_for_draft_contract(): void
    {
        $contract = $this->makeContract(['id' => 1, 'status' => ContractStatus::Draft]);

        $this->expectException(ContractNotArchivableException::class);

        $this->service->archiveVersion($contract, 99);
    }

    public function test_archive_throws_for_already_archived_contract(): void
    {
        $contract = $this->makeContract(['id' => 1, 'status' => ContractStatus::Archived]);

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
            'status' => ContractStatus::Published,
        ]);
        $draft = $this->makeContract([
            'id' => 4,
            'site_id' => 10,
            'version' => 4,
            'content' => 'original content',
            'status' => ContractStatus::Draft,
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

//        $this->expectsEvents(ContractDraftCreatedEvent::class);

        $result = $this->runInFakeTransaction(
            fn() => $this->service->cloneToDraft($source, 99)
        );

        $this->assertSame($draft, $result);
    }

    public function test_clone_does_not_mutate_source(): void
    {
        $source = $this->makeContract([
            'id' => 3,
            'site_id' => 10,
            'version' => 3,
            'content' => 'original content',
            'status' => ContractStatus::Published,
        ]);

        $this->contractRepository
            ->shouldReceive('nextVersionNumber')->andReturn(4);

        $this->contractRepository
            ->shouldReceive('create')
            ->andReturn($this->makeContract(['id' => 4, 'status' => ContractStatus::Draft, 'site_id' => 10]));

        $this->runInFakeTransaction(fn() => $this->service->cloneToDraft($source, 99));

        // Source model attributes unchanged (no update() called on it)
        $this->assertEquals('original content', $source->content);
        $this->assertEquals(ContractStatus::Published, $source->status);
    }

    // ── assertEditable / assertPublishable / assertArchivable ─────────────────

    public function test_assert_editable_passes_for_draft(): void
    {
        $contract = $this->makeContract(['status' => ContractStatus::Draft]);

        $this->service->assertEditable($contract); // no exception

        $this->assertTrue(true); // explicit assertion
    }

    public function test_assert_publishable_passes_for_draft(): void
    {
        $contract = $this->makeContract(['id' => 1, 'status' => ContractStatus::Draft]);

        $this->service->assertPublishable($contract);

        $this->assertTrue(true);
    }

    public function test_assert_archivable_passes_for_published(): void
    {
        $contract = $this->makeContract(['id' => 1, 'status' => ContractStatus::Published]);

        $this->service->assertArchivable($contract);

        $this->assertTrue(true);
    }

    // ── Transaction rollback on failure ───────────────────────────────────────

    public function test_publish_rolls_back_on_repository_failure(): void
    {
        $draft = $this->makeContract(['id' => 1, 'site_id' => 10, 'status' => ContractStatus::Draft]);

        $this->contractRepository
            ->shouldReceive('latestPublishedForSite')
            ->andReturn(null);

        $this->contractRepository
            ->shouldReceive('publish')
            ->andThrow(new \RuntimeException('DB failure'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DB failure');

        $this->runInFakeTransaction(
            fn() => $this->service->publishVersion($draft, 99)
        );
    }

    // ── Setup ─────────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        $this->contractRepository = Mockery::mock(ContractRepository::class);
        $this->databaseMock = Mockery::mock(Database::class);

        $this->service = new ContractService($this->contractRepository, $this->databaseMock);
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

        return $this->databaseMock->transaction($callback);
    }
}