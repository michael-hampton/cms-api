<?php

namespace App\Controllers\Members\Api\Subscriptions;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Support\SiteContext;
use App\Repositories\Billing\PaymentRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;

class MemberSubscriptionPaymentsApiController extends Controller
{
    public function __construct(
        private readonly PaymentRepository      $paymentRepository,
        private readonly SubscriptionRepository $subscriptionRepository,
    )
    {
        parent::__construct();
    }

    public function index(): mixed
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $member = MemberAuth::getMember();
        $siteId = SiteContext::getId();

        $subscriptions = $this->subscriptionRepository->getSubscriptionHistory($member->id, $siteId);

        if ($subscriptions->isEmpty()) {
            return $this->jsonResponse([
                'success' => true,
                'payments' => [],
                'paymentSummary' => [
                    'total_count' => 0,
                    'currency' => 'GBP',
                    'total_paid' => 0,
                    'successful_count' => 0,
                    'failed_count' => 0,
                ],
            ]);
        }

        $payments = collect();
        foreach ($subscriptions->pluck('id') as $subscriptionId) {
            $payments = $payments->merge(
                $this->paymentRepository->findBySubscriptionId($subscriptionId)
            );
        }

        $payments = $payments->sortByDesc(fn($p) => $p->created_at->format('Y-m-d H:i:s'))->values();

        $completed = $payments->where('status', 'completed');

        return $this->jsonResponse([
            'success' => true,
            'payments' => $payments,
            'paymentSummary' => [
                'total_count' => $payments->count(),
                'successful_count' => $completed->count(),
                'failed_count' => $payments->where('status', 'failed')->count(),
                'total_paid' => $completed->sum('amount'),
                'currency' => $completed->first()?->currency ?? 'GBP',
            ],
        ]);
    }
}
