<?php

namespace App\Controllers;

use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Repositories\Members\MemberRepository;
use App\Services\Billing\PaymentProviders\StripePaymentProcessor;
use App\Services\Subscriptions\MemberSubscriptionService;

class MemberController extends Controller
{
    public function __construct(
        private readonly MemberRepository       $memberRepository,
        private readonly StripePaymentProcessor    $stripeProcessor,
        private readonly MemberSubscriptionService $subscriptionService
    )
    {
        parent::__construct();
    }

    public function search(Request $request, string $siteName)
    {
        try {
            $site = SiteContext::get();

            $search = $request->get('search', '');
            $perPage = min($request->get('per_page', 10), 50);

            $members = $this->memberRepository->searchMembers($search, $perPage);

            return $this->resourceResponse([
                'success' => true,
                'items' => $members,
                'total' => $members->count()
            ]);

        } catch (\Exception $e) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Failed to search members'
            ], 500);
        }
    }

    public function me(): JsonResponse
    {
        return $this->resourceResponse(['member' => MemberAuth::member()]);
    }

    public function accountDetails()
    {
        $site = SiteContext::get();
        $member = MemberAuth::getMember();

        // Get fresh member instance with relationships
        $memberWithRelations = $this->memberRepository
            ->find($member->id, ['roles', 'addresses', 'subscriptions']);

        return $this->view('member/account-details', [
            'site' => $site,
            'member' => $memberWithRelations ?? $member,
            'pageTitle' => 'Account Details',
            'preferences' => $member->communication_preferences ?? []
        ]);
    }

    public function updateAccountDetails(Request $request)
    {
        try {
            $site = SiteContext::get();
            $member = MemberAuth::member();

            $data = $request->only(['first_name', 'last_name', 'display_name', 'email']);

            // Check if email is being updated
            $emailChanged = isset($data['email']) && $data['email'] !== $member->email;
            $oldEmail = $member->email;

            $updatedMember = $this->memberRepository->updateAccountDetails($member->id, $data);

            if (!$updatedMember) {
                return $this->back();
            }

            // Update Stripe customer email if changed and customer exists
            if ($emailChanged && $updatedMember->stripe_customer_id) {
                try {
                    $this->stripeProcessor->updateCustomerEmail(
                        $updatedMember->stripe_customer_id,
                        $data['email']
                    );
                } catch (\Exception $e) {
                    // Log error but don't fail the update
                    \App\Framework\Support\Logger::error('Failed to update Stripe customer email', [
                        'member_id' => $member->id,
                        'stripe_customer_id' => $updatedMember->stripe_customer_id,
                        'old_email' => $oldEmail,
                        'new_email' => $data['email'],
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return $this->redirect("/{$site->slug}/member/account-details");

        } catch (\InvalidArgumentException $e) {
            return $this->back();
        } catch (\Exception $e) {
            return $this->back();
        }
    }

    public function communicationPreferences()
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/member/login');
        }

        $member = MemberAuth::member();

        return $this->view('member/profile/communication-preferences', [
            'member' => $member,
            'site' => SiteContext::get(),
            'preferences' => $member->communication_preferences ?? []
        ]);
    }

    public function updateCommunicationPreferences(Request $request)
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $member = MemberAuth::member();

        $preferences = [
            'marketing_emails' => $request->input('marketing_emails', false),
            'special_offers' => $request->input('special_offers', false),
            'third_party_communications' => $request->input('third_party_communications', false),
            'product_updates' => $request->input('product_updates', false),
            'newsletter' => $request->input('newsletter', false)
        ];

        $updated = $this->subscriptionService->updateCommunicationPreferences(
            $member->id,
            $preferences
        );

        if ($request->getHeader('X-Requested-With') === 'XMLHttpRequest') {
            return $this->jsonResponse([
                'success' => true,
                'message' => 'Communication preferences updated successfully'
            ]);
        }

        $_SESSION['flash_success'] = 'Communication preferences updated successfully';
        return $this->redirect('/' . SiteContext::slug() . '/member/account-details');
    }
}