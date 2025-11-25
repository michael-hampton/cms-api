<?php

namespace App\Controllers\Members;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Services\SubscriptionPlanService;

class MemberSubscriptionPlansController extends Controller
{
    public function __construct(
        private readonly SubscriptionPlanService $planService
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

        if (!$canSubscribe['can_subscribe']) {
            if ($request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
                return $this->resourceResponse([
                    'success' => false,
                    'message' => $canSubscribe['reason']
                ], 400);
            }

            $_SESSION['flash_error'] = $canSubscribe['reason'];
            return $this->redirect("/member/subscription-plans/{$slug}");
        }

        try {
            // In real app, this would handle payment processing
            // For now, we'll create the subscription directly
            $subscription = $this->planService->subscribeMemberToPlan(
                $member->id,
                $plan->id,
                $siteId,
                [
                    'payment_method' => $request->input('payment_method', 'manual'),
                    'transaction_id' => $request->input('transaction_id')
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