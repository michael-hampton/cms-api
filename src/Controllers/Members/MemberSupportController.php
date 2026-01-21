<?php

namespace App\Controllers\Members;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Models\SupportTicket;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Members\EmailService;

class MemberSupportController extends Controller
{
    public function __construct(
        private readonly EmailService           $emailService,
        private readonly SubscriptionRepository $subscriptionRepository
    )
    {
        parent::__construct();
    }

    public function index()
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/' . SiteContext::slug() . '/member/login');
        }

        $member = MemberAuth::getMember();
        $siteId = SiteContext::getId();

        if (!$member) {
            return $this->errorResponse('Member not found', 302);
        }

        // Get member's active subscriptions for the dropdown
        $activeSubscriptions = $this->subscriptionRepository->getActiveSubscriptionForMember($member->id, $siteId);

        $contactReasons = [
            'delivery_issue' => 'Delivery Issue',
            'billing_question' => 'Billing Question',
            'account_access' => 'Account Access',
            'subscription_change' => 'Subscription Change',
            'technical_issue' => 'Technical Issue',
            'content_feedback' => 'Content Feedback',
            'other' => 'Other'
        ];

        return $this->view('member/support/contact', [
            'member' => $member,
            'site' => SiteContext::get(),
            'activeSubscriptions' => $activeSubscriptions,
            'contactReasons' => $contactReasons
        ]);
    }

    public function submit(Request $request)
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        $member = MemberAuth::getMember();
        $data = $request->all();

        // Validate required fields
        if (empty($data['reason']) || empty($data['message'])) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Please fill in all required fields'
            ], 400);
        }

        // Create support ticket using model
        $ticket = SupportTicket::create([
            'member_id' => $member->id,
            'site_id' => SiteContext::getId(),
            'reason' => $data['reason'],
            'subscription_id' => $data['subscription_id'] ?: null,
            'brand' => $data['brand'] ?? null,
            'message' => $data['message'],
            'contact_name' => $data['contact_name'] ?? ($member->first_name . ' ' . $member->last_name),
            'contact_email' => $data['contact_email'] ?? $member->email,
            'contact_phone' => $data['contact_phone'] ?? null,
            'status' => 'open'
        ]);

        // Send confirmation email to member
        $this->emailService->sendSupportConfirmation($ticket, $member);

        // Send notification to support team
        $this->emailService->sendSupportNotification($ticket, $member);

        return $this->resourceResponse([
            'success' => true,
            'message' => 'Your request has been received. We\'ll get back to you shortly.',
            'ticket_id' => $ticket->id
        ]);
    }
}