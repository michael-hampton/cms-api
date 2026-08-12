<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Framework\Support\Collection;
use App\Models\PaymentTerms;
use App\Repositories\OpenCollab\EarningsLedgerRepository;
use App\Repositories\OpenCollab\PaymentTermsRepository;
use App\Services\OpenCollab\PaymentTermsService;
use App\Tests\Unit\UnitTestCase;
use Mockery;
use Mockery\MockInterface;

class PaymentTermsServiceTest extends UnitTestCase
{
    private PaymentTermsService $service;
    private MockInterface $paymentTermsRepository;
    private MockInterface $ledgerRepository;

    // ── forSite() ─────────────────────────────────────────────────────────────

    public function test_for_site_returns_configured_terms_when_they_exist(): void
    {
        $terms = $this->makeTerms(['payout_delay_days' => 14, 'minimum_payout_amount' => 10000]);

        $this->paymentTermsRepository->shouldReceive('forSite')->with(1)->andReturn($terms);

        $result = $this->service->forSite(1);

        $this->assertEquals(14, $result->payout_delay_days);
        $this->assertEquals(10000, $result->minimum_payout_amount);
    }

    private function makeTerms(array $attributes = []): PaymentTerms
    {
        $defaults = [
            'id' => 1,
            'site_id' => 1,
            'payout_delay_days' => 7,
            'minimum_payout_amount' => 5000,
        ];
        $terms = new PaymentTerms(array_merge($defaults, $attributes));
        $terms->exists = true;
        return $terms;
    }

    public function test_for_site_returns_defaults_when_no_terms_configured(): void
    {
        $this->paymentTermsRepository->shouldReceive('forSite')->andReturn(null);

        $result = $this->service->forSite(1);

        // Default values from the service constants
        $this->assertEquals(7, $result->payout_delay_days);
        $this->assertEquals(5000, $result->minimum_payout_amount);
    }

    // ── save() ────────────────────────────────────────────────────────────────

    public function test_for_site_default_model_is_not_persisted(): void
    {
        $this->paymentTermsRepository->shouldReceive('forSite')->andReturn(null);
        $this->paymentTermsRepository->shouldNotReceive('upsertForSite');

        $result = $this->service->forSite(1);

        $this->assertInstanceOf(PaymentTerms::class, $result);
        $this->assertSame(1, $result->site_id);
        $this->assertSame(7, $result->payout_delay_days);
        $this->assertSame(5000, $result->minimum_payout_amount);
    }

    public function test_save_delegates_to_repository_and_returns_terms(): void
    {
        $terms = $this->makeTerms(['payout_delay_days' => 30, 'minimum_payout_amount' => 2000]);

        $this->paymentTermsRepository->shouldReceive('upsertForSite')
            ->once()
            ->with(1, 30, 2000)
            ->andReturn($terms);

        $result = $this->service->save(siteId: 1, payoutDelayDays: 30, minimumPayoutAmount: 2000);

        $this->assertEquals(30, $result->payout_delay_days);
        $this->assertEquals(2000, $result->minimum_payout_amount);
    }

    public function test_save_throws_when_delay_days_is_negative(): void
    {
        $this->paymentTermsRepository->shouldNotReceive('upsertForSite');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/negative/i');

        $this->service->save(1, payoutDelayDays: -1, minimumPayoutAmount: 5000);
    }

    public function test_save_allows_zero_delay_days(): void
    {
        $terms = $this->makeTerms(['payout_delay_days' => 0, 'minimum_payout_amount' => 5000]);

        $this->paymentTermsRepository->shouldReceive('upsertForSite')
            ->once()
            ->andReturn($terms);

        $result = $this->service->save(1, payoutDelayDays: 0, minimumPayoutAmount: 5000);

        $this->assertEquals(0, $result->payout_delay_days);
    }

    // ── eligibleLedgerEntries() ───────────────────────────────────────────────

    public function test_save_throws_when_minimum_amount_is_negative(): void
    {
        $this->paymentTermsRepository->shouldNotReceive('upsertForSite');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/negative/i');

        $this->service->save(1, payoutDelayDays: 7, minimumPayoutAmount: -1);
    }

    public function test_eligible_ledger_entries_uses_terms_cutoff(): void
    {
        $terms = $this->makeTerms(['payout_delay_days' => 7]);

        $this->paymentTermsRepository->shouldReceive('forSite')->andReturn($terms);
        $this->ledgerRepository->shouldReceive('eligibleForPayout')
            ->once()
            ->withArgs(function ($userId, \DateTime $cutoff): bool {
                // Cutoff should be approximately 7 days ago
                $expected = (new \DateTime())->modify('-7 days');
                return $userId === 7
                    && abs($cutoff->getTimestamp() - $expected->getTimestamp()) < 60;
            })
            ->andReturn(new Collection([]));

        $this->service->eligibleLedgerEntries(userId: 7, siteId: 1);
        $this->assertTrue(true);
    }

    // ── meetsMinimumThreshold() ───────────────────────────────────────────────

    public function test_eligible_ledger_entries_falls_back_to_defaults_when_no_terms(): void
    {
        $this->paymentTermsRepository->shouldReceive('forSite')->andReturn(null);
        $this->ledgerRepository->shouldReceive('eligibleForPayout')
            ->once()
            ->withArgs(function ($userId, \DateTime $cutoff): bool {
                // Default delay is 7 days
                $expected = (new \DateTime())->modify('-7 days');
                return abs($cutoff->getTimestamp() - $expected->getTimestamp()) < 60;
            })
            ->andReturn(new Collection([]));

        $this->service->eligibleLedgerEntries(7, 1);
        $this->assertTrue(true);
    }

    public function test_meets_minimum_threshold_returns_true_when_balance_is_above_minimum(): void
    {
        $terms = $this->makeTerms(['minimum_payout_amount' => 5000]);

        $this->paymentTermsRepository->shouldReceive('forSite')->andReturn($terms);

        $this->assertTrue($this->service->meetsMinimumThreshold(7, 1, 6000));
    }

    public function test_meets_minimum_threshold_returns_true_when_balance_equals_minimum(): void
    {
        $terms = $this->makeTerms(['minimum_payout_amount' => 5000]);

        $this->paymentTermsRepository->shouldReceive('forSite')->andReturn($terms);

        $this->assertTrue($this->service->meetsMinimumThreshold(7, 1, 5000));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function test_meets_minimum_threshold_returns_false_when_balance_is_below_minimum(): void
    {
        $terms = $this->makeTerms(['minimum_payout_amount' => 5000]);

        $this->paymentTermsRepository->shouldReceive('forSite')->andReturn($terms);

        $this->assertFalse($this->service->meetsMinimumThreshold(7, 1, 4999));
    }

    protected function setUp(): void
    {

        $this->paymentTermsRepository = Mockery::mock(PaymentTermsRepository::class);
        $this->ledgerRepository = Mockery::mock(EarningsLedgerRepository::class);

        $this->service = new PaymentTermsService(
            $this->paymentTermsRepository,
            $this->ledgerRepository,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}