<?php

namespace App\Tests\Unit\Imports;

use App\Framework\Database\Database;
use App\Imports\CsvParser;
use App\Imports\ImportOptions;
use App\Imports\MerchantVoucherImport;
use App\Models\RewardDefinition;
use App\Models\Voucher;
use App\Repositories\Rewards\RewardDefinitionRepository;
use App\Repositories\Vouchers\VoucherRepository;
use App\Tests\Unit\UnitTestCase;
use Mockery;

class MerchantVoucherImportTest extends UnitTestCase
{
    private const MERCHANT_ID = 10;
    private const SITE_ID = 1;

    private VoucherRepository $voucherRepo;
    private RewardDefinitionRepository $rewardRepo;
    private CsvParser $csvParser;
    private Database $databaseMock;

    public function test_valid_row_imports_for_merchant(): void
    {
        $this->stubTransaction();
        $this->csvParser->shouldReceive('parse')->once()->andReturn([$this->validRow()]);
        $this->voucherRepo->shouldReceive('findByCodeAndMerchant')->with('SAVE10', self::MERCHANT_ID)->andReturn(null);
        $this->voucherRepo->shouldReceive('create')->once()->andReturn(Mockery::mock(Voucher::class));

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
            'code' => 'SAVE10',
            'type' => 'percentage',
            'value' => '10',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'usage_limit' => '100',
        ], $overrides);
    }

    private function makeImporter(bool $updateExisting = false): MerchantVoucherImport
    {
        $merchantVoucherImport = new MerchantVoucherImport(
            $this->databaseMock,
            $this->csvParser,
            $this->voucherRepo,
            $this->rewardRepo
        );

        $merchantVoucherImport->setOptions(
            new ImportOptions(
                self::MERCHANT_ID,
                self::SITE_ID,
                $updateExisting
            )
        );

        return $merchantVoucherImport;
    }

    public function test_invalid_date_row_is_skipped(): void
    {
        $this->stubTransaction();
        $this->csvParser->shouldReceive('parse')->once()->andReturn([
            $this->validRow(['start_date' => 'not-a-date']),
        ]);

        $result = $this->makeImporter()->import('/tmp/file.csv');

        $this->assertSame(0, $result->importedCount());
        $this->assertCount(1, $result->skippedRows());
        $this->assertStringContainsString('start_date', $result->skippedRows()[0]['reason']);
    }

    public function test_start_date_after_end_date_is_skipped(): void
    {
        $this->stubTransaction();
        $this->csvParser->shouldReceive('parse')->once()->andReturn([
            $this->validRow(['start_date' => '2025-12-31', 'end_date' => '2025-01-01']),
        ]);

        $result = $this->makeImporter()->import('/tmp/file.csv');

        $this->assertSame(0, $result->importedCount());
        $this->assertStringContainsString('start_date', $result->skippedRows()[0]['reason']);
    }

    public function test_duplicate_code_for_same_merchant_updates_row_when_flag_on(): void
    {
        $this->stubTransaction();
        $existing = Mockery::mock(Voucher::class)->makePartial();
        $existing->id = 99;
        $existing->usage_count = 5;

        $this->csvParser->shouldReceive('parse')->once()->andReturn([$this->validRow()]);
        $this->voucherRepo->shouldReceive('findByCodeAndMerchant')->with('SAVE10', self::MERCHANT_ID)->andReturn($existing);
        $this->voucherRepo->shouldReceive('update')->once()->with(99, Mockery::subset(['code' => 'SAVE10']));

        $result = $this->makeImporter(updateExisting: true)->import('/tmp/file.csv');

        $this->assertSame(1, $result->importedCount());
    }

    public function test_duplicate_code_for_same_merchant_is_skipped_when_flag_off(): void
    {
        $this->stubTransaction();
        $existing = Mockery::mock(Voucher::class)->makePartial();
        $existing->usage_count = 5;

        $this->csvParser->shouldReceive('parse')->once()->andReturn([$this->validRow()]);
        $this->voucherRepo->shouldReceive('findByCodeAndMerchant')->with('SAVE10', self::MERCHANT_ID)->andReturn($existing);
        $this->voucherRepo->shouldReceive('update')->never();
        $this->voucherRepo->shouldReceive('create')->never();

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
            $this->validRow(['reward_id' => '5']),
        ]);
        $this->rewardRepo->shouldReceive('findRewardDefinitionById')->with(5)->andReturn($definition);
        $this->voucherRepo->shouldReceive('findByCodeAndMerchant')->andReturn(null);
        $this->voucherRepo->shouldReceive('create')
            ->once()
            ->withArgs(fn($data) => $data['reward_definition_id'] === 5);

        $result = $this->makeImporter()->import('/tmp/file.csv');

        $this->assertSame(1, $result->importedCount());
    }

    public function test_reward_id_from_different_site_is_skipped(): void
    {
        $this->stubTransaction();

        $definition = Mockery::mock(RewardDefinition::class)->makePartial();
        $definition->site_id = 999; // wrong site

        $this->csvParser->shouldReceive('parse')->once()->andReturn([
            $this->validRow(['reward_id' => '5']),
        ]);
        $this->rewardRepo->shouldReceive('findRewardDefinitionById')->with(5)->andReturn($definition);

        $result = $this->makeImporter()->import('/tmp/file.csv');

        $this->assertSame(0, $result->importedCount());
        $this->assertStringContainsString('site', $result->skippedRows()[0]['reason']);
    }

    public function test_missing_required_field_is_skipped(): void
    {
        $this->stubTransaction();
        $this->csvParser->shouldReceive('parse')->once()->andReturn([
            $this->validRow(['code' => '']),
        ]);

        $result = $this->makeImporter()->import('/tmp/file.csv');

        $this->assertSame(0, $result->importedCount());
        $this->assertStringContainsString('code', $result->skippedRows()[0]['reason']);
    }

    public function test_negative_value_is_skipped(): void
    {
        $this->stubTransaction();
        $this->csvParser->shouldReceive('parse')->once()->andReturn([
            $this->validRow(['value' => '-5']),
        ]);

        $result = $this->makeImporter()->import('/tmp/file.csv');

        $this->assertSame(0, $result->importedCount());
    }

    public function test_invalid_type_is_skipped(): void
    {
        $this->stubTransaction();
        $this->csvParser->shouldReceive('parse')->once()->andReturn([
            $this->validRow(['type' => 'bogus']),
        ]);

        $result = $this->makeImporter()->import('/tmp/file.csv');

        $this->assertSame(0, $result->importedCount());
        $this->assertStringContainsString('type', $result->skippedRows()[0]['reason']);
    }

    public function test_site_id_is_stamped_on_created_voucher(): void
    {
        $this->stubTransaction();
        $this->csvParser->shouldReceive('parse')->once()->andReturn([$this->validRow()]);
        $this->voucherRepo->shouldReceive('findByCodeAndMerchant')->andReturn(null);
        $this->voucherRepo->shouldReceive('create')
            ->once()
            ->withArgs(fn($data) => $data['site_id'] === self::SITE_ID
                && $data['merchant_id'] === self::MERCHANT_ID);

        $result = $this->makeImporter()->import('/tmp/file.csv');

        $this->assertSame(1, $result->importedCount());
    }

    protected function setUp(): void
    {

        $this->voucherRepo = Mockery::mock(VoucherRepository::class);
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