<?php

namespace App\Controllers\Subscription;

use App\Controllers\Controller;
use App\Events\Members\MemberPostcodeUpdated;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Requests\CreateAddressRequest;
use App\Requests\UpdateAddressRequest;
use App\Services\Members\MemberAddressBookService;
use Throwable;

class ShopAccountAddressApiController extends Controller
{
    public function __construct(
        private readonly MemberAddressBookService $addressBook,
    ) {
        parent::__construct();
    }

    public function index(Request $request): mixed
    {
        $member = MemberAuth::getMember();

        if (!$member) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorised.'], 401);
        }

        return $this->jsonResponse([
            'success' => true,
            'items' => $this->addressBook->list((int) $member->id),
        ]);
    }

    public function store(CreateAddressRequest $request): mixed
    {
        $member = MemberAuth::getMember();

        if (!$member) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorised.'], 401);
        }

        try {
            $data = $request->validated();
            $address = $this->addressBook->create($member, $data, null);

            if (!empty($data['postcode'])) {
                event(new MemberPostcodeUpdated($member, $data['postcode'], null));
            }

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Address added successfully.',
                'address' => $address->toArray(),
            ]);
        } catch (Throwable $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage() ?: 'Failed to add address.',
            ], 422);
        }
    }

    public function update(int $id, UpdateAddressRequest $request): mixed
    {
        $member = MemberAuth::getMember();

        if (!$member) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorised.'], 401);
        }

        try {
            $address = $this->addressBook->ownedAddress($member, $id);
            $originalPostcode = $address->postcode;
            $data = $request->validated();
            $updated = $this->addressBook->update($member, $id, $data);

            if (!empty($data['postcode'])) {
                event(new MemberPostcodeUpdated($member, $data['postcode'], $originalPostcode));
            }

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Address updated successfully.',
                'address' => $updated->toArray(),
            ]);
        } catch (Throwable $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage() ?: 'Failed to update address.',
            ], 422);
        }
    }

    public function destroy(int $id, Request $request): mixed
    {
        $member = MemberAuth::getMember();

        if (!$member) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorised.'], 401);
        }

        try {
            $this->addressBook->delete($member, $id);

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Address deleted successfully.',
            ]);
        } catch (Throwable $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage() ?: 'Failed to delete address.',
            ], 422);
        }
    }

    public function setDefault(int $id, Request $request): mixed
    {
        $member = MemberAuth::getMember();

        if (!$member) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorised.'], 401);
        }

        try {
            $address = $this->addressBook->setDefault($member, $id);

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Default address updated.',
                'address' => $address->toArray(),
            ]);
        } catch (Throwable $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage() ?: 'Failed to set default address.',
            ], 422);
        }
    }
}
