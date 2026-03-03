<?php

namespace App\Tests\Unit\Actions\Subscriptions;

use App\Actions\Subscriptions\ImportIssueSchedulesAction;
use App\Enums\Subscriptions\IssueScheduleStatus;
use App\Framework\Database\Database;
use App\Imports\CsvParser;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ImportIssueSchedulesActionTest extends TestCase
{
    private MockInterface|CsvParser $csvParser;
    private MockInterface|IssueDeliveryRepository $repository;
    private MockInterface|Database $database;
    private ImportIssueSchedulesAction $action;

    public static function invalidRowProvider(): array
    {
        return [
            'missing title' => [['title' => ''], 'Title is required'],
            'missing issue_number' => [['issue_number' => ''], 'Issue number is required'],
            'non-numeric issue_number' => [['issue_number' => 'abc'], 'Issue number must be numeric'],
            'missing on_sale_date' => [['on_sale_date' => ''], 'On-sale date is required'],
            'invalid on_sale_date' => [['on_sale_date' => 'not-a-date'], 'On-sale date must be a valid date'],
            'invalid status' => [['status' => 'bad_status'], 'Invalid status value'],
        ];
    }

    public function test_it_imports_valid_rows_successfully(): void
    {
        $rows = [$this->validRow()];

        $this->csvParser
            ->shouldReceive('parse')
            ->once()
            ->with('/path/to/file.csv')
            ->andReturn($rows);

        $createdModel = new \stdClass();
        $this->stubRepositoryBulkCreate([$createdModel]);

        $result = $this->action->execute(1, '/path/to/file.csv');

        $this->assertSame(1, $result['total']);
        $this->assertSame(1, $result['success_count']);
        $this->assertSame(0, $result['error_count']);
        $this->assertCount(1, $result['created']);
        $this->assertEmpty($result['errors']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function validRow(array $overrides = []): array
    {
        return array_merge([
            '__line' => 2,
            'title' => 'Issue One',
            'issue_number' => '42',
            'on_sale_date' => '2025-06-01',
            'status' => '',
        ], $overrides);
    }

    private function stubRepositoryBulkCreate(array $created = [], array $errors = []): void
    {
        $this->repository
            ->shouldReceive('bulkCreateFromCsv')
            ->once()
            ->andReturn([
                'created' => $created,
                'errors' => $errors,
                'success_count' => count($created),
            ]);
    }

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function test_it_accepts_a_valid_status_value(): void
    {
        $rows = [$this->validRow(['status' => IssueScheduleStatus::ACTIVE->value])];

        $this->csvParser->shouldReceive('parse')->andReturn($rows);
        $this->stubRepositoryBulkCreate([new \stdClass()]);

        $result = $this->action->execute(1, '/path/to/file.csv');

        $this->assertSame(0, $result['error_count']);
    }

    #[DataProvider('invalidRowProvider')]
    public function test_it_records_validation_errors(array $rowOverrides, string $expectedError): void
    {
        $this->csvParser
            ->shouldReceive('parse')
            ->andReturn([$this->validRow($rowOverrides)]);

        $this->repository->shouldNotReceive('bulkCreateFromCsv');

        $result = $this->action->execute(1, '/path/to/file.csv');

        $this->assertSame(1, $result['error_count']);
        $this->assertStringContainsString($expectedError, $result['errors'][0]['error']);
    }

    // -------------------------------------------------------------------------
    // Validation failures
    // -------------------------------------------------------------------------

    public function test_it_records_malformed_rows(): void
    {
        $malformed = ['__line' => 3, '__malformed' => true];

        $this->csvParser->shouldReceive('parse')->andReturn([$malformed]);
        $this->repository->shouldNotReceive('bulkCreateFromCsv');

        $result = $this->action->execute(1, '/path/to/file.csv');

        $this->assertSame(1, $result['error_count']);
        $this->assertStringContainsString('Column count', $result['errors'][0]['error']);
    }

    public function test_it_separates_valid_and_invalid_rows(): void
    {
        $rows = [
            $this->validRow(['__line' => 2]),
            $this->validRow(['__line' => 3, 'title' => '']),
        ];

        $this->csvParser->shouldReceive('parse')->andReturn($rows);

        $createdModel = new \stdClass();
        $this->stubRepositoryBulkCreate([$createdModel]);

        $result = $this->action->execute(1, '/path/to/file.csv');

        $this->assertSame(2, $result['total']);
        $this->assertSame(1, $result['success_count']);
        $this->assertSame(1, $result['error_count']);
    }

    // -------------------------------------------------------------------------
    // Malformed rows
    // -------------------------------------------------------------------------

    public function test_it_returns_empty_result_for_empty_csv(): void
    {
        $this->csvParser->shouldReceive('parse')->andReturn([]);
        $this->repository->shouldNotReceive('bulkCreateFromCsv');

        $result = $this->action->execute(1, '/path/to/file.csv');

        $this->assertSame(0, $result['total']);
        $this->assertSame(0, $result['success_count']);
        $this->assertSame(0, $result['error_count']);
    }

    // -------------------------------------------------------------------------
    // Mixed rows
    // -------------------------------------------------------------------------

    public function test_it_merges_repository_errors_into_result(): void
    {
        $this->csvParser->shouldReceive('parse')->andReturn([$this->validRow()]);

        $repoError = ['row' => 2, 'error' => 'Duplicate entry', 'data' => []];
        $this->stubRepositoryBulkCreate([], [$repoError]);

        $result = $this->action->execute(1, '/path/to/file.csv');

        $this->assertSame(1, $result['error_count']);
        $this->assertSame('Duplicate entry', $result['errors'][0]['error']);
    }

    // -------------------------------------------------------------------------
    // Empty file
    // -------------------------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();

        $this->csvParser = Mockery::mock(CsvParser::class);
        $this->repository = Mockery::mock(IssueDeliveryRepository::class);
        $this->database = Mockery::mock(Database::class);

        // Immediately invoke the closure passed to transaction()
        $this->database
            ->shouldReceive('transaction')
            ->andReturnUsing(fn(callable $callback) => $callback());

        $this->action = new ImportIssueSchedulesAction(
            $this->csvParser,
            $this->repository,
            $this->database,
        );
    }

    // -------------------------------------------------------------------------
    // Repository errors merged
    // -------------------------------------------------------------------------

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}