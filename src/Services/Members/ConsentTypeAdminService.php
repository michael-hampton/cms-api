<?php

namespace App\Services\Members;

use App\Framework\Database\Database;
use App\Models\ConsentType;
use App\Repositories\Members\Consents\ConsentTypeRepository;

class ConsentTypeAdminService
{
    public function __construct(
        private readonly ConsentTypeRepository $repository,
        private readonly Database              $database,
    )
    {
    }

    public function list(
        int     $page = 1,
        int     $perPage = 20,
        ?string $search = null,
        ?string $category = null,
        string  $sortBy = 'name',
        string  $sortOrder = 'asc',
    ): array
    {
        return $this->repository->paginateAdmin($perPage, $page, $search, $category, $sortBy, $sortOrder);
    }

    public function create(array $payload): ConsentType
    {
        if ($this->repository->existsByCode($payload['code'])) {
            throw new \InvalidArgumentException("Consent type code \"{$payload['code']}\" already exists.");
        }

        return $this->database->transaction(function () use ($payload) {
            return $this->repository->create([
                'code' => trim($payload['code']),
                'name' => trim($payload['name']),
                'description' => $payload['description'] ?? null,
                'category' => $payload['category'],
                'required' => $payload['required'] ?? false,
                'retention_days' => $payload['retention_days'] ?? null,
                'data_purposes' => $payload['data_purposes'] ?? [],
                'is_active' => $payload['is_active'] ?? true,
            ]);
        });
    }

    public function update(int $id, array $payload): ConsentType
    {
        $consentType = $this->find($id);

        if (
            isset($payload['code']) &&
            $this->repository->existsByCode($payload['code'], $consentType->id)
        ) {
            throw new \InvalidArgumentException("Consent type code \"{$payload['code']}\" already exists.");
        }

        return $this->database->transaction(function () use ($consentType, $payload) {
            $this->repository->update($consentType->id, array_filter([
                'code' => isset($payload['code']) ? trim($payload['code']) : null,
                'name' => isset($payload['name']) ? trim($payload['name']) : null,
                'description' => $payload['description'] ?? null,
                'category' => $payload['category'] ?? null,
                'required' => $payload['required'] ?? null,
                'retention_days' => $payload['retention_days'] ?? null,
                'data_purposes' => $payload['data_purposes'] ?? null,
                'is_active' => $payload['is_active'] ?? null,
            ], fn($value) => $value !== null));

            return $this->find($consentType->id);
        });
    }

    public function find(int $id): ConsentType
    {
        $consentType = $this->repository->find($id);

        if ($consentType === null) {
            throw new \InvalidArgumentException("Consent type #{$id} not found.");
        }

        return $consentType;
    }

    public function delete(int $id): void
    {
        $consentType = $this->find($id);

        $this->database->transaction(function () use ($consentType) {
            $this->repository->delete($consentType->id);
        });
    }
}
