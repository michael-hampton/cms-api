<?php

namespace App\Repositories;

use App\Framework\Support\Collection;
use App\Models\PageHistory;

class PageHistoryRepository extends Repository
{
    protected function getModelClass(): string
    {
        return PageHistory::class;
    }

    public function getPageHistory(int $pageId, int $limit = 50): Collection
    {
        return PageHistory::with(['user'])
            ->where('page_id', $pageId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getRecentHistory(int $siteId, int $limit = 20): Collection
    {
        $query = PageHistory::with(['page', 'user'])
            ->where('site_id', $siteId)
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        return $this->applySiteFilter($query)->get();
    }

    public function getUserHistory(int $userId, int $limit = 50): Collection
    {
        $query = PageHistory::with(['page'])
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit);

        return $this->applySiteFilter($query)->get();
    }

    public function getHistoryByAction(int $pageId, string $action): Collection
    {
        return PageHistory::with(['user'])
            ->where('page_id', $pageId)
            ->where('action', $action)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getHistoryBetween(int $pageId, string $startDate, string $endDate): Collection
    {
        return PageHistory::with(['user'])
            ->where('page_id', $pageId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function findById(int $id): ?PageHistory
    {
        return PageHistory::with(['page', 'user'])->find($id);
    }

    public function deletePageHistory(int $pageId): bool
    {
        return PageHistory::where('page_id', $pageId)->delete();
    }

    public function deleteOlderThan(int $days): int
    {
        $date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        return PageHistory::where('created_at', '<', $date)->delete();
    }
}