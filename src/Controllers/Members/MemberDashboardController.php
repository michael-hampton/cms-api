<?php

namespace App\Controllers\Members;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;

class MemberDashboardController extends Controller
{
    public function __construct(
    ) {
        parent::__construct();
    }

    public function index(Request $request)
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/' . SiteContext::slug() . '/member/login');
        }

        $member = MemberAuth::getMember();

        return $this->view(!empty($request->input('old')) ? 'member/dashboard-old' : 'member/dashboard', [
            'member' => $member,
            'site' => SiteContext::get(),
        ]);
    }

    private function getNewsletterCount(string $email, int $siteId): int
    {
        return $this->subscriberRepository->getNewslettersForMember($email, $siteId)->count();
    }
}