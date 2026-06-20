<?php

namespace App\Controllers\Members\Subscriptions;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Services\Subscriptions\MemberSubscriptionAccountContextResolver;
use App\Services\Subscriptions\SubscriptionAccountContext;
use App\Services\Subscriptions\SubscriptionAccountPageProvider;
use RuntimeException;

final class UnifiedMemberSubscriptionsController extends Controller
{
    public function __construct(
        private readonly SubscriptionAccountPageProvider $pageProvider,
        private readonly MemberSubscriptionAccountContextResolver $siteResolver,
    ) {
        parent::__construct();
    }

    public function __invoke(string $site): mixed
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/' . $site . '/member/login');
        }

        try {
            $resolvedSite = $this->siteResolver->resolve($site);
        } catch (RuntimeException) {
            return $this->notFound('Site not found');
        }

        $member = MemberAuth::getMember();
        $pageData = $this->pageProvider->forMember(
            (int) $member->id,
            (int) $resolvedSite->id,
            SubscriptionAccountContext::memberArea($resolvedSite, $site),
        );
        $pageData['member'] = $member;
        $pageData['site'] = $resolvedSite;
        $pageData['subscription_modal_data']['member'] = $member;

        return $this->view('member/subscriptions/unified', $pageData);
    }
}
