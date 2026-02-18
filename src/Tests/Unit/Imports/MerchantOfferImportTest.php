<?php

namespace App\Tests\Unit\Imports;

use App\Framework\Database\Database;
use App\Imports\CsvParser;
use App\Imports\ImportOptions;
use App\Imports\MerchantOfferImport;
use App\Models\ProductOffer;
use App\Models\RewardDefinition;
use App\Repositories\Offers\ProductOfferRepository;
use App\Repositories\Product\MerchantRepository;
use App\Repositories\Rewards\RewardDefinitionRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class MerchantOfferImportTest extends FunctionalTestCase
{
    private const MERCHANT_ID = 10;
    private const SITE_ID = 1;

    private ProductOfferRepository $offerRepo;
    private MerchantRepository $merchantProductRepo;
    private RewardDefinitionRepository $rewardRepo;
    private CsvParser $csvParser;
    private Database $databaseMock;

    public function test_valid_row_imports_for_merchant(): void
    {
        $this->stubTransaction();
        $this->csvParser->shouldReceive('parse')->once()->andReturn([$this->validRow()]);
        $this->merchantProductRepo->shouldReceive('existsForMerchant')->with(42, self::MERCHANT_ID)->andReturn(true);
        $this->offerRepo->shouldReceive('findByProductAndMerchant')->with(42, self::MERCHANT_ID)->andReturn(null);
        $this->offerRepo->shouldReceive('create')->once()->andReturn(Mockery::mock(ProductOffer::class));

        $result = $this->makeImporter()->import('/tmp/file.csv');

        $this->assertSame(1, $result->importedCount());
        $this->assertCount(0, $result->skippedRows());
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
            'product_id' => '42',
            'sale_price' => '19.99',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
        ], $overrides);
    }

    private function makeImporter(bool $updateExisting = false): MerchantOfferImport
    {
        $merchantOfferImport = new MerchantOfferImport(
            $this->databaseMock,
            $this->csvParser,
            $this->offerRepo,
            $this->merchantProductRepo,
            $this->rewardRepo
        );

        $merchantOfferImport->setOptions(
            new ImportOptions(
                self::MERCHANT_ID,
                self::SITE_ID,
                $updateExisting
            )
        );

        return $merchantOfferImport;
    }

    public function test_product_not_in_merchant_catalog_is_skipped(): void
    {
        $this->stubTransaction();
        $this->csvParser->shouldReceive('parse')->once()->andReturn([$this->validRow()]);
        $this->merchantProductRepo->shouldReceive('existsForMerchant')->with(42, self::MERCHANT_ID)->andReturn(false);

        $result = $this->makeImporter()->import('/tmp/file.csv');

        $this->assertSame(0, $result->importedCount());
        $this->assertStringContainsString('catalog', $result->skippedRows()[0]['reason']);
    }

    public function test_invalid_date_row_is_skipped(): void
    {
        $this->stubTransaction();
        $this->csvParser->shouldReceive('parse')->once()->andReturn([
            $this->validRow(['end_date' => 'not-a-date']),
        ]);

        $result = $this->makeImporter()->import('/tmp/file.csv');

        $this->assertSame(0, $result->importedCount());
        $this->assertStringContainsString('end_date', $result->skippedRows()[0]['reason']);
    }

    public function test_duplicate_offer_for_same_merchant_updates_when_flag_on(): void
    {
        $this->stubTransaction();
        $existing = Mockery::mock(ProductOffer::class)->makePartial();
        $existing->id = 77;

        $this->csvParser->shouldReceive('parse')->once()->andReturn([$this->validRow()]);
        $this->merchantProductRepo->shouldReceive('existsForMerchant')->andReturn(true);
        $this->offerRepo->shouldReceive('findByProductAndMerchant')->andReturn($existing);
        $this->offerRepo->shouldReceive('update')->once()->with(77, Mockery::any());

        $result = $this->makeImporter(updateExisting: true)->import('/tmp/file.csv');

        $this->assertSame(1, $result->importedCount());
    }

    public function test_duplicate_offer_for_same_merchant_is_skipped_when_flag_off(): void
    {
        $this->stubTransaction();
        $existing = Mockery::mock(ProductOffer::class)->makePartial();

        $this->csvParser->shouldReceive('parse')->once()->andReturn([$this->validRow()]);
        $this->merchantProductRepo->shouldReceive('existsForMerchant')->andReturn(true);
        $this->offerRepo->shouldReceive('findByProductAndMerchant')->andReturn($existing);
        $this->offerRepo->shouldReceive('update')->never();
        $this->offerRepo->shouldReceive('create')->never();

        $result = $this->makeImporter(updateExisting: false)->import('/tmp/file.csv');

        $this->assertSame(0, $result->importedCount());
        $this->assertCount(1, $result->skippedRows());
    }

    public function test_reward_context_linked_to_site_only(): void
    {
        $this->stubTransaction();

        $definition = Mockery::mock(RewardDefinition::class)->makePartial();
        $definition->site_id = self::SITE_ID;

        $this->csvParser->shouldReceive('parse')->once()->andReturn([
            $this->validRow(['reward_id' => '7']),
        ]);
        $this->merchantProductRepo->shouldReceive('existsForMerchant')->andReturn(true);
        $this->rewardRepo->shouldReceive('findRewardDefinitionById')->with(7)->andReturn($definition);
        $this->offerRepo->shouldReceive('findByProductAndMerchant')->andReturn(null);
        $this->offerRepo->shouldReceive('create')
            ->once()
            ->withArgs(fn($data) => $data['reward_definition_id'] === 7);

        $result = $this->makeImporter()->import('/tmp/file.csv');

        $this->assertSame(1, $result->importedCount());
    }

    public function test_reward_id_belonging_to_different_site_is_skipped(): void
    {
        $this->stubTransaction();

        $definition = Mockery::mock(RewardDefinition::class)->makePartial();
        $definition->site_id = 999;

        $this->csvParser->shouldReceive('parse')->once()->andReturn([
            $this->validRow(['reward_id' => '7']),
        ]);
        $this->merchantProductRepo->shouldReceive('existsForMerchant')->andReturn(true);
        $this->rewardRepo->shouldReceive('findRewardDefinitionById')->with(7)->andReturn($definition);

        $result = $this->makeImporter()->import('/tmp/file.csv');

        $this->assertSame(0, $result->importedCount());
    }

    public function test_merchant_id_is_stamped_on_created_offer(): void
    {
        $this->stubTransaction();
        $this->csvParser->shouldReceive('parse')->once()->andReturn([$this->validRow()]);
        $this->merchantProductRepo->shouldReceive('existsForMerchant')->andReturn(true);
        $this->offerRepo->shouldReceive('findByProductAndMerchant')->andReturn(null);
        $this->offerRepo->shouldReceive('create')
            ->once()
            ->withArgs(fn($data) => $data['merchant_id'] === self::MERCHANT_ID);

        $result = $this->makeImporter()->import('/tmp/file.csv');

        $this->assertSame(1, $result->importedCount());
    }

    public function test_negative_sale_price_is_skipped(): void
    {
        $this->stubTransaction();
        $this->csvParser->shouldReceive('parse')->once()->andReturn([
            $this->validRow(['sale_price' => '-5.00']),
        ]);

        $result = $this->makeImporter()->import('/tmp/file.csv');

        $this->assertSame(0, $result->importedCount());
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->offerRepo = Mockery::mock(ProductOfferRepository::class);
        $this->merchantProductRepo = Mockery::mock(MerchantRepository::class);
        $this->rewardRepo = Mockery::mock(RewardDefinitionRepository::class);
        $this->csvParser = Mockery::mock(CsvParser::class);
        $this->databaseMock = Mockery::mock(Database::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}