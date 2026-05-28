<?php

namespace App\Controllers\Members\Subscriptions;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Repositories\Cms\CategoryRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Subscriptions\MemberSubscriptionService;
use App\Services\Subscriptions\SubscriptionPlanService;

class MemberSubscriptionsController extends Controller
{
    public function __construct(
        private readonly SubscriptionRepository     $subscriptionRepository,
        private readonly MemberSubscriptionService  $subscriptionService,
        private readonly CategoryRepository         $categoryRepository,
        private readonly SubscriptionPlanService    $subscriptionPlanService,
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
        $plans = $this->subscriptionPlanService->getActivePlansForSite(SiteContext::getId());

        return $this->view('member/subscriptions/index', [
            'member' => $member,
            'site' => SiteContext::get(),
            'plans' => $plans,
            'subscriptionModalData' => [
                'plans' => $plans,
                'show_modal' => false,
                'is_direct' => true,
                'member' => $member,
            ]
        ]);
    }

    public function preferences()
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/member/login');
        }

        $member = MemberAuth::member();
        $siteId = SiteContext::getId();

        $preference = $this->subscriptionService->getPreferencesForMember($member->id, $siteId);
        $categories = $this->categoryRepository->getActive();

        $contentTypes = [
            'news' => 'News & Updates',
            'blog' => 'Blog Posts',
            'articles' => 'Articles',
            'products' => 'Product Updates',
            'promotions' => 'Promotions & Offers'
        ];

        return $this->view('member/subscriptions/preferences', [
            'member' => $member,
            'site' => SiteContext::get(),
            'preference' => $preference,
            'categories' => $categories,
            'contentTypes' => $contentTypes
        ]);
    }

    public function updatePreferences(Request $request)
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $member = MemberAuth::member();
        $siteId = SiteContext::getId();

        $data = [
            'email_notifications' => $request->input('email_notifications', false),
            'newsletter_frequency' => $request->input('newsletter_frequency', 'weekly'),
            'content_types' => $request->input('content_types', []),
            'category_preferences' => $request->input('category_preferences', [])
        ];

        try {
            $preference = $this->subscriptionService->updatePreferences($member->id, $data, $siteId);

            // Check if AJAX request
            if ($request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
                return $this->jsonResponse(['preference' => $preference]);
            }

            $_SESSION['flash_success'] = 'Your subscription preferences have been updated.';
            return $this->redirect('/' . SiteContext::slug() . '/member/subscriptions/preferences');

        } catch (\Exception $e) {
            if ($request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Failed to update preferences'
                ], 500);
            }

            $_SESSION['flash_error'] = 'Failed to update preferences. Please try again.';
            return $this->redirect('/member/subscriptions/preferences');
        }
    }

    public function unsubscribeForm(string $token)
    {
        //$preference = $this->subscriptionService->getPreferencesForMember(0, 0);

        // Find by token instead
        $repo = new \App\Repositories\Subscriptions\MemberSubscriptionPreferenceRepository();
        $preference = $repo->findByToken($token);

        if (!$preference) {
            return $this->view('member/subscriptions/unsubscribe-invalid', [
                'site' => SiteContext::get()
            ]);
        }

        return $this->view('member/subscriptions/unsubscribe', [
            'site' => SiteContext::get(),
            'preference' => $preference,
            'token' => $token
        ]);
    }

    public function unsubscribe(Request $request, string $token)
    {
        $repo = new \App\Repositories\Subscriptions\MemberSubscriptionPreferenceRepository();
        $preference = $repo->findByToken($token);

        if (!$preference) {
            if ($request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
                return $this->resourceResponse([
                    'success' => false,
                    'message' => 'Invalid unsubscribe token'
                ], 404);
            }

            return $this->view('member/subscriptions/unsubscribe-invalid', [
                'site' => SiteContext::get()
            ]);
        }

        // Check if member has an active Stripe subscription
        $activeSubscription = $this->subscriptionRepository->getActiveSubscriptionForMember(
            $preference->member_id,
            $preference->site_id
        );

        // If they have a Stripe subscription, warn them this only affects email preferences
        $hasStripeSubscription = $activeSubscription && $activeSubscription->hasStripeSubscription();

        $success = $this->subscriptionService->unsubscribeByToken($token);

        if (!$success) {
            if ($request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
                return $this->resourceResponse([
                    'success' => false,
                    'message' => 'Failed to unsubscribe'
                ], 500);
            }

            return $this->view('member/subscriptions/unsubscribe-invalid', [
                'site' => SiteContext::get()
            ]);
        }

        $message = 'Successfully unsubscribed from all emails';
        if ($hasStripeSubscription) {
            $message .= '. Note: This does not cancel your paid subscription. To cancel your subscription, please visit your account settings.';
        }

        if ($request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
            return $this->resourceResponse([
                'success' => true,
                'message' => $message,
                'has_active_subscription' => $hasStripeSubscription
            ]);
        }

        return $this->view('member/subscriptions/unsubscribe-success', [
            'site' => SiteContext::get(),
            'token' => $token,
            'has_active_subscription' => $hasStripeSubscription
        ]);
    }

    public function resubscribe(Request $request, string $token)
    {
        $repo = new \App\Repositories\Subscriptions\MemberSubscriptionPreferenceRepository();
        $preference = $repo->findByToken($token);

        if (!$preference) {
            if ($request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
                return $this->resourceResponse([
                    'success' => false,
                    'message' => 'Invalid token'
                ], 404);
            }

            $_SESSION['flash_error'] = 'Invalid resubscribe token';
            return $this->redirect('/member/subscriptions');
        }

        // Resubscribe to email preferences
        $success = $this->subscriptionService->resubscribeByToken($token);

        if (!$success) {
            if ($request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
                return $this->resourceResponse([
                    'success' => false,
                    'message' => 'Failed to resubscribe'
                ], 500);
            }

            $_SESSION['flash_error'] = 'Failed to resubscribe. Please try again.';
            return $this->redirect('/member/subscriptions');
        }

        // Check if they have a cancelled Stripe subscription that can be reactivated
        $cancelledSubscription = $this->subscriptionRepository->getCancelledSubscriptionForMember(
            $preference->member_id,
            $preference->site_id
        );

        $reactivatedSubscription = false;
        $reactivationMessage = null;

        if ($cancelledSubscription && $cancelledSubscription->hasStripeSubscription()) {
            // Check if subscription is still within the billing period (can be reactivated)
            $canReactivate = $cancelledSubscription->end_date &&
                $cancelledSubscription->end_date > new \DateTime();

            if ($canReactivate) {
                try {
                    $result = $this->cancellationService->reactivateSubscription($cancelledSubscription->id);
                    $reactivatedSubscription = $result['success'];
                } catch (\Exception $e) {
                    Logger::error('Failed to reactivate subscription during resubscribe', [
                        'subscription_id' => $cancelledSubscription->id,
                        'error' => $e->getMessage()
                    ]);

                    // Set a message if the subscription can't be reactivated
                    if (str_contains($e->getMessage(), 'cannot be reactivated')) {
                        $reactivationMessage = 'Your email preferences have been updated. Your previous subscription cannot be reactivated - please subscribe to a new plan if you wish to continue.';
                    }
                }
            }
        }

        $message = 'Successfully resubscribed to email notifications';
        if ($reactivatedSubscription) {
            $message .= ' and your subscription has been reactivated';
        } elseif ($reactivationMessage) {
            $message = $reactivationMessage;
        }

        if ($request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
            return $this->resourceResponse([
                'success' => true,
                'message' => $message,
                'subscription_reactivated' => $reactivatedSubscription
            ]);
        }

        $_SESSION['flash_success'] = $message;
        return $this->redirect('/member/subscriptions');
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

        try {
            $cancelAtPeriodEnd = $request->input('cancel_at_period_end', true);

            $result = $this->cancellationService->cancelSubscription($subscriptionId, [
                'cancel_at_period_end' => $cancelAtPeriodEnd
            ]);

            if (!$result['success']) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Failed to cancel subscription'
                ], 500);
            }

            $message = $cancelAtPeriodEnd
                ? 'Subscription will be cancelled at the end of the billing period'
                : 'Subscription cancelled successfully';

            return $this->jsonResponse([
                'success' => true,
                'message' => $message,
                'data' => ['subscription' => $result['subscription']]
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to cancel subscription', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage()
            ]);

            return $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function reactivate(Request $request, int $subscriptionId)
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $member = MemberAuth::member();
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription || $subscription->member_id !== $member->id) {
            return $this->jsonResponse(['success' => false, 'message' => 'Subscription not found'], 404);
        }

        try {
            $result = $this->cancellationService->reactivateSubscription($subscriptionId);

            if (!$result['success']) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Failed to reactivate subscription'
                ], 500);
            }

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Subscription reactivated successfully',
                'data' => ['subscription' => $result['subscription']]
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to reactivate subscription', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage()
            ]);

            return $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function manageByToken(string $token)
    {
        $repo = new \App\Repositories\Subscriptions\MemberSubscriptionPreferenceRepository();
        $preference = $repo->findByToken($token);

        if (!$preference) {
            return $this->view('member/subscriptions/unsubscribe-invalid', [
                'site' => SiteContext::get()
            ]);
        }

        $member = $preference->member;
        $categories = $this->categoryRepository->getActive($preference->site_id);

        $contentTypes = [
            'news' => 'News & Updates',
            'blog' => 'Blog Posts',
            'articles' => 'Articles',
            'products' => 'Product Updates',
            'promotions' => 'Promotions & Offers'
        ];

        return $this->view('member/subscriptions/manage', [
            'site' => SiteContext::get(),
            'preference' => $preference,
            'member' => $member,
            'categories' => $categories,
            'contentTypes' => $contentTypes,
            'token' => $token
        ]);
    }

    public function updateByToken(Request $request, string $token)
    {
        $repo = new \App\Repositories\Subscriptions\MemberSubscriptionPreferenceRepository();
        $preference = $repo->findByToken($token);

        if (!$preference) {
            if ($request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Invalid token'
                ], 404);
            }

            return $this->view('member/subscriptions/unsubscribe-invalid', [
                'site' => SiteContext::get()
            ]);
        }

        $data = [
            'email_notifications' => $request->input('email_notifications', false),
            'newsletter_frequency' => $request->input('newsletter_frequency', 'weekly'),
            'content_types' => $request->input('content_types', []),
            'category_preferences' => $request->input('category_preferences', [])
        ];

        try {
            $updated = $this->subscriptionService->updatePreferences(
                $preference->member_id,
                $data,
                $preference->site_id
            );

            if ($request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
                return $this->jsonResponse([
                    'success' => true,
                    'message' => 'Preferences updated successfully',
                    'preference' => $updated
                ]);
            }

            $_SESSION['flash_success'] = 'Your subscription preferences have been updated.';
            return $this->redirect("/member/subscriptions/manage/{$token}");

        } catch (\Exception $e) {
            if ($request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Failed to update preferences'
                ], 500);
            }

            $_SESSION['flash_success'] = 'Your subscription preferences have been updated.';
            return $this->redirect("/member/subscriptions/manage/{$token}");
        }
    }

    public function updateBillingDate(Request $request, int $subscriptionId)
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $member = MemberAuth::member();
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription || $subscription->member_id !== $member->id) {
            return $this->jsonResponse(['success' => false, 'message' => 'Subscription not found'], 404);
        }

        $dayOfMonth = (int)$request->input('day_of_month');

        if ($dayOfMonth < 1 || $dayOfMonth > 31) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Please select a day between 1 and 31'
            ], 400);
        }

        try {
            $result = $this->subscriptionBillingService->updateBillingDate($subscriptionId, $dayOfMonth);

            if ($result['success']) {
                return $this->jsonResponse([
                    'success' => true,
                    'message' => 'Billing date updated successfully',
                    'data' => [
                        'new_billing_date' => $result['new_billing_date']
                    ]
                ]);
            }

            return $this->jsonResponse([
                'success' => false,
                'message' => $result['message'] ?? 'Failed to update billing date'
            ], 500);

        } catch (\Exception $e) {
            Logger::error('Failed to update billing date', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage()
            ]);

            return $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function previewBillingDateChange(Request $request, int $subscriptionId)
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $member = MemberAuth::member();
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription || $subscription->member_id !== $member->id) {
            return $this->jsonResponse(['success' => false, 'message' => 'Subscription not found'], 404);
        }

        $dayOfMonth = (int)$request->input('day_of_month');

        if ($dayOfMonth < 1 || $dayOfMonth > 31) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Please select a day between 1 and 31'
            ], 400);
        }

        try {

            $preview = $this->subscriptionBillingService->previewBillingDateChange($subscriptionId, $dayOfMonth);

            if ($preview['success']) {
                return $this->resourceResponse([
                    'success' => true,
                    'data' => $preview
                ]);
            }

            return $this->resourceResponse([
                'success' => false,
                'message' => $preview['message'] ?? 'Failed to preview billing date change'
            ], 500);

        } catch (\Exception $e) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Failed to preview billing date change'
            ], 500);
        }
    }

    public function pauseDelivery(Request $request, int $subscriptionId)
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $member = MemberAuth::member();
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription || $subscription->member_id !== $member->id) {
            return $this->jsonResponse(['success' => false, 'message' => 'Subscription not found'], 404);
        }

        try {
            $pauseStart = new \DateTime($request->input('pause_start'));
            $pauseEnd = new \DateTime($request->input('pause_end'));
            $reason = $request->input('reason');

            $result = $this->deliveryService->pauseDelivery(
                $subscriptionId,
                $pauseStart,
                $pauseEnd,
                $reason
            );

            return $this->resourceResponse($result);
        } catch (\Exception $e) {
            return $this->resourceResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function resumeDelivery(Request $request, int $subscriptionId)
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $member = MemberAuth::member();
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription || $subscription->member_id !== $member->id) {
            return $this->jsonResponse(['success' => false, 'message' => 'Subscription not found'], 404);
        }

        try {
            $result = $this->deliveryService->resumeDelivery($subscriptionId);

            return $this->resourceResponse($result);
        } catch (\Exception $e) {
            return $this->resourceResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function getPauseStatus(int $subscriptionId)
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $member = MemberAuth::member();
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription || $subscription->member_id !== $member->id) {
            return $this->resourceResponse(['success' => false, 'message' => 'Subscription not found'], 404);
        }

        $status = $this->deliveryService->getPauseStatus($subscriptionId);

        return $this->resourceResponse($status);
    }

    public function autoRenew(Request $request, int $subscriptionId)
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $member = MemberAuth::member();
        $autoRenew = (bool)$request->input('auto_renew');
        $consentGiven = (bool)$request->input('consent_given', false);

        try {
            $result = $this->subscriptionService->updateAutoRenew(
                $subscriptionId,
                $member->id,
                $autoRenew,
                $consentGiven
            );

            return $this->jsonResponse([
                'success' => true,
                'message' => $autoRenew ? 'Auto-renewal enabled' : 'Auto-renewal disabled',
                'auto_renew' => $result['auto_renew'],
            ]);

        } catch (\InvalidArgumentException $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 404);
        } catch (\RuntimeException $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Logger::error('Failed to update auto-renewal', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);

            return $this->jsonResponse(['success' => false, 'message' => 'Failed to update auto-renewal'], 500);
        }
    }
}
