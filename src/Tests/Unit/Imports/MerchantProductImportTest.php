<?php

namespace App\Tests\Unit\Imports;

use App\Framework\Database\Database;
use App\Imports\CsvParser;
use App\Imports\ImportOptions;
use App\Imports\MerchantProductImport;
use App\Models\Product;
use App\Models\ProductMerchant;
use App\Repositories\Product\MerchantRepository;
use App\Repositories\Product\ProductRepositoryInterface;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class MerchantProductImportTest extends FunctionalTestCase
{
    private const MERCHANT_ID = 10;
    private const SITE_ID = 1;

    private ProductRepositoryInterface $productRepo;
    private MerchantRepository $merchantProductRepo;
    private CsvParser $csvParser;
    private Database $databaseMock;

    public function test_valid_row_creates_product_and_merchant_link(): void
    {
        $this->stubTransaction();

        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 55;

        $this->csvParser->shouldReceive('parse')->once()->andReturn([$this->validRow()]);
        $this->merchantProductRepo->shouldReceive('findByNameAndMerchant')->with('Test Product', self::MERCHANT_ID)->andReturn(null);
        $this->productRepo->shouldReceive('create')->once()->andReturn($product);
        $this->merchantProductRepo->shouldReceive('create')
            ->once()
            ->withArgs(fn($data) => $data['product_id'] === 55 && $data['merchant_id'] === self::MERCHANT_ID);

        $result = $this->makeImporter()->import('/tmp/file.csv');

        $this->assertSame(1, $result->importedCount());
    }

    private function stubTransaction(): void
    {
        $this->databaseMock
            ->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn(callable $cb) => $cb());
    }

    private function validRow(array $overrides = []): array
    {
        return array_merge([
            '__line' => 2,
            'name' => 'Test Product',
            'price' => '29.99',
            'category_id' => '3',
        ], $overrides);
    }

    private function makeImporter(bool $updateExisting = false): MerchantProductImport
    {
        $merchantProductImport = new MerchantProductImport(
            $this->databaseMock,
            $this->csvParser,
            $this->productRepo,
            $this->merchantProductRepo
        );

        $merchantProductImport->setOptions(
            new ImportOptions(
                self::MERCHANT_ID,
                self::SITE_ID,
                $updateExisting
            )
        );

        return $merchantProductImport;
    }

    public function test_duplicate_name_for_same_merchant_updates_when_flag_on(): void
    {
        $this->stubTransaction();

        $existing = Mockery::mock(ProductMerchant::class)->makePartial();
        $existing->product_id = 55;

        $this->csvParser->shouldReceive('parse')->once()->andReturn([$this->validRow()]);
        $this->merchantProductRepo->shouldReceive('findByNameAndMerchant')->andReturn($existing);
        $this->productRepo->shouldReceive('update')->once()->with(55, Mockery::any());
        $this->merchantProductRepo->shouldReceive('create')->never();

        $result = $this->makeImporter(updateExisting: true)->import('/tmp/file.csv');

        $this->assertSame(1, $result->importedCount());
    }

    public function test_duplicate_name_is_skipped_when_flag_off(): void
    {
        $this->stubTransaction();

        $existing = Mockery::mock(ProductMerchant::class)->makePartial();

        $this->csvParser->shouldReceive('parse')->once()->andReturn([$this->validRow()]);
        $this->merchantProductRepo->shouldReceive('findByNameAndMerchant')->andReturn($existing);
        $this->productRepo->shouldReceive('create')->never();

        $result = $this->makeImporter(updateExisting: false)->import('/tmp/file.csv');

        $this->assertSame(0, $result->importedCount());
        $this->assertCount(1, $result->skippedRows());
    }

    public function test_missing_required_field_is_skipped(): void
    {
        $this->stubTransaction();
        $this->csvParser->shouldReceive('parse')->once()->andReturn([
            $this->validRow(['name' => '']),
        ]);

        $result = $this->makeImporter()->import('/tmp/file.csv');

        $this->assertSame(0, $result->importedCount());
        $this->assertStringContainsString('name', $result->skippedRows()[0]['reason']);
    }

    public function test_negative_price_is_skipped(): void
    {
        $this->stubTransaction();
        $this->csvParser->shouldReceive('parse')->once()->andReturn([
            $this->validRow(['price' => '-10']),
        ]);

        $result = $this->makeImporter()->import('/tmp/file.csv');

        $this->assertSame(0, $result->importedCount());
    }

    public function test_site_id_stamped_on_product(): void
    {
        $this->stubTransaction();

        $product = Mockery::mock(Product::class)->makePartial();
        $product->id = 55;

        $this->csvParser->shouldReceive('parse')->once()->andReturn([$this->validRow()]);
        $this->merchantProductRepo->shouldReceive('findByNameAndMerchant')->andReturn(null);
        $this->productRepo->shouldReceive('create')
            ->once()
            ->withArgs(fn($data) => $data['site_id'] === self::SITE_ID)
            ->andReturn($product);
        $this->merchantProductRepo->shouldReceive('create')->once();

        $result = $this->makeImporter()->import('/tmp/file.csv');

        $this->assertSame(1, $result->importedCount());
    }

    public function test_malformed_row_is_skipped(): void
    {
        $this->stubTransaction();
        $this->csvParser->shouldReceive('parse')->once()->andReturn([
            ['__line' => 2, '__malformed' => true],
        ]);

        $result = $this->makeImporter()->import('/tmp/file.csv');

        $this->assertSame(0, $result->importedCount());
        $this->assertCount(1, $result->skippedRows());
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->productRepo = Mockery::mock(ProductRepositoryInterface::class);
        $this->merchantProductRepo = Mockery::mock(MerchantRepository::class);
        $this->csvParser = Mockery::mock(CsvParser::class);
        $this->databaseMock = Mockery::mock(Database::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}