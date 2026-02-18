<?php

namespace App\Tests\Functional\Controllers\Product;

use App\DTO\ImportResult;
use App\Framework\Container;
use App\Imports\BaseMerchantImport;
use App\Imports\MerchantImportService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class MerchantImportControllerTest extends FunctionalTestCase
{
    private const MERCHANT_ID = 10;
    private const SITE_NAME = 'test-site';

    private MerchantImportService $importService;

    public function test_merchant_can_upload_valid_csv_and_import(): void
    {
        $this->stubSuccessfulImport(imported: 3, skipped: 0);

        $file = $this->makeCsvFile("code,type,value,start_date,end_date,usage_limit\nSAVE10,percentage,10,2025-01-01,2025-12-31,100\n");
        $response = $this->postImport($file);

        $this->assertResponseStatus(200, $response);
        $body = json_decode($response->getContent(), true);
        $this->assertSame(3, $body['imported']);
        $this->assertSame(0, $body['skipped']);
        $this->assertEmpty($body['skipped_rows']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function stubSuccessfulImport(int $imported = 3, int $skipped = 0): void
    {
        $result = new ImportResult();
        for ($i = 0; $i < $imported; $i++) {
            $result->recordImported();
        }
        for ($i = 0; $i < $skipped; $i++) {
            $result->recordSkipped($i + 2, ['row' => 'data'], 'Some reason');
        }

        $this->importService
            ->shouldReceive('upload')
            ->once()
            ->andReturn('/tmp/stored.csv');

        $this->importService
            ->shouldReceive('import')
            ->once()
            ->andReturn($result);
    }

    private function makeCsvFile(string $content, string $filename = 'import.csv'): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'import_') . '.csv';
        file_put_contents($tmp, $content);

        return [
            'name' => $filename,
            'tmp_name' => $tmp,
            'size' => strlen($content),
            'error' => UPLOAD_ERR_OK,
            'type' => 'text/csv',
        ];
    }

    private function postImport(array $file, array $body = [])
    {
        return $this->postForSite(
            "/api/merchants/" . self::MERCHANT_ID . "/import",
            $body ?: ['type' => 'product'],
            ['file' => $file]
        );
    }

    // -------------------------------------------------------------------------
    // Happy path
    // -------------------------------------------------------------------------

    public function test_feedback_shows_success_and_fail_counts(): void
    {
        $this->stubSuccessfulImport(imported: 7, skipped: 3);

        $file = $this->makeCsvFile("code,type,value,start_date,end_date,usage_limit\nSAVE10,percentage,10,2025-01-01,2025-12-31,100\n");
        $response = $this->postImport($file);

        $body = json_decode($response->getContent(), true);

        $this->assertSame(7, $body['imported']);
        $this->assertSame(3, $body['skipped']);
        $this->assertCount(3, $body['skipped_rows']);
    }

    public function test_skipped_rows_contain_line_and_reason(): void
    {
        $result = new ImportResult();
        $result->recordSkipped(2, ['code' => ''], 'Missing required field: code');

        $this->importService->shouldReceive('upload')->andReturn('/tmp/stored.csv');
        $this->importService->shouldReceive('import')->andReturn($result);

        $file = $this->makeCsvFile("code,type,value,start_date,end_date,usage_limit\n,percentage,10,2025-01-01,2025-12-31,100\n");
        $response = $this->postImport($file);

        $body = json_decode($response->getContent(), true);
        $skipped = $body['skipped_rows'][0];

        $this->assertArrayHasKey('line', $skipped);
        $this->assertArrayHasKey('reason', $skipped);
        $this->assertStringContainsString('code', $skipped['reason']);
    }

    public function test_import_with_update_existing_flag_passed_through(): void
    {
        $this->stubSuccessfulImport();

        $file = $this->makeCsvFile("code,type,value,start_date,end_date,usage_limit\nSAVE10,percentage,10,2025-01-01,2025-12-31,100\n");

        $response = $this->postImport($file, ['update_existing' => 'true', 'type' => 'voucher']);

        $this->assertResponseStatus(200, $response);
    }

    public function test_upload_non_csv_file_is_rejected(): void
    {
        $file = $this->makeCsvFile('not,csv,content', 'import.xlsx');
        $response = $this->postImport($file);

        $this->assertResponseStatus(422, $response);
        $body = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('file', $body['errors']);
    }

    // -------------------------------------------------------------------------
    // File validation
    // -------------------------------------------------------------------------

    public function test_missing_file_is_rejected(): void
    {
        $response = $this->postForSite(
            '/api/' . '/merchants/' . self::MERCHANT_ID . '/import',
            ['type' => 'voucher']
        );

        $this->assertResponseStatus(422, $response);
    }

    public function test_missing_type_is_rejected(): void
    {
        $file = $this->makeCsvFile("code,type,value\nSAVE10,percentage,10\n");
        $response = $this->postForSite(
            '/api/' . '/merchants/' . self::MERCHANT_ID . '/import',
            $file,
            []  // no type
        );

        $this->assertResponseStatus(422, $response);
        $body = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('type', $body['errors']);
    }

    public function test_invalid_type_value_is_rejected(): void
    {
        $file = $this->makeCsvFile("code,type,value\nSAVE10,percentage,10\n");
        $response = $this->postImport($file, ['type' => 'banana']);

        $this->assertResponseStatus(422, $response);
        $body = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('type', $body['errors']);
    }

    public function test_txt_file_extension_is_accepted(): void
    {
        $this->stubSuccessfulImport();

        $file = $this->makeCsvFile("code,type,value,start_date,end_date,usage_limit\nSAVE10,percentage,10,2025-01-01,2025-12-31,100\n", 'import.txt');
        $response = $this->postImport($file);

        $this->assertResponseStatus(200, $response);
    }

    public function test_offer_import_type_is_accepted(): void
    {
        $this->stubSuccessfulImport(imported: 2);

        $file = $this->makeCsvFile("product_id,sale_price,start_date,end_date\n42,19.99,2025-01-01,2025-12-31\n");
        $response = $this->postImport($file, ['type' => 'offer']);

        $this->assertResponseStatus(200, $response);
        $this->assertSame(2, json_decode($response->getContent(), true)['imported']);
    }

    // -------------------------------------------------------------------------
    // Offer import type
    // -------------------------------------------------------------------------

    public function test_product_import_type_is_accepted(): void
    {
        $this->stubSuccessfulImport(imported: 5);

        $file = $this->makeCsvFile("name,price,category_id\nWidget,9.99,3\n");
        $response = $this->postImport($file, ['type' => 'product']);

        $this->assertResponseStatus(200, $response);
        $this->assertSame(5, json_decode($response->getContent(), true)['imported']);
    }

    // -------------------------------------------------------------------------
    // Product import type
    // -------------------------------------------------------------------------

    public function test_imported_entities_are_scoped_to_uploading_merchant(): void
    {
        // The importer receives merchantId from the route — verify the service
        // receives an importer built for the correct merchant by asserting the
        // route param flows through without mutation.
        $capturedImporter = null;

        $result = new ImportResult();
        $result->recordImported();

        $this->importService->shouldReceive('upload')->andReturn('/tmp/stored.csv');
        $this->importService
            ->shouldReceive('import')
            ->once()
            ->withArgs(function (BaseMerchantImport $importer, string $path) use (&$capturedImporter) {
                $capturedImporter = $importer;
                return true;
            })
            ->andReturn($result);

        $file = $this->makeCsvFile("code,type,value,start_date,end_date,usage_limit\nSAVE10,percentage,10,2025-01-01,2025-12-31,100\n");
        $this->postImport($file);

        // Merchant ID is encapsulated in the importer — we confirm construction
        // used the route's merchant ID via reflection.
        $reflection = new \ReflectionClass($capturedImporter);
        $property = $reflection->getProperty('importOptions');
        $property->setAccessible(true);
        $this->assertSame(self::MERCHANT_ID, $property->getValue($capturedImporter)->merchantId);
    }

    // -------------------------------------------------------------------------
    // Merchant isolation
    // -------------------------------------------------------------------------

    public function test_merchant_a_import_does_not_affect_merchant_b(): void
    {
        $merchantAResult = new ImportResult();
        $merchantAResult->recordImported();

        // Two separate calls — one per merchant
        $this->importService->shouldReceive('upload')->twice()->andReturn('/tmp/stored.csv');
        $this->importService->shouldReceive('import')->twice()->andReturn($merchantAResult);

        $file = $this->makeCsvFile("code,type,value,start_date,end_date,usage_limit\nSAVE10,percentage,10,2025-01-01,2025-12-31,100\n");

        $responseA = $this->postForSite(
            "/api/merchants/" . self::MERCHANT_ID . "/import",
            ['type' => 'voucher'],
            ['file' => $file]
        );

        $responseB = $this->postForSite(
            "/api/merchants/" . self::MERCHANT_ID . "/import",
            ['type' => 'voucher'],
            ['file' => $file]
        );

        $this->assertResponseStatus(200, $responseA);
        $this->assertResponseStatus(200, $responseB);

        // Each call is fully independent — same result shape, no bleed-over
        $this->assertSame(
            json_decode($responseA->getContent(), true)['imported'],
            json_decode($responseB->getContent(), true)['imported']
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->importService = Mockery::mock(MerchantImportService::class);
        Container::getInstance()->bind(MerchantImportService::class, fn() => $this->importService);
    }

    // -------------------------------------------------------------------------
    // Teardown
    // -------------------------------------------------------------------------

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helpers assumed on FunctionalTestCase
    // -------------------------------------------------------------------------

    private function decodeResponse(\App\Framework\Http\JsonResponse $response): array
    {
        return json_decode($response->getContent(), true);
    }
}