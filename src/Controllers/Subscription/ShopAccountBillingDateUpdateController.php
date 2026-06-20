<?php

namespace App\Controllers\Subscription;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\SubscriptionBillingService;
use Throwable;

final class ShopAccountBillingDateUpdateController extends Controller
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

        try {
            $result = $this->billingService->updateBillingDate($id, $dayOfMonth);

            return $this->jsonResponse([
                'success' => true,
                'message' => $result['message'] ?? 'Billing date updated successfully.',
                'new_billing_date' => $result['new_billing_date'] ?? null,
            ]);
        } catch (Throwable $exception) {
            return $this->jsonResponse(['success' => false, 'message' => $exception->getMessage()], 422);
        }
    }
}
