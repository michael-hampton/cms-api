<?php

namespace App\Framework\Middleware;

use App\Framework\Authorization\AuthenticationService;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Http\Request;
use App\Framework\Http\Response;
use App\Framework\Support\SiteContext;
use App\Models\Member;

class AuthenticateMemberWithToken
{
    public function __construct(
        private readonly AuthenticationService $authService
    )
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $token = $this->extractToken($request);

        $siteId = (int)SiteContext::getId();

//        if (!$token && MemberAuth::check()) {
//            $member = MemberAuth::getMember();
//            if ($member) {
//                return $next($request);
//            }
//        }

        if (!$token) {
            return Response::json([
                'success' => false,
                'message' => 'Token not provided',
            ], 401);
        }

        $accessToken = $this->authService->validateAccessToken($token, $siteId);

        if (!$accessToken || $accessToken->getTokenableType() !== Member::class) {
            return Response::json([
                'success' => false,
                'message' => 'Invalid or expired token',
            ], 401);
        }

        $member = Member::where('id', $accessToken->getTokenableId())
            ->where('site_id', $siteId)
            ->first();

        if (!$member || !$member->isActive()) {
            return Response::json([
                'success' => false,
                'message' => 'Member not found',
            ], 401);
        }

        MemberAuth::authenticateApi($member);
        //$request->member = $member;

        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization') ?? '';

        if (preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
