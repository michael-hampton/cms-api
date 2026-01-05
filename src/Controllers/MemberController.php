<?php

namespace App\Controllers;

use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Repositories\MemberRepository;
use App\Services\Payment\StripePaymentProcessor;

class MemberController extends Controller
{
    public function __construct(
        private readonly MemberRepository       $memberRepository,
        private readonly StripePaymentProcessor $stripeProcessor
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
        $member = MemberAuth::member();

        // Get fresh member instance with relationships
        $memberWithRelations = $this->memberRepository
            ->find($member->id, ['roles', 'addresses', 'subscriptions']);

        return $this->view('member/account-details', [
            'site' => $site,
            'member' => $memberWithRelations ?? $member,
            'pageTitle' => 'Account Details'
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
}