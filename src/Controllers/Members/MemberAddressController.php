<?php

namespace App\Controllers\Members;

use App\Controllers\Controller;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Support\SiteContext;
use App\Repositories\Members\AddressRepository;

class MemberAddressController extends Controller
{
    public function __construct(
        private readonly AddressRepository $addressRepository
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
        $addresses = $this->addressRepository->getAddressesForMember($member->id);

        return $this->view('member/addresses/index', [
            'member' => $member,
            'addresses' => $addresses,
            'site' => SiteContext::get()
        ]);
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
}