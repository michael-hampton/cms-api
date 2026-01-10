<?php

namespace App\Controllers\Members\Newsletters;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Mail\MailManager;
use App\Framework\Support\Logger;
use App\Framework\Support\SiteContext;
use App\Mail\NewsletterSignupConfirmationWithTracking;
use App\Repositories\NewsletterRepository;
use App\Repositories\SubscriberRepository;
use App\Services\Newsletter\NewsletterSignupService;

class MemberNewslettersController extends Controller
{
    public function __construct(
        private readonly SubscriberRepository    $subscriberRepository,
        private readonly NewsletterRepository    $newsletterRepository,
        private readonly NewsletterSignupService $newsletterSignupService,
    ) {
        parent::__construct();
    }

    public function index()
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/member/login');
        }

        $member = MemberAuth::member();
        $siteId = SiteContext::getId();

        $subscriptions = $this->subscriberRepository->getNewslettersForMember($member->email, $siteId);
        $availableNewsletters = $this->newsletterRepository->where('site_id', $siteId)
            ->where('active', true)
            ->get();

        return $this->view('member/newsletters/index', [
            'member' => $member,
            'site' => SiteContext::get(),
            'subscriptions' => $subscriptions,
            'availableNewsletters' => $availableNewsletters
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
}