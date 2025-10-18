<?php

namespace App\Framework\Security;

use App\Framework\Session\Session;

class Csrf
{
    private const TOKEN_NAME = '_csrf_token';

    /**
     * Generate a new CSRF token
     */
    public static function generateToken(): string
    {
        $token = bin2hex(random_bytes(32));
        Session::put(self::TOKEN_NAME, $token);
        return $token;
    }

    /**
     * Get the current CSRF token (generate if doesn't exist)
     */
    public static function getToken(): string
    {
        $token = Session::get(self::TOKEN_NAME);

        if (!$token) {
            $token = self::generateToken();
        }

        return $token;
    }

    /**
     * Validate a CSRF token
     */
    public static function validateToken(string $token): bool
    {
        $sessionToken = Session::get(self::TOKEN_NAME);

        if (!$sessionToken) {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }

    /**
     * Verify CSRF token from request
     */
    public static function verify(array $data): bool
    {
        $token = $data['_token'] ?? '';
        return self::validateToken($token);
    }
}