<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Subscriptions\Printing\Label;

use App\Models\PrintFulfillment;
use App\Services\Subscriptions\Printing\Label\CsvLabelExportFormatStrategy;
use App\Services\Subscriptions\Printing\Label\LabelContext;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class CsvLabelExportFormatStrategyTest extends TestCase
{
    private CsvLabelExportFormatStrategy $strategy;

    public function test_it_generates_csv_with_correct_headers(): void
    {
        $output = $this->strategy->generate(
            $this->makeFulfillment(),
            $this->makeContext(),
        );

        $lines = array_filter(explode("\n", trim($output)));
        $headers = str_getcsv(reset($lines));

        $this->assertSame([
            'full_name',
            'address_line_1',
            'address_line_2',
            'city',
            'postcode',
            'country',
            'subscription_account_number',
            'issue_number',
            'issue_title',
            'return_name',
            'return_address_line_1',
            'return_address_line_2',
            'return_city',
            'return_postcode',
            'return_country',
        ], $headers);
    }

    private function makeFulfillment(
        string  $fullName = 'Test User',
        string  $addressLine1 = '1 Test Street',
        ?string $addressLine2 = null,
        string  $city = 'London',
        string  $postcode = 'E1 1AA',
        string  $country = 'GB',
        int     $subscriptionId = 1,
    ): MockInterface
    {
        $fulfillment = Mockery::mock(PrintFulfillment::class)->makePartial();

        $fulfillment->full_name = $fullName;
        $fulfillment->address_line_1 = $addressLine1;
        $fulfillment->address_line_2 = $addressLine2;
        $fulfillment->city = $city;
        $fulfillment->postcode = $postcode;
        $fulfillment->country = $country;
        $fulfillment->subscription_id = $subscriptionId;

        return $fulfillment;
    }

    // =========================================================================
    // Output shape
    // =========================================================================

    private function makeContext(
        ?string $issueNumber = '1',
        ?string $issueTitle = 'Test Issue',
        string  $returnName = 'Publisher',
        string  $returnAddressLine1 = '1 Return Street',
        ?string $returnAddressLine2 = null,
        string  $returnCity = 'London',
        string  $returnPostcode = 'EC1A 1BB',
        string  $returnCountry = 'GB',
    ): LabelContext
    {
        return new LabelContext(
            issueDeliveryId: 1,
            issueNumber: $issueNumber,
            issueTitle: $issueTitle,
            returnAddressLine1: $returnAddressLine1,
            returnAddressLine2: $returnAddressLine2,
            returnCity: $returnCity,
            returnPostcode: $returnPostcode,
            returnCountry: $returnCountry,
            returnName: $returnName,
        );
    }

    public function test_it_generates_exactly_two_rows_header_plus_data(): void
    {
        $output = $this->strategy->generate(
            $this->makeFulfillment(),
            $this->makeContext(),
        );

        $lines = array_filter(explode("\n", trim($output)));

        $this->assertCount(2, $lines);
    }

    public function test_it_writes_fulfillment_data_to_data_row(): void
    {
        $fulfillment = $this->makeFulfillment(
            fullName: 'Jane Smith',
            addressLine1: '10 Downing Street',
            addressLine2: 'Flat 2',
            city: 'London',
            postcode: 'SW1A 2AA',
            country: 'GB',
            subscriptionId: 42,
        );

        $output = $this->strategy->generate($fulfillment, $this->makeContext());

        $lines = array_filter(explode("\n", trim($output)));
        $dataRow = str_getcsv(array_values($lines)[1]);

        $this->assertSame('Jane Smith', $dataRow[0]);
        $this->assertSame('10 Downing Street', $dataRow[1]);
        $this->assertSame('Flat 2', $dataRow[2]);
        $this->assertSame('London', $dataRow[3]);
        $this->assertSame('SW1A 2AA', $dataRow[4]);
        $this->assertSame('GB', $dataRow[5]);
        $this->assertSame('42', $dataRow[6]); // subscription_account_number
    }

    public function test_it_writes_issue_and_return_address_data_to_data_row(): void
    {
        $context = $this->makeContext(
            issueNumber: '99',
            issueTitle: 'Winter Edition',
            returnName: 'Publisher Ltd',
            returnAddressLine1: '1 Publisher Way',
            returnCity: 'Manchester',
            returnPostcode: 'M1 1AA',
            returnCountry: 'GB',
        );

        $output = $this->strategy->generate($this->makeFulfillment(), $context);
        $lines = array_filter(explode("\n", trim($output)));
        $dataRow = str_getcsv(array_values($lines)[1]);

        $this->assertSame('99', $dataRow[7]);           // issue_number
        $this->assertSame('Winter Edition', $dataRow[8]); // issue_title
        $this->assertSame('Publisher Ltd', $dataRow[9]);  // return_name
        $this->assertSame('1 Publisher Way', $dataRow[10]);
        $this->assertSame('Manchester', $dataRow[12]);
        $this->assertSame('M1 1AA', $dataRow[13]);
        $this->assertSame('GB', $dataRow[14]);
    }

    public function test_it_outputs_empty_string_for_null_address_line_2(): void
    {
        $fulfillment = $this->makeFulfillment(addressLine2: null);

        $output = $this->strategy->generate($fulfillment, $this->makeContext());
        $lines = array_filter(explode("\n", trim($output)));
        $dataRow = str_getcsv(array_values($lines)[1]);

        $this->assertSame('', $dataRow[2]);
    }

    public function test_it_outputs_empty_string_for_null_issue_number_and_title(): void
    {
        $context = $this->makeContext(issueNumber: null, issueTitle: null);

        $output = $this->strategy->generate($this->makeFulfillment(), $context);
        $lines = array_filter(explode("\n", trim($output)));
        $dataRow = str_getcsv(array_values($lines)[1]);

        $this->assertSame('', $dataRow[7]);
        $this->assertSame('', $dataRow[8]);
    }

    // =========================================================================
    // Metadata
    // =========================================================================

    public function test_it_returns_csv_as_extension(): void
    {
        $this->assertSame('csv', $this->strategy->extension());
    }

    public function test_it_returns_csv_as_format_name(): void
    {
        $this->assertSame('csv', $this->strategy->formatName());
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new CsvLabelExportFormatStrategy();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}