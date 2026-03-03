<?php

namespace App\Tests\Unit\Actions\Subscriptions;

use App\Actions\Subscriptions\ExportIssueSchedulesAction;
use App\Enums\Subscriptions\IssueScheduleStatus;
use App\Framework\Storage\StoragePathResolverInterface;
use App\Repositories\Subscriptions\IssueDeliveryRepository;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class ExportIssueSchedulesActionTest extends TestCase
{
    private MockInterface|IssueDeliveryRepository $repository;
    private ExportIssueSchedulesAction $action;

    public function test_it_returns_a_filepath_string(): void
    {
        $this->repository->shouldReceive('getAllForSite')->with(1)->andReturn(collect([]));

        $filepath = $this->action->execute(1);

        $this->assertIsString($filepath);
        $this->assertStringContainsString('issue_schedules_', $filepath);
        $this->assertStringEndsWith('.csv', $filepath);
    }

    public function test_it_creates_csv_with_correct_headers(): void
    {
        $this->repository->shouldReceive('getAllForSite')->andReturn(collect([]));

        $filepath = $this->action->execute(1);

        $handle = fopen($filepath, 'r');
        $headers = fgetcsv($handle);
        fclose($handle);

        $this->assertSame(
            ['ID', 'Title', 'Issue Number', 'Issue Code', 'Product ID',
                'Promotion ID', 'On Sale Date', 'Cut Off Date', 'Fulfilment Date',
                'Status', 'Created At'],
            $headers
        );

        @unlink($filepath);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function test_it_writes_schedule_data_to_csv(): void
    {
        $schedule = $this->makeSchedule();
        $this->repository->shouldReceive('getAllForSite')->andReturn(collect([$schedule]));

        $filepath = $this->action->execute(1);

        $handle = fopen($filepath, 'r');
        fgetcsv($handle); // skip headers
        $row = fgetcsv($handle);
        fclose($handle);

        $this->assertSame('1', $row[0]);
        $this->assertSame('Test Issue', $row[1]);
        $this->assertSame('42', $row[2]);
        $this->assertSame('TI-42', $row[3]);
        $this->assertSame('100', $row[4]);
        $this->assertSame('200', $row[5]);
        $this->assertSame('2025-06-01', $row[6]);
        $this->assertSame('2025-05-25', $row[7]);
        $this->assertSame('2025-06-10', $row[8]);
        $this->assertSame(IssueScheduleStatus::ACTIVE->value, $row[9]);
        $this->assertSame('2025-01-01 10:00:00', $row[10]);

        @unlink($filepath);
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    private function makeSchedule(array $overrides = []): object
    {
        $defaults = [
            'id' => 1,
            'title' => 'Test Issue',
            'issue_number' => 42,
            'issue_code' => 'TI-42',
            'product_id' => 100,
            'promotion_id' => 200,
            'on_sale_date' => new \DateTime('2025-06-01'),
            'cut_off_date' => new \DateTime('2025-05-25'),
            'fulfilment_date' => new \DateTime('2025-06-10'),
            'status' => IssueScheduleStatus::ACTIVE,
            'created_at' => new \DateTime('2025-01-01 10:00:00'),
        ];

        return (object)array_merge($defaults, $overrides);
    }

    public function test_it_handles_nullable_fields_gracefully(): void
    {
        $schedule = $this->makeSchedule([
            'issue_code' => null,
            'product_id' => null,
            'promotion_id' => null,
            'cut_off_date' => null,
            'fulfilment_date' => null,
        ]);

        $this->repository->shouldReceive('getAllForSite')->andReturn(collect([$schedule]));

        $filepath = $this->action->execute(1);

        $handle = fopen($filepath, 'r');
        fgetcsv($handle); // skip headers
        $row = fgetcsv($handle);
        fclose($handle);

        $this->assertSame('', $row[3]); // issue_code
        $this->assertSame('', $row[4]); // product_id
        $this->assertSame('', $row[5]); // promotion_id
        $this->assertSame('', $row[7]); // cut_off_date
        $this->assertSame('', $row[8]); // fulfilment_date

        @unlink($filepath);
    }

    public function test_it_creates_an_empty_csv_with_only_headers_when_no_schedules(): void
    {
        $this->repository->shouldReceive('getAllForSite')->andReturn(collect([]));

        $filepath = $this->action->execute(1);

        $rows = array_filter(file($filepath));

        $this->assertCount(1, $rows); // header row only

        @unlink($filepath);
    }

    public function test_it_writes_multiple_schedules(): void
    {
        $schedules = [
            $this->makeSchedule(['id' => 1, 'title' => 'Issue A']),
            $this->makeSchedule(['id' => 2, 'title' => 'Issue B']),
        ];

        $this->repository->shouldReceive('getAllForSite')->andReturn(collect($schedules));

        $filepath = $this->action->execute(1);

        $rows = array_values(array_filter(file($filepath)));

        $this->assertCount(3, $rows); // header + 2 data rows

        @unlink($filepath);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(IssueDeliveryRepository::class);

        $storagePathResolver = new class implements StoragePathResolverInterface {
            public function resolve(string $relativePath): string
            {
                return sys_get_temp_dir() . '/' . ltrim($relativePath, '/');
            }
        };

        $this->action = new ExportIssueSchedulesAction(
            $this->repository,
            $storagePathResolver,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}