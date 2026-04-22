<?php

namespace App\Framework\Middleware;

use App\Framework\Authorization\Auth;
use App\Framework\Http\MiddlewareInterface;
use App\Framework\Http\Request;
use App\Framework\Http\Response;

class AuthenticateWithSession implements MiddlewareInterface
{
    private string $loginPath;
    private string $guard;

    public function __construct(string $loginPath = '/login', string $guard = 'portal')
    {
        $this->loginPath = $loginPath;
        $this->guard = $guard;
    }

    public function handle(Request $request, callable $next)
    {
        if (!Auth::check()) {
            // Store intended URL so we can redirect after login
            \App\Framework\Session\Session::put('intended_url', $request->getPath());

            // AJAX / JSON requests get a 401 rather than a redirect
            if ($request->wantsJson()) {
                return Response::json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            return Response::redirect($this->loginPath);
        }

        // Optional: role gate — portals require at least role 'agent' or 'admin'
        $user = Auth::user();

        if (!in_array($user->role ?? '', ['admin', 'agent', 'merchant'])) {
            if ($request->wantsJson()) {
                return Response::json([
                    'success' => false,
                    'message' => 'Forbidden.',
                ], 403);
            }

            Auth::logout();
            return Response::redirect($this->loginPath . '?error=forbidden');
        }

        return $next($request);
    }

    public function register(): void
    {
    }
}