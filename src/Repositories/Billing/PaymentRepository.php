<?php

namespace App\Repositories\Billing;

use App\Framework\Support\Collection;
use App\Models\Payment;
use App\Repositories\Repository;
use App\Search\PaginatedResult;
use App\Search\SearchConfigurationFactory;
use App\Search\SearchCriteria;
use App\Search\SearchEngine;

class PaymentRepository extends Repository
{
    public function findByOrderId(int $orderId): Collection
    {
        return Payment::where('order_id', $orderId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function findByTransactionId(string $transactionId): ?Payment
    {
        return Payment::where('transaction_id', $transactionId)->first();
    }

    public function findByPaymentIntentId(string $paymentIntentId): ?Payment
    {
        return Payment::where('payment_intent_id', $paymentIntentId)->first();
    }

    public function getPendingPayments(): Collection
    {
        return $this->getByStatus('pending');
    }

    public function getByStatus(string $status): Collection
    {
        return $this->applySiteFilter(
            Payment::where('status', $status)
                ->orderBy('created_at', 'desc')
        )->get();
    }

    public function getFailedPayments(): Collection
    {
        return $this->getByStatus('failed');
    }

    public function getTotalCollected(?string $startDate = null, ?string $endDate = null): float
    {
        $query = Payment::where('status', 'completed');

        if ($startDate) {
            $query->where('paid_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('paid_at', '<=', $endDate);
        }

        $payments = $this->applySiteFilter($query)->get();

        return $payments->sum('amount');
    }

    public function getByPaymentMethod(string $paymentMethod): Collection
    {
        return $this->applySiteFilter(
            Payment::where('payment_method', $paymentMethod)
                ->orderBy('created_at', 'desc')
        )->get();
    }

    public function findBySubscriptionId(int $subscriptionId): Collection
    {
        return Payment::where('subscription_id', $subscriptionId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getLastSubscriptionPayment(int $subscriptionId): ?Payment
    {
        return Payment::where('subscription_id', $subscriptionId)
            ->where('status', 'completed')
            ->orderBy('paid_at', 'desc')
            ->first();
    }

    public function getFailedSubscriptionPayments(): Collection
    {
        return $this->applySiteFilter(
            Payment::where('status', 'failed')
                ->whereNotNull('subscription_id')
                ->orderBy('created_at', 'desc')
        )->get();
    }

    public function getAllPayments(): Collection
    {
        return $this->applySiteFilter(
            Payment::whereNotNull('subscription_id')
                ->orderBy('created_at', 'desc')
        )->get();
    }

    public function countSubscriptionPayments(int $subscriptionId, ?string $status = null): int
    {
        $query = Payment::where('subscription_id', $subscriptionId);

        if ($status) {
            $query->where('status', $status);
        }

        return $query->count();
    }

    public function search(SearchCriteria $criteria): PaginatedResult
    {
        $configuration = SearchConfigurationFactory::create('payment');
        $engine = new SearchEngine($configuration);

        // Replace with however your repository accesses its base query builder,
        // e.g. Campaign::query() or $this->model->newQuery()
        return $engine->search($this->query(), $criteria);
    }

    protected function getModelClass(): string
    {
        return Payment::class;
    }
}