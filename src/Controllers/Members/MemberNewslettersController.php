<?php

namespace App\Controllers\Members;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Repositories\NewsletterRepository;
use App\Repositories\SubscriberRepository;

class MemberNewslettersController extends Controller
{
    public function __construct(
        private SubscriberRepository $subscriberRepository,
        private NewsletterRepository $newsletterRepository
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

        $subscriptions = $this->subscriberRepository->getNewslettersForMember($member->email, $siteId);
        $availableNewsletters = $this->newsletterRepository->where('site_id', $siteId)
            ->where('active', true)
            ->get();

        return $this->view('member/newsletters/index', [
            'member' => $member,
            'site' => SiteContext::get(),
            'subscriptions' => $subscriptions,
            'availableNewsletters' => $availableNewsletters
        ]);
    }

    public function unsubscribe(Request $request)
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $member = MemberAuth::member();
        $subscriberId = $request->input('subscriber_id');

        $subscriber = $this->subscriberRepository->find($subscriberId);

        if (!$subscriber || $subscriber->email !== $member->email) {
            return $this->jsonResponse(['success' => false, 'message' => 'Subscription not found'], 404);
        }

        if ($this->subscriberRepository->delete($subscriberId)) {
            return $this->jsonResponse([
                'success' => true,
                'message' => 'Unsubscribed successfully'
            ]);
        }

        return $this->jsonResponse(['success' => false, 'message' => 'Failed to unsubscribe'], 500);
    }
}