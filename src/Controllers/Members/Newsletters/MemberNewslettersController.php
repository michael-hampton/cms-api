<?php

namespace App\Controllers\Members\Newsletters;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Mail\MailManager;
use App\Framework\Support\Logger;
use App\Framework\Support\SiteContext;
use App\Mail\NewsletterSignupConfirmationWithTracking;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Repositories\Subscriptions\SubscriberRepository;
use App\Services\Newsletter\NewsletterAccessService;
use App\Services\Newsletter\NewsletterSignupService;
use App\Services\Subscriptions\SubscriptionCheckoutService;
use App\Services\Subscriptions\SubscriptionPlanService;

class MemberNewslettersController extends Controller
{
    public function __construct(
        private readonly SubscriberRepository    $subscriberRepository,
        private readonly NewsletterRepository    $newsletterRepository,
        private readonly NewsletterSignupService     $newsletterSignupService,
        private readonly NewsletterAccessService     $newsletterAccessService,
        private readonly SubscriptionPlanService     $subscriptionPlanService,
        private readonly SubscriptioncheckoutService $checkoutService
    )
    {
        parent::__construct();
    }

    public function index()
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/member/login');
        }

        $member = MemberAuth::getMember();
        $siteId = SiteContext::getId();

        $subscriptions = $this->subscriberRepository->getNewslettersForMember($member->email, $siteId);
        $availableNewsletters = $this->newsletterRepository->where('site_id', $siteId)
            ->where('active', true)
            ->get();

        // Check access for each newsletter
        // Check access for each newsletter
        $newslettersWithAccess = $availableNewsletters->map(function ($newsletter) use ($member, $siteId) {
            $accessCheck = $this->newsletterAccessService->checkAccess(
                $newsletter->id,
                $member->id,
                $siteId
            );

            return [
                'newsletter' => $newsletter,
                'has_access' => $accessCheck['has_access'],
                'access_reason' => $accessCheck['reason'],
                'access_message' => $accessCheck['message'] ?? null,
                'required_level' => $accessCheck['required_level'] ?? null
            ];
        });

        // Get plans for upgrade CTAs
        $plans = $this->subscriptionPlanService->getActivePlansForSite($siteId);

        return $this->view('member/newsletters/index', [
            'member' => $member,
            'site' => SiteContext::get(),
            'subscriptions' => $subscriptions,
            'availableNewsletters' => $availableNewsletters,
            'newslettersWithAccess' => $newslettersWithAccess,
            'plans' => $plans
        ]);
    }

    public function unsubscribe(Request $request)
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        try {
            $member = MemberAuth::member();
            $siteId = SiteContext::getId();
            $subscriberId = $request->input('subscriber_id');

            // Verify the subscriber belongs to this member
            $subscriber = $this->subscriberRepository->find($subscriberId);

            if (!$subscriber || $subscriber->site_id !== $siteId || $subscriber->email !== $member->email) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Subscription not found'
                ], 404);
            }

            // Use the service to handle unsubscription
            $result = $this->newsletterSignupService->unsubscribeById($subscriberId, $siteId);

            if (!$result['success']) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => $result['error']
                ], 404);
            }

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Unsubscribed successfully'
            ]);

        } catch (\Exception $e) {
            Logger::error('Member newsletter unsubscription failed', [
                'error' => $e->getMessage(),
                'member_id' => $member->id ?? null
            ]);
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to unsubscribe: ' . $e->getMessage()
            ], 500);
        }
    }

    public function subscribe(Request $request)
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        try {
            $member = MemberAuth::member();
            $siteId = SiteContext::getId();
            $newsletterId = $request->input('newsletter_id');

            // Use the service to handle subscription
            $result = $this->newsletterSignupService->signup($member->email, true, $newsletterId, $siteId);

            if (!$result['success']) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => $result['error']
                ], 400);
            }

            // Send confirmation email only if not resubscribed (new subscription)
            if (!isset($result['resubscribed']) || !$result['resubscribed']) {
                $this->sendSignupConfirmationEmail(
                    $member->email,
                    $result['confirmation_token'],
                    $member->first_name
                );
            }

            return $this->jsonResponse([
                'success' => true,
                'message' => isset($result['resubscribed']) && $result['resubscribed']
                    ? 'Successfully resubscribed to newsletter'
                    : 'Successfully subscribed to newsletter',
                'newsletter_id' => $result['newsletter_id'],
                'subscriber_id' => $result['subscriber_id'],
                'resubscribed' => $result['resubscribed'] ?? false
            ]);

        } catch (\Exception $e) {
            Logger::error('Member newsletter subscription failed', [
                'error' => $e->getMessage(),
                'member_id' => $member->id ?? null
            ]);
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to subscribe: ' . $e->getMessage()
            ], 500);
        }
    }

    public function bulkSubscribe(Request $request)
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        try {
            $member = MemberAuth::member();
            $siteId = SiteContext::getId();
            $newsletterIds = $request->input('newsletter_ids', []);

            if (empty($newsletterIds)) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'No newsletters selected'
                ], 400);
            }

            $successCount = 0;
            $errors = [];
            $subscribedIds = [];
            $resubscribedCount = 0;

            foreach ($newsletterIds as $newsletterId) {
                // Use the service to handle subscription
                $result = $this->newsletterSignupService->signup($member->email, true, $newsletterId, $siteId);

                if ($result['success']) {
                    $successCount++;
                    $subscribedIds[] = $result['newsletter_id'];

                    if (isset($result['resubscribed']) && $result['resubscribed']) {
                        $resubscribedCount++;
                    } else {
                        // Send confirmation email only for new subscriptions
                        $this->sendSignupConfirmationEmail(
                            $member->email,
                            $result['confirmation_token'],
                            $member->first_name
                        );
                    }
                } else {
                    $errors[] = $result['error'];
                }
            }

            if ($successCount > 0) {
                $message = "Successfully subscribed to $successCount newsletter(s)";
                if ($resubscribedCount > 0) {
                    $message .= " ($resubscribedCount resubscribed)";
                }

                return $this->jsonResponse([
                    'success' => true,
                    'message' => $message,
                    'count' => $successCount,
                    'resubscribed_count' => $resubscribedCount,
                    'newsletter_ids' => $subscribedIds
                ]);
            }

            return $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to subscribe to newsletters',
                'errors' => $errors
            ], 400);

        } catch (\Exception $e) {
            Logger::error('Member bulk newsletter subscription failed', [
                'error' => $e->getMessage(),
                'member_id' => $member->id ?? null
            ]);
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to subscribe: ' . $e->getMessage()
            ], 500);
        }
    }

    private function sendSignupConfirmationEmail(string $email, string $token, ?string $firstName = null): void
    {
        $mailable = new NewsletterSignupConfirmationWithTracking(
            $email,
            $token,
            $firstName
        );

        try {
            MailManager::getInstance()->send($mailable);
        } catch (\Exception $e) {
            Logger::error('Failed to send newsletter confirmation email', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function getUpgradeOptions(Request $request)
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        try {
            $data = $request->all();
            $member = MemberAuth::member();
            $siteId = (int)$data['site_id'];
            $newsletterId = $request->input('newsletter_id');

            if (!$newsletterId) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Newsletter ID required'
                ], 400);
            }

            $newsletter = $this->newsletterRepository->find($newsletterId);

            if (!$newsletter || $newsletter->site_id !== $siteId) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Newsletter not found'
                ], 404);
            }

            // Get all active plans that grant access to this newsletter
            $plans = $this->subscriptionPlanService->getActivePlansForSite($siteId);

            /**
             * $eligiblePlans = $plans->filter(function($plan) use ($newsletter) {
             * return $plan->grantsPremiumAccess('newsletter', $newsletter->slug ?? 'insider');
             * })->map(function($plan) {
             */

            $eligiblePlans = $plans->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'description' => $plan->description,
                    'price' => $plan->price,
                    'currency' => $plan->currency,
                    'billing_period' => $plan->billing_period,
                    'features' => $plan->features,
                    'is_featured' => $plan->is_featured
                ];
            })->values();

            return $this->jsonResponse([
                'success' => true,
                'newsletter' => [
                    'id' => $newsletter->id,
                    'title' => $newsletter->title,
                ],
                'plans' => $eligiblePlans
            ]);

        } catch (\Exception $e) {
            Logger::error('Failed to get upgrade options', [
                'error' => $e->getMessage(),
                'member_id' => $member->id ?? null
            ]);
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to load upgrade options'
            ], 500);
        }
    }

    public function processUpgrade(Request $request)
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        try {
            $member = MemberAuth::member();
            $siteId = SiteContext::getId();

            $planId = $request->input('plan_id');
            $newsletterId = $request->input('newsletter_id');
            $paymentMethod = $request->input('payment_method');
            $voucherCode = $request->input('voucher_code');

            if (!$planId || !$newsletterId || !$paymentMethod) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Missing required fields'
                ], 400);
            }

            // Verify the plan grants access to this newsletter
            $plan = $this->subscriptionPlanService->getPlanBySlug($planId, $siteId);
            if (!$plan) {
                $plan = $this->subscriptionPlanService->getActivePlansForSite($siteId)
                    ->firstWhere('id', $planId);
            }

            if (!$plan) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Invalid plan selected'
                ], 400);
            }

            $newsletter = $this->newsletterRepository->find($newsletterId);
            if (!$newsletter) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Newsletter not found'
                ], 404);
            }

            // Check eligibility
            $eligibility = $this->subscriptionPlanService->canMemberSubscribe(
                $member->id,
                $plan->id,
                $siteId
            );

            if (!$eligibility['can_subscribe']) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => $eligibility['reason']
                ], 400);
            }

            // Process the subscription checkout
            $checkoutData = [
                'subscription_plan_id' => $plan->id,
                'payment_method' => $paymentMethod,
            ];

            if ($voucherCode) {
                $checkoutData['voucher_code'] = $voucherCode;
            }

            // You'll need to inject SubscriptionCheckoutService
            $result = $this->checkoutService->processSubscriptionCheckout(
                $member->id,
                $checkoutData,
                $siteId
            );

            if (!$result['success']) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => $result['message']
                ], 400);
            }

            // Auto-subscribe to the newsletter after successful upgrade
            $newsletterSignup = $this->newsletterSignupService->signup(
                $member->email,
                true,
                $newsletterId,
                $siteId
            );

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Subscription successful! You now have access to this newsletter.',
                'subscription_id' => $result['subscription_id'],
                'newsletter_subscribed' => $newsletterSignup['success'],
                'redirect_url' => $result['redirect_url'] ?? null
            ]);

        } catch (\Exception $e) {
            Logger::error('Newsletter upgrade failed', [
                'error' => $e->getMessage(),
                'member_id' => $member->id ?? null
            ]);
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Upgrade failed: ' . $e->getMessage()
            ], 500);
        }
    }
}