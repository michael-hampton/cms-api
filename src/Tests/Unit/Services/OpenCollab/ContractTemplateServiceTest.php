<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Enums\OpenCollab\ContractStatus;
use App\Events\OpenCollab\ContractDraftCreatedEvent;
use App\Framework\Database\Database;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Repositories\OpenCollab\ContractRepository;
use App\Repositories\OpenCollab\ContractTemplateRepository;
use App\Services\OpenCollab\ContractTemplateService;
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

        //$this->expectsEvents(ContractDraftCreatedEvent::class);

        $result = $this->runInFakeTransaction(
            fn() => $this->service->createDraftFromTemplate($template, 10, 99)
        );

        $this->assertSame($draft, $result);
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

    // ── Setup ─────────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        $this->templateRepository = Mockery::mock(ContractTemplateRepository::class);
        $this->contractRepository = Mockery::mock(ContractRepository::class);
        $this->databaseMock = Mockery::mock(Database::class);

        $this->service = new ContractTemplateService(
            $this->templateRepository,
            $this->contractRepository,
            $this->databaseMock
        );
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

        return $this->databaseMock->transaction($callback);
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
}