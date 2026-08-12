<?php

namespace App\Tests\Unit\Services\OpenCollab\Surfaces;

use App\Services\OpenCollab\Surfaces\SurfaceResolver;
use PHPUnit\Framework\TestCase;

class SurfaceResolverTest extends TestCase
{
    public function test_earnings_manifest_contains_stats_and_transactions_sections(): void
    {
        $manifest = (new SurfaceResolver())->manifest('earnings.index', 'guitar-world');

        $this->assertSame(['earnings.stats', 'earnings.transactions_table'], array_column($manifest, 'key'));
        $this->assertSame('earnings_stats_grid', $manifest[0]['component']);
        $this->assertSame('earnings_finance_table', $manifest[1]['component']);
        $this->assertSame('/api/guitar-world/open-collab/dashboard/widgets/earnings', $manifest[0]['endpoint']);
        $this->assertSame('/api/guitar-world/open-collab/dashboard/widgets/earnings', $manifest[1]['endpoint']);
    }

    public function test_payouts_manifest_contains_stats_and_history_sections(): void
    {
        $manifest = (new SurfaceResolver())->manifest('payouts.index', 'guitar-world');

        $this->assertSame(['payouts.stats', 'payouts.history_table'], array_column($manifest, 'key'));
        $this->assertSame('payout_stats_grid', $manifest[0]['component']);
        $this->assertSame('payout_history_table', $manifest[1]['component']);
        $this->assertSame('/api/guitar-world/open-collab/payouts', $manifest[1]['endpoint']);
    }

    public function test_disputes_manifest_contains_stats_and_table_sections(): void
    {
        $manifest = (new SurfaceResolver())->manifest('disputes.index', 'guitar-world');

        $this->assertSame(['disputes.stats', 'disputes.table'], array_column($manifest, 'key'));
        $this->assertSame('dispute_stats_grid', $manifest[0]['component']);
        $this->assertSame('disputes_table', $manifest[1]['component']);
        $this->assertSame('/api/guitar-world/open-collab/disputes', $manifest[0]['endpoint']);
        $this->assertSame('/api/guitar-world/open-collab/disputes', $manifest[1]['endpoint']);
    }

    public function test_admin_manifests_contain_finance_stats_sections(): void
    {
        $payouts = (new SurfaceResolver())->manifest('admin.payouts.index', 'guitar-world');
        $disputes = (new SurfaceResolver())->manifest('admin.disputes.index', 'guitar-world');

        $this->assertSame('admin.payouts.summary_stats', $payouts[0]['key']);
        $this->assertSame('admin_payout_summary_stats', $payouts[0]['component']);
        $this->assertSame('/api/guitar-world/open-collab/admin/payouts/stats', $payouts[0]['endpoint']);

        $this->assertSame('admin.payouts.stats', $payouts[1]['key']);
        $this->assertSame('admin_payout_stats_grid', $payouts[1]['component']);
        $this->assertSame('/api/guitar-world/open-collab/admin/payouts?per_page=200', $payouts[1]['endpoint']);

        $this->assertSame('admin.disputes.summary_stats', $disputes[0]['key']);
        $this->assertSame('admin_dispute_summary_stats', $disputes[0]['component']);
        $this->assertSame('/api/guitar-world/open-collab/admin/disputes', $disputes[0]['endpoint']);

        $this->assertSame('admin.disputes.stats', $disputes[1]['key']);
        $this->assertSame('admin_dispute_stats_grid', $disputes[1]['component']);
        $this->assertSame('/api/guitar-world/open-collab/admin/disputes', $disputes[1]['endpoint']);
    }

    public function test_unknown_surface_returns_empty_manifest(): void
    {
        $this->assertSame([], (new SurfaceResolver())->manifest('unknown.surface', 'guitar-world'));
    }
}
