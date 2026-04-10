<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Exceptions\OpenCollab\InvalidInvitationException;
use App\Framework\Authorization\Auth;
use App\Framework\Authorization\AuthenticationService;
use App\Framework\Authorization\LoginRequest as AuthLoginRequest;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Support\SiteContext;
use App\Requests\OpenCollab\AcceptInvitationRequest;
use App\Requests\OpenCollab\CreateInvitationRequest;
use App\Services\OpenCollab\InvitationService;

class InvitationController extends Controller
{
    public function __construct(
        private readonly InvitationService     $invitationService,
        private readonly AuthenticationService $authenticationService,
    )
    {
        parent::__construct();
    }

    /**
     * POST /api/{site}/open-collab/invitations
     * Admin action: create an invitation for a given email.
     */
    public function store(CreateInvitationRequest $request): JsonResponse
    {
        try {

            $data = $request->validated();

            $invitation = $this->invitationService->create(
                email: $data['email'],
                invitedBy: Auth::id(),
                siteId: SiteContext::getId(),
            );

            return $this->jsonResponse(['invitation' => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'expires_at' => $invitation->expires_at,
            ]], 201);
        } catch (ValidationException $validationException) {
            return $this->errorResponse('Validation failed', 422, $validationException->getErrors());
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * POST /api/{site}/open-collab/invitations/{token}/accept
     * Guest action: accepts an invitation and registers as a contributor.
     * Returns a Bearer token so the client is immediately authenticated.
     */
    public function accept(AcceptInvitationRequest $request, string $token): JsonResponse
    {
        try {
            $data = $request->validated();
            $user = $this->invitationService->accept(
                token: $token,
                name: $data['name'],
                password: $data['password'],
            );

            $authRequest = new AuthLoginRequest(
                email: $user->email,
                password: $data['password'],
                siteId: SiteContext::getId(),
            );

            $authResponse = $this->authenticationService->login($authRequest);

            return $this->jsonResponse([
                'token' => $authResponse->accessToken,
                'token_type' => $authResponse->tokenType,
                'user' => [
                    'id' => $authResponse->userId,
                    'name' => $authResponse->userName,
                    'email' => $authResponse->userEmail,
                    'role' => $authResponse->role,
                ],
            ], 201);
        } catch (ValidationException $validationException) {
            return $this->errorResponse('Validation failed', 422, $validationException->getErrors());
        } catch (InvalidInvitationException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }
}