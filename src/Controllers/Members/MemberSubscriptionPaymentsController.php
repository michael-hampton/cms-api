<?php

namespace App\Controllers\Members;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Support\SiteContext;
use App\Repositories\PaymentRepository;
use App\Repositories\SubscriptionRepository;

class MemberSubscriptionPaymentsController extends Controller
{
    public function __construct(
        private PaymentRepository      $paymentRepository,
        private SubscriptionRepository $subscriptionRepository
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

        // Get all member subscriptions
        $subscriptions = $this->subscriptionRepository->getActiveSubscriptionForMember($member->id, $siteId);

        $subscriptionIds = $subscriptions->pluck('id');

        // Get all payments for these subscriptions
        $payments = collect();
        foreach ($subscriptionIds as $subscriptionId) {
            $subPayments = $this->paymentRepository->findBySubscriptionId($subscriptionId);
            $payments = $payments->merge($subPayments);
        }

        // Sort by date
        $payments = $payments->sortByDesc(function ($payment) {
            return $payment->created_at;
        });

        // Calculate summary
        $completedPayments = $payments->where('status', 'completed');
        $paymentSummary = [
            'total_count' => $payments->count(),
            'successful_count' => $completedPayments->count(),
            'failed_count' => $payments->where('status', 'failed')->count(),
            'total_paid' => $completedPayments->sum('amount'),
            'currency' => $completedPayments->first()->currency ?? 'USD'
        ];

        return $this->view('member/subscriptions/payments', [
            'member' => $member,
            'site' => SiteContext::get(),
            'payments' => $payments,
            'paymentSummary' => $paymentSummary
        ]);
    }
}