<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\BusinessDecisions;

use App\Enums\Subscriptions\BusinessDecisions\BusinessDecisionCategoryEnum;
use App\Framework\Database\Database;
use App\Models\BusinessDecision;
use App\Models\BusinessDecisionAssignment;
use App\Models\CancellationReasonPolicy;
use App\Models\Site;
use App\Models\SubscriptionPlan;
use App\Models\SuspensionPolicy;
use App\Repositories\Subscriptions\BusinessDecisions\BusinessDecisionAssignmentRepository;
use App\Repositories\Subscriptions\BusinessDecisions\BusinessDecisionRepository;
use App\Repositories\Subscriptions\BusinessDecisions\CancellationReasonPolicyRepository;
use App\Repositories\Subscriptions\BusinessDecisions\CancellationReasonRepository;
use App\Repositories\Subscriptions\BusinessDecisions\SuspensionPolicyRepository;
use InvalidArgumentException;

/**
 * Admin orchestration for the BusinessDecision CRUD, its per-category
 * assignment to a Site ("brand") or SubscriptionPlan ("product"), and —
 * for the CANCELLATIONS category specifically — the per-reason save
 * options attached to a decision. Everything here is a write path, so
 * multi-step operations go through Database::transaction() per the
 * coding contract.
 */
class BusinessDecisionAdminService
{
    /** Maps the request's assignable_type value to the morph class. */
    private const ASSIGNABLE_TYPE_MAP = [
        'site' => Site::class,
        'plan' => SubscriptionPlan::class,
    ];

    public function __construct(
        private readonly BusinessDecisionRepository $decisionRepository,
        private readonly BusinessDecisionAssignmentRepository $assignmentRepository,
        private readonly CancellationReasonRepository $reasonRepository,
        private readonly CancellationReasonPolicyRepository $reasonPolicyRepository,
        private readonly SuspensionPolicyRepository $suspensionPolicyRepository,
        private readonly Database $database,
    ) {
    }

    public function list(?BusinessDecisionCategoryEnum $category = null): array
    {
        $query = BusinessDecision::query();

        if ($category !== null) {
            $query = $query->where('category', $category->value);
        }

        return $query->orderBy('category', 'asc')->orderBy('name', 'asc')->get()->all();
    }

    public function find(int $id): BusinessDecision
    {
        $decision = $this->decisionRepository->find($id);

        if ($decision === null) {
            throw new InvalidArgumentException("Business Decision #{$id} not found.");
        }

        return $decision;
    }

    public function create(array $payload): BusinessDecision
    {
        $category = $this->resolveCategory($payload['category']);
        $isDefault = (bool) ($payload['is_default'] ?? false);

        return $this->database->transaction(function () use ($category, $isDefault, $payload) {
            $decision = $this->decisionRepository->create([
                'category' => $category->value,
                'name' => trim($payload['name']),
                'description' => $payload['description'] ?? null,
                'is_default' => $isDefault,
                'is_active' => $payload['is_active'] ?? true,
            ]);

            if ($isDefault) {
                // Two writes (create above + clearing other defaults) —
                // both inside this transaction boundary.
                $this->decisionRepository->clearDefaultForCategory($category, (int) $decision->id);
            }

            return $decision;
        });
    }

    public function update(int $id, array $payload): BusinessDecision
    {
        $decision = $this->find($id);
        $makingDefault = isset($payload['is_default']) && (bool) $payload['is_default'] === true;

        return $this->database->transaction(function () use ($decision, $payload, $makingDefault) {
            $this->decisionRepository->update($decision->id, array_filter([
                'name' => isset($payload['name']) ? trim($payload['name']) : null,
                'description' => $payload['description'] ?? null,
                'is_default' => $payload['is_default'] ?? null,
                'is_active' => $payload['is_active'] ?? null,
            ], static fn ($value) => $value !== null));

            if ($makingDefault) {
                $this->decisionRepository->clearDefaultForCategory($decision->category, $decision->id);
            }

            return $this->find($decision->id);
        });
    }

    /**
     * Assigns a decision to a Site ("brand") or SubscriptionPlan
     * ("product") for whichever category that decision belongs to.
     * A decision can only be assigned within its own category — an
     * admin can't attach a FULFILMENT decision under the guise of
     * governing cancellations.
     */
    public function assign(string $assignableType, int $assignableId, int $businessDecisionId): BusinessDecisionAssignment
    {
        $decision = $this->find($businessDecisionId);

        if (!isset(self::ASSIGNABLE_TYPE_MAP[$assignableType])) {
            throw new InvalidArgumentException("Unknown assignable_type \"{$assignableType}\" — expected \"site\" or \"plan\".");
        }

        $modelClass = self::ASSIGNABLE_TYPE_MAP[$assignableType];

        return $this->assignmentRepository->upsert($modelClass, $assignableId, BusinessDecisionCategoryEnum::tryFrom($decision->category), $decision->id);
    }

    /**
     * Every active reason, cross-joined with whatever row (if any) this
     * decision has for it — so the admin UI can show every reason even
     * when most have never been overridden (null fields = "inherits").
     *
     * @return array<int, array{reason: \App\Models\CancellationReason, policy: ?CancellationReasonPolicy}>
     */
    public function listReasonPolicies(int $businessDecisionId): array
    {
        $this->find($businessDecisionId); // 404s if the decision doesn't exist

        $policiesByReasonId = $this->reasonPolicyRepository->findAllForDecision($businessDecisionId);

        return array_map(
            static fn ($reason) => [
                'reason' => $reason,
                'policy' => $policiesByReasonId[(int) $reason->id] ?? null,
            ],
            $this->reasonRepository->listActive()->all(),
        );
    }

    public function getSuspensionPolicy(int $businessDecisionId): ?\App\Models\SuspensionPolicy
    {
        $this->find($businessDecisionId); // 404s if the decision doesn't exist

        return $this->suspensionPolicyRepository->findForDecision($businessDecisionId);
    }

    /**
     * Create/update the save-options row for one (decision, reason)
     * pair. Only meaningful for CANCELLATIONS-category decisions, but
     * left generic (no hard category check) since the underlying table
     * only ever stores reason-specific fields regardless.
     */
    public function upsertReasonPolicy(int $businessDecisionId, int $cancellationReasonId, array $fields): CancellationReasonPolicy
    {
        $this->find($businessDecisionId); // 404s if the decision doesn't exist

        return $this->reasonPolicyRepository->upsert(
            $businessDecisionId,
            $cancellationReasonId,
            array_filter($fields, static fn ($value) => $value !== null),
        );
    }

    /**
     * Create/update the single suspension-governance row for a decision.
     */
    public function upsertSuspensionPolicy(int $businessDecisionId, array $fields): SuspensionPolicy
    {
        $this->find($businessDecisionId); // 404s if the decision doesn't exist

        return $this->suspensionPolicyRepository->upsert(
            $businessDecisionId,
            array_filter($fields, static fn ($value) => $value !== null),
        );
    }

    private function resolveCategory(string $value): BusinessDecisionCategoryEnum
    {
        $category = BusinessDecisionCategoryEnum::tryFrom($value);

        if ($category === null) {
            throw new InvalidArgumentException("Unknown Business Decision category \"{$value}\".");
        }

        return $category;
    }
}