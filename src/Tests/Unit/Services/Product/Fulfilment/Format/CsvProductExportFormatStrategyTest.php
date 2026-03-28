<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services\Product\Fulfilment\Format;

use App\Models\ProductFulfilment;
use App\Services\Product\Fulfilment\Format\CsvProductExportFormatStrategy;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class CsvProductExportFormatStrategyTest extends TestCase
{
    private CsvProductExportFormatStrategy $strategy;

    public function test_it_generates_a_csv_with_correct_headers(): void
    {
        $csv = $this->strategy->generate(batchId: 1, fulfillments: [], issue: []);
        $lines = $this->parseCsv($csv);

        $this->assertSame([
            'batch_id', 'order_id', 'order_line_id', 'sku', 'quantity',
            'full_name', 'address_line_1', 'address_line_2',
            'city', 'postcode', 'country', 'territory_id', 'tracking_number',
        ], $lines[0]);
    }

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

    public function test_it_generates_one_data_row_per_fulfilment(): void
    {
        $fulfilments = [$this->makeFulfilment(), $this->makeFulfilment()];

        $csv = $this->strategy->generate(batchId: 7, fulfillments: $fulfilments, issue: []);
        $lines = $this->parseCsv($csv);

        // Header + 2 data rows.
        $this->assertCount(3, $lines);
    }

    private function makeFulfilment(
        int     $orderId = 1,
        int     $orderLineId = 1,
        string  $sku = 'SKU-DEFAULT',
        int     $quantity = 1,
        string  $fullName = 'Test User',
        string  $addressLine1 = '1 Test Street',
        ?string $addressLine2 = null,
        string  $city = 'London',
        string  $postcode = 'EC1A 1BB',
        string  $country = 'GB',
        ?int    $territoryId = null,
        ?string $trackingNumber = null,
    ): ProductFulfilment&MockInterface
    {
        $f = Mockery::mock(ProductFulfilment::class)->makePartial();
        $f->order_id = $orderId;
        $f->order_line_id = $orderLineId;
        $f->sku = $sku;
        $f->quantity = $quantity;
        $f->full_name = $fullName;
        $f->address_line_1 = $addressLine1;
        $f->address_line_2 = $addressLine2;
        $f->city = $city;
        $f->postcode = $postcode;
        $f->country = $country;
        $f->territory_id = $territoryId;
        $f->tracking_number = $trackingNumber;
        return $f;
    }

    public function test_it_writes_fulfilment_fields_in_correct_column_order(): void
    {
        $fulfilment = $this->makeFulfilment(
            orderId: 10,
            orderLineId: 20,
            sku: 'SKU-123',
            quantity: 3,
            fullName: 'Jane Smith',
            addressLine1: '10 Downing Street',
            addressLine2: 'Flat 2',
            city: 'London',
            postcode: 'SW1A 2AA',
            country: 'GB',
            territoryId: 5,
            trackingNumber: 'TRACK001',
        );

        $csv = $this->strategy->generate(batchId: 42, fulfillments: [$fulfilment], issue: []);
        $lines = $this->parseCsv($csv);
        $row = $lines[1];

        $this->assertSame('42', $row[0]);  // batch_id
        $this->assertSame('10', $row[1]);  // order_id
        $this->assertSame('20', $row[2]);  // order_line_id
        $this->assertSame('SKU-123', $row[3]);  // sku
        $this->assertSame('3', $row[4]);  // quantity
        $this->assertSame('Jane Smith', $row[5]);  // full_name
        $this->assertSame('10 Downing Street', $row[6]);
        $this->assertSame('Flat 2', $row[7]);
        $this->assertSame('London', $row[8]);
        $this->assertSame('SW1A 2AA', $row[9]);
        $this->assertSame('GB', $row[10]);
        $this->assertSame('5', $row[11]); // territory_id
        $this->assertSame('TRACK001', $row[12]);
    }

    public function test_it_writes_empty_string_for_null_address_line_2(): void
    {
        $fulfilment = $this->makeFulfilment(addressLine2: null);

        $csv = $this->strategy->generate(batchId: 1, fulfillments: [$fulfilment], issue: []);
        $lines = $this->parseCsv($csv);

        $this->assertSame('', $lines[1][7]);
    }

    public function test_it_writes_empty_string_for_null_territory_id(): void
    {
        $fulfilment = $this->makeFulfilment(territoryId: null);

        $csv = $this->strategy->generate(batchId: 1, fulfillments: [$fulfilment], issue: []);
        $lines = $this->parseCsv($csv);

        $this->assertSame('', $lines[1][11]);
    }

    public function test_it_writes_empty_string_for_null_tracking_number(): void
    {
        $fulfilment = $this->makeFulfilment(trackingNumber: null);

        $csv = $this->strategy->generate(batchId: 1, fulfillments: [$fulfilment], issue: []);
        $lines = $this->parseCsv($csv);

        $this->assertSame('', $lines[1][12]);
    }

    public function test_it_ignores_the_issue_parameter(): void
    {
        // The issue array is required by the shared interface but irrelevant for products.
        $fulfilment = $this->makeFulfilment();

        $withIssue = $this->strategy->generate(1, [$fulfilment], ['id' => 9, 'title' => 'Issue 12']);
        $withoutIssue = $this->strategy->generate(1, [$fulfilment], []);

        $this->assertSame($withIssue, $withoutIssue);
    }

    public function test_it_returns_csv_as_a_string(): void
    {
        $result = $this->strategy->generate(1, [], []);
        $this->assertIsString($result);
    }

    public function test_it_reports_csv_as_its_extension(): void
    {
        $this->assertSame('csv', $this->strategy->extension());
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new CsvProductExportFormatStrategy();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}