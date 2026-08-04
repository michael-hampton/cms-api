<?php

declare(strict_types=1);

namespace App\Repositories\Subscriptions;

use App\Enums\Subscriptions\AdHocFulfilmentProcess;
use App\Models\AdHocFulfilmentRequest;
use App\Models\Model;

/**
 * Persistence only — see AdHocFulfilmentGenerationService for the
 * orchestration (validation, dispatch, event emission) around these writes.
 */
class AdHocFulfilmentRequestRepository
{
    public function createForPrintBatch(int $printBatchId, int $requestedByUserId): Model
    {
        return AdHocFulfilmentRequest::create([
            'process' => AdHocFulfilmentProcess::PRINT_BATCH->value,
            'print_batch_id' => $printBatchId,
            'requested_by_user_id' => $requestedByUserId,
        ]);
    }

    public function find(int $id): ?Model
    {
        return AdHocFulfilmentRequest::find($id);
    }

    /**
     * Paginated, filterable list of ad-hoc requests for report/listing endpoints.
     *
     * @param array{
     *     process?: string,
     *     requested_by_user_id?: int,
     *     from?: string,
     *     to?: string,
     * } $filters
     */
    public function search(array $filters = [], int $perPage = 25, int $page = 1): array
    {
        $query = AdHocFulfilmentRequest::with(['printBatch', 'requestedBy']);

        if (!empty($filters['process'])) {
            $query->where('process', $filters['process']);
        }

        if (!empty($filters['requested_by_user_id'])) {
            $query->where('requested_by_user_id', (int)$filters['requested_by_user_id']);
        }

        if (!empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        return $query->orderByDesc('created_at')->paginate($perPage, $page);
    }
}