<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Http\UploadedFile;
use App\Framework\Support\SiteContext;
use App\Repositories\OpenCollab\GuidelineTemplateRepository;
use App\Services\OpenCollab\GuidelineTemplateService;

class AdminGuidelineTemplateController extends Controller
{
    public function __construct(
        private readonly GuidelineTemplateRepository $templateRepository,
        private readonly GuidelineTemplateService    $templateService
    )
    {
        parent::__construct();
    }

    public function index(): JsonResponse
    {
        return $this->jsonResponse($this->templateRepository->allActive()->toArray());
    }

    public function store(Request $request): JsonResponse
    {
        $template = $this->templateService->createTemplate(
            name: $request->input('name'),
            slug: $request->input('slug'),
            content: $request->input('content'),
            createdByUserId: Auth::id(),
            description: $request->input('description')
        );

        return $this->resourceResponse($template->toArray(), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $template = $this->templateRepository->find($id);
        if (!$template) return $this->errorResponse('Template not found', 404);

        $updated = $this->templateService->updateTemplate(
            template: $template,
            name: $request->input('name'),
            content: $request->input('content'),
            updatedByUserId: Auth::id(),
            description: $request->input('description')
        );

        return $this->resourceResponse($updated->toArray());
    }

    public function destroy(int $id): JsonResponse
    {
        $template = $this->templateRepository->find($id);
        if (!$template) return $this->errorResponse('Template not found', 404);

        $this->templateService->deactivate($template, Auth::id());
        return $this->successResponse('Template deactivated.');
    }

    public function importDocument(Request $request): JsonResponse
    {
        $file = $this->uploadedDocument($request);

        if (!$file) {
            return $this->errorResponse('Document is required.', 422);
        }

        $name = trim((string)$request->input('name', ''));

        if ($name === '') {
            return $this->errorResponse('Template name is required.', 422);
        }

        $template = $this->templateService->importFromDocument(
            file: $file,
            siteId: SiteContext::getId(),
            name: $name,
            slug: $request->input('slug') ?: $this->slugFromName($name),
            createdByUserId: Auth::id(),
            description: $request->input('description')
        );

        return $this->resourceResponse($template->toArray(), 201);
    }

    private function uploadedDocument(Request $request): ?UploadedFile
    {
        return $request->files()['document'] ?? $request->file('document');
    }

    private function slugFromName(string $name): string
    {
        return trim(strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name) ?? ''), '-');
    }
}
