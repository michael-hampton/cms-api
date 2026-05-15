<?php

namespace App\Controllers\Crm;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Repositories\Members\AddressRepository;
use App\Repositories\Members\CrmMemberRepository;
use App\Requests\Crm\UpdateMemberRequest;
use App\Services\Members\CrmMemberProfileService;
use App\Services\Members\CrmMemberService;
use Exception;
use InvalidArgumentException;

class CrmMemberController extends Controller
{
    public function __construct(
        private readonly CrmMemberRepository     $crmMemberRepository,
        private readonly CrmMemberService        $crmMemberService,
        private readonly AddressRepository       $addressRepository,
        private readonly CrmMemberProfileService $crmMemberProfileService,
    ) {
        parent::__construct();
    }

    /**
     * GET /crm/members
     *
     * Supports two search modes:
     *   • Legacy broad search  — ?search=   (name / email / order number)
     *   • Advanced field search — ?order_number= &last_name= &postcode= &email= &phone=
     *
     * Both modes may be combined with the standard status / country / subscription_status
     * / agent_id filters.
     */
    public function index(Request $request): mixed
    {
        if (!Auth::check()) {
            return $this->redirect('/login');
        }

        $siteId = SiteContext::getId();

        $result = $this->crmMemberRepository->searchMembers(
            siteId:             $siteId,
            search:             $request->get('search', ''),
            status:             $request->get('status'),
            assignedAgentId:    $request->get('agent_id') ? (int) $request->get('agent_id') : null,
            perPage:            20,
            page:               max(1, (int) $request->get('page', 1)),
            country:            $request->get('country'),
            subscriptionStatus: $request->get('subscription_status'),
            // Advanced field-level search
            orderNumber:        $request->get('order_number'),
            lastName:           $request->get('last_name'),
            postcode:           $request->get('postcode'),
            email:              $request->get('email'),
            phone:              $request->get('phone'),
        );

        $agents = $this->crmMemberRepository->getAgents($siteId);

        return $this->resourceResponse([
            'items' => $result['data']->map(fn($m) => [
                ...$m->toArray(),
                'created_at' => $m->created_at?->format('Y-m-d H:i:s'),
            ]),
            'pagination' => [
                'total'        => $result['total'],
                'per_page'     => $result['per_page'],
                'current_page' => $result['current_page'],
                'last_page'    => $result['last_page'],
            ],
        ]);
    }

    /**
     * GET /crm/members/{id}
     */
    public function show(Request $request, int $id): mixed
    {
        if (!Auth::check()) {
            return $this->redirect('/login');
        }

        $member = $this->crmMemberRepository->findForSite($id, SiteContext::getId());

        if (!$member) {
            return $this->redirect('/crm/members')->withErrors(['message' => 'Member not found.']);
        }

        return $this->resourceResponse([
            'member' => $this->crmMemberProfileService->buildDetailPayload($member, SiteContext::getId()),
        ]);
    }

    /**
     * GET /crm/members/{id}/edit
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
     * POST /crm/members
     */
    public function store(UpdateMemberRequest $request): JsonResponse
    {
        if (!Auth::check()) {
            return $this->errorResponse('Unauthorized', 401);
        }

        try {
            $data    = $request->validated();
            $created = $this->crmMemberService->createMember(SiteContext::getId(), $data);

            return $this->resourceResponse([
                'success' => true,
                'message' => 'Member created successfully.',
                'member'  => [
                    'id'         => $created->id,
                    'first_name' => $created->first_name,
                    'last_name'  => $created->last_name,
                    'email'      => $created->email,
                    'is_active'  => $created->is_active,
                ],
            ], 201);
        } catch (InvalidArgumentException $e) {
            return $this->resourceResponse(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (Exception $e) {
            return $this->resourceResponse(['success' => false, 'message' => 'Failed to create member.'], 500);
        }
    }

    /**
     * POST /crm/members/{id}
     */
    public function update(int $id, UpdateMemberRequest $request): JsonResponse
    {
        if (!Auth::check()) {
            return $this->errorResponse('Unauthorized', 401);
        }

        try {
            $data    = $request->validated();
            $updated = $this->crmMemberService->updateMember($id, SiteContext::getId(), $data);

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Member updated successfully.',
                'member'  => [
                    'id'         => $updated->id,
                    'first_name' => $updated->first_name,
                    'last_name'  => $updated->last_name,
                    'email'      => $updated->email,
                    'is_active'  => $updated->is_active,
                ],
            ]);
        } catch (InvalidArgumentException $e) {
            return $this->resourceResponse(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (Exception $e) {
            return $this->resourceResponse(['success' => false, 'message' => 'Failed to update member. ' . $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /crm/members/{id}
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

    public function filterOptions(): mixed
    {
        if (!Auth::check()) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $siteId = SiteContext::getId();

        return $this->resourceResponse([
            'countries'             => $this->crmMemberRepository->getDistinctCountries($siteId),
            'subscription_statuses' => $this->crmMemberRepository->getDistinctSubscriptionStatuses($siteId),
        ]);
    }
}