<?php

namespace App\Controllers\Members;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Repositories\SubscriptionRepository;

class MemberSubscriptionsController extends Controller
{
    public function __construct(private SubscriptionRepository $subscriptionRepository)
    {
        parent::__construct();
    }

    public function index()
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/member/login');
        }

        $member = MemberAuth::member();
        $siteId = SiteContext::getId();

        $activeSubscription = $this->subscriptionRepository->getActiveSubscriptionForMember($member->id, $siteId);
        $subscriptionHistory = $this->subscriptionRepository->getSubscriptionHistory($member->id, $siteId);

        return $this->view('member/subscriptions/index', [
            'member' => $member,
            'site' => SiteContext::get(),
            'activeSubscription' => $activeSubscription,
            'subscriptionHistory' => $subscriptionHistory
        ]);
    }

    public function cancel(Request $request, int $subscriptionId)
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $member = MemberAuth::member();
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription || $subscription->member_id !== $member->id) {
            return $this->jsonResponse(['success' => false, 'message' => 'Subscription not found'], 404);
        }

        if ($this->subscriptionRepository->cancelSubscription($subscriptionId)) {
            return $this->jsonResponse([
                'success' => true,
                'message' => 'Subscription cancelled successfully'
            ]);
        }

        return $this->jsonResponse(['success' => false, 'message' => 'Failed to cancel subscription'], 500);
    }
}