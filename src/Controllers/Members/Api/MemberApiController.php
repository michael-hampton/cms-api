<?php

namespace App\Controllers\Members\Api;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Repositories\Members\MemberRepository;
use App\Services\Billing\PaymentProviders\StripePaymentProcessor;
use App\Services\Subscriptions\MemberSubscriptionService;

class MemberApiController extends Controller
{
    public function __construct(
        private readonly MemberRepository          $memberRepository,
        private readonly StripePaymentProcessor    $stripeProcessor,
        private readonly MemberSubscriptionService $subscriptionService
    )
    {
        parent::__construct();
    }

    /**
     * Get current member data
     */
    public function me(): JsonResponse
    {
        if (!MemberAuth::check()) return $this->jsonResponse(['success' => false], 401);

        $member = MemberAuth::getMember();

        $memberWithRelations = $this->memberRepository
            ->find($member->id, ['roles', 'addresses', 'subscriptions']);

        return $this->resourceResponse([
            'success' => true,
            'data' => [
                'member' => $memberWithRelations->toArray(),
                'preferences' => $member->communication_preferences ?? [],
                'site_slug' => SiteContext::slug()
            ]
        ]);
    }

    /**
     * Update Profile Information
     */
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

                    return $this->errorResponse('Error updating stripe');
                }
            }

            return $this->resourceResponse(['success' => true, 'data' => $updatedMember]);

        } catch (\InvalidArgumentException $e) {
            return $this->back();
        } catch (\Exception $e) {
            return $this->back();
        }
    }

    /**
     * Update Privacy Settings
     */
    public function updatePrivacy(Request $request): JsonResponse
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $member = MemberAuth::member();

        $updated = $this->memberRepository->update($member->id, [
            'show_activity' => $request->input('show_activity') == 1,
            'show_badges' => $request->input('show_badges') == 1,
        ]);

        return $this->jsonResponse([
            'success' => (bool)$updated,
            'message' => 'Privacy settings updated'
        ]);
    }

    public function updateCommunicationPreferences(Request $request)
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $member = MemberAuth::member();


        if ($request->has('preferences')) {
            $preferences = $request->input('preferences');
        } else {
            $preferences = [
                'marketing_emails' => $request->input('marketing_emails'),
                'special_offers' => $request->input('special_offers'),
                'third_party_communications' => $request->input('third_party_communications', false),
                'product_updates' => $request->input('product_updates', false),
                'newsletter' => $request->input('newsletter', false)
            ];
        }


        $updated = $this->subscriptionService->updateCommunicationPreferences(
            $member->id,
            $preferences
        );

        return $this->jsonResponse([
            'success' => true,
            'message' => 'Communication preferences updated successfully'
        ]);
    }
}