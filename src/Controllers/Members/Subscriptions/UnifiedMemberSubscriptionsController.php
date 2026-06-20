<?php

namespace App\Controllers\Members\Subscriptions;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Models\Site;
use App\Services\Subscriptions\SubscriptionAccountContext;
use App\Services\Subscriptions\SubscriptionAccountPageProvider;

final class UnifiedMemberSubscriptionsController extends Controller
{
    public function __construct(private readonly SubscriptionAccountPageProvider $pageProvider)
    {
        parent::__construct();
    }

    public function __invoke(string $site): mixed
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/' . $site . '/member/login');
        }

        $member = MemberAuth::getMember();
        $resolvedSite = Site::where('slug', $site)
            ->where('is_active', 1)
            ->first();

        if (!$resolvedSite) {
            return $this->notFound('Site not found');
        }

        $pageData = $this->pageProvider->forMember(
            (int) $member->id,
            (int) $resolvedSite->id,
            SubscriptionAccountContext::memberArea($resolvedSite),
        );
        $pageData['member'] = $member;
        $pageData['site'] = $resolvedSite;
        $pageData['subscription_modal_data']['member'] = $member;

        return $this->view('member/subscriptions/unified', $pageData);
    }
}
