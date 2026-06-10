<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Http\StreamedResponse;
use App\Framework\Support\Logger;
use App\Framework\Support\SiteContext;
use App\Models\Payout;
use App\Models\Site;
use App\Models\User;
use App\Repositories\OpenCollab\ArticlePaymentRepository;
use App\Repositories\OpenCollab\PayoutRepository;
use DateTimeInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Throwable;

/**
 * Generates and streams a payout statement PDF using dompdf.
 *
 * Routes:
 *   GET /api/{site}/open-collab/payouts/{id}/statement          — contributor downloads own
 *   GET /api/{site}/open-collab/admin/payouts/{id}/statement    — admin downloads any
 */
class PayoutStatementController extends Controller
{
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
     * Contributors may only download their own payout.
     */
    public function download(int $id): ?StreamedResponse
    {
        $payout = $this->payoutRepository->find($id);

        if (!$payout || (int)$payout->site_id !== SiteContext::getId()) {
            http_response_code(404);
            echo json_encode(['error' => 'Payout not found.']);
            return null;
        }

        $userId = Auth::id();
        $user = Auth::user();
        $isAdmin = $user && in_array($user->role ?? '', ['admin', 'agent'], true);

        if (!$isAdmin && (int)$payout->user_id !== $userId) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied.']);
            return null;
        }

        return $this->streamStatement($payout);
    }

    private function streamStatement(Payout $payout): ?StreamedResponse
    {
        try {
            return new StreamedResponse(function () use ($payout) {
                // Generate PDF using DomPDF
                $options = new Options();
                $options->set('isHtml5ParserEnabled', true);
                $options->set('isRemoteEnabled', false);
                $options->set('defaultFont', 'Helvetica');

                $dompdf = new Dompdf($options);

                // Load HTML content
                $html = $this->buildHtml($payout);
                $dompdf->loadHtml($html);

                // Set paper size
                $dompdf->setPaper('A4', 'portrait');

                // Render PDF
                $dompdf->render();

                $filename = "payout-statement-{$payout->id}.pdf";

                // Output PDF
                $dompdf->stream($filename, [
                    'Attachment' => true
                ]);
            }, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="invoice-' . $payout->id . '.pdf"'
            ]);
        } catch (Throwable $e) {
            echo $e->getMessage();
            die;
            $this->logger->error('Exception generating payout PDF.', [
                'payout_id' => $payout->id,
                'error' => $e->getMessage(),
            ]);
            http_response_code(500);
            echo json_encode(['error' => 'Statement generation failed.']);
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function buildHtml(Payout $payout): string
    {
        $contributor = User::find($payout->user_id);
        $site = Site::find($payout->site_id);
        $transactions = $this->buildTransactions($payout);

        $totalEarnings = array_sum(
            array_column(array_filter($transactions, fn($t) => $t['amount_pence'] > 0), 'amount_pence')
        );
        $totalRefunds = abs(array_sum(
            array_column(array_filter($transactions, fn($t) => $t['amount_pence'] < 0), 'amount_pence')
        ));

        $platformName = htmlspecialchars($site?->name ?? 'OpenCollab');
        $contributorName = htmlspecialchars($contributor?->name ?? 'Contributor');
        $contributorEmail = htmlspecialchars($contributor?->email ?? '');
        $statementDate = date('d M Y');
        $dateRange = $this->payoutDateRange($payout);
        $payoutId = 'PAY-' . ($payout->created_at?->format('Ymd') ?? date('Ymd')) . '-' . $payout->id;
        $paymentMethod = htmlspecialchars(ucwords(str_replace('_', ' ', $payout->method ?? 'bank_transfer')));
        $processedDate = $payout->processed_at
            ? htmlspecialchars($payout->processed_at?->format('d M Y'))
            : 'Pending';
        $reference = htmlspecialchars($payout->reference ?? '—');

        $totalEarningsFmt = '£' . number_format($totalEarnings / 100, 2);
        $totalRefundsFmt = '£' . number_format($totalRefunds / 100, 2);
        $payoutAmountFmt = '£' . number_format((int)$payout->amount / 100, 2);

        // Build transaction rows
        $txRows = '';
        foreach ($transactions as $tx) {
            $amount = '£' . number_format(abs($tx['amount_pence']) / 100, 2);
            $isRefund = $tx['amount_pence'] < 0;
            $color = $isRefund ? '#dc2626' : '#16a34a';
            $prefix = $isRefund ? '−' : '+';

            $txRows .= sprintf(
                '<tr>
                    <td>%s</td>
                    <td>%s</td>
                    <td style="text-align:center;">%s</td>
                    <td style="text-align:right;color:%s;font-weight:600;">%s%s</td>
                    <td style="text-align:center;">%s</td>
                </tr>',
                htmlspecialchars($tx['date']),
                htmlspecialchars($tx['article_title']),
                htmlspecialchars($tx['type']),
                $color,
                $prefix,
                $amount,
                htmlspecialchars($tx['status']),
            );
        }

        if ($txRows === '') {
            $txRows = '<tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:20px;">No transactions in this period</td></tr>';
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: Helvetica, Arial, sans-serif;
        font-size: 10pt;
        color: #1e293b;
        background: #fff;
    }
    .page { padding: 40px 48px; }

    /* Header */
    .header {
        display: table;
        width: 100%;
        margin-bottom: 32px;
        border-bottom: 3px solid #0f172a;
        padding-bottom: 20px;
    }
    .header-left  { display: table-cell; vertical-align: middle; }
    .header-right { display: table-cell; vertical-align: middle; text-align: right; }
    .platform-name {
        font-size: 20pt;
        font-weight: 700;
        color: #0f172a;
        letter-spacing: -0.5px;
    }
    .doc-title {
        font-size: 11pt;
        font-weight: 600;
        color: #475569;
        margin-top: 2px;
    }
    .statement-date { font-size: 9pt; color: #64748b; }

    /* Summary grid */
    .summary {
        display: table;
        width: 100%;
        margin-bottom: 28px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        overflow: hidden;
    }
    .summary-row { display: table-row; }
    .summary-cell {
        display: table-cell;
        padding: 14px 18px;
        border-right: 1px solid #e2e8f0;
        width: 33.33%;
        vertical-align: top;
    }
    .summary-cell:last-child { border-right: none; }
    .summary-label {
        font-size: 7.5pt;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #64748b;
        margin-bottom: 4px;
    }
    .summary-value {
        font-size: 11pt;
        font-weight: 600;
        color: #0f172a;
    }
    .summary-sub { font-size: 8pt; color: #94a3b8; margin-top: 2px; }

    /* Parties */
    .parties {
        display: table;
        width: 100%;
        margin-bottom: 28px;
    }
    .party-cell {
        display: table-cell;
        width: 50%;
        vertical-align: top;
        padding-right: 24px;
    }
    .party-cell:last-child { padding-right: 0; padding-left: 24px; }
    .party-label {
        font-size: 7.5pt;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #64748b;
        margin-bottom: 6px;
        border-bottom: 1px solid #e2e8f0;
        padding-bottom: 4px;
    }
    .party-name  { font-size: 10pt; font-weight: 600; color: #0f172a; }
    .party-email { font-size: 9pt; color: #64748b; margin-top: 2px; }

    /* Totals bar */
    .totals-bar {
        display: table;
        width: 100%;
        margin-bottom: 20px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 12px 18px;
    }
    .totals-bar-cell { display: table-cell; width: 33.33%; }
    .totals-label { font-size: 8pt; color: #64748b; }
    .totals-value { font-size: 11pt; font-weight: 700; color: #0f172a; }
    .totals-value--green  { color: #16a34a; }
    .totals-value--red    { color: #dc2626; }
    .totals-value--accent { color: #0f172a; }

    /* Transactions table */
    .section-title {
        font-size: 9pt;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.07em;
        color: #64748b;
        margin-bottom: 10px;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 9pt;
    }
    thead tr {
        background: #0f172a;
        color: #fff;
    }
    thead th {
        padding: 8px 12px;
        text-align: left;
        font-weight: 600;
        font-size: 8pt;
        letter-spacing: 0.04em;
    }
    tbody tr:nth-child(even) { background: #f8fafc; }
    tbody td {
        padding: 8px 12px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }

    /* Footer */
    .footer {
        margin-top: 40px;
        padding-top: 16px;
        border-top: 1px solid #e2e8f0;
        font-size: 8pt;
        color: #94a3b8;
        text-align: center;
    }
    .payout-id-badge {
        display: inline-block;
        background: #0f172a;
        color: #f1f5f9;
        font-size: 8pt;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 4px;
        letter-spacing: 0.06em;
        margin-bottom: 28px;
    }
</style>
</head>
<body>
<div class="page">

    <!-- Header -->
    <div class="header">
        <div class="header-left">
            <div class="platform-name">{$platformName}</div>
            <div class="doc-title">Contributor Payout Statement</div>
        </div>
        <div class="header-right">
            <div class="payout-id-badge">{$payoutId}</div>
            <div class="statement-date">Issued: {$statementDate}</div>
            <div class="statement-date" style="margin-top:2px;">Period: {$dateRange}</div>
        </div>
    </div>

    <!-- Parties -->
    <div class="parties">
        <div class="party-cell">
            <div class="party-label">Paid to</div>
            <div class="party-name">{$contributorName}</div>
            <div class="party-email">{$contributorEmail}</div>
        </div>
        <div class="party-cell" style="text-align:right;">
            <div class="party-label">Payment details</div>
            <div class="party-name">{$paymentMethod}</div>
            <div class="party-email">Ref: {$reference}</div>
            <div class="party-email">Processed: {$processedDate}</div>
        </div>
    </div>

    <!-- Summary cards -->
    <div class="summary">
        <div class="summary-row">
            <div class="summary-cell">
                <div class="summary-label">Gross Earnings</div>
                <div class="summary-value" style="color:#16a34a;">{$totalEarningsFmt}</div>
                <div class="summary-sub">Total sales in period</div>
            </div>
            <div class="summary-cell">
                <div class="summary-label">Total Refunds</div>
                <div class="summary-value" style="color:#dc2626;">{$totalRefundsFmt}</div>
                <div class="summary-sub">Reversed transactions</div>
            </div>
            <div class="summary-cell">
                <div class="summary-label">Payout Amount</div>
                <div class="summary-value" style="font-size:14pt;">{$payoutAmountFmt}</div>
                <div class="summary-sub">Net disbursement</div>
            </div>
        </div>
    </div>

    <!-- Transactions -->
    <div class="section-title">Transaction Detail</div>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Article</th>
                <th style="text-align:center;">Type</th>
                <th style="text-align:right;">Amount</th>
                <th style="text-align:center;">Status</th>
            </tr>
        </thead>
        <tbody>
            {$txRows}
        </tbody>
    </table>

    <!-- Footer -->
    <div class="footer">
        <p>This statement is automatically generated by {$platformName} and is valid without a signature.</p>
        <p style="margin-top:4px;">For disputes or queries, please contact your account manager.</p>
    </div>

</div>
</body>
</html>
HTML;
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function buildTransactions(Payout $payout): array
    {
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

            // created_at is a DateTime object per the project note
            $createdAt = $txArr['created_at'] ?? null;
            $date = $createdAt instanceof DateTimeInterface
                ? $createdAt->format('d M Y')
                : (is_string($createdAt) ? date('d M Y', strtotime($createdAt)) : '—');

            $transactions[] = [
                'date' => $date,
                'article_title' => $txArr['page_title'] ?? $txArr['title'] ?? '—',
                'type' => $isRefund ? 'Refund' : 'Sale',
                'amount_pence' => $isRefund
                    ? -(int)($txArr['amount'] ?? 0)
                    : (int)($txArr['amount'] ?? 0),
                'status' => ucfirst($txArr['status'] ?? 'succeeded'),
            ];
        }

        return $transactions;
    }

    private function payoutDateRange(Payout $payout): string
    {
        $created = $payout->created_at;
        if (!$created instanceof DateTimeInterface) {
            return '';
        }

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