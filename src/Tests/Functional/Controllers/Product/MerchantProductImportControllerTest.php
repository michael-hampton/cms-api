<?php

namespace App\Tests\Functional\Controllers\Product;

use App\Models\Merchant;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class MerchantProductImportControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    private Merchant $merchant;

    public function test_valid_product_csv_imports_successfully(): void
    {

        $file = $this->makeCsvFile($this->validCsvContent());

        $response = $this->postForSite(
            "/api/merchants/1/import",
            ['type' => 'product'],
            ['file' => $file]
        );

        $this->assertResponseStatus(200, $response);
        $body = json_decode($response->getContent(), true);
        $this->assertSame(1, $body['imported']);
    }

    private function makeCsvFile(string $content, string $filename = 'products.csv'): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'product_import_') . '.csv';
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
        $category = $this->createCategory();

        return "name,price,category_id\n" .
            "Blue Widget,29.99,{$category->id}\n";
    }

    public function test_product_and_merchant_link_written_to_database(): void
    {
        $file = $this->makeCsvFile($this->validCsvContent());

        $this->postForSite(
            "/api/merchants/{$this->merchant->id}/import",
            ['type' => 'product'],
            ['file' => $file]
        );

        $this->assertDatabaseHas('products', [
            'name' => 'Blue Widget',
            'site_id' => $this->siteId,
        ]);

        // Find the created product then confirm the merchant link
        $product = \App\Models\Product::where('name', 'Blue Widget')->first();
        $this->assertNotNull($product);
        $this->assertDatabaseHas('product_merchants', [
            'product_id' => $product->id,
            'merchant_id' => $this->merchant->id,
        ]);
    }

    public function test_duplicate_product_skipped_when_flag_off(): void
    {
        $file = $this->makeCsvFile($this->validCsvContent());

        $this->postForSite("/api/merchants/{$this->merchant->id}/import", ['type' => 'product'], ['file' => $file]);

        $response = $this->postForSite(
            "/api/merchants/{$this->merchant->id}/import",
            ['type' => 'product', 'update_existing' => 'false'],
            ['file' => $file]
        );

        $body = json_decode($response->getContent(), true);
        $this->assertSame(0, $body['imported']);
        $this->assertSame(1, $body['skipped']);
    }

    public function test_duplicate_product_updated_when_flag_on(): void
    {
        $file = $this->makeCsvFile($this->validCsvContent());
        $this->postForSite("/api/merchants/{$this->merchant->id}/import", ['type' => 'product'], ['file' => $file]);

        $updatedContent = "name,price,category_id\nBlue Widget,49.99,1\n";
        $file = $this->makeCsvFile($updatedContent);

        $response = $this->postForSite(
            "/api/merchants/{$this->merchant->id}/import",
            ['type' => 'product', 'update_existing' => 'true'],
            ['file' => $file]
        );

        $this->assertResponseStatus(200, $response);
        $this->assertDatabaseHas('products', ['name' => 'Blue Widget', 'price' => 49.99]);
    }

    public function test_product_site_id_stamped_from_site_context(): void
    {
        $file = $this->makeCsvFile($this->validCsvContent());

        $this->postForSite(
            "/api/merchants/{$this->merchant->id}/import",
            ['type' => 'product'],
            ['file' => $file]
        );

        $this->assertDatabaseHas('products', [
            'name' => 'Blue Widget',
            'site_id' => $this->siteId,
        ]);
    }

    public function test_merchant_a_products_not_linked_to_merchant_b(): void
    {
        $merchantB = Merchant::create(['name' => 'Merchant B', 'slug' => 'merchant-b-prod', 'is_active' => true]);

        $this->postForSite(
            "/api/merchants/{$this->merchant->id}/import",
            ['type' => 'product'],
            ['file' => $this->makeCsvFile($this->validCsvContent())]
        );

        $product = \App\Models\Product::where('name', 'Blue Widget')->first();

        $this->assertDatabaseMissing('product_merchants', [
            'product_id' => $product->id,
            'merchant_id' => $merchantB->id,
        ]);
    }

    public function test_negative_price_row_is_skipped(): void
    {
        $content = "name,price,category_id\nBad Product,-10.00,1\n";
        $file = $this->makeCsvFile($content);

        $response = $this->postForSite(
            "/api/merchants/{$this->merchant->id}/import",
            ['type' => 'product'],
            ['file' => $file]
        );

        $body = json_decode($response->getContent(), true);
        $this->assertSame(0, $body['imported']);
        $this->assertSame(1, $body['skipped']);
        $this->assertDatabaseMissing('products', ['name' => 'Bad Product']);
    }

    public function test_missing_required_column_row_is_skipped(): void
    {
        $content = "name,price,category_id\n,29.99,1\n"; // empty name

        $file = $this->makeCsvFile($content);

        $response = $this->postForSite(
            "/api/merchants/{$this->merchant->id}/import",
            ['type' => 'product'],
            ['file' => $file]
        );

        $body = json_decode($response->getContent(), true);
        $this->assertSame(0, $body['imported']);
        $this->assertSame(1, $body['skipped']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->merchant = Merchant::create([
            'name' => 'Product Merchant',
            'slug' => 'product-merchant',
            'is_active' => true,
        ]);
    }
}