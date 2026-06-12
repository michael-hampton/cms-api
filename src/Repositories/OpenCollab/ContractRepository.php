<?php

namespace App\Repositories\OpenCollab;

use App\Enums\OpenCollab\ContractStatus;
use App\Framework\Support\Collection;
use App\Models\Contract;
use App\Models\UserContractSignature;
use App\Repositories\Repository;

class ContractRepository extends Repository
{
    // ── Version Resolution ────────────────────────────────────────────────────

    /**
     * Highest version regardless of status. Used by admin listing only.
     */
    public function latestForSite(int $siteId): ?Contract
    {
        return Contract::where('site_id', $siteId)
            ->orderByDesc('version')
            ->first();
    }

    /**
     * Latest published version. Used by compliance/onboarding resolution.
     * Drafts and archived versions are intentionally excluded.
     */
    public function latestPublishedForSite(int $siteId): ?Contract
    {
        return Contract::where('site_id', $siteId)
            ->where('status', ContractStatus::Published->value)
            ->orderByDesc('version')
            ->first();
    }

    public function getContractsForSite(int $siteId): Collection
    {
        return Contract::where('site_id', $siteId)
            ->orderByDesc('version')
            ->get();
    }

    // ── Lifecycle Writes ──────────────────────────────────────────────────────

    /**
     * Transition a draft contract to published.
     * Sets published_at, published_by, and increments version to the next
     * available number for this site.
     */
    public function publish(Contract $contract, int $publishedByUserId): Contract
    {
        $contract->update([
            'status' => ContractStatus::Published->value,
            'published_at' => date('Y-m-d H:i:s'),
            'published_by' => $publishedByUserId,
        ]);

        return $contract->fresh();
    }

    /**
     * Transition a published contract to archived.
     * Archived records remain historically queryable but are excluded from
     * compliance resolution.
     */
    public function archive(Contract $contract, int $archivedByUserId): Contract
    {
        $contract->update([
            'status' => ContractStatus::Archived->value,
            'archived_at' => date('Y-m-d H:i:s'),
            'archived_by' => $archivedByUserId,
        ]);

        return $contract->fresh();
    }

    // ── Version Sequencing ────────────────────────────────────────────────────

    public function nextVersionNumber(int $siteId): int
    {
        $latest = $this->latestForSite($siteId);

        return $latest ? $latest->version + 1 : 1;
    }

    // ── Signature Queries ─────────────────────────────────────────────────────

    public function hasSigned(int $userId, int $contractId): bool
    {
        return UserContractSignature::where('user_id', $userId)
            ->where('contract_id', $contractId)
            ->exists();
    }

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

    public function recordSignature(
        int $userId,
        int $contractId,
        string $ipAddress,
        ?int $contractVersion = null,
        ?string $userAgent = null,
    ): UserContractSignature
    {
        $signature = new UserContractSignature();
        $signature->user_id = $userId;
        $signature->contract_id = $contractId;
        $signature->contract_version = $contractVersion;
        $signature->signed_at = date('Y-m-d H:i:s');
        $signature->ip_address = $ipAddress;
        $signature->user_agent = $userAgent;
        $signature->save();

        return $signature;
    }

    protected function getModelClass(): string
    {
        return Contract::class;
    }
}
