<?php

namespace App\Tests\Functional\Controllers\Product;

use App\Models\Merchant;
use App\Models\Product;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MerchantOfferImportControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private Merchant $merchant;
    private Product $product;

    public function test_valid_offer_csv_imports_successfully(): void
    {
        $response = $this->postForSite(
            "/api/merchants/{$this->merchant->id}/import",
            ['type' => 'offer'],
            ['file' => $this->makeCsvFile($this->validCsvContent())]
        );

        $this->assertResponseStatus(200, $response);
        $body = json_decode($response->getContent(), true);
        $this->assertSame(1, $body['imported']);
    }

    private function makeCsvFile(string $content, string $filename = 'offers.csv'): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'offer_import_') . '.csv';
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

        return "product_id,sale_price,start_date,end_date\n"
            . "{$this->product->id},79.99,{$start},{$end}\n";
    }

    public function test_offer_written_to_database(): void
    {
        $this->postForSite(
            "/api/merchants/{$this->merchant->id}/import",
            ['type' => 'offer'],
            ['file' => $this->makeCsvFile($this->validCsvContent())]
        );

        $this->assertDatabaseHas('product_offers', [
            'product_id' => $this->product->id,
            'merchant_id' => $this->merchant->id,
            'sale_price' => 79.99,
        ]);
    }

    public function test_duplicate_offer_skipped_when_flag_off(): void
    {
        $csv = $this->makeCsvFile($this->validCsvContent());

        $this->postForSite(
            "/api/merchants/{$this->merchant->id}/import",
            ['type' => 'offer'],
            ['file' => $csv]
        );

        $csv = $this->makeCsvFile($this->validCsvContent());
        $response = $this->postForSite(
            "/api/merchants/{$this->merchant->id}/import",
            ['type' => 'offer', 'update_existing' => 'false'],
            ['file' => $csv]
        );

        $body = json_decode($response->getContent(), true);
        $this->assertSame(0, $body['imported']);
        $this->assertSame(1, $body['skipped']);
    }

    public function test_duplicate_offer_updated_when_flag_on(): void
    {
        $this->postForSite(
            "/api/merchants/{$this->merchant->id}/import",
            ['type' => 'offer'],
            ['file' => $this->makeCsvFile($this->validCsvContent())]
        );

        $start = date('Y-m-d', strtotime('+1 day'));
        $end = date('Y-m-d', strtotime('+30 days'));
        $updated = "product_id,sale_price,start_date,end_date\n"
            . "{$this->product->id},49.99,{$start},{$end}\n";

        $response = $this->postForSite(
            "/api/merchants/{$this->merchant->id}/import",
            ['type' => 'offer', 'update_existing' => 'true'],
            ['file' => $this->makeCsvFile($updated)]
        );

        $this->assertResponseStatus(200, $response);
        $this->assertDatabaseHas('product_offers', [
            'product_id' => $this->product->id,
            'merchant_id' => $this->merchant->id,
            'sale_price' => 49.99,
        ]);
    }

    public function test_product_not_in_merchant_catalog_is_skipped(): void
    {
        $unlinkedProduct = Product::create([
            'name' => 'Unlinked Product',
            'price' => 50.00,
            'category_id' => $this->createCategory()->id,
            'is_active' => true,
            'site_id' => $this->siteId,
        ]);

        $start = date('Y-m-d', strtotime('+1 day'));
        $end = date('Y-m-d', strtotime('+30 days'));
        $content = "product_id,sale_price,start_date,end_date\n"
            . "{$unlinkedProduct->id},39.99,{$start},{$end}\n";

        $response = $this->postForSite(
            "/api/merchants/{$this->merchant->id}/import",
            ['type' => 'offer'],
            ['file' => $this->makeCsvFile($content)]
        );

        $body = json_decode($response->getContent(), true);
        $this->assertSame(0, $body['imported']);
        $this->assertSame(1, $body['skipped']);
        $this->assertDatabaseMissing('product_offers', ['product_id' => $unlinkedProduct->id]);
    }

    public function test_start_date_after_end_date_is_skipped(): void
    {
        $content = "product_id,sale_price,start_date,end_date\n"
            . "{$this->product->id},79.99,2030-12-31,2025-01-01\n";

        $response = $this->postForSite(
            "/api/merchants/{$this->merchant->id}/import",
            ['type' => 'offer'],
            ['file' => $this->makeCsvFile($content)]
        );

        $body = json_decode($response->getContent(), true);
        $this->assertSame(0, $body['imported']);
        $this->assertSame(1, $body['skipped']);
    }

    public function test_negative_sale_price_is_skipped(): void
    {
        $start = date('Y-m-d', strtotime('+1 day'));
        $end = date('Y-m-d', strtotime('+30 days'));
        $content = "product_id,sale_price,start_date,end_date\n"
            . "{$this->product->id},-10.00,{$start},{$end}\n";

        $response = $this->postForSite(
            "/api/merchants/{$this->merchant->id}/import",
            ['type' => 'offer'],
            ['file' => $this->makeCsvFile($content)]
        );

        $body = json_decode($response->getContent(), true);
        $this->assertSame(0, $body['imported']);
        $this->assertSame(1, $body['skipped']);
    }

    public function test_missing_required_column_is_skipped(): void
    {
// Missing sale_price value
        $start = date('Y-m-d', strtotime('+1 day'));
        $end = date('Y-m-d', strtotime('+30 days'));
        $content = "product_id,sale_price,start_date,end_date\n"
            . "{$this->product->id},,{$start},{$end}\n";

        $response = $this->postForSite(
            "/api/merchants/{$this->merchant->id}/import",
            ['type' => 'offer'],
            ['file' => $this->makeCsvFile($content)]
        );

        $body = json_decode($response->getContent(), true);
        $this->assertSame(0, $body['imported']);
        $this->assertSame(1, $body['skipped']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->merchant = Merchant::create([
            'name' => 'Offer Merchant',
            'slug' => 'offer-merchant',
            'is_active' => true,
        ]);

// A product that belongs to this merchant — required for offer validation
        $this->product = Product::create([
            'name' => 'Offerable Product',
            'price' => 100.00,
            'category_id' => $this->createCategory()->id,
            'is_active' => true,
            'site_id' => $this->siteId,
        ]);

// Link product to merchant so existsForMerchant() passes
        \App\Models\ProductMerchant::create([
            'product_id' => $this->product->id,
            'merchant_id' => $this->merchant->id,
            'price' => 100.00,
            'is_available' => true,
            'url' => 'https://example.com/product',
        ]);
    }
}