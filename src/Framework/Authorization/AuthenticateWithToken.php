<?php

namespace App\Framework\Authorization;

use App\Framework\Http\MiddlewareInterface;
use App\Framework\Http\Request;
use App\Framework\Http\Response;
use App\Repositories\Cms\UserRepositoryInterface;

class AuthenticateWithToken implements MiddlewareInterface
{
    public function __construct(
        private AuthenticationService $authService,
        private UserRepositoryInterface $userRepository
    ) {}

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

        $userId = $this->authService->validateToken($token, $siteId);

        if (!$userId) {
            die('here');
            return Response::json([
                'success' => false,
                'message' => 'Invalid or expired token',
            ], 401);
        }

        $user = $this->userRepository->findById($userId, $siteId);

        if (!$user) {
            return Response::json([
                'success' => false,
                'message' => 'User not found',
            ], 401);
        }

        // Set user on request
        $request->user = $user;

        Auth::login([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => 'user',
        ]);

        return $next($request);
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
        return (int) $request->header('X-Site-Id', 1);
    }

    public function register(): void
    {
        // TODO: Implement register() method.
    }
}