<?php

namespace App\Framework\Session;

/**
 * Injectable, non-static wrapper around the Session facade so that
 * services can depend on session storage via constructor injection
 * instead of calling the static Session facade directly.
 *
 * Mirrors the pattern used by App\Framework\Authorization\MemberAuthWrapper.
 */
class SessionManager
{
    public function put(string $key, mixed $value): void
    {
        Session::put($key, $value);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Session::get($key, $default);
    }

    public function has(string $key): bool
    {
        return Session::has($key);
    }
}
