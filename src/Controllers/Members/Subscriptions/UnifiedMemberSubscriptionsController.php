<?php

namespace App\Controllers\Members\Subscriptions;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Support\SiteContext;
use App\Services\Subscriptions\SubscriptionAccountContext;
use App\Services\Subscriptions\SubscriptionAccountPageProvider;

final class UnifiedMemberSubscriptionsController extends Controller
{
    public function __construct(private readonly SubscriptionAccountPageProvider $pageProvider)
    {
        parent::__construct();
    }

    public function __invoke(): mixed
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/member/login');
        }

        $member = MemberAuth::getMember();
        $site = SiteContext::get();
        $pageData = $this->pageProvider->forMember(
            $member->id,
            SiteContext::getId(),
            SubscriptionAccountContext::memberArea($site),
        );
        $pageData['member'] = $member;
        $pageData['site'] = $site;
        $pageData['subscription_modal_data']['member'] = $member;

        return $this->view('member/subscriptions/unified', $pageData);
    }
}
