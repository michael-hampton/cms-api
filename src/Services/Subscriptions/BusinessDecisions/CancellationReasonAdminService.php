<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\BusinessDecisions;

use App\Framework\Database\Database;
use App\Models\CancellationReason;
use App\Repositories\Subscriptions\BusinessDecisions\CancellationReasonRepository;
use InvalidArgumentException;

class CancellationReasonAdminService
{
    public function __construct(
        private readonly CancellationReasonRepository $repository,
        private readonly Database $database,
    ) {
    }

    public function list(
        int $page = 1,
        int $perPage = 20,
        ?string $search = null,
        string $sortBy = 'sort_order',
        string $sortOrder = 'asc',
    ): array {
        return $this->repository->paginateAdmin($perPage, $page, $search, $sortBy, $sortOrder);
    }

    public function find(int $id): CancellationReason
    {
        $reason = $this->repository->find($id);

        if ($reason === null) {
            throw new InvalidArgumentException("Cancellation reason #{$id} not found.");
        }

        return $reason;
    }

    public function create(array $payload): CancellationReason
    {
        if ($this->repository->existsByCode($payload['code'])) {
            throw new InvalidArgumentException("Cancellation reason code \"{$payload['code']}\" already exists.");
        }

        return $this->repository->create([
            'code' => trim($payload['code']),
            'label' => trim($payload['label']),
            'requires_note' => $payload['requires_note'] ?? false,
            'is_active' => $payload['is_active'] ?? true,
            'sort_order' => $payload['sort_order'] ?? 0,
        ]);
    }

    public function update(int $id, array $payload): CancellationReason
    {
        $reason = $this->find($id);

        if (isset($payload['code']) && $this->repository->existsByCode($payload['code'], $reason->id)) {
            throw new InvalidArgumentException("Cancellation reason code \"{$payload['code']}\" already exists.");
        }

        $this->repository->update($reason->id, array_filter([
            'code' => isset($payload['code']) ? trim($payload['code']) : null,
            'label' => isset($payload['label']) ? trim($payload['label']) : null,
            'requires_note' => $payload['requires_note'] ?? null,
            'is_active' => $payload['is_active'] ?? null,
            'sort_order' => $payload['sort_order'] ?? null,
        ], static fn ($value) => $value !== null));

        return $this->find($reason->id);
    }

    /**
     * Reasons are never hard-deleted — they may already be referenced by
     * historic subscriptions.cancellation_reason_id — deactivating keeps
     * that history intact while removing the reason from the active
     * cancel-save journey.
     */
    public function deactivate(int $id): void
    {
        $reason = $this->find($id);
        $this->repository->update($reason->id, ['is_active' => false]);
    }
}
