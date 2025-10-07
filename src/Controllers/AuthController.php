<?php

namespace App\Controllers;

use App\Framework\Authorization\AuthenticationService;
use App\Framework\Authorization\Exceptions\InactiveUserException;
use App\Framework\Authorization\Exceptions\InvalidCredentialsException;
use App\Framework\Authorization\LoginRequest;
use App\Framework\Http\JsonResponse;
use App\Framework\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private AuthenticationService $authService
    ) {
        parent::__construct();
    }

    public function login(\App\Requests\LoginRequest $request): JsonResponse
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

            return $this->jsonResponse($response->toArray(), 200);

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

        return $this->jsonResponse([
            'success' => true,
            'message' => 'Successfully logged out',
        ], 200);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user;

        return $this->jsonResponse([
            'success' => true,
            'data' => [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
            ],
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
        // Implement your logic to determine site_id
        // This could be from subdomain, header, or request parameter
        return (int) $request->header('X-Site-Id', 1);
    }
}