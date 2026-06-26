<?php

namespace App\Controllers\Members\Api;

use App\Controllers\Controller;
use App\Events\Members\MemberPostcodeUpdated;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Models\Address;
use App\Models\Member;
use App\Repositories\Members\AddressRepository;
use App\Requests\CreateAddressRequest;
use App\Requests\UpdateAddressRequest;
use App\Services\Members\MemberAddressBookService;
use Exception;

class MemberAddressApiController extends Controller
{
    public function __construct(
        private readonly AddressRepository $addressRepository,
        private readonly MemberAddressBookService $addressBook,
    ) {
        parent::__construct();
    }

    public function index()
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/member/login');
        }

        $member = MemberAuth::member();

        return $this->resourceResponse(['items' => $this->addressBook->list((int) $member->id)]);
    }

    public function search(int $memberId)
    {
        $addresses = $this->addressRepository->getAddressesForMember($memberId);

        return $this->resourceResponse(['items' => $addresses->toArray()]);
    }

    public function show(int $memberId)
    {
        $member = Member::find($memberId);
        $addresses = $this->addressRepository->getAddressesForMember($memberId);

        return $this->resourceResponse(['items' => $addresses]);
    }

    public function store(CreateAddressRequest $request)
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/member/login');
        }

        try {
            $member = MemberAuth::getMember();
            $data = $request->validated();
            $address = $this->addressBook->create($member, $data, SiteContext::getId());

            if (!empty($data['postcode'])) {
                event(new MemberPostcodeUpdated($member, $data['postcode'], null));
            }

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Address added successfully',
                'address' => $address->toArray(),
            ]);
        } catch (ValidationException $validationException) {
            return $this->errorResponse('Validation failed', 422, $validationException->getErrors());
        } catch (Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => 'Failed to add address'], 422);
        }
    }

    public function update(int $id, UpdateAddressRequest $request)
    {
        if (!MemberAuth::check()) {
            return $this->errorResponse('Unauthorized', 401);
        }

        try {
            $member = MemberAuth::getMember();
            $address = $this->addressBook->ownedAddress($member, $id);
            $originalPostcode = $address->postcode;
            $data = $request->validated();
            $updated = $this->addressBook->update($member, $id, $data);

            if (!empty($data['postcode'])) {
                event(new MemberPostcodeUpdated($member, $data['postcode'], $originalPostcode));
            }

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Address updated successfully',
                'address' => $updated->toArray(),
            ]);
        } catch (Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => 'Failed to update address'], 422);
        }
    }

    public function destroy(int $id)
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/member/login');
        }

        try {
            $member = MemberAuth::member();
            $this->addressBook->delete($member, $id);

            return $this->jsonResponse(['success' => true, 'message' => 'Address deleted successfully']);
        } catch (Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => 'Failed to delete address'], 422);
        }
    }

    public function setDefault(int $id, Request $request)
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/member/login');
        }

        try {
            $member = MemberAuth::member();
            $address = $this->addressBook->setDefault($member, $id);

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Default address updated',
                'address' => $address->toArray(),
            ]);
        } catch (Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => 'Failed to set default address'], 422);
        }
    }

    public function getCurrentAddress()
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false], 401);
        }

        $member = MemberAuth::getMember();
        $address = Address::where('member_id', $member->id)
            ->where('site_id', SiteContext::getId())
            ->where('is_default', true)
            ->whereIn('type', ['shipping', 'both'])
            ->first();

        return $this->jsonResponse([
            'success' => true,
            'address' => $address ? $address->toArray() : null
        ]);
    }
}
