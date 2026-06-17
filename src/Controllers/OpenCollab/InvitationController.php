<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Controllers\OpenCollab\Concerns\AuthorizesSitePermissions;
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
use App\Services\OpenCollab\OpenCollabAuthorizationService;
use InvalidArgumentException;
use Throwable;

class InvitationController extends Controller
{
    use AuthorizesSitePermissions;

    public function __construct(
        private readonly InvitationService              $invitationService,
        private readonly AuthenticationService          $authenticationService,
        private readonly OpenCollabAuthorizationService $authorization,
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
        if (!Auth::check()) {
            return $this->errorResponse('Not logged in', 401);
        }

        if ($response = $this->authorizeSitePermissions(['creator.invite', 'site.members'])) {
            return $response;
        }

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
        } catch (InvalidArgumentException $e) {
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
                password: $data['password']
            );

            try {
                $authRequest = new AuthLoginRequest(
                    email: $user->email,
                    password: $data['password'],
                    siteId: SiteContext::getId(),
                    abilities: [AuthenticationService::ABILITY_OPEN_COLLAB],
                );

                $authResponse = $this->authenticationService->login($authRequest);

                Auth::login([
                    'id' => $authResponse->userId,
                    'name' => $authResponse->userName,
                    'email' => $authResponse->userEmail,
                    'role' => $authResponse->role,
                ]);

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
            } catch (Throwable) {
                return $this->jsonResponse([
                    'message' => 'Invitation accepted. Please log in with your existing password.',
                    'requires_login' => true,
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,
                    ],
                ], 201);
            }
        } catch (ValidationException $validationException) {
            return $this->errorResponse(
                'Validation failed',
                422,
                $validationException->getErrors()
            );
        } catch (InvalidInvitationException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        } catch (InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }
}
