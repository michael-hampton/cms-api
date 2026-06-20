<?php

namespace App\Controllers\Members\Subscriptions;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Models\Subscription;
use App\Services\Subscriptions\SubscriptionListingService;

final class UnifiedMemberSubscriptionContinuationController extends Controller
{
    public function __construct(private readonly SubscriptionListingService $listingService)
    {
        parent::__construct();
    }

    public function renew(string $site, int $id): mixed
    {
        return $this->continue($site, $id, 'renew');
    }

    public function resubscribe(string $site, int $id): mixed
    {
        return $this->continue($site, $id, 'resubscribe');
    }

    private function continue(string $site, int $id, string $action): mixed
    {
        $member = MemberAuth::getMember();
        $subscription = Subscription::find($id);

        if (!$member || !$subscription || (int) $subscription->member_id !== (int) $member->id) {
            return $this->redirect('/' . $site . '/member/subscriptions/unified');
        }

        $allowed = false;
        $formatted = $this->listingService->formatSubscriptionForListing($subscription);

        foreach ($formatted['actions'] ?? [] as $candidate) {
            if (($candidate['key'] ?? null) === $action) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            return $this->redirect('/' . $site . '/member/subscriptions/unified');
        }

        return $this->redirect(
            '/' . $site . '/checkout?subscription_id=' . $subscription->id . '&' . $action . '=true',
        );
    }
}
