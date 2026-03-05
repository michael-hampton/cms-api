<?php

namespace App\Tests\Unit\Services\Subscriptions\Printing;

use App\Models\PrintFulfillment;
use App\Services\Subscriptions\Printing\Format\CsvPrintExportFormatStrategy;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class CsvPrintExportFormatStrategyTest extends FunctionalTestCase
{
    private CsvPrintExportFormatStrategy $strategy;

    public function test_generates_csv_with_correct_headers(): void
    {
        $csv = $this->strategy->generate(1, [], ['id' => 5, 'title' => 'Spring Issue']);

        $lines = $this->parseCsv($csv);
        $this->assertSame([
            'batch_id', 'subscription_id', 'member_name',
            'address_line_1', 'address_line_2', 'city',
            'postcode', 'country', 'issue_id', 'issue_title', 'tracking_number',
        ], $lines[0]);
    }

    /**
     * @return string[][]
     */
    private function parseCsv(string $csv): array
    {
        $lines = [];
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $csv);
        rewind($handle);
        while (($row = fgetcsv($handle)) !== false) {
            $lines[] = $row;
        }
        fclose($handle);
        return $lines;
    }

    public function test_generates_row_per_fulfillment(): void
    {
        $fulfillments = [
            $this->makeFulfillment(['subscription_id' => 10, 'full_name' => 'Jane Doe']),
            $this->makeFulfillment(['subscription_id' => 11, 'full_name' => 'John Smith']),
        ];

        $csv = $this->strategy->generate(42, $fulfillments, ['id' => 5, 'title' => 'Spring Issue']);

        $lines = $this->parseCsv($csv);

        // Header + 2 data rows
        $this->assertCount(3, $lines);
        $this->assertSame('Jane Doe', $lines[1][2]);
        $this->assertSame('John Smith', $lines[2][2]);
    }

    private function makeFulfillment(array $overrides = []): PrintFulfillment
    {
        $f = Mockery::mock(PrintFulfillment::class)->makePartial();
        $f->subscription_id = $overrides['subscription_id'] ?? 1;
        $f->full_name = $overrides['full_name'] ?? 'Test User';
        $f->address_line_1 = $overrides['address_line_1'] ?? '1 Main St';
        $f->address_line_2 = $overrides['address_line_2'] ?? null;
        $f->city = $overrides['city'] ?? 'London';
        $f->postcode = $overrides['postcode'] ?? 'E1 1AA';
        $f->country = $overrides['country'] ?? 'GB';
        $f->tracking_number = $overrides['tracking_number'] ?? null;
        return $f;
    }

    public function test_includes_batch_id_in_every_row(): void
    {
        $fulfillments = [$this->makeFulfillment()];

        $csv = $this->strategy->generate(99, $fulfillments, ['id' => 1, 'title' => null]);

        $lines = $this->parseCsv($csv);

        $this->assertSame('99', $lines[1][0]);
    }

    public function test_tracking_number_is_empty_string_when_null(): void
    {
        $fulfillment = $this->makeFulfillment(['tracking_number' => null]);

        $csv = $this->strategy->generate(1, [$fulfillment], ['id' => 1, 'title' => 'Issue']);

        $lines = $this->parseCsv($csv);

        $this->assertSame('', $lines[1][10]);
    }

    public function test_tracking_number_included_when_present(): void
    {
        $fulfillment = $this->makeFulfillment(['tracking_number' => 'TRK-123456']);

        $csv = $this->strategy->generate(1, [$fulfillment], ['id' => 1, 'title' => 'Issue']);

        $lines = $this->parseCsv($csv);

        $this->assertSame('TRK-123456', $lines[1][10]);
    }

    public function test_returns_csv_extension(): void
    {
        $this->assertSame('csv', $this->strategy->extension());
    }

    public function test_empty_fulfillments_returns_header_only(): void
    {
        $csv = $this->strategy->generate(1, [], ['id' => 1, 'title' => 'Issue']);

        $lines = $this->parseCsv($csv);

        $this->assertCount(1, $lines);
    }

    public function test_can_view_real_csv_output(): void
    {
        $fulfillment = $this->makeFulfillment([
            'subscription_id' => 10,
            'full_name' => 'Jane Doe',
            'address_line_1' => '1 Main St',
            'city' => 'London',
            'postcode' => 'E1 1AA',
            'country' => 'GB',
            'tracking_number' => 'TRK-123'
        ]);

        $csv = $this->strategy->generate(
            1,
            [$fulfillment],
            ['id' => 5, 'title' => 'Spring Issue']
        );

        echo PHP_EOL . "===== CSV OUTPUT =====" . PHP_EOL;
        echo $csv . PHP_EOL;
        echo "======================" . PHP_EOL;

        $this->assertNotEmpty($csv);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new CsvPrintExportFormatStrategy();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}