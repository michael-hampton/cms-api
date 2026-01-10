<?php

namespace App\Controllers\Members\Subscriptions;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Support\SiteContext;
use App\Repositories\IssueDeliveryRepository;
use App\Repositories\SubscriptionRepository;

class MemberIssueDeliveriesController extends Controller
{
    public function __construct(
        private readonly IssueDeliveryRepository $deliveryRepository,
        private readonly SubscriptionRepository  $subscriptionRepository
    )
    {
        parent::__construct();
    }

    public function index(int $subscriptionId)
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/member/login');
        }

        $member = MemberAuth::getMember();
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        // Verify subscription belongs to member
        if (!$subscription || $subscription->member_id !== $member->id) {
            return $this->notFound('Subscription not found');
        }

        // Only show for print or bundle subscriptions
        if (!$subscription->isPrint()) {
            $_SESSION['flash_error'] = 'Delivery schedule is only available for print subscriptions';
            return $this->redirect('/' . SiteContext::slug() . '/member/subscriptions');
        }

        $upcomingDeliveries = $this->deliveryRepository->getUpcomingDeliveries($subscriptionId);
        $pastDeliveries = $this->deliveryRepository->getPastDeliveries($subscriptionId);

        return $this->view('member/subscriptions/issue-deliveries', [
            'member' => $member,
            'site' => SiteContext::get(),
            'subscription' => $subscription,
            'upcomingDeliveries' => $upcomingDeliveries,
            'pastDeliveries' => $pastDeliveries
        ]);
    }
}