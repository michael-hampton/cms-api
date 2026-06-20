<?php

namespace App\Controllers\Subscription;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\SubscriptionListingService;

final class ShopAccountSubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly SubscriptionListingService $subscriptionListingService,
    ) {
        parent::__construct();
    }

    public function show(int $id, Request $request): mixed
    {
        $member = MemberAuth::getMember();

        if (!$member) {
            return $this->redirect('/member/login');
        }

        $subscription = $this->subscriptionRepository->find($id);

        if (!$subscription || $subscription->member_id !== $member->id) {
            return $this->redirect('/press-stack/account/subscriptions');
        }

        return $this->view('subscriptions/account/subscription', [
            'member' => $member,
            'subscription' => $subscription,
            'subscription_data' => $this->subscriptionListingService
                ->formatSubscriptionForListing($subscription),
            'active_tab' => 'subscriptions',
        ]);
    }
}
