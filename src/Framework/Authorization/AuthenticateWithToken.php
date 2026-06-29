<?php

namespace App\Framework\Authorization;

use App\Framework\Http\MiddlewareInterface;
use App\Framework\Http\Request;
use App\Framework\Http\Response;
use App\Framework\Support\SiteContext;
use App\Models\User;
use App\Models\UserSite;
use App\Repositories\Cms\UserRepositoryInterface;

class AuthenticateWithToken implements MiddlewareInterface
{
    public function __construct(
        private AuthenticationService $authService,
        private UserRepositoryInterface $userRepository
    )
    {
    }

    public function handle(Request $request, callable $next)
    {
        $token = $this->extractToken($request);

        $siteId = $this->getCurrentSiteId($request);

        if (!$token) {
            return Response::json([
                'success' => false,
                'message' => 'Token not provided',
            ], 401);
        }

        $accessToken = $this->authService->validateAccessToken($token, $siteId)
            ?? $this->authService->validateUserAccessTokenAcrossSites($token);

        if (!$accessToken || $accessToken->getTokenableType() !== User::class) {
            return Response::json([
                'success' => false,
                'message' => 'Invalid or expired token',
            ], 401);
        }

        if ($this->isOpenCollabRequest($request) && !$accessToken->can(AuthenticationService::ABILITY_OPEN_COLLAB)) {
            return Response::json([
                'success' => false,
                'message' => 'Token does not allow OpenCollab access',
            ], 403);
        }

        $user = $this->userRepository->findById($accessToken->getTokenableId(), $siteId);

        if (!$user || !$user->isActive()) {
            return Response::json([
                'success' => false,
                'message' => 'User not found',
            ], 401);
        }

        if (!$this->userCanAccessSite($accessToken->getTokenableId(), $siteId)) {
            return Response::json([
                'success' => false,
                'message' => 'You do not have access to this site.',
            ], 403);
        }

        // Set user on request
        $request->user = $user;

        Auth::authenticateApi([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role ?? 'user',
        ]);

        return $next($request);
    }


    private function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');

        if (empty($header)) {
            return null;
        }

        if (preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function getCurrentSiteId(Request $request): int
    {
        return SiteContext::getId() ?? (int)$request->header('X-Site-Id', 1);
    }

    private function isOpenCollabRequest(Request $request): bool
    {
        return str_contains($request->getPath(), '/open-collab/');
    }

    private function userCanAccessSite(int $userId, int $siteId): bool
    {
        return UserSite::where('user_id', $userId)
            ->where('site_id', $siteId)
            ->exists();
    }

    public function register(): void
    {
        // TODO: Implement register() method.
    }
}
