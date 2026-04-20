<?php

namespace App\Repositories\Members\Consents;

use App\Exceptions\Consents\ConsentTypeNotFoundException;
use App\Framework\Support\Collection;
use App\Models\ConsentType;

class ConsentTypeRepository
{
    public function paginateAdmin(
        int     $perPage = 20,
        int     $page = 1,
        ?string $search = null,
        ?string $category = null,
        string  $sortBy = 'name',
        string  $sortOrder = 'asc',
    ): array
    {
        $allowedSort = ['name', 'category', 'created_at'];
        $sortBy = in_array($sortBy, $allowedSort, true) ? $sortBy : 'name';
        $sortOrder = strtolower($sortOrder) === 'desc' ? 'desc' : 'asc';

        $query = ConsentType::query();

        if (!empty($search)) {
            $term = '%' . $search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'LIKE', $term)
                    ->orWhere('code', 'LIKE', $term)
                    ->orWhere('description', 'LIKE', $term);
            });
        }

        if (!empty($category)) {
            $query->where('category', $category);
        }

        return $query->orderBy($sortBy, $sortOrder)->paginate($perPage, $page);
    }

    public function find(int $id): ?ConsentType
    {
        return ConsentType::find($id);
    }

    public function existsByCode(string $code, ?int $excludeId = null): bool
    {
        $query = ConsentType::query()
            ->where('code', trim($code));

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    public function findActiveByCode(string $code): ?ConsentType
    {
        $consentType = ConsentType::where('code', $code)
            ->where('is_active', true)
            ->first();

        if (!$consentType) {
            throw new ConsentTypeNotFoundException($code);
        }

        return $consentType;
    }

    public function findAllActive(): Collection
    {
        return ConsentType::where('is_active', true)->get();
    }

    public function findActiveByCategory(string $category): Collection
    {
        return ConsentType::where('category', $category)
            ->where('is_active', true)
            ->get();
    }

    public function findActiveOptional(): Collection
    {
        return ConsentType::where('is_active', true)
            ->where('required', false)
            ->get();
    }

    public function getActiveMarketingConsents(): Collection
    {
        return $this->findActiveByCategory('marketing');
    }

    public function getAuditLog(int $memberId, string $consentCode = ''): Collection
    {
        $query = \App\Models\ConsentAuditLog::where('member_id', $memberId)
            ->orderBy('created_at', 'desc');

        if ($consentCode) {
            $consentType = $this->findActiveByCode($consentCode);
            $query->where('consent_type_id', $consentType->id);
        }

        return $query->with(['consentType', 'adminUser'])
            ->get();
    }

    public function create(array $data): ConsentType
    {
        return ConsentType::create($data);
    }

    public function update(int $id, array $data): ?ConsentType
    {
        $consentType = $this->find($id);

        if ($consentType === null) {
            return null;
        }

        $consentType->fill($data);
        $consentType->save();

        return $consentType;
    }

    public function delete(int $id): bool
    {
        $consentType = $this->find($id);

        return $consentType ? $consentType->delete() : false;
    }
}
