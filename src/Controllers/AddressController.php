<?php

namespace App\Controllers;

use App\Framework\Authorization\MemberAuth;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Repositories\AddressRepository;
use App\Requests\CreateAddressRequest;
use App\Requests\UpdateAddressRequest;
use Exception;

class AddressController extends Controller
{
    public function __construct(
        private readonly AddressRepository $addressRepository
    ) {
        parent::__construct();
    }

    public function index(Request $request)
    {
        try {
            $memberId = $request->get('member_id');

            if (!$memberId) {
                return $this->errorResponse('Member ID is required', 400);
            }

            $addresses = $this->addressRepository->getAddressesForMember($memberId);

            return $this->resourceResponse([
                'success' => true,
                'items' => $addresses->toArray()
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function store(CreateAddressRequest $request)
    {
        try {
            $data = $request->validated();
            $memberId = $data['member_id'];
            $siteId = $data['site_id'] ?? Sitecontext::getId();

            $address = $this->addressRepository->createAddressForMember($memberId, $data, $siteId);

            return $this->resourceResponse(['address' => $address->toArray()], 201);
        } catch (ValidationException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function update(int $id, UpdateAddressRequest $request)
    {
        try {
            $data = $request->validated();
            $address = $this->addressRepository->update($id, $data);

            if (!$address) {
                return $this->resourceResponse(['Address not found'], 404);
            }

            return $this->resourceResponse(['address' => $address->toArray()]);
        } catch (ValidationException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function destroy(int $id, Request $request)
    {
        try {
            $address = $this->addressRepository->find($id);

            if (!$address) {
                return $this->errorResponse('Address not found', 404);
            }

            $address->delete();

            return $this->successResponse('Address deleted successfully');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function setDefault(int $id, Request $request)
    {
        try {
            $memberId = $request->get('member_id');

            if (!$memberId) {
                return $this->errorResponse('Member ID is required', 400);
            }

            $result = $this->addressRepository->setDefaultAddress($id, $memberId);

            if (!$result) {
                return $this->errorResponse('Failed to set default address', 400);
            }

            return $this->successResponse('Default address updated');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function getMemberAddresses(int $memberId, Request $request)
    {
        try {
            $type = $request->get('type'); // 'shipping', 'billing', or null for all

            $addresses = match($type) {
                'shipping' => $this->addressRepository->getShippingAddressesForMember($memberId),
                'billing' => $this->addressRepository->getBillingAddressesForMember($memberId),
                default => $this->addressRepository->getAddressesForMember($memberId)
            };

            return $this->resourceResponse([
                'success' => true,
                'items' => $addresses->toArray()
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}