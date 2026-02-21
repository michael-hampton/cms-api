<?php

namespace App\Repositories\Billing;

use App\Framework\Support\Collection;
use App\Models\Order;
use App\Repositories\Repository;
use App\Search\PaginatedResult;
use App\Search\SearchConfigurationFactory;
use App\Search\SearchCriteria;
use App\Search\SearchEngine;

class OrderRepository extends Repository
{
    private SearchEngine $searchEngine;

    public function __construct()
    {
        parent::__construct();
        $config = SearchConfigurationFactory::create('order');
        $this->searchEngine = new SearchEngine($config);
    }

    public function findByPaymentIntent(string $paymentIntentId)
    {
        return Order::where('payment_intent_id', $paymentIntentId)->first();
    }

    protected function getModelClass(): string
    {
        return Order::class;
    }

    public function search(SearchCriteria $criteria): PaginatedResult
    {
        $query = Order::with(['items', 'user', 'billingAddress', 'shippingAddress'])->withCount(['items']);
        return $this->searchEngine->search($query, $criteria);
    }

    public function findByOrderNumber(string $orderNumber): ?Order
    {
        return Order::where('order_number', $orderNumber)
            ->with(['items', 'user', 'history', 'refunds'])
            ->first();
    }

    public function getByStatus(string $status): Collection
    {
        return Order::where('status', $status)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getByUser(int $userId, ?int $limit = null): Collection
    {
        $query = Order::with([
            'items.product',  // ✅ Eager load nested
            'user',
            'shippingAddress',
            'billingAddress'
        ])
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    public function getRecentOrders(?int $limit = 10): Collection
    {
        $query = Order::orderBy('created_at', 'desc');

        return $this->applySiteFilter($query)->limit($limit)
            ->get();
    }

    public function getOrdersWithItems(?int $limit = null): Collection
    {
        $query = Order::with(['items', 'user'])
            ->orderBy('created_at', 'desc');

        if ($limit) {
            $query->limit($limit);
        }

        return $this->applySiteFilter($query)->get();
    }

    public function getTotalRevenue(?string $startDate = null, ?string $endDate = null): float
    {
        $query = Order::where('status', 'completed')
            ->where('payment_status', 'paid');

        if ($startDate) {
            $query->where('completed_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('completed_at', '<=', $endDate);
        }

        $orders = $this->applySiteFilter($query)->get();

        return $orders->sum('total');
    }

    public function getOrderCount(?string $status = null): int
    {
        $query = Order::query();

        if ($status) {
            $query->where('status', $status);
        }

        return $this->applySiteFilter($query)->count();
    }

    public function getOrderById(int $id): ?Order
    {
        return Order::with(['items', 'user', 'item.product', 'history', 'refunds']) // ADD 'history'
        ->find($id);
    }

    public function updateSubscriptionForOrder(int $orderId, int $subscriptionId): ?int
    {
        return Order::where('id', $orderId)->update([
            'one_time_subscription_id' => $subscriptionId
        ]);
    }

    public function getOrdersByCheckoutId(string $checkoutId): Collection
    {
        return Order::where('checkout_id', $checkoutId)->get();
    }

    public function getAll(int $page = 1, int $perPage = 50): array
    {
        $query = Order::with(['items', 'user'])
            ->orderBy('created_at', 'desc');

        $total = $query->count();
        $orders = $query->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return [
            'data' => $orders,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'total_pages' => ceil($total / $perPage)
            ]
        ];
    }

    /**
     * Database-level paginated order retrieval for a specific user.
     *
     * Uses LIMIT/OFFSET at the query layer so we never load the full order
     * history into memory. This is the correct method for any user-facing
     * paginated list — getByUser() should only be used for small bounded
     * fetches (e.g. the overview "last 5 orders").
     *
     * @return array{data: Collection, pagination: array{total: int, per_page: int, current_page: int, total_pages: int}}
     */
    public function getByUserPaginated(int $userId, int $page = 1, int $perPage = 10): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage)); // Guard against absurd values

        $base = Order::where('user_id', $userId);

        $total = (clone $base)->count();

        $orders = (clone $base)
            ->with([
                'items.product',
                'shippingAddress',
                'billingAddress',
                'items.subscription'
            ])
            ->whereNotNull('one_time_subscription_id')
            ->orderBy('created_at', 'desc')
            ->limit($perPage)
            ->offset(($page - 1) * $perPage)
            ->get();

        return [
            'data' => $orders,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'total_pages' => (int)ceil($total / $perPage),
            ],
        ];
    }
}