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

    public function search(Request $request, string $site)
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
}