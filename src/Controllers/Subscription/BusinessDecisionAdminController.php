<?php

namespace App\Controllers\Subscription;

use App\Controllers\Controller;
use App\Enums\Subscriptions\BusinessDecisions\BusinessDecisionCategoryEnum;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Models\BusinessDecision;
use App\Models\CancellationReasonPolicy;
use App\Requests\Subscription\BusinessDecisions\AssignBusinessDecisionRequest;
use App\Requests\Subscription\BusinessDecisions\StoreBusinessDecisionRequest;
use App\Requests\Subscription\BusinessDecisions\UpdateBusinessDecisionRequest;
use App\Requests\Subscription\BusinessDecisions\UpsertCancellationReasonPolicyRequest;
use App\Requests\Subscription\BusinessDecisions\UpsertSuspensionPolicyRequest;
use App\Services\Subscriptions\BusinessDecisions\BusinessDecisionAdminService;
use InvalidArgumentException;

class BusinessDecisionAdminController extends Controller
{
    public function __construct(
        private readonly BusinessDecisionAdminService $service,
    ) {
        parent::__construct();
    }

    public function index(Request $request): JsonResponse
    {
        $category = $request->get('category')
            ? BusinessDecisionCategoryEnum::tryFrom((string) $request->get('category'))
            : null;

        $decisions = $this->service->list($category);

        return $this->resourceResponse([
            'data' => array_map(fn (BusinessDecision $decision) => $this->format($decision), $decisions),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        try {
            return $this->resourceResponse($this->format($this->service->find($id)));
        } catch (InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 404);
        }
    }

    public function store(StoreBusinessDecisionRequest $request): JsonResponse
    {
        try {
            return $this->resourceResponse($this->format($this->service->create($request->validated())), 201);
        } catch (InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        }
    }

    public function update(int $id, UpdateBusinessDecisionRequest $request): JsonResponse
    {
        try {
            return $this->resourceResponse($this->format($this->service->update($id, $request->validated())));
        } catch (InvalidArgumentException $exception) {
            $status = str_contains(strtolower($exception->getMessage()), 'not found') ? 404 : 422;
            return $this->errorResponse($exception->getMessage(), $status);
        }
    }

    /**
     * POST /api/admin/business-decisions/assign
     * Assigns a decision to a Site ("brand") or SubscriptionPlan
     * ("product") — see business_decision_assignments migration.
     */
    public function assign(AssignBusinessDecisionRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $assignment = $this->service->assign(
                $data['assignable_type'],
                (int) $data['assignable_id'],
                (int) $data['business_decision_id'],
            );

            return $this->resourceResponse([
                'id' => $assignment->id,
                'assignable_type' => $data['assignable_type'],
                'assignable_id' => $assignment->assignable_id,
                'category' => $assignment->category,
                'business_decision_id' => $assignment->business_decision_id,
            ]);
        } catch (InvalidArgumentException $exception) {
            $status = str_contains(strtolower($exception->getMessage()), 'not found') ? 404 : 422;
            return $this->errorResponse($exception->getMessage(), $status);
        }
    }

    /**
     * GET /api/admin/business-decisions/{id}/reason-policies
     * Every active reason for this decision, with whatever override (if
     * any) exists — used to populate the admin editor grid.
     */
    public function listReasonPolicies(int $id): JsonResponse
    {
        try {
            $rows = $this->service->listReasonPolicies($id);

            return $this->resourceResponse([
                'data' => array_map(fn (array $row) => [
                    'cancellation_reason_id' => $row['reason']->id,
                    'code' => $row['reason']->code,
                    'label' => $row['reason']->label,
                    'policy' => $row['policy'] ? $this->formatReasonPolicy($row['policy']) : null,
                ], $rows),
            ]);
        } catch (InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 404);
        }
    }

    /**
     * PUT /api/admin/business-decisions/{id}/reason-policies
     * Creates/updates the save-options row for one cancellation reason
     * under this decision.
     */
    public function upsertReasonPolicy(int $id, UpsertCancellationReasonPolicyRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $policy = $this->service->upsertReasonPolicy($id, (int) $data['cancellation_reason_id'], [
                'show_save_actions' => $data['show_save_actions'] ?? null,
                'allow_discount' => $data['allow_discount'] ?? null,
                'allow_offer_switch' => $data['allow_offer_switch'] ?? null,
                'allow_cancel' => $data['allow_cancel'] ?? null,
                'refund_max_percent' => $data['refund_max_percent'] ?? null,
                'marketing_consent' => $data['marketing_consent'] ?? null,
            ]);

            return $this->resourceResponse($this->formatReasonPolicy($policy));
        } catch (InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 404);
        }
    }

    /**
     * GET /api/admin/business-decisions/{id}/suspension-policy
     */
    public function getSuspensionPolicy(int $id): JsonResponse
    {
        try {
            $policy = $this->service->getSuspensionPolicy($id);

            return $this->resourceResponse([
                'data' => $policy ? [
                    'id' => $policy->id,
                    'business_decision_id' => $policy->business_decision_id,
                    'allow_suspend' => $policy->allow_suspend,
                    'requires_note' => $policy->requires_note,
                ] : null,
            ]);
        } catch (InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 404);
        }
    }

    /**
     * PUT /api/admin/business-decisions/{id}/suspension-policy
     * Creates/updates the allow_suspend/requires_note row for this
     * decision (SUSPENSIONS category).
     */
    public function upsertSuspensionPolicy(int $id, UpsertSuspensionPolicyRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $policy = $this->service->upsertSuspensionPolicy($id, [
                'allow_suspend' => $data['allow_suspend'] ?? null,
                'requires_note' => $data['requires_note'] ?? null,
            ]);

            return $this->resourceResponse([
                'id' => $policy->id,
                'business_decision_id' => $policy->business_decision_id,
                'allow_suspend' => $policy->allow_suspend,
                'requires_note' => $policy->requires_note,
            ]);
        } catch (InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 404);
        }
    }

    private function format(BusinessDecision $decision): array
    {
        return [
            'id' => $decision->id,
            'category' => $decision->category,
            'name' => $decision->name,
            'description' => $decision->description,
            'is_default' => (bool) $decision->is_default,
            'is_active' => (bool) $decision->is_active,
        ];
    }

    private function formatReasonPolicy(CancellationReasonPolicy $policy): array
    {
        return [
            'id' => $policy->id,
            'business_decision_id' => $policy->business_decision_id,
            'cancellation_reason_id' => $policy->cancellation_reason_id,
            'show_save_actions' => $policy->show_save_actions,
            'allow_discount' => $policy->allow_discount,
            'allow_offer_switch' => $policy->allow_offer_switch,
            'allow_cancel' => $policy->allow_cancel,
            'refund_max_percent' => $policy->refund_max_percent,
            'marketing_consent' => $policy->marketing_consent,
        ];
    }
}