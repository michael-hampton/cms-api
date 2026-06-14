<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Controllers\OpenCollab\Concerns\AuthorizesSitePermissions;
use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Http\UploadedFile;
use App\Framework\Support\SiteContext;
use App\Repositories\OpenCollab\TermsVersionRepository;
use App\Requests\OpenCollab\StoreTermsVersionRequest;
use App\Requests\OpenCollab\UpdateTermsVersionRequest;
use App\Resources\OpenCollab\TermsVersionResource;
use App\Services\OpenCollab\OpenCollabAuthorizationService;
use App\Services\OpenCollab\TermsVersionService;
use RuntimeException;

class AdminTermsController extends Controller
{
    use AuthorizesSitePermissions;

    public function __construct(
        private readonly TermsVersionRepository $repository,
        private readonly TermsVersionService $service,
        private readonly OpenCollabAuthorizationService $authorization,
    ) {
        parent::__construct();
    }

    public function index(): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['terms.edit', 'terms.publish', 'terms.archive'])) {
            return $response;
        }

        return $this->jsonResponse([
            'terms' => $this->repository->allForSite(SiteContext::getId())
                ->map(fn($terms) => (new TermsVersionResource($terms))->toArray())
                ->toArray(),
        ]);
    }

    public function latest(): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['terms.view', 'terms.publish'])) {
            return $response;
        }

        $terms = $this->repository->latestPublishedForSite(SiteContext::getId());
        if (!$terms) {
            return $this->errorResponse('No published Terms and Conditions found for this site.', 404);
        }

        return $this->jsonResponse(['terms' => (new TermsVersionResource($terms))->toArray()]);
    }

    public function show(int $id): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['terms.edit', 'terms.publish', 'terms.archive'])) {
            return $response;
        }

        $terms = $this->repository->findForSite($id, SiteContext::getId());
        if (!$terms) {
            return $this->errorResponse('Terms version not found.', 404);
        }

        return $this->jsonResponse(['terms' => (new TermsVersionResource($terms))->toArray()]);
    }

    public function store(StoreTermsVersionRequest $request): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['terms.create'])) {
            return $response;
        }

        $data = $request->validated();
        $terms = $this->service->createDraft(
            SiteContext::getId(),
            (string)$data['semantic_version'],
            (string)$data['title'],
            (string)$data['source_content'],
            Auth::id(),
            $data,
        );

        return $this->jsonResponse([
            'terms' => (new TermsVersionResource($terms))->toArray(),
            'message' => 'Terms version created.',
        ], 201);
    }

    public function update(UpdateTermsVersionRequest $request, int $id): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['terms.edit'])) {
            return $response;
        }

        $terms = $this->repository->findForSite($id, SiteContext::getId());
        if (!$terms) {
            return $this->errorResponse('Terms version not found.', 404);
        }

        try {
            $terms = $this->service->updateDraft($terms, $request->validated());
        } catch (RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), 409);
        }

        return $this->jsonResponse(['terms' => (new TermsVersionResource($terms))->toArray()]);
    }

    public function publish(int $id): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['terms.publish'])) {
            return $response;
        }

        $terms = $this->repository->findForSite($id, SiteContext::getId());
        if (!$terms) {
            return $this->errorResponse('Terms version not found.', 404);
        }

        try {
            $terms = $this->service->publish($terms, Auth::id());
        } catch (RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), 409);
        }

        return $this->jsonResponse(['terms' => (new TermsVersionResource($terms))->toArray()]);
    }

    public function storeFromDocument(Request $request): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['terms.create'])) {
            return $response;
        }

        $file = $this->uploadedDocument($request);
        if (!$file) {
            return $this->errorResponse('Document is required.', 422);
        }

        $semanticVersion = trim((string)$request->input('semantic_version', ''));
        $title = trim((string)$request->input('title', ''));
        if ($semanticVersion === '' || $title === '') {
            return $this->errorResponse('Semantic version and title are required.', 422);
        }

        $terms = $this->service->createDraftFromDocument(
            $file,
            SiteContext::getId(),
            $semanticVersion,
            $title,
            Auth::id(),
            (bool)$request->input('is_material_change', false),
            $request->input('change_summary'),
        );

        return $this->jsonResponse(['terms' => (new TermsVersionResource($terms))->toArray()], 201);
    }

    private function uploadedDocument(Request $request): ?UploadedFile
    {
        return $request->files()['document'] ?? $request->file('document');
    }
}
