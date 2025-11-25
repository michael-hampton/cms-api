<?php

namespace App\Controllers\Members;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Repositories\CategoryRepository;
use App\Repositories\SubscriptionRepository;
use App\Services\MemberSubscriptionService;
use App\Services\SubscriptionPlanService;

class MemberSubscriptionsController extends Controller
{
    public function __construct(
        private readonly SubscriptionRepository    $subscriptionRepository,
        private readonly MemberSubscriptionService $subscriptionService,
        private readonly CategoryRepository        $categoryRepository,
        private readonly SubscriptionPlanService   $subscriptionPlanService
    )
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

        // Get email subscription preferences
        $subscriptionSummary = $this->subscriptionService->getSubscriptionSummary($member->id, $siteId);

        $plans = $this->subscriptionPlanService->getAvailablePlans($siteId);

        return $this->view('member/subscriptions/index', [
            'member' => $member,
            'site' => SiteContext::get(),
            'activeSubscription' => $activeSubscription,
            'subscriptionHistory' => $subscriptionHistory,
            'subscriptionSummary' => $subscriptionSummary,
            'plans' => $plans
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
        $repo = new \App\Repositories\MemberSubscriptionPreferenceRepository();
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
        $success = $this->subscriptionService->unsubscribeByToken($token);

        if (!$success) {
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

        if ($request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
            return $this->resourceResponse([
                'success' => true,
                'message' => 'Successfully unsubscribed from all emails'
            ]);
        }

        return $this->view('member/subscriptions/unsubscribe-success', [
            'site' => SiteContext::get(),
            'token' => $token
        ]);
    }

    public function resubscribe(Request $request, string $token)
    {
        $success = $this->subscriptionService->resubscribeByToken($token);

        if (!$success) {
            if ($request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
                return $this->resourceResponse([
                    'success' => false,
                    'message' => 'Invalid token'
                ], 404);
            }

            $_SESSION['flash_success'] = 'Your subscription preferences have been updated.';
            return $this->redirect('/member/subscriptions');
        }

        if ($request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
            return $this->resourceResponse([
                'success' => true,
                'message' => 'Successfully resubscribed'
            ]);
        }

        $_SESSION['flash_success'] = 'Your subscription preferences have been updated.';
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

        if ($this->subscriptionRepository->cancelSubscription($subscriptionId)) {
            return $this->jsonResponse([
                'success' => true,
                'message' => 'Subscription cancelled successfully'
            ]);
        }

        return $this->jsonResponse(['success' => false, 'message' => 'Failed to cancel subscription'], 500);
    }

    public function manageByToken(string $token)
    {
        $repo = new \App\Repositories\MemberSubscriptionPreferenceRepository();
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
        $repo = new \App\Repositories\MemberSubscriptionPreferenceRepository();
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
                    'data' => ['preference' => $updated]
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
}