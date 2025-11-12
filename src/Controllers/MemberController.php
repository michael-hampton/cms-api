<?php

namespace App\Controllers;

use App\Framework\Authorization\Auth;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Repositories\MemberRepository;

class MemberController extends Controller
{
    public function __construct(private readonly MemberRepository $memberRepository)
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

            $updatedMember = $this->memberRepository->updateAccountDetails($member->id, $data);

            if (!$updatedMember) {
                return $this->back();
            }

            return $this->redirect("/{$site->slug}/member/account-details");

        } catch (\InvalidArgumentException $e) {
            return $this->back();
        } catch (\Exception $e) {
            return $this->back();
        }
    }
}