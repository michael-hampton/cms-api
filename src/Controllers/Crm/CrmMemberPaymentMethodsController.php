<?php

namespace App\Controllers\Crm;

use App\Controllers\Controller;
use App\Controllers\Concerns\RequiresSitePermission;
use App\DTO\Billing\PaymentMethodDto;
use App\Repositories\Members\MemberRepository;
use App\Services\Billing\Stripe\StripeCustomerPaymentMethodService;

class CrmMemberPaymentMethodsController extends Controller
{
    use RequiresSitePermission;

    public function __construct(
        private readonly MemberRepository $memberRepository,
        private readonly StripeCustomerPaymentMethodService $paymentMethodService,
    )
    {
        parent::__construct();
    }

    public function index(int $memberId): mixed
    {
        if ($response = $this->requireSitePermission('crm.payment_methods.view')) {
            return $response;
        }

        $member = $this->memberRepository->find($memberId);

        if (!$member) {
            return $this->jsonResponse(['success' => false, 'message' => 'Member not found.'], 404);
        }

        $methods = $this->paymentMethodService->getCustomerPaymentMethods($member);

        return $this->resourceResponse([
            'payment_methods' => array_map(
                static fn (PaymentMethodDto $method) => $method->toArray(),
                $methods['payment_methods'] ?? []
            ),
        ]);
    }
}
