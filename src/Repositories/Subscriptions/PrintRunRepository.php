<?php

namespace App\Repositories\Subscriptions;

use App\Enums\Subscriptions\PrintRunStatus;
use App\Models\PrintRun;
use App\Repositories\Repository;

/**
 * Persistence for PrintRun records.
 *
 * Owns all queries against the print_runs table. No business logic lives here.
 */
class PrintRunRepository extends Repository
{
    /**
     * @return \App\Framework\Support\Collection<PrintRun>
     */
    public function pendingForIssueDelivery(int $issueDeliveryId): iterable
    {
        return PrintRun::where('issue_delivery_id', $issueDeliveryId)
            ->where('status', PrintRunStatus::PENDING->value)
            ->get();
    }

    /**
     * Paginated list with optional filters.
     *
     * @param array{
     *     status?: string,
     *     issue_delivery_id?: int,
     *     date?: string,
     *     from?: string,
     *     to?: string,
     *     updated_from?: string,
     *     updated_to?: string,
     * } $filters
     */
    public function search(array $filters = [], int $perPage = 25): mixed
    {
        $query = PrintRun::query();

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['issue_delivery_id'])) {
            $query->where('issue_delivery_id', (int)$filters['issue_delivery_id']);
        }

        if (!empty($filters['date'])) {
            $query->whereDate('created_at', $filters['date']);
        }

        if (!empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        if (!empty($filters['updated_from'])) {
            $query->whereDate('updated_at', '>=', $filters['updated_from']);
        }

        if (!empty($filters['updated_to'])) {
            $query->whereDate('updated_at', '<=', $filters['updated_to']);
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    /**
     * List all print runs for a given issue delivery, ordered newest-first.
     *
     * @param array{status?: string} $filters
     */
    public function listForIssueDelivery(int $issueDeliveryId, array $filters = []): iterable
    {
        $query = PrintRun::where('issue_delivery_id', $issueDeliveryId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderByDesc('created_at')->get();
    }

    public function cancelAllPendingForIssueDelivery(int $issueDeliveryId): int
    {
        return PrintRun::where('issue_delivery_id', $issueDeliveryId)
            ->where('status', PrintRunStatus::PENDING->value)
            ->update(['status' => PrintRunStatus::CANCELLED->value]);
    }

    public function findActiveForIssueDelivery(int $issueDeliveryId): ?PrintRun
    {
        return PrintRun::where('issue_delivery_id', $issueDeliveryId)
            ->whereNotIn('status', [
                PrintRunStatus::CANCELLED->value,
                PrintRunStatus::FAILED->value,
            ])
            ->latest()
            ->first();
    }

    protected function getModelClass(): string
    {
        return PrintRun::class;
    }
}