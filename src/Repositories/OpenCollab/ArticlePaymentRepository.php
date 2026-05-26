<?php

namespace App\Repositories\OpenCollab;

use App\Enums\OpenCollab\PaymentStatus;
use App\Framework\Database\Database;
use App\Models\ArticlePayment;
use App\Models\Model;
use App\Repositories\Repository;

class ArticlePaymentRepository extends Repository
{
    public function findByPaymentIntentId(string $paymentIntentId): ?ArticlePayment
    {
        /** @var ArticlePayment|null */
        return ArticlePayment::where('stripe_payment_intent_id', $paymentIntentId)->first();
    }

    public function updateStatus(int $id, string $status): Model
    {
        return $this->update($id, ['status' => $status]);
    }

    /**
     * Total pence earned by a contributor across all their succeeded payments.
     * Joins through pages to resolve contributor ownership.
     */
    public function sumSucceededAmountForContributor(int $contributorId): int
    {
        $row = Database::table('oc_article_payments as ap')
            ->join('pages as p', 'p.id', '=', 'ap.page_id')
            ->where('p.contributor_id', $contributorId)
            ->where('ap.status', PaymentStatus::Succeeded->value)
            ->selectRaw('SUM(ap.amount) as total')
            ->first();

        return (int)($row['total'] ?? $row->total ?? 0);
    }

    /**
     * Per-page earnings breakdown for the contributor dashboard.
     * Returns [ ['page_id' => int, 'title' => string, 'total' => int], ... ]
     */
    public function earningsBreakdownForContributor(int $contributorId): array
    {
        return Database::table('oc_article_payments as ap')
            ->join('pages as p', 'p.id', '=', 'ap.page_id')
            ->where('p.contributor_id', $contributorId)
            ->where('ap.status', PaymentStatus::Succeeded->value)
            ->selectRaw('ap.page_id, p.title, SUM(ap.amount) as total')
            ->groupBy('ap.page_id', 'p.title')
            ->orderByDesc('total')
            ->get()
            ->map(fn($row) => [
                'page_id' => (int)($row['page_id'] ?? $row->page_id),
                'title' => $row['title'] ?? $row->title,
                'total' => (int)($row['total'] ?? $row->total),
            ])
            ->toArray();
    }

    /**
     * Full transaction history for a contributor, newest first.
     * Used by the earnings dashboard transaction table.
     */
    public function transactionHistoryForContributor(int $contributorId, int $perPage = 20): array
    {
        return ArticlePayment::join('pages', 'pages.id', '=', 'oc_article_payments.page_id')
            ->where('pages.contributor_id', $contributorId)
            ->whereIn('oc_article_payments.status', [
                PaymentStatus::Succeeded->value,
                PaymentStatus::Refunded->value,
            ])
            ->select(
                'oc_article_payments.id',
                'oc_article_payments.page_id',
                'oc_article_payments.amount',
                'oc_article_payments.currency',
                'oc_article_payments.status',
                'oc_article_payments.created_at',
                'pages.title as page_title',
            )
            ->orderByDesc('oc_article_payments.created_at')
            ->paginate($perPage);
    }

    /**
     * Full transaction history for the earnings transaction table.
     *
     * Returns both succeeded and refunded payments, sorted descending by date.
     * The renderer uses `status` to colour refunds red and prefix a minus sign.
     *
     * @return array<int, array{page_title: string, amount: int, status: string, created_at: string}>
     */
    public function transactionsForContributor(int $contributorId): array
    {
        return Database::table('oc_article_payments as ap')
            ->leftJoin('pages as p', 'p.id', '=', 'ap.page_id')
            ->where('ap.user_id', $contributorId)
            ->whereIn('ap.status', ['succeeded', 'refunded'])
            ->orderByDesc('ap.created_at')
            ->get()
            ->map(function ($row) {
                return [
                    'page_title' => $row['page_title'] ?? '–',
                    'amount'     => (int) $row['amount'],
                    'status'     => $row['status'],
                    'created_at' => $row['created_at'],
                ];
            })
            ->values()
            ->toArray();
    }

    protected function getModelClass(): string
    {
        return ArticlePayment::class;
    }
}
