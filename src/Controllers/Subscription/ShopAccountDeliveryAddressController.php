<?php

namespace App\Controllers\Subscription;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Repositories\Members\AddressRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;

final class ShopAccountDeliveryAddressController extends Controller
{
    public function __construct(
        private readonly AddressRepository $addressRepository,
        private readonly SubscriptionRepository $subscriptionRepository,
    ) {
        parent::__construct();
    }

    public function index(int $id): mixed
    {
        $member = MemberAuth::getMember();
        if (!$member) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorised.'], 401);
        }

        $subscription = $this->subscriptionRepository->find($id);
        if (!$subscription || $subscription->member_id !== $member->id || !$subscription->isPrint()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Subscription not found.'], 404);
        }

        $addresses = $this->addressRepository
            ->getShippingAddressesForMember($member->id)
            ->map(static fn($address) => [
                'id' => $address->id,
                'address_line_1' => $address->address_line_1,
                'address_line_2' => $address->address_line_2,
                'city' => $address->city,
                'postcode' => $address->postcode,
                'country' => $address->country,
                'is_default' => (bool) $address->is_default,
            ])
            ->toArray();

        return $this->jsonResponse([
            'success' => true,
            'addresses' => $addresses,
        ]);
    }

    public function setDefault(int $id, int $addressId): mixed
    {
        $member = MemberAuth::getMember();
        if (!$member) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorised.'], 401);
        }

        $subscription = $this->subscriptionRepository->find($id);
        if (!$subscription || $subscription->member_id !== $member->id || !$subscription->isPrint()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Subscription not found.'], 404);
        }

        if (!$this->addressRepository->setDefaultAddress($addressId, $member->id)) {
            return $this->jsonResponse(['success' => false, 'message' => 'Address not found.'], 404);
        }

        return $this->jsonResponse([
            'success' => true,
            'message' => 'Delivery address updated.',
        ]);
    }
}
