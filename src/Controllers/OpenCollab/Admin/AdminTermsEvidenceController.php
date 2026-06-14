<?php

namespace App\Controllers\OpenCollab\Admin;

use App\Controllers\Controller;
use App\Controllers\OpenCollab\Concerns\AuthorizesSitePermissions;
use App\Framework\Http\JsonResponse;
use App\Resources\OpenCollab\TermsAcceptanceEvidenceResource;
use App\Services\OpenCollab\OpenCollabAuthorizationService;
use App\Services\OpenCollab\TermsAcceptanceEvidenceService;
use RuntimeException;

class AdminTermsEvidenceController extends Controller
{
    use AuthorizesSitePermissions;

    public function __construct(
        private readonly TermsAcceptanceEvidenceService $evidenceService,
        private readonly OpenCollabAuthorizationService $authorization,
    ) {
        parent::__construct();
    }

    public function show(int $id): JsonResponse
    {
        if ($response = $this->authorizeSitePermissions(['terms.evidence.view'])) {
            return $response;
        }

        try {
            $evidence = $this->evidenceService->get($id);
        } catch (RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), 404);
        }

        return $this->jsonResponse([
            'evidence' => (new TermsAcceptanceEvidenceResource($evidence))->toArray(),
        ]);
    }
}
