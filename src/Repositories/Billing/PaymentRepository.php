<?php

namespace App\Repositories\Billing;

use App\Enums\PaymentStatus;
use App\Framework\Database\Database;
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

    public function findLatestRecoverableSubscriptionPayment(int $subscriptionId): ?Payment
    {
        return Payment::where('subscription_id', $subscriptionId)
            ->whereIn('status', ['failed', 'pending', 'processing'])
            ->orderBy('created_at', 'desc')
            ->first();
    }

    public function getLastSubscriptionPayment(int $subscriptionId): ?Payment
    {
        return Payment::where('subscription_id', $subscriptionId)
            ->where('status', 'completed')
            // Refund rows are also status=completed with a negative amount;
            // refund strategies must target the original charge, not a refund.
            ->where('amount', '>', 0)
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

    public function recordInvoicePaymentFailed(
        int     $subscriptionId,
        string  $stripeInvoiceId,
        ?string $stripePaymentIntentId,
        int     $amountCents,
        string  $currency,
        ?string $failureReason,
        ?string $failureCode,
        ?int    $memberId = null,
        ?string $hostedInvoiceUrl = null,
        ?string $rawPayload = null,
    ): Payment
    {
        return Payment::updateOrCreate(
            [
                'transaction_id' => $stripeInvoiceId],
            [
                'subscription_id' => $subscriptionId,
                'member_id' => $memberId,
                'payment_intent_id' => $stripePaymentIntentId,
                'stripe_invoice_id' => $stripeInvoiceId,
                // amountCents arrives in the smallest currency unit (Stripe
                // convention); the `amount` column stores a decimal major-unit
                // value, so it must be converted here rather than at the caller.
                'amount' => $amountCents / 100,
                'currency' => strtoupper($currency),
                'status' => PaymentStatus::FAILED->value,
                'error_message' => $failureReason,
                //'failure_code'              => $failureCode, //todo needs column
                'paid_at' => null,
                'payment_method' => 'stripe',
                'payment_provider' => 'stripe',
                'hosted_invoice_url' => $hostedInvoiceUrl,
                'raw_payload' => $rawPayload,
            ]
        );
    }

    public function recordInvoicePaymentSucceeded(
        int                $subscriptionId,
        string             $stripeInvoiceId,
        ?string            $stripePaymentIntentId,
        int                $amountCents,
        string             $currency,
        \DateTimeImmutable $paidAt,
        ?int               $memberId = null,
        ?string            $hostedInvoiceUrl = null,
        ?string            $rawPayload = null,
    ): Payment
    {
        return Payment::updateOrCreate(
            ['transaction_id' => $stripeInvoiceId],
            [
                'subscription_id' => $subscriptionId,
                'member_id' => $memberId,
                'payment_intent_id' => $stripePaymentIntentId,
                'stripe_invoice_id' => $stripeInvoiceId,
                // amountCents arrives in the smallest currency unit (Stripe
                // convention); the `amount` column stores a decimal major-unit
                // value, so it must be converted here rather than at the caller.
                'amount' => $amountCents / 100,
                'currency' => strtoupper($currency),
                'status' => PaymentStatus::COMPLETED->value,
                'error_message' => null,
                //'failure_code'              => null,
                'paid_at' => $paidAt->format('Y-m-d H:i:s'),
                'payment_method' => 'stripe',
                'payment_provider' => 'stripe',
                'hosted_invoice_url' => $hostedInvoiceUrl,
                'raw_payload' => $rawPayload,
            ]
        );
    }

    /**
     * Fetch all payments linked to any of the member's subscriptions on a given
     * site in a single JOIN query — eliminates the N+1 pattern.
     *
     * @return Collection
     */
    public function findByMemberSubscriptions(int $memberId, int $siteId): Collection
    {
        return Database::table('payments')
            ->join('subscriptions', 'subscriptions.id', '=', 'payments.subscription_id')
            ->where('subscriptions.member_id', $memberId)
            ->where('subscriptions.site_id', $siteId)
            ->select('payments.*')
            ->orderByDesc('payments.created_at')
            ->get();
    }

    /**
     * Fetch all payments linked to orders belonging to a member.
     *
     * @param bool $excludeSubscriptionLinked When true, rows where
     *   subscription_id IS NOT NULL are excluded to prevent double-counting
     *   payments that were also captured via a subscription.
     *
     * @return Collection
     */
    public function findByMemberOrders(int $memberId, bool $excludeSubscriptionLinked = true): Collection
    {
        $query = Database::table('payments')
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->where('orders.user_id', $memberId)
            ->select('payments.*');

        if ($excludeSubscriptionLinked) {
            $query->whereNull('payments.subscription_id');
        }

        return $query
            ->orderByDesc('payments.created_at')
            ->get();
    }

    public function findByMemberPaginated(int $memberId, int $siteId, int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;

        $total = Payment::where('member_id', $memberId)
            ->where('site_id', $siteId)
            ->count();

        $items = Payment::where('member_id', $memberId)
            ->where('site_id', $siteId)
            ->orderByDesc('received_at')
            ->limit($perPage)
            ->offset($offset)
            ->get();

        return [
            'items'      => $items,
            'total'      => $total,
            'per_page'   => $perPage,
            'page'       => $page,
            'last_page'  => (int) ceil($total / $perPage),
        ];
    }

    public function sumRefundsForOriginalPayment(int $paymentId): float
    {
        $refunds = Payment::where('amount', '<', 0)->get();
        $total = 0.0;

        foreach ($refunds as $refund) {
            $metadata = $this->normaliseMetadata($refund->metadata ?? null);

            if ((int)($metadata['original_payment_id'] ?? 0) === $paymentId) {
                $total += abs((float)$refund->amount);
            }
        }

        return round($total, 2);
    }

    private function normaliseMetadata(mixed $metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }

        if (is_object($metadata)) {
            return (array)$metadata;
        }

        if (is_string($metadata) && $metadata !== '') {
            $decoded = json_decode($metadata, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    protected function getModelClass(): string
    {
        return Payment::class;
    }
}
