<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
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

        return $this->jsonResponse($template, 201);
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

        return $this->jsonResponse($updated);
    }

    public function destroy(int $id): JsonResponse
    {
        $template = $this->templateRepository->find($id);
        if (!$template) return $this->errorResponse('Template not found', 404);

        $this->templateService->deactivate($template, Auth::id());
        return $this->successResponse('Template deactivated.');
    }
}