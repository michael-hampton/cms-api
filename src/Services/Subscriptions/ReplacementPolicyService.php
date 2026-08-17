<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\ReplacementPolicy;
use App\Repositories\Subscriptions\ReplacementPolicyRepository;

/**
 * Orchestrates ReplacementPolicy CRUD and enforces the one invariant
 * that spans multiple rows: at most one active default policy per site.
 *
 * Everything else (field validation) happens in ReplacementPolicyRequest
 * before this service is ever called — this class only concerns itself
 * with cross-row business rules.
 */
class ReplacementPolicyService
{
    public function __construct(
        private readonly ReplacementPolicyRepository $policyRepository,
        private readonly Database $database,
        private readonly Logger $logger,
    ) {
    }

    public function list(int $siteId): \App\Framework\Support\Collection
    {
        return $this->policyRepository->listForSite($siteId);
    }

    public function find(int $id, int $siteId): ?ReplacementPolicy
    {
        return $this->policyRepository->findForSite($id, $siteId);
    }

    public function create(int $siteId, array $data): ReplacementPolicy
    {
        $data['site_id'] = $siteId;

        if (($data['is_default'] ?? false) === true) {
            // 2 writes (clear existing default + create) -> transaction.
            return $this->database->transaction(function () use ($siteId, $data) {
                $this->policyRepository->clearDefaultForSite($siteId);

                return $this->policyRepository->create($data);
            });
        }

        return $this->policyRepository->create($data);
    }

    public function update(int $id, int $siteId, array $data): ReplacementPolicy
    {
        $policy = $this->policyRepository->findForSite($id, $siteId);

        if (!$policy) {
            throw new \InvalidArgumentException('Replacement policy not found.');
        }

        $becomingDefault = ($data['is_default'] ?? false) === true && !$policy->is_default;

        if ($becomingDefault) {
            // 2 writes (clear existing default + update this one) -> transaction.
            return $this->database->transaction(function () use ($id, $siteId, $data) {
                $this->policyRepository->clearDefaultForSite($siteId, $id);

                return $this->policyRepository->update($id, $data);
            });
        }

        $updated = $this->policyRepository->update($id, $data);

        if (!$updated) {
            throw new \InvalidArgumentException('Replacement policy not found.');
        }

        return $updated;
    }

    /**
     * Soft delete: sets active = false rather than physically removing
     * the row. Historical resolutions reference replacement_policy_id
     * with a RESTRICT foreign key specifically so audit records never
     * point at a deleted row — a hard delete here would either violate
     * that constraint (if referenced) or silently break the audit trail
     * (if it didn't).
     *
     * Refuses to deactivate a site's only active default policy, since
     * that would make every future resolution on that site throw a
     * configuration exception (ReplacementPolicyResolver::resolveDefault)
     * until someone notices and fixes it. Reassign the default first.
     */
    public function deactivate(int $id, int $siteId): ReplacementPolicy
    {
        $policy = $this->policyRepository->findForSite($id, $siteId);

        if (!$policy) {
            throw new \InvalidArgumentException('Replacement policy not found.');
        }

        if ($policy->is_default && $policy->active) {
            $otherDefault = $this->policyRepository->findOtherActiveDefault($siteId, $id);

            if (!$otherDefault) {
                throw new \InvalidArgumentException(
                    'Cannot deactivate the only active default policy for this site. Assign a new default first.'
                );
            }
        }

        $updated = $this->policyRepository->update($id, ['active' => false]);

        $this->logger->info('Replacement policy deactivated', [
            'policy_id' => $id,
            'site_id' => $siteId,
        ]);

        return $updated;
    }
}