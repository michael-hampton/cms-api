<?php

namespace App\Repositories\OpenCollab;

use App\Framework\Support\Collection;
use App\Models\Contract;
use App\Models\UserContractSignature;
use App\Repositories\Repository;

class ContractRepository extends Repository
{
    public function latestForSite(int $siteId): ?Contract
    {
        return Contract::where('site_id', $siteId)
            ->orderByDesc('version')
            ->first();
    }

    public function getContractsForSite(int $siteId): ?Collection
    {
        return Contract::where('site_id', $siteId)
            ->orderByDesc('version')
            ->get();
    }

    public function hasSigned(int $userId, int $contractId): bool
    {
        return UserContractSignature::where('user_id', $userId)
            ->where('contract_id', $contractId)
            ->exists();
    }

    /**
     * Returns true if ANY user has signed this contract version.
     * Used to guard against editing/deleting signed contracts.
     */
    public function hasAnySigned(int $contractId): bool
    {
        return UserContractSignature::where('contract_id', $contractId)->exists();
    }

    public function getForUser(int $userId, int $contractId): ?UserContractSignature
    {
        return UserContractSignature::where('user_id', $userId)
            ->where('contract_id', $contractId)
            ->first();
    }

    public function recordSignature(int $userId, int $contractId, string $ipAddress): UserContractSignature
    {
        $signature = new UserContractSignature();
        $signature->user_id = $userId;
        $signature->contract_id = $contractId;
        $signature->signed_at = date('Y-m-d H:i:s');
        $signature->ip_address = $ipAddress;
        $signature->save();

        return $signature;
    }

    protected function getModelClass(): string
    {
        return Contract::class;
    }
}