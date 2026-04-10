<?php

namespace App\Repositories\OpenCollab;

use App\Enums\OpenCollab\PaymentStatus;
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
        return (int)ArticlePayment::join('pages', 'pages.id', '=', 'oc_article_payments.page_id')
            ->where('pages.contributor_id', $contributorId)
            ->where('oc_article_payments.status', PaymentStatus::Succeeded->value)
            ->sum('oc_article_payments.amount');
    }

    /**
     * Per-page earnings breakdown for the contributor dashboard.
     * Returns [ ['page_id' => int, 'title' => string, 'total' => int], ... ]
     */
    public function earningsBreakdownForContributor(int $contributorId): array
    {
        return ArticlePayment::join('pages', 'pages.id', '=', 'oc_article_payments.page_id')
            ->where('pages.contributor_id', $contributorId)
            ->where('oc_article_payments.status', PaymentStatus::Succeeded->value)
            ->selectRaw('oc_article_payments.page_id, pages.title, SUM(oc_article_payments.amount) as total')
            ->groupBy('oc_article_payments.page_id', 'pages.title')
            ->orderByDesc('total')
            ->get()
            ->map(fn($row) => [
                'page_id' => (int)$row->page_id,
                'title' => $row->title,
                'total' => (int)$row->total,
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

    protected function getModelClass(): string
    {
        return ArticlePayment::class;
    }
}