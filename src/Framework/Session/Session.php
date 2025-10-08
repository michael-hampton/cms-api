<?php

namespace App\Framework\Session;

class Session
{
    private static bool $started = false;

    /**
     * Start the session if not already started
     */
    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (headers_sent()) {
            throw new \RuntimeException('Cannot start session - headers already sent');
        }

        session_start();
        self::$started = true;
    }

    /**
     * Check if session has been started
     */
    public static function isStarted(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    /**
     * Set a session value
     */
    public static function put(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    /**
     * Get a session value
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Check if a session key exists
     */
    public static function has(string $key): bool
    {
        self::start();
        return isset($_SESSION[$key]);
    }

    /**
     * Remove a session value
     */
    public static function forget(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    /**
     * Remove multiple session values
     */
    public static function forgetMultiple(array $keys): void
    {
        self::start();
        foreach ($keys as $key) {
            unset($_SESSION[$key]);
        }
    }

    /**
     * Get all session data
     */
    public static function all(): array
    {
        self::start();
        return $_SESSION;
    }

    /**
     * Flash a message for the next request only
     */
    public static function flash(string $key, mixed $value): void
    {
        self::start();
        $_SESSION['_flash'][$key] = $value;
    }

    /**
     * Get flashed data and remove it
     */
    public static function getFlash(string $key, mixed $default = null): mixed
    {
        self::start();

        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);

        return $value;
    }

    /**
     * Check if flash data exists
     */
    public static function hasFlash(string $key): bool
    {
        self::start();
        return isset($_SESSION['_flash'][$key]);
    }

    /**
     * Get all flash data
     */
    public static function getAllFlash(): array
    {
        self::start();
        return $_SESSION['_flash'] ?? [];
    }

    /**
     * Keep flash data for another request
     */
    public static function reflash(): void
    {
        self::start();

        if (isset($_SESSION['_flash'])) {
            $_SESSION['_flash_old'] = $_SESSION['_flash'];
        }
    }

    /**
     * Keep specific flash keys for another request
     */
    public static function keep(array $keys): void
    {
        self::start();

        foreach ($keys as $key) {
            if (isset($_SESSION['_flash'][$key])) {
                $_SESSION['_flash_old'][$key] = $_SESSION['_flash'][$key];
            }
        }
    }

    /**
     * Clear flash data (called automatically at end of request)
     */
    public static function ageFlashData(): void
    {
        self::start();

        // Move old flash to current
        if (isset($_SESSION['_flash_old'])) {
            $_SESSION['_flash'] = $_SESSION['_flash_old'];
            unset($_SESSION['_flash_old']);
        } else {
            $_SESSION['_flash'] = [];
        }
    }

    /**
     * Destroy the entire session
     */
    public static function destroy(): void
    {
        self::start();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
        self::$started = false;
    }

    /**
     * Regenerate the session ID
     */
    public static function regenerate(bool $deleteOldSession = true): void
    {
        self::start();
        session_regenerate_id($deleteOldSession);
    }

    /**
     * Clear all session data without destroying the session
     */
    public static function flush(): void
    {
        self::start();
        $_SESSION = [];
    }

    /**
     * Get the session ID
     */
    public static function getId(): string
    {
        self::start();
        return session_id();
    }

    /**
     * Set the session ID
     */
    public static function setId(string $id): void
    {
        session_id($id);
    }

    /**
     * Get the session name
     */
    public static function getName(): string
    {
        return session_name();
    }

    /**
     * Set the session name
     */
    public static function setName(string $name): void
    {
        session_name($name);
    }

    /**
     * Pull a value from session and remove it
     */
    public static function pull(string $key, mixed $default = null): mixed
    {
        $value = self::get($key, $default);
        self::forget($key);
        return $value;
    }

    /**
     * Increment a session value
     */
    public static function increment(string $key, int $amount = 1): int
    {
        self::start();
        $_SESSION[$key] = self::get($key, 0) + $amount;
        return $_SESSION[$key];
    }

    /**
     * Decrement a session value
     */
    public static function decrement(string $key, int $amount = 1): int
    {
        return self::increment($key, $amount * -1);
    }

    /**
     * Get CSRF token
     */
    public static function token(): string
    {
        self::start();

        if (!isset($_SESSION['_token'])) {
            $_SESSION['_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_token'];
    }

    /**
     * Regenerate CSRF token
     */
    public static function regenerateToken(): string
    {
        self::start();
        $_SESSION['_token'] = bin2hex(random_bytes(32));
        return $_SESSION['_token'];
    }

    /**
     * Get the previous URL
     */
    public static function previousUrl(): ?string
    {
        return self::get('_previous_url');
    }

    /**
     * Set the previous URL
     */
    public static function setPreviousUrl(string $url): void
    {
        self::put('_previous_url', $url);
    }
}