<?php

namespace App\Controllers\Subscription;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\SubscriptionHistoryService;

final class ShopAccountSubscriptionHistoryController extends Controller
{
    public function __construct(
        private readonly SubscriptionHistoryService $historyService,
        private readonly SubscriptionRepository $subscriptionRepository,
    ) {
        parent::__construct();
    }

    public function __invoke(int $id, Request $request): mixed
    {
        $member = MemberAuth::getMember();

        if (!$member) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Unauthorised.',
            ], 401);
        }

        $subscription = $this->subscriptionRepository->find($id);

        if (!$subscription || $subscription->member_id !== $member->id) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Subscription not found.',
            ], 404);
        }

        $page = $this->positiveInteger($request->input('page', 1), 1);
        $perPage = $this->positiveInteger($request->input('per_page', 10), 10);
        $perPage = min($perPage, 50);

        $history = $this->historyService->getPaginatedHistory(
            $id,
            $page,
            $perPage,
        );

        return $this->jsonResponse([
            'success' => true,
            'events' => $history['events'],
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $history['total'],
                'has_more' => ($page * $perPage) < $history['total'],
            ],
        ]);
    }

    private function positiveInteger(mixed $value, int $default): int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value) && (int) $value > 0) {
            return (int) $value;
        }

        return $default;
    }
}
