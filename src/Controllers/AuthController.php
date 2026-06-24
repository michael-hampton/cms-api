<?php

namespace App\Controllers;

use App\Framework\Authorization\Auth;
use App\Framework\Authorization\AuthenticationService;
use App\Framework\Authorization\Exceptions\InactiveUserException;
use App\Framework\Authorization\Exceptions\InvalidCredentialsException;
use App\Framework\Authorization\LoginRequest;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use App\Models\Site;
use App\Models\User;
use App\Models\UserSite;
use App\Requests\MemberRegistrationRequest;
use App\Services\OpenCollab\SitePermissionResolver;

class AuthController extends Controller
{
    public function __construct(
        private AuthenticationService $authService,
        private SitePermissionResolver $permissionResolver
    ) {
        parent::__construct();
    }

    public function login(MemberRegistrationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $siteId = $this->getCurrentSiteId($request);

        try {
            $loginRequest = new LoginRequest(
                $validated['email'],
                $validated['password'],
                $siteId
            );

            $response = $this->authService->login($loginRequest);

            Auth::login([
                'id' => $response->userId,
                'name' => $response->userName,
                'email' => $response->userEmail,
                'role' => $response->role,
            ]);

            if (!$this->userCanAccessSite($response->userId, $response->siteId)) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'You do not have access to this site.',
                ], 403);
            }

            return $this->jsonResponse($this->withAuthContext($response->toArray(), $response->userId, $response->siteId), 200);

        } catch (InvalidCredentialsException $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], 401);
        } catch (InactiveUserException $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    public function globalLogin(MemberRegistrationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !$user->verifyPassword($validated['password'])) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Invalid email or password',
            ], 401);
        }

        if (!$user->isActive()) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'User account is inactive',
            ], 403);
        }

        $siteIds = UserSite::where('user_id', $user->id)
            ->get()
            ->pluck('site_id')
            ->map(fn($id) => (int) $id)
            ->toArray();

        $site = $siteIds === []
            ? null
            : Site::whereIn('id', $siteIds)->where('is_active', 1)->orderBy('name')->first();

        if (!$site) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'You do not have access to any active sites.',
            ], 403);
        }

        try {
            $response = $this->authService->login(new LoginRequest(
                $validated['email'],
                $validated['password'],
                (int) $site->id
            ));

            Auth::login([
                'id' => $response->userId,
                'name' => $response->userName,
                'email' => $response->userEmail,
                'role' => $response->role,
            ]);

            return $this->jsonResponse($this->withAuthContextForSite(
                $response->toArray(),
                $response->userId,
                (int) $site->id,
                $site
            ), 200);
        } catch (InvalidCredentialsException $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], 401);
        } catch (InactiveUserException $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $this->extractToken($request);
        $siteId = $this->getCurrentSiteId($request);

        if ($token) {
            $this->authService->logout($token, $siteId);
        }

        Auth::logout();

        return $this->jsonResponse([
            'success' => true,
            'message' => 'Successfully logged out',
        ], 200);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user;
        $site = SiteContext::get();
        $siteId = $site?->id ?? $this->getCurrentSiteId($request);

        if (!$this->userCanAccessSite((int) $user->id, $siteId)) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'You do not have access to this site.',
            ], 403);
        }

        return $this->jsonResponse([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'site_id' => $siteId,
                'role' => $user->role ?? null,
            ],
            'site' => $site ? $this->serializeSite($site) : null,
            'permissions' => $this->permissionsFor((int) $user->id, $siteId),
        ], 200);
    }

    private function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');

        if (preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function getCurrentSiteId(Request $request): int
    {
        return SiteContext::getId() ?? (int) $request->header('X-Site-Id', 1);
    }

    private function withAuthContext(array $payload, int $userId, int $siteId): array
    {
        $site = SiteContext::get();

        $payload['permissions'] = $this->permissionsFor($userId, $siteId);
        $payload['site'] = $site ? $this->serializeSite($site) : null;
        $payload['user']['permissions'] = $payload['permissions'];

        return $payload;
    }

    private function withAuthContextForSite(array $payload, int $userId, int $siteId, Site $site): array
    {
        $payload['permissions'] = $this->permissionsFor($userId, $siteId);
        $payload['site'] = $this->serializeSite($site);
        $payload['user']['permissions'] = $payload['permissions'];
        $payload['user']['site_id'] = $siteId;

        return $payload;
    }

    private function permissionsFor(int $userId, int $siteId): array
    {
        return $this->permissionResolver->forUser($userId, $siteId);
    }

    private function userCanAccessSite(int $userId, int $siteId): bool
    {
        return UserSite::where('user_id', $userId)
            ->where('site_id', $siteId)
            ->first() !== null;
    }

    private function serializeSite(Site $site): array
    {
        return [
            'id' => (int) $site->id,
            'name' => $site->name,
            'slug' => $site->slug,
            'display_name' => $site->display_name ?? $site->name,
        ];
    }
}
