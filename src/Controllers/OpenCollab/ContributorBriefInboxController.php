<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Models\User;
use App\Resources\OpenCollab\ContributorBriefInboxResource;
use App\Services\OpenCollab\ContributorBriefInboxService;

class ContributorBriefInboxController extends Controller
{
    public function __construct(
        private readonly ContributorBriefInboxService $briefInboxService,
    )
    {
        parent::__construct();
    }

    public function index()
    {
        return $this->view('open-collab.briefs.index', [
            'currentUser' => User::hydrateStatic(Auth::getUser()),
            'site' => SiteContext::slug(),
        ]);
    }

    public function apiIndex(Request $request): JsonResponse
    {
        try {
            $filters = $this->briefInboxService->normalizeFilters($request->all());
        } catch (\InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage(), 422);
        }

        $contributorId = (int)Auth::id();
        $briefs = $this->briefInboxService->getAssignedBriefs(
            $contributorId,
            SiteContext::getId(),
            $filters,
        );

        $data = $briefs
            ->map(fn($brief) => (new ContributorBriefInboxResource(
                $brief,
                $this->briefInboxService,
                $contributorId,
            ))->toArray())
            ->toArray();

        return $this->resourceResponse([
            'data' => $data,
            'meta' => [
                'summary' => $this->briefInboxService->summarize($briefs, $contributorId),
                'filters' => $filters,
            ],
        ]);
    }
}
