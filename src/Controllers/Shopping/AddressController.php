<?php

namespace App\Controllers\Shopping;

use App\Controllers\Controller;
use App\Events\Members\MemberPostcodeUpdated;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Models\Member;
use App\Repositories\Members\AddressRepository;
use App\Requests\CreateAddressRequest;
use App\Requests\UpdateAddressRequest;
use App\Services\Members\AddressLookupServiceInterface;
use Exception;

class AddressController extends Controller
{
    public function __construct(
        private readonly AddressRepository             $addressRepository,
        private readonly AddressLookupServiceInterface $addressLookupService,
    )
    {
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
                'items' => $addresses->toArray(),
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
            $member = Member::find($memberId);
            $siteId = $data['site_id'] ?? SiteContext::getId();

            $address = $this->addressRepository->createAddressForMember($memberId, $data, $siteId);

            if (!empty($data['postcode'])) {
                event(new MemberPostcodeUpdated($member, $data['postcode'], null));
            }

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

            $addresses = match ($type) {
                'shipping' => $this->addressRepository->getShippingAddressesForMember($memberId),
                'billing' => $this->addressRepository->getBillingAddressesForMember($memberId),
                default => $this->addressRepository->getAddressesForMember($memberId),
            };

            return $this->resourceResponse([
                'success' => true,
                'items' => $addresses->toArray(),
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * GET /address-lookup?postcode={postcode}
     *
     * Returns a list of address suggestions for the given postcode.
     * Used by the address-lookup UI component on the checkout/billing form.
     *
     * Response 200:
     *   { success: true, addresses: [{ address, city, county, postal_code, country }] }
     *
     * Response 422:
     *   { success: false, message: string }
     */
    public function lookup(Request $request)
    {
        $postcode = trim((string)$request->get('postcode', ''));

        if ($postcode === '') {
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Postcode is required',
            ], 422);
        }

        try {
            $addresses = $this->addressLookupService->lookup($postcode);

            if (empty($addresses)) {
                return $this->resourceResponse([
                    'success' => false,
                    'message' => 'No addresses found for that postcode',
                ], 422);
            }

            return $this->resourceResponse([
                'success' => true,
                'addresses' => $addresses,
            ]);
        } catch (Exception $e) {
            echo $e->getMessage();
            return $this->resourceResponse([
                'success' => false,
                'message' => 'Address lookup unavailable',
            ], 500);
        }
    }
}