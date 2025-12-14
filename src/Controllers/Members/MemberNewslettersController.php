<?php

namespace App\Controllers\Members;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Repositories\NewsletterRepository;
use App\Repositories\SubscriberRepository;

class MemberNewslettersController extends Controller
{
    public function __construct(
        private SubscriberRepository $subscriberRepository,
        private NewsletterRepository $newsletterRepository
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

        $member = MemberAuth::member();
        $subscriberId = $request->input('subscriber_id');

        $subscriber = $this->subscriberRepository->find($subscriberId);

        if (!$subscriber || $subscriber->email !== $member->email) {
            return $this->jsonResponse(['success' => false, 'message' => 'Subscription not found'], 404);
        }

        if ($this->subscriberRepository->delete($subscriberId)) {
            return $this->jsonResponse([
                'success' => true,
                'message' => 'Unsubscribed successfully'
            ]);
        }

        return $this->jsonResponse(['success' => false, 'message' => 'Failed to unsubscribe'], 500);
    }

    public function subscribe(Request $request)
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $member = MemberAuth::member();
        $siteId = SiteContext::getId();
        $newsletterId = $request->input('newsletter_id');

        // Check if newsletter exists and is active
        $newsletter = $this->newsletterRepository->find($newsletterId);

        if (!$newsletter || !$newsletter->active || $newsletter->site_id !== $siteId) {
            return $this->jsonResponse(['success' => false, 'message' => 'Newsletter not found'], 404);
        }

        // Check if already subscribed
        $existing = $this->subscriberRepository->findByEmailAndNewsletter($member->email, $newsletterId, $siteId);

        if ($existing) {
            return $this->jsonResponse(['success' => false, 'message' => 'Already subscribed to this newsletter'], 400);
        }

        // Create subscription
        $subscriber = $this->subscriberRepository->create([
            'email' => $member->email,
            'newsletter_id' => $newsletterId,
            'site_id' => $siteId,
            'confirmed' => true, // Auto-confirm for logged-in members
            'confirmation_token' => bin2hex(random_bytes(16)),
            'unsubscribe_token' => bin2hex(random_bytes(16)),
            'subscribed_at' => date('Y-m-d H:i:s')
        ]);

        if ($subscriber) {
            return $this->jsonResponse([
                'success' => true,
                'message' => 'Successfully subscribed to newsletter'
            ]);
        }

        return $this->jsonResponse(['success' => false, 'message' => 'Failed to subscribe'], 500);
    }

    public function bulkSubscribe(Request $request)
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $member = MemberAuth::member();
        $siteId = SiteContext::getId();
        $newsletterIds = $request->input('newsletter_ids', []);

        if (empty($newsletterIds)) {
            return $this->jsonResponse(['success' => false, 'message' => 'No newsletters selected'], 400);
        }

        $successCount = 0;
        $errors = [];

        foreach ($newsletterIds as $newsletterId) {
            // Check if newsletter exists and is active
            $newsletter = $this->newsletterRepository->find($newsletterId);

            if (!$newsletter || !$newsletter->active || $newsletter->site_id !== $siteId) {
                $errors[] = "Newsletter ID $newsletterId not found or inactive";
                continue;
            }

            // Check if already subscribed
            $existing = $this->subscriberRepository->findByEmailAndNewsletter($member->email, $newsletterId, $siteId);

            if ($existing) {
                continue; // Skip if already subscribed
            }

            // Create subscription
            $subscriber = $this->subscriberRepository->create([
                'email' => $member->email,
                'newsletter_id' => $newsletterId,
                'site_id' => $siteId,
                'confirmed' => true, // Auto-confirm for logged-in members
                'confirmation_token' => bin2hex(random_bytes(16)),
                'unsubscribe_token' => bin2hex(random_bytes(16)),
                'subscribed_at' => date('Y-m-d H:i:s')
            ]);

            if ($subscriber) {
                $successCount++;
            }
        }

        if ($successCount > 0) {
            return $this->jsonResponse([
                'success' => true,
                'message' => "Successfully subscribed to $successCount newsletter(s)",
                'count' => $successCount
            ]);
        }

        return $this->jsonResponse([
            'success' => false,
            'message' => 'Failed to subscribe to newsletters',
            'errors' => $errors
        ], 400);
    }
}