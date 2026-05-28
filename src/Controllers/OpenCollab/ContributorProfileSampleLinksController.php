<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Resources\OpenCollab\ContributorProfileResource;
use App\Services\OpenCollab\ContributorProfileService;
use Exception;

class ContributorProfileSampleLinksController extends Controller
{
    public function __construct(
        private readonly ContributorProfileService $profileService,
    )
    {
        parent::__construct();
    }

    /**
     * PUT /api/{site}/open-collab/profile/sample-links
     */
    public function update(Request $request): JsonResponse
    {
        $userId = Auth::id();

        if (!$userId) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        $links = $request->input('sample_links', []);

        if ($links === null) {
            $links = [];
        }

        if (!is_array($links)) {
            return $this->errorResponse('Validation failed', 422, [
                'sample_links' => ['Writing sample links must be an array.'],
            ]);
        }

        try {
            $profile = $this->profileService->updateSampleLinks($userId, SiteContext::getId(), $links);

            return $this->jsonResponse([
                'profile' => (new ContributorProfileResource($profile))->toArray(),
                'message' => 'Writing sample links saved.',
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->getErrors());
        } catch (Exception $e) {
            return $this->errorResponse('Could not save writing sample links. Please try again.', 500);
        }
    }
}
