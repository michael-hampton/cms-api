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
use App\Models\Site;
use App\Models\User;
use App\Models\UserSite;
use App\Requests\OpenCollab\ContributorLoginRequest;
use App\Services\OpenCollab\SitePermissionResolver;

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
        private readonly SitePermissionResolver $permissionResolver,
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
        if ($request->getPath() === '/api/auth/login') {
            return $this->globalCmsLogin($request);
        }

        try {
            $data = $request->validated();

            $authRequest = new AuthLoginRequest(
                email: $data['email'],
                password: $data['password'],
                siteId: SiteContext::getId(),
                abilities: [AuthenticationService::ABILITY_OPEN_COLLAB],
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

    private function globalCmsLogin(ContributorLoginRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $user = User::where('email', $data['email'])->first();

            if (!$user || !$user->verifyPassword($data['password'])) {
                return $this->errorResponse('Invalid email or password.', 401);
            }

            if (!$user->isActive()) {
                return $this->errorResponse('Your account has been deactivated. Please contact support.', 403);
            }

            $siteIds = UserSite::where('user_id', $user->id)
                ->get()
                ->pluck('site_id')
                ->map(fn($id) => (int) $id)
                ->toArray();

            $site = $siteIds === []
                ? null
                : Site::whereIn('id', $siteIds)
                    ->where('is_active', 1)
                    ->orderBy('name')
                    ->first();

            if (!$site) {
                return $this->errorResponse('You do not have access to any active sites.', 403);
            }

            $response = $this->authenticationService->login(new AuthLoginRequest(
                email: $data['email'],
                password: $data['password'],
                siteId: (int) $site->id,
            ));

            Auth::login([
                'id' => $response->userId,
                'name' => $response->userName,
                'email' => $response->userEmail,
                'role' => $response->role,
            ]);

            $permissions = $this->permissionResolver->forUser($response->userId, (int) $site->id);
            $payload = $response->toArray();
            $payload['site'] = [
                'id' => (int) $site->id,
                'name' => $site->name,
                'slug' => $site->slug,
                'display_name' => $site->display_name ?? $site->name,
            ];
            $payload['permissions'] = $permissions;
            $payload['user']['permissions'] = $permissions;
            $payload['user']['site_id'] = (int) $site->id;

            return $this->jsonResponse($payload);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->getErrors());
        } catch (InvalidCredentialsException) {
            return $this->errorResponse('Invalid email or password.', 401);
        } catch (InactiveUserException) {
            return $this->errorResponse('Your account has been deactivated. Please contact support.', 403);
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
