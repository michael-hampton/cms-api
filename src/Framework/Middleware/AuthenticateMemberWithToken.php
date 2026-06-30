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
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        $token = $this->extractToken($request);
        $tokenIsFromHeader = $token !== null && $this->hasBearerToken($request);
        $siteId = (int) SiteContext::getId();

        if (!$token && MemberAuth::check()) {
            $this->refreshMemberTokenCookieForSession($siteId);

            return $next($request);
        }

        if (!$token && $this->isPressStackAccountPageRequest($request)) {
            return $next($request);
        }

        if (!$token) {
            return $this->unauthorised($request, 'Token not provided');
        }

        $accessToken = $this->isPressStackAccountRequest($request)
            ? $this->authService->validateMemberAccessTokenAcrossSites($token)
            : $this->authService->validateAccessToken($token, $siteId);

        if (!$accessToken || $accessToken->getTokenableType() !== Member::class) {
            if ($tokenIsFromHeader && $this->isPressStackAccountPageRequest($request)) {
                return $next($request);
            }

            if (!$this->isPressStackAccountRequest($request) && MemberAuth::check()) {
                $this->refreshMemberTokenCookieForSession($siteId);

                return $next($request);
            }

            return $this->unauthorised($request, 'Invalid or expired token');
        }

        $member = Member::where('id', $accessToken->getTokenableId())->first();

        if (!$member || !$member->isActive()) {
            if (!$this->isPressStackAccountRequest($request) && MemberAuth::check()) {
                $this->refreshMemberTokenCookieForSession($siteId);

                return $next($request);
            }

            return $this->unauthorised($request, 'Member not found');
        }

        MemberAuth::authenticateApi($member);

        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization') ?? '';

        if (preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            return trim($matches[1]);
        }

        return isset($_COOKIE['member_access_token'])
            ? trim((string) $_COOKIE['member_access_token'])
            : null;
    }

    private function hasBearerToken(Request $request): bool
    {
        $header = $request->header('Authorization') ?? '';

        return preg_match('/Bearer\s+\S+/i', $header) === 1;
    }

    private function isPressStackAccountRequest(Request $request): bool
    {
        $path = parse_url($request->getUri(), PHP_URL_PATH) ?: '';

        return $path === '/press-stack/account'
            || str_starts_with($path, '/press-stack/account/');
    }

    private function isPressStackAccountPageRequest(Request $request): bool
    {
        if (!$request->isGet()) {
            return false;
        }

        $path = parse_url($request->getUri(), PHP_URL_PATH) ?: '';

        return $path === '/press-stack/account'
            || $path === '/press-stack/account/subscriptions'
            || $path === '/press-stack/account/orders'
            || preg_match('#^/press-stack/account/orders/\d+$#', $path) === 1
            || $path === '/press-stack/account/billing';
    }

    private function refreshMemberTokenCookieForSession(int $siteId): void
    {
        $member = MemberAuth::getMember();

        if (!$member || !$member->isActive()) {
            return;
        }

        $token = $this->authService->createMemberToken($member, $siteId);
        $this->setMemberTokenCookie($token);
    }

    private function setMemberTokenCookie(string $token): void
    {
        $_COOKIE['member_access_token'] = $token;

        setcookie('member_access_token', $token, [
            'expires' => time() + (8 * 60 * 60),
            'path' => '/',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function clearMemberTokenCookie(): void
    {
        if (!isset($_COOKIE['member_access_token'])) {
            return;
        }

        unset($_COOKIE['member_access_token']);

        setcookie('member_access_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function unauthorised(Request $request, string $message): Response
    {
        if ($this->isApiRequest($request)) {
            return Response::json(['success' => false, 'message' => $message], 401);
        }

        $site = $request->route('site');
        $loginUrl = is_string($site) && $site !== ''
            ? '/' . $site . '/member/login'
            : '/member/login';

        return Response::redirect(
            $loginUrl . '?redirect=' . urlencode($request->getUri()),
        );
    }

    private function isApiRequest(Request $request): bool
    {
        return str_starts_with($request->getPath(), '/api/')
            || $request->wantsJson();
    }
}
