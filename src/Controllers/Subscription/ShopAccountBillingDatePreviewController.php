<?php

namespace App\Controllers\Subscription;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\SubscriptionBillingService;

final class ShopAccountBillingDatePreviewController extends Controller
{
    public function __construct(
        private readonly SubscriptionBillingService $billingService,
        private readonly SubscriptionRepository $subscriptionRepository,
    ) {
        parent::__construct();
    }

    public function __invoke(int $id, Request $request): mixed
    {
        $member = MemberAuth::getMember();

        if (!$member) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorised.'], 401);
        }

        $subscription = $this->subscriptionRepository->find($id);

        if (!$subscription || $subscription->member_id !== $member->id) {
            return $this->jsonResponse(['success' => false, 'message' => 'Subscription not found.'], 404);
        }

        $value = $request->input('day_of_month');

        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            return $this->jsonResponse(['success' => false, 'message' => 'Please select a day between 1 and 31.'], 422);
        }

        $dayOfMonth = (int) $value;

        if ($dayOfMonth < 1 || $dayOfMonth > 31) {
            return $this->jsonResponse(['success' => false, 'message' => 'Please select a day between 1 and 31.'], 422);
        }

        $preview = $this->billingService->previewBillingDateChange($id, $dayOfMonth);

        if (!$preview['success']) {
            return $this->jsonResponse([
                'success' => false,
                'message' => $preview['message'] ?? 'Failed to preview billing date change.',
            ], 422);
        }

        return $this->jsonResponse(['success' => true, 'preview' => $preview]);
    }
}
