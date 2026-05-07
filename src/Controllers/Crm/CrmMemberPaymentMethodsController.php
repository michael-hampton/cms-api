<?php

namespace App\Controllers\Crm;

use App\Controllers\Controller;
use App\Repositories\Members\MemberRepository;
use App\Services\Billing\PaymentProviders\StripePaymentProcessor;

class CrmMemberPaymentMethodsController extends Controller
{
    public function __construct(
        private readonly MemberRepository       $memberRepository,
        private readonly StripePaymentProcessor $stripePaymentProcessor,
    )
    {
        parent::__construct();
    }

    public function index(int $memberId): mixed
    {
        $member = $this->memberRepository->find($memberId);

        if (!$member) {
            return $this->jsonResponse(['success' => false, 'message' => 'Member not found.'], 404);
        }

        $methods = $this->stripePaymentProcessor->getCustomerPaymentMethods($member);

        return $this->resourceResponse([
            'payment_methods' => $methods['payment_methods'],
        ]);
    }
}