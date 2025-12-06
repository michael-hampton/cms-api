<?php

namespace App\Controllers\Members;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Support\Logger;
use App\Framework\Support\SiteContext;
use App\Repositories\SubscriptionRepository;
use App\Services\SubscriptionCancellationService;
use App\Services\SubscriptionPlanService;

class MemberSubscriptionPlansController extends Controller
{
    public function __construct(
        private readonly SubscriptionPlanService         $planService,
        private readonly SubscriptionRepository          $subscriptionRepository,
        private readonly SubscriptionCancellationService $cancellationService
    )
    {
        parent::__construct();
    }

    public function index()
    {
        $siteId = SiteContext::getId();

        $plans = $this->planService->getAvailablePlans($siteId);

        $member = MemberAuth::check() ? MemberAuth::getMember() : null;
        $currentSubscription = null;

        if ($member) {
            $currentSubscription = $member->activeSubscription;
        }

        return $this->view('member/subscription-plans/index', [
            'site' => SiteContext::get(),
            'plans' => $plans,
            'member' => $member,
            'currentSubscription' => $currentSubscription
        ]);
    }

    public function show(string $slug)
    {
        $siteId = SiteContext::getId();
        $plan = $this->planService->getPlanBySlug($slug, $siteId);

        if (!$plan) {
            return $this->resourceResponse(['message' => 'Plan not found'], 404);
        }

        $member = MemberAuth::check() ? MemberAuth::member() : null;
        $canSubscribe = ['can_subscribe' => false];

        if ($member) {
            $canSubscribe = $this->planService->canMemberSubscribe($member->id, $plan->id, $siteId);
        }

        return $this->view('member/subscription-plans/show', [
            'site' => SiteContext::get(),
            'plan' => $plan,
            'member' => $member,
            'canSubscribe' => $canSubscribe
        ]);
    }

    public function subscribe(Request $request, string $slug)
    {
        if (!MemberAuth::check()) {
            if ($request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Please login to subscribe'
                ], 401);
            }

            $_SESSION['intended_url'] = "/member/subscription-plans/{$slug}";
            return $this->redirect('/member/login');
        }

        $member = MemberAuth::getMember();
        $siteId = SiteContext::getId();

        $plan = $this->planService->getPlanBySlug($slug, $siteId);

        if (!$plan) {
            if ($request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Plan not found'
                ], 404);
            }
            return $this->notFound('Plan not found');
        }

        // Check if member can subscribe
        $canSubscribe = $this->planService->canMemberSubscribe($member->id, $plan->id, $siteId);

        // If they can't subscribe, check if it's because they have a cancelled subscription
        if (!$canSubscribe['can_subscribe']) {
            $cancelledSubscription = $this->subscriptionRepository->getCancelledSubscriptionForPlan(
                $member->id,
                $plan->id,
                $siteId
            );

            // If they have a cancelled subscription, try to reactivate it
            if ($cancelledSubscription && $cancelledSubscription->hasStripeSubscription()) {
                // Check if subscription is still within the billing period (can potentially be reactivated)
                $canReactivate = $cancelledSubscription->end_date &&
                    $cancelledSubscription->end_date > new \DateTime();

                if ($canReactivate) {
                    try {
                        $result = $this->cancellationService->reactivateSubscription($cancelledSubscription->id);

                        if ($result['success']) {
                            if ($request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
                                return $this->resourceResponse([
                                    'success' => true,
                                    'message' => 'Subscription reactivated successfully',
                                    'data' => ['subscription' => $result['subscription']]
                                ]);
                            }

                            $_SESSION['flash_success'] = 'Subscription reactivated successfully';
                            return $this->redirect('/' . SiteContext::slug() . '/member/subscriptions');
                        }
                    } catch (\Exception $e) {
                        Logger::error('Failed to reactivate subscription during subscribe', [
                            'subscription_id' => $cancelledSubscription->id,
                            'error' => $e->getMessage()
                        ]);

                        // If reactivation failed because subscription is fully canceled,
                        // we'll allow them to create a new subscription below
                        // Otherwise, show the error
                        if (!str_contains($e->getMessage(), 'cannot be reactivated')) {
                            if ($request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
                                return $this->jsonResponse([
                                    'success' => false,
                                    'message' => $e->getMessage()
                                ], 400);
                            }

                            $_SESSION['flash_error'] = $e->getMessage();
                            return $this->redirect("/member/subscription-plans/{$slug}");
                        }
                        // If we get here, reactivation failed because subscription is fully canceled
                        // Continue to allow creating a new subscription
                    }
                }

                // If we reach here, either:
                // 1. The subscription has ended (canReactivate = false), OR
                // 2. Reactivation failed because subscription is fully canceled
                // In both cases, we should allow creating a new subscription
                // So we need to "archive" or ignore the old cancelled subscription
                // and proceed with creating a new one

                // The issue is that canSubscribe returned false because of the cancelled subscription
                // We need to override this and allow the new subscription

                // Update the cancelled subscription to mark it as replaced/archived
                try {
                    $this->subscriptionRepository->update($cancelledSubscription->id, [
                        'metadata' => array_merge(
                            $cancelledSubscription->metadata ?? [],
                            ['archived_for_new_subscription' => true, 'archived_at' => date('Y-m-d H:i:s')]
                        )
                    ]);
                } catch (\Exception $e) {
                    Logger::warning('Failed to archive old subscription', [
                        'subscription_id' => $cancelledSubscription->id,
                        'error' => $e->getMessage()
                    ]);
                }

                // Now proceed to create new subscription (fall through to creation logic below)
            } else {
                // They can't subscribe for a different reason (e.g., already has active subscription)
                if ($request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
                    return $this->resourceResponse([
                        'success' => false,
                        'message' => $canSubscribe['reason']
                    ], 400);
                }

                $_SESSION['flash_error'] = $canSubscribe['reason'];
                return $this->redirect("/member/subscription-plans/{$slug}");
            }
        }

        // Create new subscription
        try {
            $subscription = $this->planService->subscribeMemberToPlan(
                $member->id,
                $plan->id,
                $siteId,
                [
                    'payment_method' => $request->input('payment_method', 'manual'),
                    'transaction_id' => $request->input('transaction_id'),
                    'payment_method_id' => $request->input('payment_method_id') // For Stripe
                ]
            );

            if ($request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
                return $this->resourceResponse([
                    'success' => true,
                    'message' => 'Successfully subscribed to ' . $plan->name,
                    'data' => ['subscription' => $subscription]
                ]);
            }

            $_SESSION['flash_success'] = 'Successfully subscribed to ' . $plan->name;
            return $this->redirect('/' . SiteContext::slug() . '/member/subscriptions');

        } catch (\Exception $e) {
            echo $e->getMessage();
            die;
            Logger::error('Failed to create subscription', [
                'member_id' => $member->id,
                'plan_id' => $plan->id,
                'error' => $e->getMessage()
            ]);

            if ($request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }

            $_SESSION['flash_error'] = $e->getMessage();
            return $this->redirect("/member/subscription-plans/{$slug}");
        }
    }
}