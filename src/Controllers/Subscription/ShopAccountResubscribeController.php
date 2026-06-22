<?php

namespace App\Controllers\Subscription;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Models\Subscription;
use App\Services\Subscriptions\SubscriptionListingService;

final class ShopAccountResubscribeController extends Controller
{
    public function __construct(private readonly SubscriptionListingService $listingService)
    {
        parent::__construct();
    }

    public function __invoke(int $id, Request $request): mixed
    {
        $member = MemberAuth::getMember();
        $subscription = Subscription::find($id);

        if (!$member) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorised.'], 401);
        }

        if (!$subscription || (int) $subscription->member_id !== (int) $member->id) {
            return $this->jsonResponse(['success' => false, 'message' => 'Subscription not found.'], 404);
        }

        if (!$subscription->plan_id || !$this->hasAction($subscription)) {
            return $this->jsonResponse(['success' => false, 'message' => 'This subscription cannot be resubscribed.'], 422);
        }

        return $this->jsonResponse(['success' => true, 'message' => 'Resubscribe request accepted.']);
    }

    private function hasAction(Subscription $subscription): bool
    {
        $formatted = $this->listingService->formatSubscriptionForListing($subscription);

        foreach ($formatted['actions'] ?? [] as $action) {
            if (($action['key'] ?? null) === 'resubscribe') {
                return true;
            }
        }

        return false;
    }
}
