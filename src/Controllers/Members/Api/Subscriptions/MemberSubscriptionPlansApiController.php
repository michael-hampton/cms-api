<?php

namespace App\Controllers\Members\Api\Subscriptions;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Support\Logger;
use App\Framework\Support\SiteContext;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\SubscriptionCancellationService;
use App\Services\Subscriptions\SubscriptionPlanService;
use App\Services\Vouchers\VoucherService;

class MemberSubscriptionPlansApiController extends Controller
{
    public function __construct(
        private readonly SubscriptionPlanService         $planService,
        private readonly SubscriptionRepository          $subscriptionRepository,
        private readonly SubscriptionCancellationService $cancellationService,
        private readonly VoucherService                  $voucherService,
    )
    {
        parent::__construct();
    }

    public function index(): mixed
    {
        $siteId = SiteContext::getId();
        $plans = $this->planService->getActivePlansForSite($siteId);

        $member = MemberAuth::check() ? MemberAuth::getMember() : null;
        $currentSubscription = $member?->activeSubscription;

        return $this->jsonResponse([
            'success' => true,
            'plans' => $plans,
            'currentSubscription' => $currentSubscription,
        ]);
    }

    public function show(string $slug): mixed
    {
        $siteId = SiteContext::getId();
        $plan = $this->planService->getPlanBySlug($slug, $siteId);

        if (!$plan) {
            return $this->jsonResponse(['success' => false, 'message' => 'Plan not found'], 404);
        }

        $member = MemberAuth::check() ? MemberAuth::getMember() : null;
        $canSubscribe = ['can_subscribe' => false];

        if ($member) {
            $canSubscribe = $this->planService->canMemberSubscribe($member->id, $plan->id, $siteId);
        }

        return $this->jsonResponse([
            'success' => true,
            'plan' => $plan,
            'canSubscribe' => $canSubscribe,
        ]);
    }

    public function subscribe(Request $request, string $slug): mixed
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Please login to subscribe'], 401);
        }

        $member = MemberAuth::getMember();
        $siteId = SiteContext::getId();
        $plan = $this->planService->getPlanBySlug($slug, $siteId);

        if (!$plan) {
            return $this->jsonResponse(['success' => false, 'message' => 'Plan not found'], 404);
        }

        $canSubscribe = $this->planService->canMemberSubscribe($member->id, $plan->id, $siteId);

        if (!$canSubscribe['can_subscribe']) {
            $cancelledSubscription = $this->subscriptionRepository->getCancelledSubscriptionForPlan(
                $member->id,
                $plan->id,
                $siteId,
            );

            if ($cancelledSubscription && $cancelledSubscription->hasStripeSubscription()) {
                $canReactivate = $cancelledSubscription->end_date &&
                    $cancelledSubscription->end_date > new \DateTime();

                if ($canReactivate) {
                    try {
                        $result = $this->cancellationService->reactivateSubscription($cancelledSubscription->id);

                        if ($result['success']) {
                            return $this->jsonResponse([
                                'success' => true,
                                'message' => 'Subscription reactivated successfully',
                                'subscription' => $result['subscription'],
                            ]);
                        }
                    } catch (\Exception $e) {
                        Logger::error('Failed to reactivate subscription during subscribe', [
                            'subscription_id' => $cancelledSubscription->id,
                            'error' => $e->getMessage(),
                        ]);

                        if (!str_contains($e->getMessage(), 'cannot be reactivated')) {
                            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
                        }
                    }
                }

                // Archive the old cancelled subscription so a fresh one can be created.
                try {
                    $this->subscriptionRepository->update($cancelledSubscription->id, [
                        'metadata' => array_merge(
                            $cancelledSubscription->metadata ?? [],
                            ['archived_for_new_subscription' => true, 'archived_at' => date('Y-m-d H:i:s')],
                        ),
                    ]);
                } catch (\Exception $e) {
                    Logger::warning('Failed to archive old subscription', [
                        'subscription_id' => $cancelledSubscription->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            } else {
                return $this->jsonResponse(['success' => false, 'message' => $canSubscribe['reason']], 400);
            }
        }

        try {
            $subscription = $this->planService->subscribeMemberToPlanWithVoucher(
                $member->id,
                $plan->id,
                $siteId,
                $request->input('voucher_code'),
                [
                    'payment_method' => $request->input('payment_method', 'stripe'),
                    'transaction_id' => $request->input('transaction_id'),
                    'payment_method_id' => $request->input('payment_method_id'),
                ],
            );

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Successfully subscribed to ' . $plan->name,
                'subscription' => $subscription,
                'discount_applied' => $subscription->hasVoucher(),
                'discount_amount' => $subscription->discount_amount,
                'final_price' => $subscription->getDiscountedPrice(),
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to create subscription', [
                'member_id' => $member->id,
                'plan_id' => $plan->id,
                'error' => $e->getMessage(),
            ]);

            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function validateVoucher(Request $request, string $slug): mixed
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Please login to validate voucher'], 401);
        }

        $member = MemberAuth::getMember();
        $siteId = SiteContext::getId();
        $plan = $this->planService->getPlanBySlug($slug, $siteId);

        if (!$plan) {
            return $this->jsonResponse(['success' => false, 'message' => 'Plan not found'], 404);
        }

        $voucherCode = $request->input('voucher_code');

        if (empty($voucherCode)) {
            return $this->jsonResponse(['success' => false, 'message' => 'Voucher code is required'], 400);
        }

        $validation = $this->voucherService->validateVoucherForSubscription(
            $voucherCode,
            $plan->id,
            $member->id,
        );

        if (!$validation->valid) {
            return $this->jsonResponse(['success' => false, 'message' => $validation->message], 400);
        }

        return $this->jsonResponse([
            'success' => true,
            'message' => $validation->message,
            'discount' => $validation->discount,
            'original_price' => $plan->price,
            'final_price' => $plan->price - $validation->discount,
            'voucher' => [
                'id' => $validation->voucherId,
                'code' => $voucherCode,
                'type' => $validation->voucher->type,
                'value' => $validation->voucher->value,
            ],
        ]);
    }
}