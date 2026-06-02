<?php

namespace App\Controllers\Subscription;

use App\Controllers\Controller;
use App\Framework\Http\JsonResponse;
use App\Services\Subscriptions\Communications\SubscriptionCommunicationService;

class SubscriptionCommunicationHistoryController extends Controller
{
    public function __construct(
        private readonly SubscriptionCommunicationService $service,
    ) {
        parent::__construct();
    }

    public function index(int $subscriptionId): JsonResponse
    {
        return $this->resourceResponse(['history' => $this->service->historyForSubscription($subscriptionId)]);
    }

    public function communication(int $communicationId): JsonResponse
    {
        try {
            return $this->jsonResponse(['history' => $this->service->historyForCommunication($communicationId)]);
        } catch (\RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), 404);
        }
    }
}
