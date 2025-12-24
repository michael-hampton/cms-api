<?php

namespace App\Framework\Authorization;

use App\Framework\AuthenticatedUser;
use App\Framework\Session\Session;
use App\Framework\Support\Event;
use App\Framework\Support\SiteContext;
use App\Models\User;

class Auth
{
    public static $user = null;
    private static $guard = 'web';

    public static function attempt(array $credentials): bool
    {
        $email = $credentials['email'] ?? null;
        $password = $credentials['password'] ?? null;

        if (!$email || !$password) {
            return false;
        }

        $user = AuthenticatedUser::where('email', $email)->first();

        if ($user && password_verify($password, $user['password'])) {
            self::login($user);
            return true;
        }

        return false;
    }

    public static function login($user): void
    {
        if (is_array($user)) {
            $user = new AuthenticatedUser(
                $user['id'],
                $user['name'],
                $user['email'],
                $user['role'] ?? 'user'
            );
            $user->exists = true;
        }

        self::$user = $user;

        // Store in session using Session class
        Session::put('user_id', $user->id);
        Session::put('user_name', $user->name);
        Session::put('user_email', $user->email);
        Session::put('user_role', $user->role);
        Session::put('authenticated', true);

        // Regenerate session ID for security
        Session::regenerate();

        //Event::fire('user.login', $user);
    }

    public static function logout(): void
    {
        $user = self::$user;
        self::$user = null;

        // Clear session using Session class
        Session::forgetMultiple([
            'user_id',
            'user_name',
            'user_email',
            'user_role',
            'authenticated'
        ]);

        // Regenerate session ID
        Session::regenerate();

        if ($user) {
            Event::fire('user.logout', $user);
        }
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function guest(): bool
    {
        return !self::check();
    }

    public static function user(): ?AuthenticatedUser
    {
        if (self::$user !== null) {
            return self::$user;
        }

        // Try to load from bearer token first (for API requests)
        $user = self::loadUserFromToken();
        if ($user) {
            return $user;
        }

        // Fall back to session (for web requests)
        if (Session::get('authenticated') === true && Session::has('user_id')) {
            $user = User::where('id', Session::get('user_id'))->first();

            if (empty($user)) {
                return null;
            }

            self::$user = new AuthenticatedUser(
                $user['id'],
                $user['name'],
                $user['email'],
                Session::get('user_role', 'user')
            );
            self::$user->exists = true;
            return self::$user;
        }

        return null;
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user ? $user->id : null;
    }

    public static function loadUserFromToken(): ?AuthenticatedUser
    {
        // Get bearer token from headers
        $headers = getallheaders();

        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        $token = substr($authHeader, 7);

        // Use your authentication service to validate token
        // This assumes you have a token repository/service
        $authService = app(AuthenticationService::class);
        $userId = $authService->validateToken($token, SiteContext::getId());

        if (!$userId) {
            return null;
        }

        $user = User::find($userId);

        if (!$user) {
            return null;
        }

        self::$user = (new AuthenticatedUser(
            $user->id,
            $user->name,
            $user->email,
            $user->role ?? 'user'
        ))->fill($user->toArray());
        self::$user->exists = true;

        return self::$user;
    }
}