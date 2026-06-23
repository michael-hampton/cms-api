<?php

namespace App\Controllers\Crm;

use App\Controllers\Concerns\RequiresSitePermission;
use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Repositories\Billing\OrderRepository;
use App\Repositories\Billing\PaymentRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Billing\PaymentRefundPreviewService;

class CrmPaymentRefundPreviewController extends Controller
{
    use RequiresSitePermission;

    public function __construct(
        private readonly PaymentRepository $payments,
        private readonly SubscriptionRepository $subscriptions,
        private readonly OrderRepository $orders,
        private readonly PaymentRefundPreviewService $previewService,
    ) {
        parent::__construct();
    }

    public function show(Request $request, int $memberId, int $paymentId): mixed
    {
        if (!Auth::check()) {
            return $this->resourceResponse(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        if ($response = $this->requireSitePermission('crm.subscriptions.refund')) {
            return $response;
        }

        $payment = $this->payments->find($paymentId);
        $siteId = SiteContext::getId();

        if (!$payment || (int)$payment->site_id !== $siteId || !$this->paymentBelongsToMember($payment, $memberId)) {
            return $this->resourceResponse(['success' => false, 'message' => 'Payment not found.'], 404);
        }

        if (!$payment->subscription_id) {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Refund previews are only required for subscription payments.',
                'summary' => $this->previewService->summaryForPayment($payment),
            ], 422);
        }

        $subscription = $this->subscriptions->find((int)$payment->subscription_id);

        if (!$subscription || (int)$subscription->member_id !== $memberId || (int)$subscription->site_id !== $siteId) {
            return $this->resourceResponse(['success' => false, 'message' => 'Subscription payment not found.'], 404);
        }

        return $this->resourceResponse([
            'success' => true,
            'preview' => $this->previewService->subscriptionPreview($payment, $subscription),
        ]);
    }

    private function paymentBelongsToMember(mixed $payment, int $memberId): bool
    {
        if ($payment->subscription_id) {
            $subscription = $this->subscriptions->find((int)$payment->subscription_id);

            return $subscription && (int)$subscription->member_id === $memberId;
        }

        if ($payment->order_id) {
            $order = $this->orders->find((int)$payment->order_id);

            return $order && (int)$order->user_id === $memberId;
        }

        return false;
    }
}
