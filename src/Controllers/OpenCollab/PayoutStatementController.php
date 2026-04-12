<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Support\Logger;
use App\Framework\Support\SiteContext;
use App\Repositories\OpenCollab\ArticlePaymentRepository;
use App\Repositories\OpenCollab\PayoutRepository;

/**
 * Generates and streams a payout statement PDF.
 *
 * Routes:
 *   GET /api/{site}/open-collab/payouts/{id}/statement   — contributor downloads own payout statement
 *   GET /api/{site}/open-collab/admin/payouts/{id}/statement — admin downloads any payout statement
 */
class PayoutStatementController extends Controller
{
    /** Absolute path to the Python generator script, relative to project root. */
    private const GENERATOR_SCRIPT = __DIR__ . '/../../../scripts/generate_payout_pdf.py';

    public function __construct(
        private readonly PayoutRepository         $payoutRepository,
        private readonly ArticlePaymentRepository $paymentRepository,
        private readonly Logger                   $logger,
    )
    {
        parent::__construct();
    }

    /**
     * GET /api/{site}/open-collab/payouts/{id}/statement
     * Contributor may only download their own payout.
     */
    public function download(int $id): void
    {
        $payout = $this->payoutRepository->find($id);

        if (!$payout || (int)$payout->site_id !== SiteContext::getId()) {
            http_response_code(404);
            echo json_encode(['error' => 'Payout not found.']);
            return;
        }

        // Ownership check — contributors may only download their own
        $userId = Auth::id();
        $user = Auth::user();
        $isAdmin = $user && in_array($user->role ?? '', ['admin', 'agent'], true);

        if (!$isAdmin && (int)$payout->user_id !== $userId) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied.']);
            return;
        }

        $this->streamStatement($payout);
    }

    private function streamStatement(\App\Models\Payout $payout): void
    {
        try {
            $payload = $this->buildPayload($payout);
            $tmpFile = sys_get_temp_dir() . '/payout_' . $payout->id . '_' . uniqid() . '.pdf';

            $jsonArg = escapeshellarg(json_encode($payload));
            $outArg = escapeshellarg($tmpFile);
            $script = escapeshellarg(self::GENERATOR_SCRIPT);

            $output = shell_exec("python3 {$script} {$jsonArg} {$outArg} 2>&1");

            if (!file_exists($tmpFile) || !str_starts_with((string)$output, 'OK:')) {
                $this->logger->error('Payout PDF generation failed.', [
                    'payout_id' => $payout->id,
                    'output' => $output,
                ]);
                http_response_code(500);
                echo json_encode(['error' => 'Could not generate statement. Please try again.']);
                return;
            }

            $filename = "payout-statement-{$payout->id}.pdf";

            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($tmpFile));
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');

            readfile($tmpFile);
            @unlink($tmpFile);
            exit;

        } catch (\Throwable $e) {
            echo $e->getMessage();
            die;
            $this->logger->error('Exception generating payout PDF.', [
                'payout_id' => $payout->id,
                'error' => $e->getMessage(),
            ]);
            http_response_code(500);
            echo json_encode(['error' => 'Statement generation failed.']);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function buildPayload(\App\Models\Payout $payout): array
    {
        // Load contributor info
        $contributor = \App\Models\User::find($payout->user_id);
        $site = \App\Models\Site::find($payout->site_id);

        // Fetch transaction history for this contributor (matching the payout period)
        $transactions = $this->buildTransactions($payout);

        $totalEarnings = array_sum(array_column(
            array_filter($transactions, fn($t) => $t['amount_pence'] > 0),
            'amount_pence'
        ));
        $totalRefunds = abs(array_sum(array_column(
            array_filter($transactions, fn($t) => $t['amount_pence'] < 0),
            'amount_pence'
        )));

        return [
            'platform_name' => $site?->name ?? 'OpenCollab',
            'contributor_name' => $contributor?->name ?? 'Contributor',
            'contributor_email' => $contributor?->email ?? '',
            'statement_date' => date('d M Y'),
            'date_range' => $this->payoutDateRange($payout),
            'total_earnings_pence' => $totalEarnings,
            'total_refunds_pence' => $totalRefunds,
            'payout_amount_pence' => (int)$payout->amount,
            'payout_id' => sprintf('PAY-%s-%d', $payout->created_at?->format('Ymd' ?? 'now'), $payout->id),
            'payment_method' => ucwords(str_replace('_', ' ', $payout->method ?? 'bank_transfer')),
            'processed_date' => $payout->processed_at ? $payout->processed_at?->format('d M Y') : 'Pending',
            'reference' => $payout->reference ?? '—',
            'transactions' => $transactions,
        ];
    }

    private function buildTransactions(\App\Models\Payout $payout): array
    {
        // Load succeeded + refunded payments for this contributor
        $raw = $this->paymentRepository->transactionHistoryForContributor(
            (int)$payout->user_id,
            perPage: 500
        );

        $items = is_array($raw) ? ($raw['data'] ?? collect([])) : collect([]);
        if (is_object($items) && method_exists($items, 'all')) {
            $items = $items->all();
        }

        $transactions = [];
        foreach ($items as $tx) {
            $txArr = is_array($tx) ? $tx : (method_exists($tx, 'toArray') ? $tx->toArray() : (array)$tx);
            $isRefund = ($txArr['status'] ?? '') === 'refunded';
            $transactions[] = [
                'date' => isset($txArr['created_at']) ? $txArr['created_at']->format('d M Y') : '—',
                'article_title' => $txArr['page_title'] ?? $txArr['title'] ?? '—',
                'type' => $isRefund ? 'Refund' : 'Sale',
                'amount_pence' => $isRefund ? -(int)($txArr['amount'] ?? 0) : (int)($txArr['amount'] ?? 0),
                'status' => ucfirst($txArr['status'] ?? 'succeeded'),
            ];
        }

        return $transactions;
    }

    private function payoutDateRange(\App\Models\Payout $payout): string
    {
        if (!$payout->created_at) {
            return '';
        }

        $created = $payout->created_at;

        $start = (clone $created)->modify('first day of this month')->format('d M Y');
        $end = (clone $created)->modify('last day of this month')->format('d M Y');

        return "{$start} – {$end}";
    }

    /**
     * GET /api/{site}/open-collab/admin/payouts/{id}/statement
     * Admin can download any payout statement.
     */
    public function adminDownload(int $id): void
    {
        $user = Auth::user();
        if (!$user || !in_array($user->role ?? '', ['admin', 'agent'], true)) {
            http_response_code(403);
            echo json_encode(['error' => 'Admin access required.']);
            return;
        }

        $payout = $this->payoutRepository->find($id);

        if (!$payout || (int)$payout->site_id !== SiteContext::getId()) {
            http_response_code(404);
            echo json_encode(['error' => 'Payout not found.']);
            return;
        }

        $this->streamStatement($payout);
    }
}