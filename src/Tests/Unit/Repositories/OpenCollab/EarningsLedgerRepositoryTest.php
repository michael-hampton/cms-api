<?php

namespace App\Tests\Unit\Repositories\OpenCollab;

use App\Enums\OpenCollab\AccrualStatus;
use App\Enums\OpenCollab\LedgerEntryType;
use App\Exceptions\OpenCollab\InvalidAccrualTransitionException;
use App\Models\EarningsLedger;
use App\Models\Model;
use App\Repositories\OpenCollab\EarningsLedgerRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class EarningsLedgerRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private EarningsLedgerRepository $repository;

    // ── recordSale() ──────────────────────────────────────────────────────────

    public function test_record_sale_creates_positive_ledger_entry(): void
    {
        $user = $this->createUser();
        $page = $this->createPage();

        $entry = $this->repository->recordSale($user->id, $page->id, 500, 'gbp', 'pi_abc');

        $this->assertInstanceOf(EarningsLedger::class, $entry);
        $this->assertEquals(LedgerEntryType::Sale->value, $entry->type);
        $this->assertEquals(500, $entry->amount);
        $this->assertEquals('pi_abc', $entry->reference_id);
    }

    // ── recordRefund() ────────────────────────────────────────────────────────

    public function test_record_refund_creates_negative_ledger_entry(): void
    {
        $user = $this->createUser();
        $page = $this->createPage();

        $entry = $this->repository->recordRefund($user->id, $page->id, 500, 'gbp', 'pi_abc');

        $this->assertEquals(LedgerEntryType::Refund->value, $entry->type);
        $this->assertEquals(-500, $entry->amount);
    }

    public function test_record_refund_stores_negative_even_if_positive_amount_passed(): void
    {
        $user = $this->createUser();
        $page = $this->createPage();

        $entry = $this->repository->recordRefund($user->id, $page->id, -500, 'gbp', 'pi_abc');

        $this->assertEquals(-500, $entry->amount);
    }

    // ── balanceForContributor() ───────────────────────────────────────────────

    public function test_balance_for_contributor_sums_all_entries(): void
    {
        $user = $this->createUser();
        $page = $this->createPage();

        EarningsLedger::create(['user_id' => $user->id, 'article_id' => $page->id, 'type' => LedgerEntryType::Sale->value, 'amount' => 600, 'currency' => 'gbp', 'reference_id' => 'a']);
        EarningsLedger::create(['user_id' => $user->id, 'article_id' => $page->id, 'type' => LedgerEntryType::Refund->value, 'amount' => -100, 'currency' => 'gbp', 'reference_id' => 'b']);

        $balance = $this->repository->balanceForContributor($user->id);

        $this->assertEquals(500, $balance);
    }

    public function test_balance_returns_zero_when_no_entries(): void
    {
        $this->assertEquals(0, $this->repository->balanceForContributor(9999));
    }

    public function test_balance_does_not_include_other_users(): void
    {
        $user1 = $this->createUser();
        $user2 = $this->createUser();
        $page = $this->createPage();

        EarningsLedger::create(['user_id' => $user1->id, 'article_id' => $page->id, 'type' => LedgerEntryType::Sale->value, 'amount' => 1000, 'currency' => 'gbp', 'reference_id' => 'x']);

        $this->assertEquals(0, $this->repository->balanceForContributor($user2->id));
    }

    // ── eligibleForPayout() ───────────────────────────────────────────────────

    public function test_eligible_for_payout_returns_entries_before_cutoff(): void
    {
        $user = $this->createUser();
        $page = $this->createPage();
        $cutoff = new \DateTime('2024-06-01');

        $old = EarningsLedger::create([
            'user_id' => $user->id,
            'article_id' => $page->id,
            'type' => LedgerEntryType::Sale->value,
            'amount' => 500,
            'currency' => 'gbp',
            'reference_id' => 'old',
            'earned_at' => '2024-01-01 00:00:00',
        ]);

        $new = EarningsLedger::create([
            'user_id' => $user->id,
            'article_id' => $page->id,
            'type' => LedgerEntryType::Sale->value,
            'amount' => 800,
            'currency' => 'gbp',
            'reference_id' => 'new',
            'earned_at' => '2024-12-01 00:00:00', // after cutoff
        ]);

        $results = $this->repository->eligibleForPayout($user->id, $cutoff);

        $this->assertCount(1, $results);
        $this->assertEquals($old->id, $results->first()->id);
    }

    public function test_eligible_for_payout_excludes_already_paid_entries(): void
    {
        $user = $this->createUser();
        $page = $this->createPage();
        $cutoff = new \DateTime('2025-01-01');

        EarningsLedger::create([
            'user_id' => $user->id,
            'article_id' => $page->id,
            'type' => LedgerEntryType::Sale->value,
            'amount' => 500,
            'currency' => 'gbp',
            'reference_id' => 'paid',
            'paid_at' => '2024-03-01 00:00:00', // already paid
            'created_at' => '2024-01-01 00:00:00',
        ]);

        $results = $this->repository->eligibleForPayout($user->id, $cutoff);

        $this->assertCount(0, $results);
    }

    public function test_eligible_for_payout_returns_entries_ordered_oldest_first(): void
    {
        $user = $this->createUser();
        $page = $this->createPage();
        $cutoff = new \DateTime('2025-01-01');

        $older = EarningsLedger::create([
            'user_id' => $user->id, 'article_id' => $page->id,
            'type' => LedgerEntryType::Sale->value, 'amount' => 300,
            'currency' => 'gbp', 'reference_id' => 'r1', 'earned_at' => '2024-01-01',
        ]);
        $newer = EarningsLedger::create([
            'user_id' => $user->id, 'article_id' => $page->id,
            'type' => LedgerEntryType::Sale->value, 'amount' => 400,
            'currency' => 'gbp', 'reference_id' => 'r2', 'earned_at' => '2024-06-01',
        ]);

        $results = $this->repository->eligibleForPayout($user->id, $cutoff);

        $this->assertEquals($older->id, $results->first()->id);
        $this->assertEquals($newer->id, $results->last()->id);
    }

    // ── eligibleBalanceForContributor() ───────────────────────────────────────

    public function test_eligible_balance_sums_eligible_entries_only(): void
    {
        $user = $this->createUser();
        $page = $this->createPage();
        $cutoff = new \DateTime('2025-01-01');

        EarningsLedger::create(['user_id' => $user->id, 'article_id' => $page->id, 'type' => LedgerEntryType::Sale->value, 'amount' => 600, 'currency' => 'gbp', 'reference_id' => 'a', 'earned_at' => '2024-01-01']);
        EarningsLedger::create(['user_id' => $user->id, 'article_id' => $page->id, 'type' => LedgerEntryType::Sale->value, 'amount' => 400, 'currency' => 'gbp', 'reference_id' => 'b', 'earned_at' => '2024-06-01']);
        // Already paid — must not count
        EarningsLedger::create(['user_id' => $user->id, 'article_id' => $page->id, 'type' => LedgerEntryType::Sale->value, 'amount' => 999, 'currency' => 'gbp', 'reference_id' => 'c', 'earned_at' => '2024-01-01', 'paid_at' => '2024-02-01']);

        $balance = $this->repository->eligibleBalanceForContributor($user->id, $cutoff);

        $this->assertEquals(1000, $balance);
    }

    public function test_eligible_balance_returns_zero_when_no_eligible_entries(): void
    {
        $user = $this->createUser();
        $cutoff = new \DateTime('2020-01-01'); // all entries are after this

        $this->assertEquals(0, $this->repository->eligibleBalanceForContributor($user->id, $cutoff));
    }

    // ── eligibleGroupedBySiteAndUser() ────────────────────────────────────────

    public function test_eligible_grouped_returns_entries_grouped_by_user(): void
    {
        $user1 = $this->createUser();
        $user2 = $this->createUser();
        $page1 = $this->createPage(['site_id' => $this->siteId, 'contributor_id' => $user1->id]);
        $page2 = $this->createPage(['site_id' => $this->siteId, 'contributor_id' => $user2->id]);
        $cutoff = new \DateTime('2025-01-01');

        EarningsLedger::create(['user_id' => $user1->id, 'article_id' => $page1->id, 'type' => LedgerEntryType::Sale->value, 'amount' => 500, 'currency' => 'GBP', 'reference_id' => 'u1', 'earned_at' => '2024-01-01']);
        EarningsLedger::create(['user_id' => $user2->id, 'article_id' => $page2->id, 'type' => LedgerEntryType::Sale->value, 'amount' => 800, 'currency' => 'GBP', 'reference_id' => 'u2', 'earned_at' => '2024-01-01']);

        $grouped = $this->repository->eligibleGroupedBySiteAndUser($this->siteId, $cutoff);

        $this->assertArrayHasKey($user1->id, $grouped);
        $this->assertArrayHasKey($user2->id, $grouped);
        $this->assertEquals(500, $grouped[$user1->id][0]['amount']);
        $this->assertEquals(800, $grouped[$user2->id][0]['amount']);
    }

    public function test_eligible_grouped_excludes_paid_entries(): void
    {
        $user = $this->createUser();
        $page = $this->createPage(['site_id' => $this->siteId]);
        $cutoff = new \DateTime('2025-01-01');

        EarningsLedger::create([
            'user_id' => $user->id, 'article_id' => $page->id,
            'type' => LedgerEntryType::Sale->value, 'amount' => 500,
            'currency' => 'GBP', 'reference_id' => 'paid', 'paid_at' => '2024-02-01',
            'created_at' => '2024-01-01',
        ]);

        $grouped = $this->repository->eligibleGroupedBySiteAndUser($this->siteId, $cutoff);

        $this->assertArrayNotHasKey($user->id, $grouped);
    }

    public function test_eligible_grouped_excludes_entries_after_cutoff(): void
    {
        $user = $this->createUser();
        $page = $this->createPage(['site_id' => $this->siteId]);
        $cutoff = new \DateTime('2024-01-01');

        EarningsLedger::create([
            'user_id' => $user->id, 'article_id' => $page->id,
            'type' => LedgerEntryType::Sale->value, 'amount' => 500,
            'currency' => 'GBP', 'reference_id' => 'future',
            'created_at' => '2024-06-01', // after cutoff
        ]);

        $grouped = $this->repository->eligibleGroupedBySiteAndUser($this->siteId, $cutoff);

        $this->assertArrayNotHasKey($user->id, $grouped);
    }

    public function test_eligible_grouped_is_scoped_to_site(): void
    {
        $user = $this->createUser();
        $otherSite = $this->createSite();
        $page = $this->createPage(['site_id' => $otherSite->id]);
        $cutoff = new \DateTime('2025-01-01');

        EarningsLedger::create([
            'user_id' => $user->id, 'article_id' => $page->id,
            'type' => LedgerEntryType::Sale->value, 'amount' => 500,
            'currency' => 'GBP', 'reference_id' => 'other-site',
            'created_at' => '2024-01-01',
        ]);

        $grouped = $this->repository->eligibleGroupedBySiteAndUser($this->siteId, $cutoff);

        $this->assertArrayNotHasKey($user->id, $grouped);
    }

    public function test_eligible_grouped_returns_empty_array_when_no_eligible_entries(): void
    {
        $cutoff = new \DateTime('2020-01-01');

        $grouped = $this->repository->eligibleGroupedBySiteAndUser($this->siteId, $cutoff);

        $this->assertEmpty($grouped);
    }

    public function test_transition_updates_accrual_status(): void
    {
        $entry = $this->createLedgerEntry([
            'accrual_status' => AccrualStatus::Estimated->value,
        ]);

        $updated = $this->repository->transition(
            $entry->id,
            AccrualStatus::Confirmed,
        );

        $this->assertInstanceOf(EarningsLedger::class, $updated);
        $this->assertEquals(AccrualStatus::Confirmed->value, $updated->accrual_status);
    }

    public function test_transition_sets_timestamp_column_when_provided(): void
    {
        $entry = $this->createLedgerEntry([
            'accrual_status' => AccrualStatus::Estimated->value,
            'confirmed_at' => null,
        ]);

        $updated = $this->repository->transition(
            $entry->id,
            AccrualStatus::Confirmed,
            'confirmed_at',
        );

        $this->assertEquals(AccrualStatus::Confirmed->value, $updated->accrual_status);
        $this->assertNotNull($updated->confirmed_at);
    }

    public function test_transition_applies_extra_fields(): void
    {
        $actor = $this->createUser();

        $entry = $this->createLedgerEntry([
            'accrual_status' => AccrualStatus::Estimated->value,
            'confirmed_by' => null,
        ]);

        $updated = $this->repository->transition(
            $entry->id,
            AccrualStatus::Confirmed,
            'confirmed_at',
            ['confirmed_by' => $actor->id],
        );

        $this->assertEquals(AccrualStatus::Confirmed->value, $updated->accrual_status);
        $this->assertEquals($actor->id, $updated->confirmed_by);
        $this->assertNotNull($updated->confirmed_at);
    }

    public function test_transition_throws_exception_for_invalid_transition(): void
    {
        $entry = $this->createLedgerEntry([
            'accrual_status' => AccrualStatus::Estimated->value,
        ]);

        $this->expectException(InvalidAccrualTransitionException::class);

        $this->repository->transition(
            $entry->id,
            AccrualStatus::Withdrawn,
        );
    }

    public function test_transition_does_not_update_entry_when_transition_is_invalid(): void
    {
        $entry = $this->createLedgerEntry([
            'accrual_status' => AccrualStatus::Estimated->value,
            'withdrawn_at' => null,
        ]);

        try {
            $this->repository->transition(
                $entry->id,
                AccrualStatus::Withdrawn,
                'withdrawn_at',
            );
        } catch (InvalidAccrualTransitionException) {
            // Expected.
        }

        $fresh = EarningsLedger::find($entry->id);

        $this->assertEquals(AccrualStatus::Estimated->value, $fresh->accrual_status);
        $this->assertNull($fresh->withdrawn_at);
    }

    public function test_transition_throws_exception_when_entry_does_not_exist(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Earnings ledger entry [999999] not found.');

        $this->repository->transition(
            999999,
            AccrualStatus::Confirmed,
        );
    }

    public function test_confirm_moves_estimated_entry_to_confirmed(): void
    {
        $entry = $this->createLedgerEntry([
            'accrual_status' => AccrualStatus::Estimated->value,
            'confirmed_at' => null,
        ]);

        $updated = $this->repository->confirm($entry->id);

        $this->assertEquals(AccrualStatus::Confirmed->value, $updated->accrual_status);
        $this->assertNotNull($updated->confirmed_at);
    }

    public function test_confirm_stores_actor_when_provided(): void
    {
        $actor = $this->createUser();

        $entry = $this->createLedgerEntry([
            'accrual_status' => AccrualStatus::Estimated->value,
            'confirmed_by' => null,
        ]);

        $updated = $this->repository->confirm($entry->id, $actor->id);

        $this->assertEquals(AccrualStatus::Confirmed->value, $updated->accrual_status);
        $this->assertEquals($actor->id, $updated->confirmed_by);
        $this->assertNotNull($updated->confirmed_at);
    }

    public function test_confirm_throws_exception_when_entry_is_already_confirmed(): void
    {
        $entry = $this->createLedgerEntry([
            'accrual_status' => AccrualStatus::Confirmed->value,
        ]);

        $this->expectException(InvalidAccrualTransitionException::class);

        $this->repository->confirm($entry->id);
    }

    public function test_confirm_throws_exception_for_invalid_transition(): void
    {
        $entry = $this->createLedgerEntry([
            'accrual_status' => AccrualStatus::Withdrawn->value,
        ]);

        $this->expectException(InvalidAccrualTransitionException::class);

        $this->repository->confirm($entry->id);
    }

    public function test_settle_moves_confirmed_entry_to_settled(): void
    {
        $entry = $this->createLedgerEntry([
            'accrual_status' => AccrualStatus::Confirmed->value,
            'settled_at' => null,
        ]);

        $updated = $this->repository->settle($entry->id);

        $this->assertEquals(AccrualStatus::Settled->value, $updated->accrual_status);
        $this->assertNotNull($updated->settled_at);
    }

    public function test_settle_stores_actor_when_provided(): void
    {
        $actor = $this->createUser();

        $entry = $this->createLedgerEntry([
            'accrual_status' => AccrualStatus::Confirmed->value,
            'settled_by' => null,
        ]);

        $updated = $this->repository->settle($entry->id, $actor->id);

        $this->assertEquals(AccrualStatus::Settled->value, $updated->accrual_status);
        $this->assertEquals($actor->id, $updated->settled_by);
        $this->assertNotNull($updated->settled_at);
    }

    public function test_settle_throws_exception_for_invalid_transition(): void
    {
        $entry = $this->createLedgerEntry([
            'accrual_status' => AccrualStatus::Estimated->value,
        ]);

        $this->expectException(InvalidAccrualTransitionException::class);

        $this->repository->settle($entry->id);
    }

    public function test_withdraw_moves_settled_entry_to_withdrawn(): void
    {
        $entry = $this->createLedgerEntry([
            'accrual_status' => AccrualStatus::Settled->value,
            'withdrawn_at' => null,
            'payout_id' => null,
        ]);

        $updated = $this->repository->withdraw($entry->id, 123);

        $this->assertEquals(AccrualStatus::Withdrawn->value, $updated->accrual_status);
        $this->assertEquals(123, $updated->payout_id);
        $this->assertNotNull($updated->withdrawn_at);
    }

    public function test_withdraw_throws_exception_for_invalid_transition(): void
    {
        $entry = $this->createLedgerEntry([
            'accrual_status' => AccrualStatus::Confirmed->value,
        ]);

        $this->expectException(InvalidAccrualTransitionException::class);

        $this->repository->withdraw($entry->id, 123);
    }

    // ── reverse() ────────────────────────────────────────────────────────────

    public function test_reverse_moves_estimated_entry_to_reversed(): void
    {
        $entry = $this->createLedgerEntry([
            'accrual_status' => AccrualStatus::Estimated->value,
            'reversed_at' => null,
            'reversal_reason' => null,
        ]);

        $updated = $this->repository->reverse(
            $entry->id,
            'Advertiser clawback',
        );

        $this->assertEquals(AccrualStatus::Reversed->value, $updated->accrual_status);
        $this->assertEquals('Advertiser clawback', $updated->reversal_reason);
        $this->assertNotNull($updated->reversed_at);
    }

    public function test_reverse_moves_confirmed_entry_to_reversed(): void
    {
        $entry = $this->createLedgerEntry([
            'accrual_status' => AccrualStatus::Confirmed->value,
        ]);

        $updated = $this->repository->reverse(
            $entry->id,
            'Content takedown',
        );

        $this->assertEquals(AccrualStatus::Reversed->value, $updated->accrual_status);
        $this->assertEquals('Content takedown', $updated->reversal_reason);
        $this->assertNotNull($updated->reversed_at);
    }

    public function test_reverse_moves_settled_entry_to_reversed(): void
    {
        $entry = $this->createLedgerEntry([
            'accrual_status' => AccrualStatus::Settled->value,
        ]);

        $updated = $this->repository->reverse(
            $entry->id,
            'Fraud review',
        );

        $this->assertEquals(AccrualStatus::Reversed->value, $updated->accrual_status);
        $this->assertEquals('Fraud review', $updated->reversal_reason);
        $this->assertNotNull($updated->reversed_at);
    }

    public function test_reverse_stores_actor_when_provided(): void
    {
        $actor = $this->createUser();

        $entry = $this->createLedgerEntry([
            'accrual_status' => AccrualStatus::Confirmed->value,
            'reversed_by' => null,
        ]);

        $updated = $this->repository->reverse(
            $entry->id,
            'Manual adjustment',
            $actor->id,
        );

        $this->assertEquals(AccrualStatus::Reversed->value, $updated->accrual_status);
        $this->assertEquals('Manual adjustment', $updated->reversal_reason);
        $this->assertEquals($actor->id, $updated->reversed_by);
        $this->assertNotNull($updated->reversed_at);
    }

    public function test_reverse_does_not_store_actor_when_actor_is_null(): void
    {
        $entry = $this->createLedgerEntry([
            'accrual_status' => AccrualStatus::Confirmed->value,
            'reversed_by' => null,
        ]);

        $updated = $this->repository->reverse(
            $entry->id,
            'No actor',
            null,
        );

        $this->assertEquals(AccrualStatus::Reversed->value, $updated->accrual_status);
        $this->assertEquals('No actor', $updated->reversal_reason);
        $this->assertNull($updated->reversed_by);
    }

    public function test_reverse_throws_exception_for_withdrawn_entry(): void
    {
        $entry = $this->createLedgerEntry([
            'accrual_status' => AccrualStatus::Withdrawn->value,
        ]);

        $this->expectException(InvalidAccrualTransitionException::class);

        $this->repository->reverse(
            $entry->id,
            'Cannot reverse withdrawn directly',
        );
    }

    // ── currentStatus() ──────────────────────────────────────────────────────

    public function test_current_status_returns_accrual_status_enum(): void
    {
        $entry = $this->createLedgerEntry([
            'accrual_status' => AccrualStatus::Settled->value,
        ]);

        $status = $this->repository->currentStatus($entry->id);

        $this->assertInstanceOf(AccrualStatus::class, $status);
        $this->assertEquals(AccrualStatus::Settled, $status);
    }

    public function test_current_status_throws_exception_when_entry_does_not_exist(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Earnings ledger entry [999999] not found.');

        $this->repository->currentStatus(999999);
    }

    // ── settledForContributor() ──────────────────────────────────────────────

    public function test_settled_for_contributor_returns_only_settled_entries(): void
    {
        $user = $this->createUser();

        $settled = $this->createLedgerEntry([
            'user_id' => $user->id,
            'accrual_status' => AccrualStatus::Settled->value,
            'amount' => 500,
            'earned_at' => '2024-01-01 00:00:00',
        ]);

        $this->createLedgerEntry([
            'user_id' => $user->id,
            'accrual_status' => AccrualStatus::Confirmed->value,
            'amount' => 700,
            'earned_at' => '2024-01-02 00:00:00',
        ]);

        $results = $this->repository->settledForContributor($user->id);

        $this->assertCount(1, $results);
        $this->assertEquals($settled->id, $results->first()->id);
    }

    public function test_settled_for_contributor_does_not_include_other_users(): void
    {
        $user = $this->createUser();
        $otherUser = $this->createUser();

        $this->createLedgerEntry([
            'user_id' => $otherUser->id,
            'accrual_status' => AccrualStatus::Settled->value,
            'amount' => 500,
        ]);

        $results = $this->repository->settledForContributor($user->id);

        $this->assertCount(0, $results);
    }

    public function test_settled_for_contributor_returns_entries_ordered_by_earned_at(): void
    {
        $user = $this->createUser();

        $newer = $this->createLedgerEntry([
            'user_id' => $user->id,
            'accrual_status' => AccrualStatus::Settled->value,
            'amount' => 400,
            'earned_at' => '2024-06-01 00:00:00',
        ]);

        $older = $this->createLedgerEntry([
            'user_id' => $user->id,
            'accrual_status' => AccrualStatus::Settled->value,
            'amount' => 300,
            'earned_at' => '2024-01-01 00:00:00',
        ]);

        $results = $this->repository->settledForContributor($user->id);

        $this->assertEquals($older->id, $results->first()->id);
        $this->assertEquals($newer->id, $results->last()->id);
    }

    public function test_settled_for_contributor_returns_empty_collection_when_none_exist(): void
    {
        $user = $this->createUser();

        $results = $this->repository->settledForContributor($user->id);

        $this->assertCount(0, $results);
    }

    // ── settledBalanceForContributor() ───────────────────────────────────────

    public function test_settled_balance_for_contributor_sums_settled_entries_only(): void
    {
        $user = $this->createUser();

        $this->createLedgerEntry([
            'user_id' => $user->id,
            'accrual_status' => AccrualStatus::Settled->value,
            'amount' => 600,
        ]);

        $this->createLedgerEntry([
            'user_id' => $user->id,
            'accrual_status' => AccrualStatus::Settled->value,
            'amount' => 400,
        ]);

        $this->createLedgerEntry([
            'user_id' => $user->id,
            'accrual_status' => AccrualStatus::Confirmed->value,
            'amount' => 999,
        ]);

        $balance = $this->repository->settledBalanceForContributor($user->id);

        $this->assertEquals(1000, $balance);
    }

    public function test_settled_balance_for_contributor_does_not_include_other_users(): void
    {
        $user = $this->createUser();
        $otherUser = $this->createUser();

        $this->createLedgerEntry([
            'user_id' => $otherUser->id,
            'accrual_status' => AccrualStatus::Settled->value,
            'amount' => 500,
        ]);

        $balance = $this->repository->settledBalanceForContributor($user->id);

        $this->assertEquals(0, $balance);
    }

    public function test_settled_balance_for_contributor_returns_zero_when_no_settled_entries(): void
    {
        $user = $this->createUser();

        $this->createLedgerEntry([
            'user_id' => $user->id,
            'accrual_status' => AccrualStatus::Confirmed->value,
            'amount' => 500,
        ]);

        $balance = $this->repository->settledBalanceForContributor($user->id);

        $this->assertEquals(0, $balance);
    }

    // ── balancesByStatusForContributor() ─────────────────────────────────────

    public function test_balances_by_status_for_contributor_returns_all_statuses_with_zero_defaults(): void
    {
        $user = $this->createUser();

        $balances = $this->repository->balancesByStatusForContributor($user->id);

        foreach (AccrualStatus::cases() as $status) {
            $this->assertArrayHasKey($status->value, $balances);
            $this->assertEquals(0, $balances[$status->value]);
        }
    }

    public function test_balances_by_status_for_contributor_groups_amounts_by_status(): void
    {
        $user = $this->createUser();

        $this->createLedgerEntry([
            'user_id' => $user->id,
            'accrual_status' => AccrualStatus::Estimated->value,
            'amount' => 100,
        ]);

        $this->createLedgerEntry([
            'user_id' => $user->id,
            'accrual_status' => AccrualStatus::Estimated->value,
            'amount' => 200,
        ]);

        $this->createLedgerEntry([
            'user_id' => $user->id,
            'accrual_status' => AccrualStatus::Confirmed->value,
            'amount' => 300,
        ]);

        $this->createLedgerEntry([
            'user_id' => $user->id,
            'accrual_status' => AccrualStatus::Settled->value,
            'amount' => 400,
        ]);

        $balances = $this->repository->balancesByStatusForContributor($user->id);

        $this->assertEquals(300, $balances[AccrualStatus::Estimated->value]);
        $this->assertEquals(300, $balances[AccrualStatus::Confirmed->value]);
        $this->assertEquals(400, $balances[AccrualStatus::Settled->value]);
    }

    public function test_balances_by_status_for_contributor_does_not_include_other_users(): void
    {
        $user = $this->createUser();
        $otherUser = $this->createUser();

        $this->createLedgerEntry([
            'user_id' => $otherUser->id,
            'accrual_status' => AccrualStatus::Settled->value,
            'amount' => 999,
        ]);

        $balances = $this->repository->balancesByStatusForContributor($user->id);

        $this->assertEquals(0, $balances[AccrualStatus::Settled->value]);
    }

    public function test_balances_by_status_for_contributor_includes_negative_amounts(): void
    {
        $user = $this->createUser();

        $this->createLedgerEntry([
            'user_id' => $user->id,
            'accrual_status' => AccrualStatus::Settled->value,
            'type' => LedgerEntryType::Sale->value,
            'amount' => 1000,
        ]);

        $this->createLedgerEntry([
            'user_id' => $user->id,
            'accrual_status' => AccrualStatus::Settled->value,
            'type' => LedgerEntryType::Refund->value,
            'amount' => -250,
        ]);

        $balances = $this->repository->balancesByStatusForContributor($user->id);

        $this->assertEquals(750, $balances[AccrualStatus::Settled->value]);
    }

    public function test_settled_available_for_payout_returns_only_settled_entries_without_payout(): void
    {
        $user = $this->createUser();

        \App\Models\EarningsLedger::create([
            'user_id' => $user->id,
            'article_id' => 100,
            'type' => 'sale',
            'amount' => 5000,
            'currency' => 'GBP',
            'reference_id' => 'settled-available',
            'accrual_status' => \App\Enums\OpenCollab\AccrualStatus::Settled->value,
            'earned_at' => now_datetime()->format('Y-m-d H:i:s'),
            'created_at' => now_datetime()->format('Y-m-d H:i:s'),
            'updated_at' => now_datetime()->format('Y-m-d H:i:s'),
        ]);

        \App\Models\EarningsLedger::create([
            'user_id' => $user->id,
            'article_id' => 101,
            'type' => 'sale',
            'amount' => 5000,
            'currency' => 'GBP',
            'reference_id' => 'settled-already-attached',
            'accrual_status' => \App\Enums\OpenCollab\AccrualStatus::Settled->value,
            'payout_id' => 99,
            'earned_at' => now_datetime()->format('Y-m-d H:i:s'),
            'created_at' => now_datetime()->format('Y-m-d H:i:s'),
            'updated_at' => now_datetime()->format('Y-m-d H:i:s'),
        ]);

        \App\Models\EarningsLedger::create([
            'user_id' => $user->id,
            'article_id' => 102,
            'type' => 'sale',
            'amount' => 5000,
            'currency' => 'GBP',
            'reference_id' => 'confirmed-not-available',
            'accrual_status' => \App\Enums\OpenCollab\AccrualStatus::Confirmed->value,
            'earned_at' => now_datetime()->format('Y-m-d H:i:s'),
            'created_at' => now_datetime()->format('Y-m-d H:i:s'),
            'updated_at' => now_datetime()->format('Y-m-d H:i:s'),
        ]);

        $rows = $this->repository->settledAvailableForPayout($user->id);

        $this->assertCount(1, $rows);
        $this->assertSame('settled-available', $rows->first()->reference_id);
    }

    private function createLedgerEntry(array $overrides = []): Model
    {
        $user = $this->createUser();
        $page = $this->createPage();

        return EarningsLedger::create(array_merge([
            'user_id' => $user->id,
            'article_id' => $page->id,
            'type' => LedgerEntryType::Sale->value,
            'amount' => 500,
            'currency' => 'gbp',
            'reference_id' => 'ref_' . uniqid(),
            'accrual_status' => AccrualStatus::Estimated->value,
            'earned_at' => '2024-01-01 00:00:00',
        ], $overrides));
    }


    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EarningsLedgerRepository();
    }
}