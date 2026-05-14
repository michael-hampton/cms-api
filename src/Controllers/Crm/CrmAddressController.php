<?php

namespace App\Controllers\Crm;

use App\Controllers\Controller;
use App\Enums\Address\AddressType;
use App\Events\Members\MemberDetailsChanged;
use App\Events\Members\MemberPostcodeUpdated;
use App\Framework\Authorization\Auth;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Repositories\Members\AddressRepository;
use App\Repositories\Members\CrmMemberRepository;
use App\Requests\Crm\CrmCreateAddressRequest;
use App\Requests\Crm\CrmUpdateAddressRequest;
use Exception;

class CrmAddressController extends Controller
{
    const DEFAULT_PER_PAGE = 10;

    public function __construct(
        private readonly AddressRepository   $addressRepository,
        private readonly CrmMemberRepository $crmMemberRepository,
    )
    {
        parent::__construct();
    }

    /**
     * GET /crm/members/{memberId}/addresses
     *
     * Lists addresses for a member with pagination.
     * Member must belong to the current site — guards against cross-site reads.
     */
    public function index(int $memberId, Request $request): mixed
    {
        if (!Auth::check()) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $member = $this->crmMemberRepository->findForSite($memberId, SiteContext::getId());

        if (!$member) {
            return $this->jsonResponse(['success' => false, 'message' => 'Member not found.'], 404);
        }

        $page = max(1, (int)$request->get('page', 1));
        $perPage = max(1, (int)$request->get('per_page', self::DEFAULT_PER_PAGE));

        $result = $this->addressRepository->getPaginatedAddressesForMember($memberId, $page, $perPage);

        return $this->resourceResponse([
            'items' => $result['data']->toArray(),
            'pagination' => [
                'total' => $result['total'],
                'per_page' => $result['per_page'],
                'current_page' => $result['current_page'],
                'last_page' => $result['last_page'],
            ],
        ]);
    }

    /**
     * GET /crm/members/{memberId}/addresses/create
     */
    public function create(int $memberId): mixed
    {
        if (!Auth::check()) {
            return $this->redirect('/login');
        }

        $member = $this->crmMemberRepository->findForSite($memberId, SiteContext::getId());

        if (!$member) {
            return $this->redirect('/crm/members')
                ->withErrors(['message' => 'Member not found.']);
        }

        return $this->view('crm/addresses/create', [
            'member' => $member,
            'addressTypes' => AddressType::cases(),
        ]);
    }

    /**
     * POST /crm/members/{memberId}/addresses
     */
    public function store(int $memberId, CrmCreateAddressRequest $request): mixed
    {
        if (!Auth::check()) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $member = $this->crmMemberRepository->findForSite($memberId, SiteContext::getId());

        if (!$member) {
            return $this->jsonResponse(['success' => false, 'message' => 'Member not found.'], 404);
        }

        try {
            $data = $request->validated();

            $address = $this->addressRepository->createAddressForMember(
                $member->id,
                $data,
                SiteContext::getId()
            );

            if (!empty($data['postcode'])) {
                event(new MemberPostcodeUpdated($member, $data['postcode'], null));
            }

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Address created successfully.',
                'address' => $address->toArray(),
            ]);
        } catch (ValidationException $validationException) {
            return $this->errorResponse('Validation Failed', 422, $validationException->getErrors());
        } catch (Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => 'Failed to create address.'], 500);
        }
    }

    /**
     * GET /crm/members/{memberId}/addresses/{id}/edit
     */
    public function edit(int $memberId, int $id): mixed
    {
        if (!Auth::check()) {
            return $this->redirect('/login');
        }

        $member = $this->crmMemberRepository->findForSite($memberId, SiteContext::getId());

        if (!$member) {
            return $this->redirect('/crm/members')
                ->withErrors(['message' => 'Member not found.']);
        }

        $address = $this->addressRepository->find($id);

        if (!$address || $address->member_id !== $member->id) {
            return $this->redirect('/crm/members/' . $memberId)
                ->withErrors(['message' => 'Address not found.']);
        }

        return $this->view('crm/addresses/edit', [
            'member' => $member,
            'address' => $address,
            'addressTypes' => AddressType::cases(),
        ]);
    }

    /**
     * POST /crm/members/{memberId}/addresses/{id}
     */
    public function update(int $memberId, int $id, CrmUpdateAddressRequest $request): mixed
    {
        if (!Auth::check()) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $member = $this->crmMemberRepository->findForSite($memberId, SiteContext::getId());

        if (!$member) {
            return $this->jsonResponse(['success' => false, 'message' => 'Member not found.'], 404);
        }

        $address = $this->addressRepository->find($id);

        if (!$address || $address->member_id !== $member->id) {
            return $this->jsonResponse(['success' => false, 'message' => 'Address not found.'], 404);
        }

        try {
            $data = $request->validated();
            $originalPostcode = $address->postcode;

            $updated = $this->addressRepository->update($id, $data);

            if ($address->is_default) {
                event(new MemberDetailsChanged($memberId, '', $id));
            }

            if (!empty($data['postcode'])) {
                event(new MemberPostcodeUpdated($member, $data['postcode'], $originalPostcode));
            }

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Address updated successfully.',
                'address' => $updated->toArray(),
            ]);
        } catch (ValidationException $validationException) {
            return $this->errorResponse('Validation Failed', 422, $validationException->getErrors());
        } catch (Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => 'Failed to update address.'], 500);
        }
    }

    /**
     * DELETE /crm/members/{memberId}/addresses/{id}
     */
    public function destroy(int $memberId, int $id): mixed
    {
        if (!Auth::check()) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $member = $this->crmMemberRepository->findForSite($memberId, SiteContext::getId());

        if (!$member) {
            return $this->jsonResponse(['success' => false, 'message' => 'Member not found.'], 404);
        }

        $address = $this->addressRepository->find($id);

        if (!$address || $address->member_id !== $member->id) {
            return $this->jsonResponse(['success' => false, 'message' => 'Address not found.'], 404);
        }

        try {
            $address->delete();

            return $this->jsonResponse(['success' => true, 'message' => 'Address deleted successfully.']);
        } catch (Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => 'Failed to delete address.'], 500);
        }
    }

    /**
     * POST /crm/members/{memberId}/addresses/{id}/default
     */
    public function setDefault(int $memberId, int $id): mixed
    {
        if (!Auth::check()) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $member = $this->crmMemberRepository->findForSite($memberId, SiteContext::getId());

        if (!$member) {
            return $this->jsonResponse(['success' => false, 'message' => 'Member not found.'], 404);
        }

        $result = $this->addressRepository->setDefaultAddress($id, $member->id);

        if (!$result) {
            return $this->jsonResponse(['success' => false, 'message' => 'Address not found or does not belong to member.'], 404);
        }

        return $this->jsonResponse(['success' => true, 'message' => 'Default address updated.']);
    }
}