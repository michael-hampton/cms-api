<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Requests\OpenCollab\UpdateUserPreferencesRequest;
use App\Services\OpenCollab\UserConsentService;

class ContributorNotificationPreferenceController extends Controller
{
    public function __construct(private readonly UserConsentService $consentService)
    {
        parent::__construct();
    }

    public function updateBatch(UpdateUserPreferencesRequest $request, string $site): JsonResponse
    {
        try {
            $data = $request->validated();

            $this->consentService->savePreferences(
                Auth::id(),
                $data['preferences']
            );

            return $this->jsonResponse(['success' => true]);
        } catch (ValidationException $validationException) {
            return $this->errorResponse('Validation failed', 422, $validationException->getErrors());
        }
    }
}