<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Controllers\OpenCollab\Concerns\AuthorizesSitePermissions;
use App\Exceptions\OpenCollab\ContractNotArchivableException;
use App\Exceptions\OpenCollab\ContractNotEditableException;
use App\Exceptions\OpenCollab\ContractNotPublishableException;
use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Models\Contract;
use App\Repositories\OpenCollab\ContractRepository;
use App\Repositories\OpenCollab\ContractTemplateRepository;
use App\Services\OpenCollab\ContractService;
use App\Services\OpenCollab\ContractTemplateService;
use App\Services\OpenCollab\OpenCollabAuthorizationService;

/**
 * Admin CRUD and lifecycle management for contributor contracts.
 *
 * Routes:
 *   GET    /api/{site}/open-collab/admin/contracts                      — list all
 *   GET    /api/{site}/open-collab/admin/contracts/latest               — latest published
 *   POST   /api/{site}/open-collab/admin/contracts                      — create draft
 *   GET    /api/{site}/open-collab/admin/contracts/{id}                 — show one
 *   PUT    /api/{site}/open-collab/admin/contracts/{id}                 — update draft content
 *   DELETE /api/{site}/open-collab/admin/contracts/{id}                 — delete latest draft only
 *   POST   /api/{site}/open-collab/admin/contracts/{id}/publish         — publish draft
 *   POST   /api/{site}/open-collab/admin/contracts/{id}/archive         — archive published
 *   POST   /api/{site}/open-collab/admin/contracts/{id}/clone           — clone to new draft
 *   POST   /api/{site}/open-collab/admin/contracts/from-template        — draft from template
 */
class AdminContractController extends Controller
{
    use AuthorizesSitePermissions;

    public function __construct(
        private readonly ContractRepository             $contractRepository,
        private readonly ContractTemplateRepository     $templateRepository,
        private readonly ContractService                $contractService,
        private readonly ContractTemplateService        $contractTemplateService,
        private readonly OpenCollabAuthorizationService $authorization,
    )
    {
        parent::__construct();
    }

    public function index(): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['contract.view', 'contract.create', 'contract.publish'])) {
            return $response;
        }

        $contracts = $this->contractRepository->getContractsForSite(SiteContext::getId());

        return $this->jsonResponse(
            $contracts->map(fn($c) => $this->formatContract($c))->toArray()
        );
    }

    private function formatContract(Contract $contract): array
    {
        return [
            'id' => $contract->id,
            'site_id' => $contract->site_id,
            'version' => $contract->version,
            'content' => $contract->content,
            'status' => $contract->status,
            'status_label' => $contract->status,
            'published_at' => $contract->published_at,
            'published_by' => $contract->published_by,
            'archived_at' => $contract->archived_at,
            'archived_by' => $contract->archived_by,
            'source_template_id' => $contract->source_template_id,
            'cloned_from_version_id' => $contract->cloned_from_version_id,
            'created_at' => $contract->created_at,
        ];
    }

    public function latest(): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['contract.view'])) {
            return $response;
        }

        $contract = $this->contractRepository->latestPublishedForSite(SiteContext::getId());
        if (!$contract) {
            return $this->errorResponse('No published contract found for this site.', 404);
        }

        return $this->jsonResponse(['contract' => $this->formatContract($contract)]);
    }

    public function show(int $id): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['contract.view'])) {
            return $response;
        }

        $contract = $this->contractRepository->find($id);
        if (!$contract || (int)$contract->site_id !== SiteContext::getId()) {
            return $this->errorResponse('Contract not found.', 404);
        }

        return $this->jsonResponse(['contract' => $this->formatContract($contract)]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['contract.create'])) {
            return $response;
        }

        $content = trim($request->input('content', ''));

        if (empty($content)) {
            return $this->errorResponse('Contract content is required.', 422);
        }
        if (mb_strlen($content) < 50) {
            return $this->errorResponse('Contract content must be at least 50 characters.', 422);
        }

        $contract = $this->contractService->createDraft(
            siteId: SiteContext::getId(),
            content: $content,
            createdByUserId: Auth::id(),
        );

        return $this->jsonResponse([
            'contract' => $this->formatContract($contract),
            'message' => "Contract draft version {$contract->version} created.",
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['contract.edit'])) {
            return $response;
        }

        $contract = $this->contractRepository->find($id);
        if (!$contract || (int)$contract->site_id !== SiteContext::getId()) {
            return $this->errorResponse('Contract not found.', 404);
        }

        // Guard: do not allow editing a signed contract
        if ($this->contractRepository->hasAnySigned($id)) {
            return $this->errorResponse(
                'This contract version has been signed and cannot be edited. Create a new version instead.',
                409
            );
        }

        $content = trim($request->input('content', ''));

        if (empty($content)) {
            return $this->errorResponse('Contract content is required.', 422);
        }
        if (mb_strlen($content) < 50) {
            return $this->errorResponse('Contract content must be at least 50 characters.', 422);
        }

        try {
            $updated = $this->contractService->updateDraftContent($contract, $content);
        } catch (ContractNotEditableException $e) {
            return $this->errorResponse($e->getMessage(), 409);
        }

        return $this->jsonResponse([
            'contract' => $this->formatContract($updated),
            'message' => "Contract version {$updated->version} updated.",
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['contract.archive'])) {
            return $response;
        }

        $contract = $this->contractRepository->find($id);
        if (!$contract || (int)$contract->site_id !== SiteContext::getId()) {
            return $this->errorResponse('Contract not found.', 404);
        }

        $latest = $this->contractRepository->latestForSite(SiteContext::getId());
        if (!$latest || $latest->id !== $contract->id) {
            return $this->errorResponse('Only the latest contract version can be deleted.', 409);
        }

        if ($this->contractRepository->hasAnySigned($id)) {
            return $this->errorResponse(
                'This contract version has been signed and cannot be deleted.',
                409
            );
        }

        try {
            $this->contractService->assertEditable($contract);
        } catch (ContractNotEditableException $e) {
            return $this->errorResponse($e->getMessage(), 409);
        }

        $version = $contract->version;
        $contract->delete();

        return $this->successResponse("Contract version {$version} deleted.");
    }

    public function publish(int $id): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['contract.publish'])) {
            return $response;
        }

        $contract = $this->contractRepository->find($id);
        if (!$contract || (int)$contract->site_id !== SiteContext::getId()) {
            return $this->errorResponse('Contract not found.', 404);
        }

        try {
            $published = $this->contractService->publishVersion($contract, Auth::id());
        } catch (ContractNotPublishableException $e) {
            return $this->errorResponse($e->getMessage(), 409);
        }

        return $this->jsonResponse([
            'contract' => $this->formatContract($published),
            'message' => "Contract version {$published->version} published.",
        ]);
    }

    public function archive(int $id): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['contract.archive'])) {
            return $response;
        }

        $contract = $this->contractRepository->find($id);
        if (!$contract || (int)$contract->site_id !== SiteContext::getId()) {
            return $this->errorResponse('Contract not found.', 404);
        }

        try {
            $archived = $this->contractService->archiveVersion($contract, Auth::id());
        } catch (ContractNotArchivableException $e) {
            return $this->errorResponse($e->getMessage(), 409);
        }

        return $this->jsonResponse([
            'contract' => $this->formatContract($archived),
            'message' => "Contract version {$archived->version} archived.",
        ]);
    }

    public function clone(int $id): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['contract.create'])) {
            return $response;
        }

        $contract = $this->contractRepository->find($id);
        if (!$contract || (int)$contract->site_id !== SiteContext::getId()) {
            return $this->errorResponse('Contract not found.', 404);
        }

        $draft = $this->contractService->cloneToDraft($contract, Auth::id());

        return $this->jsonResponse([
            'contract' => $this->formatContract($draft),
            'message' => "Draft version {$draft->version} created from version {$contract->version}.",
        ], 201);
    }

    public function storeFromTemplate(Request $request): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['contract.create'])) {
            return $response;
        }

        $templateId = (int)$request->input('template_id', 0);
        $template = $this->templateRepository->find($templateId);

        if (!$template || !$template->is_active) {
            return $this->errorResponse('Template not found or inactive.', 404);
        }

        $contract = $this->contractTemplateService->createDraftFromTemplate(
            $template,
            SiteContext::getId(),
            Auth::id(),
        );

        return $this->jsonResponse([
            'contract' => $this->formatContract($contract),
            'message' => "Draft version {$contract->version} created from template.",
        ], 201);
    }

    // ── Formatting ────────────────────────────────────────────────────────────

    public function fromTemplate(Request $request): JsonResponse
    {
        if ($response = $this->authorize(['contract.create'])) {
            return $response;
        }

        $template = $this->templateRepository->find($request->input('template_id'));
        if (!$template) return $this->errorResponse('Template not found', 404);

        $contract = $this->contractTemplateService->createDraftFromTemplate(
            $template,
            SiteContext::getId(),
            Auth::id()
        );
        return $this->jsonResponse(['contract' => $this->formatContract($contract)], 201);
    }
}
