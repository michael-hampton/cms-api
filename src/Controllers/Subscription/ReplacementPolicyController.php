<?php

declare(strict_types=1);

namespace App\Controllers\Subscription;

use App\Controllers\Concerns\RequiresSitePermission;
use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Http\Request;
use App\Framework\Support\Logger;
use App\Framework\Support\SiteContext;
use App\Requests\Subscription\BaseReplacementPolicyRequest;
use App\Requests\Subscription\ReplacementPolicyStoreRequest;
use App\Requests\Subscription\ReplacementPolicyUpdateRequest;
use App\Services\Subscriptions\ReplacementPolicyService;

/**
 * ASSUMPTION FLAGGED: I couldn't access the repo (the GitHub link
 * returned 404 on both the API and codeload), so I don't have your
 * routing file/convention — route registration is still needed. Suggested
 * mapping:
 *
 *   GET    /subscriptions/replacement-policies          -> index
 *   POST   /subscriptions/replacement-policies          -> store
 *   GET    /subscriptions/replacement-policies/{id}     -> show
 *   PUT  /subscriptions/replacement-policies/{id}     -> update
 *   DELETE /subscriptions/replacement-policies/{id}     -> destroy  (soft: sets active=false)
 *
 * ALSO ASSUMED: errorResponse($message, $code, $details) accepts a 3rd
 * argument for field-level errors — I've only seen the 2-arg form used
 * in CrmIssueResolutionController. Drop the 3rd argument below if your
 * Controller base doesn't support it.
 */
class ReplacementPolicyController extends Controller
{
    use RequiresSitePermission;

    public function __construct(
        private readonly ReplacementPolicyService $policyService,
    ) {
        parent::__construct();
    }

    public function index(Request $request): mixed
    {
        if (!Auth::check()) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        if ($response = $this->requireSitePermission('crm.subscriptions.edit')) {
            return $response;
        }

        $siteId = (int) SiteContext::getId();

        return $this->resourceResponse([
            'success' => true,
            'policies' => $this->policyService->list($siteId),
        ]);
    }

    public function show(Request $request, int $id): mixed
    {
        if (!Auth::check()) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        if ($response = $this->requireSitePermission('crm.subscriptions.edit')) {
            return $response;
        }

        $siteId = (int) SiteContext::getId();
        $policy = $this->policyService->find($id, $siteId);

        if (!$policy) {
            return $this->errorResponse('Replacement policy not found.', 404);
        }

        return $this->resourceResponse([
            'success' => true,
            'policy' => $policy,
        ]);
    }

    public function store(Request $request): mixed
    {
        if (!Auth::check()) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        if ($response = $this->requireSitePermission('crm.subscriptions.edit')) {
            return $response;
        }

        $formRequest = ReplacementPolicyStoreRequest::createFromRequest($request);

        if ($formRequest->fails()) {
            return $this->errorResponse('Validation failed.', 422, $formRequest->getValidationErrors());
        }

        $siteId = (int) SiteContext::getId();
        $data = $this->coerceBooleans($formRequest->validated());

        try {
            $policy = $this->policyService->create($siteId, $data);

            return $this->resourceResponse([
                'success' => true,
                'policy' => $policy,
            ], 201);
        } catch (\InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        } catch (\Throwable $exception) {
            Logger::error('Failed to create replacement policy', [
                'site_id' => $siteId,
                'error' => $exception->getMessage(),
            ]);

            return $this->errorResponse($exception->getMessage(), 500);
        }
    }

    public function update(Request $request, int $id): mixed
    {
        if (!Auth::check()) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        if ($response = $this->requireSitePermission('crm.subscriptions.edit')) {
            return $response;
        }

        $formRequest = ReplacementPolicyUpdateRequest::createFromRequest($request);

        if ($formRequest->fails()) {
            return $this->errorResponse('Validation failed.', 422, $formRequest->getValidationErrors());
        }

        $siteId = (int) SiteContext::getId();
        $data = $this->coerceBooleans($formRequest->validated());

        try {
            $policy = $this->policyService->update($id, $siteId, $data);

            return $this->resourceResponse([
                'success' => true,
                'policy' => $policy,
            ]);
        } catch (\InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        } catch (\Throwable $exception) {
            Logger::error('Failed to update replacement policy', [
                'policy_id' => $id,
                'site_id' => $siteId,
                'error' => $exception->getMessage(),
            ]);

            return $this->errorResponse($exception->getMessage(), 500);
        }
    }

    public function destroy(Request $request, int $id): mixed
    {
        if (!Auth::check()) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        if ($response = $this->requireSitePermission('crm.subscriptions.edit')) {
            return $response;
        }

        $siteId = (int) SiteContext::getId();

        try {
            $policy = $this->policyService->deactivate($id, $siteId);

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Replacement policy deactivated.',
                'policy' => $policy,
            ]);
        } catch (\InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        } catch (\Throwable $exception) {
            Logger::error('Failed to deactivate replacement policy', [
                'policy_id' => $id,
                'site_id' => $siteId,
                'error' => $exception->getMessage(),
            ]);

            return $this->errorResponse($exception->getMessage(), 500);
        }
    }

    /**
     * FormRequest::validated() returns raw input values, not cast ones
     * (confirmed by reading FormRequest.php — performValidation() just
     * diffs $data against failed fields, no casting happens). Coerce the
     * known boolean fields the same way CrmIssueResolutionController
     * already does for business_decision, so the repository always gets
     * real PHP booleans rather than "true"/"1"/"on" strings.
     */
    private function coerceBooleans(array $data): array
    {
        foreach (BaseReplacementPolicyRequest::booleanFields() as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = filter_var($data[$field], FILTER_VALIDATE_BOOLEAN);
            }
        }

        return $data;
    }
}