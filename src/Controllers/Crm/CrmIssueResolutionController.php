<?php

declare(strict_types=1);

namespace App\Controllers\Crm;

use App\Controllers\Concerns\RequiresSitePermission;
use App\Controllers\Controller;
use App\Enums\Subscriptions\ReplacementResolution;
use App\Framework\Authorization\Auth;
use App\Framework\Http\Request;
use App\Framework\Support\Logger;
use App\Framework\Support\SiteContext;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\IssueResolutionService;

class CrmIssueResolutionController extends Controller
{
    use RequiresSitePermission;

    private SubscriptionRepository $subscriptionRepository;
    private IssueResolutionService $issueResolutionService;

    public function __construct(
        SubscriptionRepository $subscriptionRepository,
        IssueResolutionService $issueResolutionService
    ) {
        parent::__construct();
        $this->subscriptionRepository = $subscriptionRepository;
        $this->issueResolutionService = $issueResolutionService;
    }

    public function resolve(Request $request, int $memberId, int $subscriptionId, int $issueId): mixed
    {
        return $this->handleResolution($request, $memberId, $subscriptionId, $issueId, null);
    }

    public function replace(Request $request, int $memberId, int $subscriptionId, int $issueId): mixed
    {
        return $this->handleResolution($request, $memberId, $subscriptionId, $issueId, ReplacementResolution::REPLACE);
    }

    private function handleResolution(
        Request $request,
        int $memberId,
        int $subscriptionId,
        int $issueId,
        ?ReplacementResolution $forcedDecision
    ): mixed {
        if (!Auth::check()) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        if ($response = $this->requireSitePermission('crm.subscriptions.edit')) {
            return $response;
        }

        $siteId = SiteContext::getId();
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription || (int) $subscription->member_id !== $memberId || (int) $subscription->site_id !== (int) $siteId) {
            return $this->errorResponse('Subscription not found.', 404);
        }

        $reason = trim((string) $request->input('reason', ''));

        if ($reason === '') {
            return $this->errorResponse('reason is required.', 422);
        }

        try {
            $decision = $forcedDecision ?: ReplacementResolution::fromRequest((string) $request->input('decision', ReplacementResolution::REPLACE->value));
            $businessDecision = filter_var($request->input('business_decision', false), FILTER_VALIDATE_BOOLEAN);
            $result = $this->issueResolutionService->resolve(
                $subscriptionId,
                $issueId,
                $decision,
                $reason,
                (int) Auth::id(),
                (int) $siteId,
                $businessDecision
            );

            return $this->resourceResponse([
                'success' => true,
                'message' => $decision === ReplacementResolution::REPLACE
                    ? 'Issue replacement requested successfully.'
                    : 'Subscription extended by one issue.',
                'decision' => $result->decision,
                'resolution' => $result->resolution,
                'replacement' => $result->replacement ?? null,
                'extension_fulfilment' => $result->extension_fulfilment ?? null,
            ], 201);
        } catch (\InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        } catch (\Throwable $exception) {
            Logger::error('Failed to resolve subscription issue', [
                'subscription_id' => $subscriptionId,
                'issue_id' => $issueId,
                'error' => $exception->getMessage(),
            ]);

            return $this->errorResponse($exception->getMessage(), 500);
        }
    }
}
