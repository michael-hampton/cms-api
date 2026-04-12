<?php

namespace App\Tests\Unit\Repositories\OpenCollab;

use App\Models\PaymentTerms;
use App\Repositories\OpenCollab\PaymentTermsRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class PaymentTermsRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private PaymentTermsRepository $repository;

    // ── forSite() ─────────────────────────────────────────────────────────────

    public function test_for_site_returns_terms_when_they_exist(): void
    {
        PaymentTerms::create([
            'site_id' => $this->siteId,
            'payout_delay_days' => 14,
            'minimum_payout_amount' => 10000,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $terms = $this->repository->forSite($this->siteId);

        $this->assertNotNull($terms);
        $this->assertEquals(14, $terms->payout_delay_days);
        $this->assertEquals(10000, $terms->minimum_payout_amount);
    }

    public function test_for_site_returns_null_when_no_terms_configured(): void
    {
        $this->assertNull($this->repository->forSite(9999));
    }

    public function test_for_site_is_scoped_to_site(): void
    {
        $otherSite = $this->createSite();

        PaymentTerms::create([
            'site_id' => $otherSite->id,
            'payout_delay_days' => 30,
            'minimum_payout_amount' => 5000,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->assertNull($this->repository->forSite($this->siteId));
    }

    // ── upsertForSite() ───────────────────────────────────────────────────────

    public function test_upsert_creates_terms_when_none_exist(): void
    {
        $terms = $this->repository->upsertForSite($this->siteId, 7, 5000);

        $this->assertInstanceOf(PaymentTerms::class, $terms);
        $this->assertEquals($this->siteId, $terms->site_id);
        $this->assertEquals(7, $terms->payout_delay_days);
        $this->assertEquals(5000, $terms->minimum_payout_amount);

        $this->assertDatabaseHas('oc_payment_terms', [
            'site_id' => $this->siteId,
            'payout_delay_days' => 7,
            'minimum_payout_amount' => 5000,
        ]);
    }

    public function test_upsert_updates_existing_terms(): void
    {
        PaymentTerms::create([
            'site_id' => $this->siteId,
            'payout_delay_days' => 7,
            'minimum_payout_amount' => 5000,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $updated = $this->repository->upsertForSite($this->siteId, 30, 10000);

        $this->assertEquals(30, $updated->payout_delay_days);
        $this->assertEquals(10000, $updated->minimum_payout_amount);

        // Must not create a second row
        $this->assertDatabaseCount('oc_payment_terms', 1);
    }

    public function test_upsert_only_creates_one_row_per_site(): void
    {
        $this->repository->upsertForSite($this->siteId, 7, 5000);
        $this->repository->upsertForSite($this->siteId, 14, 8000);

        $this->assertDatabaseCount('oc_payment_terms', 1);
    }

    public function test_upsert_for_different_sites_creates_separate_rows(): void
    {
        $otherSite = $this->createSite();

        $this->repository->upsertForSite($this->siteId, 7, 5000);
        $this->repository->upsertForSite($otherSite->id, 14, 8000);

        $this->assertDatabaseCount('oc_payment_terms', 2);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new PaymentTermsRepository();
    }
}