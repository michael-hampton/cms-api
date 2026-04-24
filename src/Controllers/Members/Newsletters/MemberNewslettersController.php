<?php

namespace App\Controllers\Members\Newsletters;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Support\SiteContext;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Repositories\Newsletters\SubscriberRepository;
use App\Services\Newsletter\NewsletterAccessService;
use App\Services\Subscriptions\SubscriptionPlanService;

class MemberNewslettersController extends Controller
{
    public function __construct(
        private readonly SubscriberRepository $subscriberRepository,
        private readonly NewsletterRepository $newsletterRepository,
        private readonly NewsletterAccessService     $newsletterAccessService,
        private readonly SubscriptionPlanService     $subscriptionPlanService,
    )
    {
        parent::__construct();
    }

    public function index()
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/member/login');
        }

        $member = MemberAuth::getMember();
        $siteId = SiteContext::getId();

        $subscriptions = $this->subscriberRepository->getNewslettersForMember($member->email, $siteId);
        $availableNewsletters = $this->newsletterRepository->where('site_id', $siteId)
            ->where('active', true)
            ->get();

        // Check access for each newsletter
        // Check access for each newsletter
        $newslettersWithAccess = $availableNewsletters->map(function ($newsletter) use ($member, $siteId) {
            $accessCheck = $this->newsletterAccessService->checkAccess(
                $newsletter->id,
                $member->id,
                $siteId
            );

            return [
                'newsletter' => $newsletter,
                'has_access' => $accessCheck['has_access'],
                'access_reason' => $accessCheck['reason'],
                'access_message' => $accessCheck['message'] ?? null,
                'required_level' => $accessCheck['required_level'] ?? null
            ];
        });

        // Get plans for upgrade CTAs
        $plans = $this->subscriptionPlanService->getActivePlansForSite($siteId);

        return $this->view('member/newsletters/index', [
            'member' => $member,
            'site' => SiteContext::get(),
            'subscriptions' => $subscriptions,
            'availableNewsletters' => $availableNewsletters,
            'newslettersWithAccess' => $newslettersWithAccess,
            'plans' => $plans
        ]);
    }
}