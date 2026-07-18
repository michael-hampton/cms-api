<?php

declare(strict_types=1);

namespace App\Controllers\Subscription;

use App\Controllers\Controller;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Services\Subscriptions\Communications\SubscriptionCommunicationScopeService;

/**
 * Admin management of enable/disable scoping for a subscription_communication
 * at system, site, or plan level. Kept as its own controller rather than
 * folded into SubscriptionCommunicationController — this is a governance
 * concern (who gets this communication), not the communication's content
 * or schedule.
 */
class SubscriptionCommunicationScopeController extends Controller
{
    public function __construct(
        private readonly SubscriptionCommunicationScopeService $service,
    ) {
        parent::__construct();
    }

    public function index(int $subscription_communication): JsonResponse
    {
        try {
            return $this->resourceResponse([
                'scopes' => $this->service->forCommunication($subscription_communication),
            ]);
        } catch (\RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), 404);
        }
    }

    public function store(int $subscription_communication, Request $request): JsonResponse
    {
        $data = $request->all();

        if (!array_key_exists('is_enabled', $data)) {
            return $this->errorResponse('Validation failed', 422, [
                'is_enabled' => 'The is_enabled field is required.',
            ]);
        }

        try {
            $scope = $this->service->upsert(
                communicationId: $subscription_communication,
                siteId: isset($data['site_id']) ? (int)$data['site_id'] : null,
                subscriptionPlanId: isset($data['subscription_plan_id']) ? (int)$data['subscription_plan_id'] : null,
                isEnabled: (bool)$data['is_enabled'],
            );

            return $this->resourceResponse(['scope' => $scope->toArray()], 201);
        } catch (\RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), 404);
        }
    }

    public function destroy(int $scope): JsonResponse
    {
        if (!$this->service->delete($scope)) {
            return $this->errorResponse('Not found.', 404);
        }

        return $this->jsonResponse(['message' => 'Subscription communication scope deleted.']);
    }
}
