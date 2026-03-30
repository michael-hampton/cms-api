<?php

namespace App\Controllers\Crm;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Repositories\Members\AddressRepository;
use App\Repositories\Members\CrmMemberRepository;
use App\Requests\Crm\UpdateMemberRequest;
use App\Services\Members\CrmMemberService;
use Exception;
use InvalidArgumentException;

class CrmMemberController extends Controller
{
    public function __construct(
        private readonly CrmMemberRepository $crmMemberRepository,
        private readonly CrmMemberService    $crmMemberService,
        private readonly AddressRepository   $addressRepository,
    )
    {
        parent::__construct();
    }

    /**
     * GET /crm/members
     * List + search members.
     */
    public function index(Request $request): mixed
    {
        if (!Auth::check()) {
            return $this->redirect('/login');
        }

        $siteId = SiteContext::getId();

        $search = $request->get('search', '');
        $status = $request->get('status');
        $agentId = $request->get('agent_id') ? (int)$request->get('agent_id') : null;
        $page = max(1, (int)$request->get('page', 1));

        $result = $this->crmMemberRepository->searchMembers(
            $siteId,
            $search,
            $status,
            $agentId,
            20,
            $page
        );

        $agents = $this->crmMemberRepository->getAgents($siteId);

        return $this->view('crm/members/index', [
            'members' => $result['data'],
            'pagination' => [
                'total' => $result['total'],
                'per_page' => $result['per_page'],
                'current_page' => $result['current_page'],
                'last_page' => $result['last_page'],
            ],
            'filters' => [
                'search' => $search,
                'status' => $status,
                'agent_id' => $agentId,
            ],
            'agents' => $agents,
        ]);
    }

    /**
     * GET /crm/members/{id}
     * Show a single member's CRM profile.
     */
    public function show(int $id): mixed
    {
        if (!Auth::check()) {
            return $this->redirect('/login');
        }

        $member = $this->crmMemberRepository->findForSite($id, SiteContext::getId());

        if (!$member) {
            return $this->redirect('/crm/members')->withErrors(['message' => 'Member not found.']);
        }

        $addresses = $this->addressRepository->getAddressesForMember($id);
        $agents = $this->crmMemberRepository->getAgents(SiteContext::getId());

        return $this->view('crm/members/show', [
            'member' => $member,
            'addresses' => $addresses,
            'agents' => $agents,
        ]);
    }

    /**
     * GET /crm/members/{id}/edit
     * Edit form for a member.
     */
    public function edit(int $id): mixed
    {
        if (!Auth::check()) {
            return $this->redirect('/login');
        }

        $member = $this->crmMemberRepository->findForSite($id, SiteContext::getId());

        if (!$member) {
            return $this->redirect('/crm/members')->withErrors(['message' => 'Member not found.']);
        }

        $agents = $this->crmMemberRepository->getAgents(SiteContext::getId());

        return $this->view('crm/members/edit', [
            'member' => $member,
            'agents' => $agents,
        ]);
    }

    /**
     * POST /crm/members/{id}
     * Persist member updates.
     */
    public function update(int $id, UpdateMemberRequest $request): mixed
    {
        if (!Auth::check()) {
            return $this->errorResponse('Unauthorized', 401);
        }

        try {
            $data = $request->validated();
            $updated = $this->crmMemberService->updateMember($id, SiteContext::getId(), $data);

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Member updated successfully.',
                'member' => [
                    'id' => $updated->id,
                    'first_name' => $updated->first_name,
                    'last_name' => $updated->last_name,
                    'email' => $updated->email,
                    'is_active' => $updated->is_active,
                ],
            ]);
        } catch (InvalidArgumentException $e) {
            return $this->resourceResponse(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (Exception $e) {
            return $this->resourceResponse(['success' => false, 'message' => 'Failed to update member.'], 500);
        }
    }

    /**
     * DELETE /crm/members/{id}
     * Soft-delete by deactivating the member.
     * Hard delete is intentionally not exposed — deactivation preserves audit history.
     */
    public function destroy(int $id): mixed
    {
        if (!Auth::check()) {
            return $this->errorResponse('Unauthorized', 401);
        }

        try {
            $this->crmMemberService->updateMember($id, SiteContext::getId(), ['is_active' => false]);

            return $this->jsonResponse(['success' => true, 'message' => 'Member deactivated.']);
        } catch (InvalidArgumentException $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => 'Failed to deactivate member.'], 500);
        }
    }

}