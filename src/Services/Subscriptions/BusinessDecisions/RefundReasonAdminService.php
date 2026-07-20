<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\BusinessDecisions;

use App\Models\RefundReason;
use App\Repositories\Subscriptions\BusinessDecisions\RefundReasonRepository;
use InvalidArgumentException;

class RefundReasonAdminService
{
    public function __construct(private readonly RefundReasonRepository $repository)
    {
    }

    public function list(int $page = 1, int $perPage = 20, ?string $search = null, string $sortBy = 'sort_order', string $sortOrder = 'asc'): array
    {
        return $this->repository->paginateAdmin($perPage, $page, $search, $sortBy, $sortOrder);
    }

    public function find(int $id): RefundReason
    {
        $reason = $this->repository->find($id);
        if ($reason === null) {
            throw new InvalidArgumentException("Refund reason #{$id} not found.");
        }

        return $reason;
    }

    public function create(array $payload): RefundReason
    {
        $code = trim($payload['code']);
        if ($this->repository->existsByCode($code)) {
            throw new InvalidArgumentException("Refund reason code \"{$code}\" already exists.");
        }

        return $this->repository->create([
            'code' => $code, 'label' => trim($payload['label']),
            'requires_note' => $payload['requires_note'] ?? false,
            'is_active' => $payload['is_active'] ?? true,
            'sort_order' => $payload['sort_order'] ?? 0,
        ]);
    }

    public function update(int $id, array $payload): RefundReason
    {
        $reason = $this->find($id);
        if (isset($payload['code']) && $this->repository->existsByCode(trim($payload['code']), (int) $reason->id)) {
            throw new InvalidArgumentException("Refund reason code \"{$payload['code']}\" already exists.");
        }
        $this->repository->update((int) $reason->id, array_filter([
            'code' => isset($payload['code']) ? trim($payload['code']) : null,
            'label' => isset($payload['label']) ? trim($payload['label']) : null,
            'requires_note' => $payload['requires_note'] ?? null,
            'is_active' => $payload['is_active'] ?? null,
            'sort_order' => $payload['sort_order'] ?? null,
        ], static fn ($value) => $value !== null));

        return $this->find((int) $reason->id);
    }

    public function deactivate(int $id): void
    {
        $this->repository->update((int) $this->find($id)->id, ['is_active' => false]);
    }
}
