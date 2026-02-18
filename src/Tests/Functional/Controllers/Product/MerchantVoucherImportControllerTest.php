<?php

namespace App\Tests\Functional\Controllers\Product;

use App\Models\Merchant;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MerchantVoucherImportControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private Merchant $merchant;

    public function test_valid_voucher_csv_imports_successfully(): void
    {
        $response = $this->postForSite(
            "/api/merchants/{$this->merchant->id}/import",
            ['type' => 'voucher'],
            ['file' => $this->makeCsvFile($this->validCsvContent())]
        );

        $this->assertResponseStatus(200, $response);
        $body = json_decode($response->getContent(), true);
        $this->assertSame(1, $body['imported']);
    }

    private function makeCsvFile(string $content, string $filename = 'vouchers.csv'): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'voucher_import_') . '.csv';
        file_put_contents($tmp, $content);

        return [
            'name' => $filename,
            'tmp_name' => $tmp,
            'size' => strlen($content),
            'error' => UPLOAD_ERR_OK,
            'type' => 'text/csv',
        ];
    }

    private function validCsvContent(): string
    {
        $start = date('Y-m-d', strtotime('+1 day'));
        $end = date('Y-m-d', strtotime('+30 days'));

        return "code,type,value,start_date,end_date,usage_limit\n"
            . "SAVE10,percentage,10,{$start},{$end},100\n";
    }

    public function test_voucher_written_to_database(): void
    {
        $this->postForSite(
            "/api/merchants/{$this->merchant->id}/import",
            ['type' => 'voucher'],
            ['file' => $this->makeCsvFile($this->validCsvContent())]
        );

        $this->assertDatabaseHas('vouchers', [
            'code' => 'SAVE10',
            'type' => 'percentage',
            'merchant_id' => $this->merchant->id,
            'site_id' => $this->siteId,
        ]);
    }

    public function test_voucher_code_is_uppercased_on_import(): void
    {
        $start = date('Y-m-d', strtotime('+1 day'));
        $end = date('Y-m-d', strtotime('+30 days'));
        $content = "code,type,value,start_date,end_date,usage_limit\n"
            . "save20,percentage,20,{$start},{$end},50\n";

        $this->postForSite(
            "/api/merchants/{$this->merchant->id}/import",
            ['type' => 'voucher'],
            ['file' => $this->makeCsvFile($content)]
        );

        $this->assertDatabaseHas('vouchers', ['code' => 'SAVE20']);
        //$this->assertDatabaseMissing('vouchers', ['code' => 'save20']);
    }

    public function test_duplicate_voucher_skipped_when_flag_off(): void
    {
        $csv = $this->makeCsvFile($this->validCsvContent());
        $this->postForSite(
            "/api/merchants/{$this->merchant->id}/import",
            ['type' => 'voucher'],
            ['file' => $csv]
        );

        $csv = $this->makeCsvFile($this->validCsvContent());
        $response = $this->postForSite(
            "/api/merchants/{$this->merchant->id}/import",
            ['type' => 'voucher', 'update_existing' => 'false'],
            ['file' => $csv]
        );

        $body = json_decode($response->getContent(), true);
        $this->assertSame(0, $body['imported']);
        $this->assertSame(1, $body['skipped']);
    }

    public function test_duplicate_voucher_updated_when_flag_on(): void
    {
        $this->postForSite(
            "/api/merchants/{$this->merchant->id}/import",
            ['type' => 'voucher'],
            ['file' => $this->makeCsvFile($this->validCsvContent())]
        );

        $start = date('Y-m-d', strtotime('+1 day'));
        $end = date('Y-m-d', strtotime('+30 days'));
        $updated = "code,type,value,start_date,end_date,usage_limit\n"
            . "SAVE10,percentage,25,{$start},{$end},100\n";

        $response = $this->postForSite(
            "/api/merchants/{$this->merchant->id}/import",
            ['type' => 'voucher', 'update_existing' => 'true'],
            ['file' => $this->makeCsvFile($updated)]
        );

        $this->assertResponseStatus(200, $response);
        $this->assertDatabaseHas('vouchers', [
            'code' => 'SAVE10',
            'value' => 25,
        ]);
    }

    public function test_invalid_voucher_type_is_skipped(): void
    {
        $start = date('Y-m-d', strtotime('+1 day'));
        $end = date('Y-m-d', strtotime('+30 days'));
        $content = "code,type,value,start_date,end_date,usage_limit\n"
            . "BADTYPE,bogustype,10,{$start},{$end},100\n";

        $response = $this->postForSite(
            "/api/merchants/{$this->merchant->id}/import",
            ['type' => 'voucher'],
            ['file' => $this->makeCsvFile($content)]
        );

        $body = json_decode($response->getContent(), true);
        $this->assertSame(0, $body['imported']);
        $this->assertSame(1, $body['skipped']);
        $this->assertDatabaseMissing('vouchers', ['code' => 'BADTYPE']);
    }

    public function test_start_date_after_end_date_is_skipped(): void
    {
        $content = "code,type,value,start_date,end_date,usage_limit\n"
            . "BADDATE,percentage,10,2030-12-31,2025-01-01,100\n";

        $response = $this->postForSite(
            "/api/merchants/{$this->merchant->id}/import",
            ['type' => 'voucher'],
            ['file' => $this->makeCsvFile($content)]
        );

        $body = json_decode($response->getContent(), true);
        $this->assertSame(0, $body['imported']);
        $this->assertSame(1, $body['skipped']);
    }

    public function test_negative_value_is_skipped(): void
    {
        $start = date('Y-m-d', strtotime('+1 day'));
        $end = date('Y-m-d', strtotime('+30 days'));
        $content = "code,type,value,start_date,end_date,usage_limit\n"
            . "NEGVAL,percentage,-5,{$start},{$end},100\n";

        $response = $this->postForSite(
            "/api/merchants/{$this->merchant->id}/import",
            ['type' => 'voucher'],
            ['file' => $this->makeCsvFile($content)]
        );

        $body = json_decode($response->getContent(), true);
        $this->assertSame(0, $body['imported']);
        $this->assertSame(1, $body['skipped']);
    }

    public function test_missing_required_column_is_skipped(): void
    {
// Missing code value
        $start = date('Y-m-d', strtotime('+1 day'));
        $end = date('Y-m-d', strtotime('+30 days'));
        $content = "code,type,value,start_date,end_date,usage_limit\n"
            . ",percentage,10,{$start},{$end},100\n";

        $response = $this->postForSite(
            "/api/merchants/{$this->merchant->id}/import",
            ['type' => 'voucher'],
            ['file' => $this->makeCsvFile($content)]
        );

        $body = json_decode($response->getContent(), true);
        $this->assertSame(0, $body['imported']);
        $this->assertSame(1, $body['skipped']);
    }

    public function test_fixed_type_voucher_imports_successfully(): void
    {
        $start = date('Y-m-d', strtotime('+1 day'));
        $end = date('Y-m-d', strtotime('+30 days'));
        $content = "code,type,value,start_date,end_date,usage_limit\n"
            . "FIXED5,fixed,5.00,{$start},{$end},50\n";

        $response = $this->postForSite(
            "/api/merchants/{$this->merchant->id}/import",
            ['type' => 'voucher'],
            ['file' => $this->makeCsvFile($content)]
        );

        $this->assertResponseStatus(200, $response);
        $body = json_decode($response->getContent(), true);
        $this->assertSame(1, $body['imported']);
        $this->assertDatabaseHas('vouchers', ['code' => 'FIXED5', 'type' => 'fixed']);
    }

    public function test_voucher_site_id_stamped_from_site_context(): void
    {
        $this->postForSite(
            "/api/merchants/{$this->merchant->id}/import",
            ['type' => 'voucher'],
            ['file' => $this->makeCsvFile($this->validCsvContent())]
        );

        $this->assertDatabaseHas('vouchers', [
            'code' => 'SAVE10',
            'site_id' => $this->siteId,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->merchant = Merchant::create([
            'name' => 'Voucher Merchant',
            'slug' => 'voucher-merchant',
            'is_active' => true,
        ]);
    }
}