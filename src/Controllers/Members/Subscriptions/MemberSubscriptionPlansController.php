<?php

namespace App\Controllers\Members\Subscriptions;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Support\Logger;
use App\Framework\Support\SiteContext;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Cms\VoucherService;
use App\Services\Subscriptions\SubscriptionCancellationService;
use App\Services\Subscriptions\SubscriptionPlanService;

class MemberSubscriptionPlansController extends Controller
{
    public function __construct(
        private readonly SubscriptionPlanService         $planService,
        private readonly SubscriptionRepository          $subscriptionRepository,
        private readonly SubscriptionCancellationService $cancellationService,
        private readonly VoucherService                  $voucherService
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

        $canSubscribe = $this->planService->canMemberSubscribe($member->id, $plan->id, $siteId);

        if (!$canSubscribe['can_subscribe']) {
            $cancelledSubscription = $this->subscriptionRepository->getCancelledSubscriptionForPlan(
                $member->id,
                $plan->id,
                $siteId
            );

            if ($cancelledSubscription && $cancelledSubscription->hasStripeSubscription()) {
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
                    }
                }

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
            } else {
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

        // Get voucher code from request
        $voucherCode = $request->input('voucher_code');

        try {
            $subscription = $this->planService->subscribeMemberToPlanWithVoucher(
                $member->id,
                $plan->id,
                $siteId,
                $voucherCode,
                [
                    'payment_method' => $request->input('payment_method', 'stripe'),
                    'transaction_id' => $request->input('transaction_id'),
                    'payment_method_id' => $request->input('payment_method_id')
                ]
            );

            if ($request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
                return $this->resourceResponse([
                    'success' => true,
                    'message' => 'Successfully subscribed to ' . $plan->name,
                    'data' => [
                        'subscription' => $subscription,
                        'discount_applied' => $subscription->hasVoucher(),
                        'discount_amount' => $subscription->discount_amount,
                        'final_price' => $subscription->getDiscountedPrice()
                    ]
                ]);
            }

            $_SESSION['flash_success'] = 'Successfully subscribed to ' . $plan->name;
            return $this->redirect('/' . SiteContext::slug() . '/member/subscriptions');

        } catch (\Exception $e) {
            Logger::error('Failed to create subscription', [
                'member_id' => $member->id,
                'plan_id' => $plan->id,
                'error' => $e->getMessage()
            ]);

            if ($request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
                return $this->resourceResponse([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }

            $_SESSION['flash_error'] = $e->getMessage();
            return $this->redirect("/member/subscription-plans/{$slug}");
        }
    }

    public function validateVoucher(Request $request, string $slug)
    {
        if (!MemberAuth::check()) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Please login to validate voucher'
            ], 401);
        }

        $member = MemberAuth::getMember();
        $siteId = SiteContext::getId();

        $plan = $this->planService->getPlanBySlug($slug, $siteId);

        if (!$plan) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Plan not found'
            ], 404);
        }

        $voucherCode = $request->input('voucher_code');

        if (empty($voucherCode)) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Voucher code is required'
            ], 400);
        }

        $validation = $this->voucherService->validateVoucherForSubscription(
            $voucherCode,
            $plan->id,
            $member->id
        );

        if ($validation['valid']) {
            $finalPrice = $plan->price - $validation['discount'];

            return $this->resourceResponse([
                'success' => true,
                'message' => $validation['message'],
                'data' => [
                    'discount' => $validation['discount'],
                    'original_price' => $plan->price,
                    'final_price' => $finalPrice,
                    'voucher' => [
                        'id' => $validation['voucher_id'],
                        'code' => $voucherCode,
                        'type' => $validation['voucher']->type,
                        'value' => $validation['voucher']->value
                    ]
                ]
            ]);
        }

        return $this->resourceResponse([
            'success' => false,
            'message' => $validation['message']
        ], 400);
    }
}