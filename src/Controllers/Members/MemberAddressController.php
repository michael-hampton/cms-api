<?php

namespace App\Controllers\Members;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Models\Address;
use App\Models\Member;
use App\Repositories\Members\AddressRepository;
use App\Requests\CreateAddressRequest;
use App\Requests\UpdateAddressRequest;
use Exception;

class MemberAddressController extends Controller
{
    public function __construct(
        private readonly AddressRepository $addressRepository
    ) {
        parent::__construct();
    }

    public function index()
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/member/login');
        }

        $member = MemberAuth::getMember();
        $addresses = $this->addressRepository->getAddressesForMember($member->id);

        return $this->view('member/addresses/index', [
            'member' => $member,
            'addresses' => $addresses,
            'site' => SiteContext::get()
        ]);
    }

    public function search()
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/member/login');
        }

        $member = MemberAuth::member();
        $addresses = $this->addressRepository->getAddressesForMember($member->id);

        return $this->resourceResponse(['items' => $addresses->toArray()]);
    }

    public function create()
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/member/login');
        }

        return $this->view('member/addresses/create', [
            'member' => MemberAuth::member(),
            'site' => SiteContext::get()
        ]);
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
            $member = MemberAuth::member();
            $data = $request->validated();
            $data['member_id'] = $member->id;
            $data['site_id'] = SiteContext::getId();

            $this->addressRepository->createAddressForMember($member->id, $data);

            return $this->jsonResponse(['message', 'Address added successfully']);
        } catch (Exception $e) {
            return $this->jsonResponse(['message' => 'Failed to add address']);
        }
    }

    public function edit(int $id)
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/member/login');
        }

        $member = MemberAuth::member();
        $address = $this->addressRepository->find($id);

        if (!$address || $address->member_id !== $member->id) {
            return $this->redirect('/member/addresses')
                ->withErrors(['message' => 'Address not found']);
        }

        return $this->view('member/addresses/edit', [
            'member' => $member,
            'address' => $address,
            'site' => SiteContext::get()
        ]);
    }

    public function update(int $id, UpdateAddressRequest $request)
    {
        if (!MemberAuth::check()) {
            return $this->errorResponse('Unauthorized', 401);
        }

        try {
            $member = MemberAuth::member();
            $address = $this->addressRepository->find($id);

            if (!$address || $address->member_id !== $member->id) {
                return $this->jsonResponse(['message' => 'Address not found']);
            }

            $data = $request->validated();
            $this->addressRepository->update($id, $data);

            return $this->jsonResponse(['message', 'Address updated successfully']);
        } catch (Exception $e) {
            return $this->jsonResponse(['message' => 'Failed to update address']);
        }
    }

    public function destroy(int $id)
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/member/login');
        }

        try {
            $member = MemberAuth::member();
            $address = $this->addressRepository->find($id);

            if (!$address || $address->member_id !== $member->id) {
                return $this->jsonResponse(['message' => 'Address not found']);
            }

            $address->delete();

            return $this->jsonResponse(['message', 'Address deleted successfully']);
        } catch (Exception $e) {
            return $this->jsonResponse(['message' => 'Failed to delete address']);
        }
    }

    public function setDefault(int $id, Request $request)
    {
        if (!MemberAuth::check()) {
            return $this->redirect('/member/login');
        }

        try {
            $member = MemberAuth::member();
            $address = $this->addressRepository->find($id);

            if (!$address || $address->member_id !== $member->id) {
                return $this->jsonResponse(['message' => 'Address not found']);
            }

            $this->addressRepository->setDefaultAddress($id, $member->id);

            return $this->jsonResponse(['message', 'Default address updated']);
        } catch (Exception $e) {
            return $this->jsonResponse(['message' => 'Failed to set default address']);
        }
    }

    public function getCurrentAddress()
    {
        if (!MemberAuth::check()) {
            return $this->jsonResponse(['success' => false], 401);
        }

        $member = MemberAuth::member();
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