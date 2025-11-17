<?php

namespace App\Repositories;

use App\Framework\Support\Collection;
use App\Models\Order;
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
        $query = Order::where('user_id', $userId)
            ->orderBy('created_at', 'desc');

        if ($limit) {
            $query->limit($limit);
        }

        return $this->applySiteFilter($query)->get();
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
}