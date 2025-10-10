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

        // Resolve the current site
        try {
            $site = SiteContext::resolve($host, $path);
        } catch (\RuntimeException $e) {
            // No active site found
            http_response_code(503);
            echo "Service Unavailable: No active site configured";
            exit;
        }

        // Store site in request for easy access
        $request->setSite($site);
        $request->setSiteId($site->id);

        // If localhost and path starts with site slug, strip it
        if (SiteContext::isLocalhost($host)) {
            $segments = array_filter(explode('/', $path));
            if (!empty($segments) && reset($segments) === $site->slug) {
                // Remove site slug from path
                array_shift($segments);
                $newPath = '/' . implode('/', $segments);
                $request->setPath($newPath ?: '/');
            }
        }

        // Define site constants for easy access
        if (!defined('CURRENT_SITE_ID')) {
            define('CURRENT_SITE_ID', $site->id);
            define('CURRENT_SITE_NAME', $site->name);
            define('CURRENT_SITE_SLUG', $site->slug);
            define('CURRENT_SITE_THEME', SiteContext::getTheme());
        }

        return $next($request);
    }
}
