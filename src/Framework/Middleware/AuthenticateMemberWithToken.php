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

        // Transitional compatibility: existing browser sessions continue to
        // work while login issues the token cookie used on subsequent requests.
        if (!$token && MemberAuth::check()) {
            return $next($request);
        }

        if (!$token) {
            return $this->unauthorised($request, 'Token not provided');
        }

        $accessToken = $this->authService->validateAccessToken($token, $siteId);

        if (!$accessToken || $accessToken->getTokenableType() !== Member::class) {
            return $this->unauthorised($request, 'Invalid or expired token');
        }

        $member = Member::where('id', $accessToken->getTokenableId())
            //->where('site_id', $siteId)
            ->first();

        if (!$member || !$member->isActive()) {
            return $this->unauthorised($request, 'Member not found');
        }

        MemberAuth::authenticateApi($member);
        //$request->member = $member;

        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization') ?? '';

        if (preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            return trim($matches[1]);
        }

        return isset($_COOKIE['member_access_token'])
            ? trim((string)$_COOKIE['member_access_token'])
            : null;
    }

    private function unauthorised(Request $request, string $message): Response
    {
        $accept = strtolower((string)$request->header('Accept', ''));
        $requestedWith = strtolower((string)$request->header('X-Requested-With', ''));

        if (str_contains($accept, 'application/json') || $requestedWith === 'xmlhttprequest') {
            return Response::json(['success' => false, 'message' => $message], 401);
        }

        $redirect = '/member/login?redirect=' . urlencode($request->getUri());
        return new Response('', 302, ['Location' => $redirect]);
    }
}
