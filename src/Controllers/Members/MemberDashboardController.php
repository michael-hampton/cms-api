<?php

namespace App\Controllers\Members;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Support\SiteContext;
use App\Models\Address;
use App\Repositories\CommentRepository;
use App\Repositories\MemberRepository;
use App\Repositories\NewsletterRepository;
use App\Repositories\OrderRepository;
use App\Repositories\PageLikeRepository;
use App\Repositories\PageRepository;
use App\Repositories\PageViewRepository;
use App\Repositories\SubscriberRepository;
use App\Repositories\SubscriptionRepository;

class MemberDashboardController extends Controller
{
    public function __construct(
        private OrderRepository $orderRepository,
        private SubscriptionRepository $subscriptionRepository,
        private SubscriberRepository $subscriberRepository,
        private CommentRepository $commentRepository,
        private MemberRepository $memberRepository,
        private PageRepository $pageRepository,
        private NewsletterRepository $newsletterRepository,
        private PageViewRepository $pageViewRepository,
        private PageLikeRepository $pageLikeRepository
    ) {
        parent::__construct();
    }

    public function index()
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/member/login');
        }

        $member = MemberAuth::member();
        $siteId = SiteContext::getId();

        // Get counts for dashboard cards
        $stats = [
            'orders' => $this->orderRepository->getOrderCount(),
            'subscriptions' => $this->subscriptionRepository->countActiveSubscriptions($member->id, $siteId),
            'newsletters' => $this->getNewsletterCount($member->email, $siteId),
            'addresses' => Address::where('member_id', $member->id)->count(),
            'comments' => $this->commentRepository->countApprovedCommentsByEmail($member->email),
            'pages_read' => $this->pageViewRepository->getUniquePagesViewedByMember($member->id, $siteId),
            'likes' => $this->pageLikeRepository->getMemberLikeCount($member->id, $siteId)
        ];

        // Get recommended pages
        $recommendedPages = $this->pageRepository->getFeaturedPages(6, $siteId);

        return $this->view('member/dashboard', [
            'member' => $member,
            'site' => SiteContext::get(),
            'stats' => $stats,
            'recommendedPages' => $recommendedPages
        ]);
    }

    private function getNewsletterCount(string $email, int $siteId): int
    {
        return $this->subscriberRepository->getNewslettersForMember($email, $siteId)->count();
    }
}