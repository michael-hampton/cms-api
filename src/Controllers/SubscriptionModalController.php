<?php

namespace App\Controllers;

use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Services\SubscriptionModalService;

class SubscriptionModalController extends Controller
{
    public function __construct(
        private SubscriptionModalService $modalService
    )
    {
        parent::__construct();
    }

    public function markShown(Request $request)
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Not authenticated'
            ], 401);
        }

        $member = MemberAuth::member();
        $siteId = SiteContext::getId();

        $this->modalService->markModalShown($member->id, $siteId);

        return $this->jsonResponse([
            'success' => true,
            'message' => 'Modal marked as shown'
        ]);
    }
}