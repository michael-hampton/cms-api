<?php

use App\Framework\Authorization\Auth;
use App\Framework\Authorization\MemberAuth;
use App\Framework\Container;
use App\Framework\Date;
use App\Framework\Events\EventDispatcher;
use App\Framework\Http\Request;
use App\Framework\Http\Router;
use App\Framework\Security\Csrf;
use App\Framework\Session\Session;
use App\Framework\Support\Collection;
use App\Framework\Support\SiteContext;

if (!function_exists('auth')) {
    function auth(): Auth
    {
        return new Auth();
    }
}

if (!function_exists('member_auth')) {
    function member_auth(): MemberAuth
    {
        return new MemberAuth();
    }
}

if (!function_exists('url')) {
    function url(string $path): string
    {
        $base = rtrim(config('app.url'), '/');
        $path = ltrim($path, '/');

        return $base . '/' . $path;
    }
}

if (!function_exists('dd')) {
    function dd(mixed $value): ?string
    {
        echo '<pre>';
        print_r($value);
        die;
    }
}

if (!function_exists('request')) {
    function request(): Request
    {
        return new Request();
    }
}

if (!function_exists('dispatch')) {
    function dispatch(object $job, ...$args): void
    {
        if (!method_exists($job, 'handle')) {
            throw new InvalidArgumentException('Dispatched job must have a handle() method.');
        }

        $job->handle(...$args);
    }

}

if (!function_exists('message')) {
    function message(): ?string
    {
        return Session::getFlash('message');
    }
}

if (!function_exists('class_basename')) {
    function class_basename(string $class): string
    {
        $class = is_object($class) ? get_class($class) : $class;
        return basename(str_replace('\\', '/', $class));
    }
}

if (!function_exists('old')) {
    function old(?string $key = null, $default = null)
    {
        $oldInput = Session::getFlash('old_input', []);

        if ($key === null) {
            return $oldInput;
        }

        return $oldInput[$key] ?? $default;
    }
}

if (!function_exists('error')) {
    function error(string $key = ''): ?string
    {
        $errors = errors();

        if (empty($key)) {
            return implode(', ', $errors);
        }

        return isset($errors[$key]) ? (is_array($errors[$key]) ? $errors[$key][0] : $errors[$key]) : null;
    }
}

if (!function_exists('errors')) {
    function errors(): array
    {
        return Session::getFlash('errors', []);
    }
}

if (!function_exists('hasError')) {
    function hasError(string $key): bool
    {
        $errors = errors();
        return isset($errors[$key]);
    }
}

if (!function_exists('collect')) {
    function collect($items = []): Collection
    {
        return new Collection($items);
    }
}

if (!function_exists('getallheaders')) {
    function getallheaders()
    {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (str_starts_with($name, 'HTTP_')) {
                $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                $headers[$headerName] = $value;
            }
        }
        return $headers;
    }
}

if (!function_exists('app')) {
    /**
     * Resolve an instance from the container.
     *
     * @template T
     * @param class-string<T>|null $abstract
     * @param array $parameters
     * @return ($abstract is null ? Container : T)
     */
    function app(?string $abstract = null, array $parameters = []): mixed
    {
        $container = Container::getInstance();

        if ($abstract === null) {
            return $container;
        }

        return $container->resolve($abstract, $parameters);
    }
}

// Add this to a helper file or bootstrap.php

if (!function_exists('config')) {
    /**
     * Get configuration value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function config(string $key, $default = null)
    {
        static $config = null;

        if ($config === null) {
            // Load all config files
            $config = [];

            $configFiles = [
                'app' => 'config/app.php',
                'legal' => 'config/legal.php',
                'boost' => 'config/boost.php',
                'database' => 'config/database.php',
                'routing' => 'config/routing.php',
                'recommendations' => 'config/recommendations.php',
                'commission' => 'config/commission.php',
                'bundles' => 'config/bundles.php',
                'shipping' => 'config/shipping.php',
            ];

            foreach ($configFiles as $name => $file) {
                $file = base_path($file);
                if (file_exists($file)) {
                    $config[$name] = require $file;
                }
            }
        }

        // Support dot notation: 'app.debug' or 'database.host'
        $keys = explode('.', $key);
        $value = $config;

        foreach ($keys as $segment) {
            if (!isset($value[$segment])) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        static $basePath;

        if ($basePath === null) {
            // Resolve project root relative to this file
            $basePath = realpath(__DIR__ . '/../');
        }

        return $path
            ? $basePath . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR)
            : $basePath;
    }
}

if (!function_exists('env')) {
    /**
     * Get environment variable
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function env(string $key, $default = null)
    {
        $value = $_ENV[$key] ?? getenv($key);

        if ($value === false) {
            return $default;
        }

        // Convert common string values to proper types
        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'null':
            case '(null)':
                return null;
            case 'empty':
            case '(empty)':
                return '';
        }

        return $value;
    }
}

if (!function_exists('site')) {
    function site(): ?\App\Models\Site
    {
        return SiteContext::get();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        $token = Csrf::getToken();
        return '<input type="hidden" name="_token" value="' . htmlspecialchars($token) . '">';
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::getToken();
    }
}

if (!function_exists('asset')) {
    function asset(string $path, string $type = 'css'): string
    {
        // Determine folder based on type
        $folder = match ($type) {
            'css' => 'css',
            'js' => 'js',
            default => '',
        };

        // Build URL
        $url = '/public/' . ($folder ? $folder . '/' : '') . ltrim($path, '/');

        // Build filesystem path for cache-busting
        $file = __DIR__ . '/../public/' . ($folder ? $folder . '/' : '') . ltrim($path, '/');


        // Add cache-busting if file exists
        if (file_exists($file)) {
            $url .= '?v=' . filemtime($file);
        }

        return $url;
    }
}

if (!function_exists('site_id')) {
    function site_id(): ?int
    {
        return SiteContext::getId();
    }
}

if (!function_exists('site_url')) {
    function site_url(string $path = ''): string
    {
        return SiteContext::url($path);
    }
}

if (!function_exists('site_asset')) {
    function site_asset(string $path): string
    {
        return SiteContext::asset($path);
    }
}

if (!function_exists('site_css')) {
    function site_css(): string
    {
        return SiteContext::css();
    }
}

if (!function_exists('accessDenied')) {
    function accessDenied(string $message): string
    {
        $html = <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <title>Member Access Required</title>
            <style>
                body { font-family: Arial, sans-serif; max-width: 600px; margin: 100px auto; padding: 20px; }
                .message { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 20px; border-radius: 5px; }
                .actions { margin-top: 20px; }
                a { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin-right: 10px; }
                a:hover { background: #0056b3; }
            </style>
        </head>
        <body>
            <div class="message">
                <h2>🔒 Member Access Required</h2>
                <p>{$message}</p>
                <div class="actions">
                    <a href="/member/login">Login</a>
                    <a href="/member/register">Sign Up</a>
                </div>
            </div>
        </body>
        </html>
        HTML;

        return $html;
    }
}

if (!function_exists('route')) {
    function route(string $name, array $params = []): string
    {
        try {
            $router = Container::getInstance()->resolve(Router::class);
            return $router->route($name, $params);
        } catch (\Exception $e) {
            // Fallback: convert route name to a slug URL
            // e.g. 'privacy' => '/privacy', 'data-rights' => '/data-rights'
            $path = '/' . ltrim(str_replace('.', '/', $name), '/');
            foreach ($params as $key => $value) {
                $path = str_replace('{' . $key . '}', $value, $path);
            }
            return $path;
        }
    }
}

if (!function_exists('method_field')) {
    function method_field(string $method): string
    {
        return '<input type="hidden" name="_method" value="' . strtoupper($method) . '">';
    }
}

if (!function_exists('now_datetime')) {
    /**
     * Return the current DateTime instance
     *
     * @param string|null $timezone Optional timezone, e.g., 'UTC' or 'America/New_York'
     * @return \DateTime
     */
    function now_datetime(?string $timezone = null): \DateTime
    {
        if ($timezone) {
            return new Date('now', new \DateTimeZone($timezone));
        }

        return new Date('now');
    }
}

/**
 * Return the current date-time as a formatted string
 */
if (!function_exists('now')) {
    function now(string $format = 'Y-m-d H:i:s', ?string $timezone = null): string
    {
        return now_datetime($timezone)->format($format);
    }
}

if (!function_exists('trait_uses')) {
    /**
     * Get all traits used by a class
     */
    function trait_uses($class): array
    {
        if (is_object($class)) {
            $class = get_class($class);
        }

        $traits = [];

        // Get traits from the class
        do {
            $traits = array_merge(class_uses($class) ?: [], $traits);
        } while ($class = get_parent_class($class));

        // Get traits from traits recursively
        foreach ($traits as $trait => $same) {
            $traits = array_merge(class_uses($trait) ?: [], $traits);
        }

        return array_unique($traits);
    }
}

if (!function_exists('class_uses_recursive')) {
    function class_uses_recursive($class): array
    {
        if (is_object($class)) {
            $class = get_class($class);
        }

        $results = [];
        $seen = [];

        // Get all parent classes and the class itself
        $classes = array_reverse(class_parents($class)) + [$class => $class];

        foreach ($classes as $class) {
            $results += trait_uses_recursive($class, $seen);
        }

        return array_unique($results);
    }

    function trait_uses_recursive($trait, &$seen): array
    {
        if (isset($seen[$trait])) {
            return [];
        }

        $seen[$trait] = true;
        $traits = class_uses($trait) ?: [];

        foreach ($traits as $traitName) {
            $traits += trait_uses_recursive($traitName, $seen);
        }

        return $traits;
    }

    if (!function_exists('mail_manager')) {
        function mail_manager(): \App\Framework\Mail\MailManager
        {
            return \App\Framework\Mail\MailManager::getInstance();
        }
    }
}

if (!function_exists('getTimeAgo')) {
    function getTimeAgo($datetime): string
    {
        if (is_string($datetime)) {
            $timestamp = strtotime($datetime);
        } elseif ($datetime instanceof \DateTime || $datetime instanceof \DateTimeImmutable) {
            $timestamp = $datetime->getTimestamp();
        } else {
            return '';
        }

        $diff = time() - $timestamp;

        if ($diff < 60) return 'just now';
        if ($diff < 3600) {
            $mins = floor($diff / 60);
            return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
        }
        if ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        }
        if ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        }
        if ($diff < 2419200) {
            $weeks = floor($diff / 604800);
            return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
        }
        if ($diff < 29030400) {
            $months = floor($diff / 2419200);
            return $months . ' month' . ($months > 1 ? 's' : '') . ' ago';
        }

        $years = floor($diff / 29030400);
        return $years . ' year' . ($years > 1 ? 's' : '') . ' ago';
    }
}


/**
 * Human readable difference between two dates.
 *
 * @param DateTimeInterface $time The time to compare (target time).
 * @param DateTimeInterface|null $now Optional "now" reference (defaults to now).
 * @param int $precision How many units to include (1 = "2 months", 2 = "2 months 3 days").
 * @param bool $short If true, use compact units ("2mo 3d" / "in 2mo").
 * @return string
 */
function diffForHumans(DateTimeInterface $time, ?DateTimeInterface $now = null, int $precision = 1, bool $short = false): string
{
    if ($now === null) {
        $now = new DateTimeImmutable('now', $time->getTimezone());
    }

    // difference in seconds (positive means $time is in the future)
    $diffSeconds = $time->getTimestamp() - $now->getTimestamp();
    $isFuture = $diffSeconds > 0;
    $diff = abs($diffSeconds);

    // unit definitions (approximate months/years)
    $units = [
        'year' => 365 * 24 * 3600,
        'month' => 30 * 24 * 3600,
        'week' => 7 * 24 * 3600,
        'day' => 24 * 3600,
        'hour' => 3600,
        'minute' => 60,
        'second' => 1,
    ];

    $shortUnits = [
        'year' => 'y',
        'month' => 'mo',
        'week' => 'w',
        'day' => 'd',
        'hour' => 'h',
        'minute' => 'm',
        'second' => 's',
    ];

    // If difference is < 1 second
    if ($diff < 1) {
        return $short ? ($isFuture ? 'in 0s' : '0s ago') : 'just now';
    }

    $parts = [];
    foreach ($units as $name => $secondsPerUnit) {
        if ($diff <= 0) break;
        $count = intdiv($diff, $secondsPerUnit);
        if ($count <= 0) continue;
        $diff -= $count * $secondsPerUnit;

        if ($short) {
            $parts[] = $count . $shortUnits[$name];
        } else {
            $parts[] = $count . ' ' . $name . ($count === 1 ? '' : 's');
        }

        if (count($parts) >= max(1, $precision)) break;
    }

    $human = implode($short ? ' ' : ' ', $parts);

    if ($short) {
        return $isFuture ? "in {$human}" : "{$human} ago";
    }

    return $isFuture ? "in {$human}" : "{$human} ago";
}

if (!function_exists('event')) {
    function event(object $event): object
    {
        $dispatcher = Container::getInstance()->resolve(EventDispatcher::class);
        return $dispatcher?->dispatch($event) ?? $event;
    }
}


if (!function_exists('tap')) {
    function tap(mixed $value, callable $callback): mixed
    {
        $callback($value);

        return $value;
    }
}



