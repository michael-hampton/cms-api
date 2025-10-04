<?php

namespace App\Framework\Authorization;

use App\Framework\AuthenticatedUser;
use App\Framework\Support\Event;

class Auth
{
    private static $user = null;
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
            $user = new AuthenticatedUser($user);
            $user->exists = true;
        }

        self::$user = $user;

        // Store in session
        $_SESSION['user_id'] = $user->id;
        $_SESSION['authenticated'] = true;

        Event::fire('user.login', $user);
    }

    public static function logout(): void
    {
        $user = self::$user;
        self::$user = null;

        // Clear session
        unset($_SESSION['user_id'], $_SESSION['authenticated']);

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

        // Try to load from session
        if (isset($_SESSION['user_id']) && $_SESSION['authenticated'] === true) {
            $user = AuthenticatedUser::find($_SESSION['user_id']);
            if ($user) {
                self::$user = $user;
                return $user;
            }
        }

        return null;
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user ? $user->id : null;
    }
}