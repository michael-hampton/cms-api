<?php

namespace App\Framework\Middleware;

use App\Framework\Http\Request;
use App\Framework\Support\SiteContext;
use Closure;

class SiteDetectionMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $host = $request->getHost();
        $path = $request->getPath();

        // SiteContext uses static state. Always reset it at the request boundary so
        // long-running workers cannot leak one site's context into the next request.
        SiteContext::clear();

        try {
            $site = SiteContext::resolve($host, $path);
        } catch (\RuntimeException $e) {
            http_response_code(503);
            echo 'Service Unavailable: No active site configured';
            exit;
        }

        $request->setSite($site);
        $request->setSiteId($site->id);

        if (SiteContext::isLocalhost($host)) {
            $segments = array_values(array_filter(explode('/', $path)));

            // Web URLs are /{site}/..., whereas versioned API URLs are
            // /api/v1/{site}/.... Only strip the leading site segment for the
            // web URL shape; API route matching still requires its full prefix.
            if (($segments[0] ?? null) === $site->slug) {
                array_shift($segments);
                $newPath = '/' . implode('/', $segments);
                $request->setPath($newPath ?: '/');
            }
        }

        if (!defined('CURRENT_SITE_ID')) {
            define('CURRENT_SITE_ID', $site->id);
            define('CURRENT_SITE_NAME', $site->name);
            define('CURRENT_SITE_SLUG', $site->slug);
            define('CURRENT_SITE_THEME', SiteContext::getTheme());
        }

        return $next($request);
    }
}
