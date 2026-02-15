<?php

namespace App\Repositories\Members\Consents;

use App\Exceptions\Consents\ConsentTypeNotFoundException;
use App\Framework\Support\Collection;
use App\Models\ConsentType;

class ConsentTypeRepository
{
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
}