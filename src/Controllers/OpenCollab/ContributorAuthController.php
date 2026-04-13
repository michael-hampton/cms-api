<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Authorization\AuthenticationService;
use App\Framework\Authorization\Exceptions\InactiveUserException;
use App\Framework\Authorization\Exceptions\InvalidCredentialsException;
use App\Framework\Authorization\LoginRequest as AuthLoginRequest;
use App\Framework\Exceptions\ValidationException;
use App\Framework\Http\JsonResponse;
use App\Framework\Support\SiteContext;
use App\Requests\OpenCollab\ContributorLoginRequest;

/**
 * Handles contributor authentication.
 *
 * Routes:
 *   POST /api/{site}/open-collab/auth/login   — issue a Bearer token
 *   POST /api/{site}/open-collab/auth/logout  — revoke the current token
 *
 * The login endpoint returns a Bearer token that the frontend stores in
 * localStorage. All subsequent API requests must include:
 *   Authorization: Bearer <token>
 *
 * There is no session involved for contributor API routes. Session-based
 * auth is reserved for the CRM and merchant portals.
 */
class ContributorAuthController extends Controller
{
    public function __construct(
        private readonly AuthenticationService $authenticationService,
    )
    {
        parent::__construct();
    }

    /**
     * POST /api/{site}/open-collab/auth/login
     *
     * Validates credentials, issues a Bearer token.
     * Returns 401 for invalid credentials, 403 for inactive accounts.
     */
    public function login(ContributorLoginRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            $authRequest = new AuthLoginRequest(
                email: $data['email'],
                password: $data['password'],
                siteId: SiteContext::getId(),
            );

            $response = $this->authenticationService->login($authRequest);

            if ($response) {
                Auth::login([
                    'id' => $response->userId,
                    'name' => $response->userName,
                    'email' => $response->userEmail,
                    'role' => $response->role,
                ]);
            }

            return $this->jsonResponse([
                'token' => $response->accessToken,
                'token_type' => $response->tokenType,
                'user' => [
                    'id' => $response->userId,
                    'name' => $response->userName,
                    'email' => $response->userEmail,
                    'role' => $response->role,
                ],
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (InvalidCredentialsException) {
            return $this->errorResponse('Invalid email or password.', 401);
        } catch (InactiveUserException) {
            return $this->errorResponse(
                'Your account has been deactivated. Please contact support.',
                403
            );
        }
    }

    /**
     * POST /api/{site}/open-collab/auth/logout
     *
     * Revokes the current Bearer token. The frontend should also remove
     * oc_token from localStorage.
     */
    public function logout(): JsonResponse
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;

        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            $this->authenticationService->logout($token, SiteContext::getId());
        }

        return $this->successResponse('Logged out successfully.');
    }
}