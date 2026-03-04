<?php

declare(strict_types=1);

namespace App\OpenApiGenerator;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * RouteParser
 *
 * Scans PHP route files for Router method calls (get/post/put/delete/patch)
 * and resolves them to controller class + method, extracting path parameters.
 *
 * Supports both handler formats used in the codebase:
 *   ->get('/path', [Controller::class, 'method'])
 *   ->post('/path', Controller::class, 'method')
 *   ->put('/path', 'Controller@method')
 */
class RouteParser
{
    private string $srcPath;
    private Reflector $reflector;

    /** Methods that indicate a list/index operation */
    private const INDEX_METHODS = ['index', 'list', 'all', 'search', 'pages', 'recent', 'active', 'featured', 'popular', 'payments', 'plans'];

    /** Methods that indicate a create operation */
    private const CREATE_METHODS = ['store', 'create', 'upload', 'duplicate', 'clone', 'merge', 'bulkDelete', 'bulkUpdate', 'bulkAssign', 'bulkArchive', 'bulkApprove', 'bulkClone', 'bulkSchedule', 'bulkToggleActive'];

    /** Methods that indicate an update operation */
    private const UPDATE_METHODS = ['update', 'patch', 'approve', 'reject', 'publish', 'unpublish', 'archive', 'unarchive', 'restore', 'activate', 'deactivate', 'pause', 'resume', 'toggleStatus', 'toggle', 'makePrivate', 'makeInternal', 'putOnHold', 'resolveComment', 'unresolveComment', 'setDeadline', 'deleteDeadline', 'updateStatus', 'updateSchedule', 'saveAsTemplate', 'addCollaborator', 'updateCollaborator', 'removeCollaborator', 'addRelationship', 'removeRelationship', 'addWorkflowChange', 'addComment', 'updateComment', 'deleteComment', 'addAttachment', 'updateAttachment', 'deleteAttachment', 'assignPages', 'unassignPages', 'reorder', 'setDefault', 'removeVoucher'];

    public function __construct(string $srcPath, Reflector $reflector)
    {
        $this->srcPath = $srcPath;
        $this->reflector = $reflector;
    }

    /**
     * Find and parse all route files, returning a flat list of route definitions.
     *
     * @return array<int, array{
     *   method: string,
     *   path: string,
     *   controllerClass: string,
     *   controllerMethod: string,
     *   pathParams: array,
     *   requestClass: string|null,
     *   tag: string,
     *   summary: string,
     *   operationId: string,
     * }>
     */
    public function parse(): array
    {
        $routeFiles = $this->findRouteFiles();
        $routes = [];

        foreach ($routeFiles as $file) {
            $routes = array_merge($routes, $this->parseFile($file));
        }

        // Deduplicate by method+path
        $seen = [];
        $unique = [];
        foreach ($routes as $route) {
            $key = $route['method'] . ':' . $route['path'];
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $route;
            }
        }

        return $unique;
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function findRouteFiles(): array
    {
        $files = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->srcPath));

        foreach ($it as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();

            // Only include api.php route files
            if (basename($path) === 'api.php') {
                $files[] = $path;
            }
        }

        return array_unique($files);
    }

    private function parseFile(string $filePath): array
    {
        $src = file_get_contents($filePath);
        $uses = $this->extractUses($src);

        // Flatten multi-line route calls into single lines so the regex below
        // can match them regardless of how the developer formatted the call.
        $src = $this->collapseMultilineCalls($src);

        $routes = [];
        $prefixStack = [];
        $middlewareStack = []; // parallel to prefixStack: middleware classes per group
        // Track the closure-depth at which each prefix was pushed.
        // We count only `function (` openings and their matching `}` closings
        // so that route-string braces like {siteName} don't corrupt the count.
        $groupDepthStack = [];
        $closureDepth = 0;

        $lines = explode("\n", $src);

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Count PHP closure openings on this line (function keyword + open brace)
            // and standalone closing braces that close a closure scope.
            // We deliberately ignore braces inside strings by stripping quoted content first.
            $stripped = preg_replace('/([\'"])(?:\\\\.|(?!\1).)*\1/', '""', $line);

            $closureOpens = preg_match_all('/\bfunction\s*\(/', $stripped);
            $closureCloses = preg_match_all('/^\s*\}\s*(?:\)|;|,)?\s*$/', $stripped)
                + preg_match_all('/\}\s*\)\s*;/', $stripped);

            // Detect ->group(['prefix' => '...', ...], ...) and push prefix BEFORE depth change.
            // Also detect middleware on the group so we can mark routes as authenticated.
            if (preg_match('/->group\s*\(\s*\[/', $line, $gm)) {
                $groupPrefix = '';
                $groupMiddleware = [];

                if (preg_match('/[\'"]prefix[\'"]\s*=>\s*[\'"]([^\'"]*)[\'"]/', $line, $pm)) {
                    $groupPrefix = trim($pm[1], '/');
                }

                // Capture middleware class references: 'middleware' => ClassName::class or [ClassName::class, ...]
                if (preg_match('/[\'"]middleware[\'"]\s*=>\s*(.+?)(?:,\s*[\'"]|\])/s', $line, $mm)) {
                    preg_match_all('/([\w\\\\]+)::class/', $mm[1], $classList);
                    $groupMiddleware = $classList[1] ?? [];
                }

                $prefixStack[] = $groupPrefix;
                $middlewareStack[] = $groupMiddleware;
                $groupDepthStack[] = $closureDepth + 1;
            }

            $closureDepth += $closureOpens;

            // Pop prefixes whose closure has now closed
            foreach ($groupDepthStack as $i => $depth) {
                if ($closureDepth - $closureCloses < $depth) {
                    unset($prefixStack[$i], $middlewareStack[$i], $groupDepthStack[$i]);
                }
            }
            $prefixStack = array_values($prefixStack);
            $middlewareStack = array_values($middlewareStack);
            $groupDepthStack = array_values($groupDepthStack);

            $closureDepth -= $closureCloses;
            if ($closureDepth < 0) {
                $closureDepth = 0;
            }

            // Match route calls: ->get('/path', ...) or ->post('/path', ...)
            if (!preg_match('/->(?P<httpMethod>get|post|put|delete|patch)\s*\((?P<args>.+)\)/', $trimmed, $match)) {
                continue;
            }

            $httpMethod = strtoupper($match['httpMethod']);
            $argsRaw = trim($match['args']);

            $parsed = $this->parseRouteArgs($argsRaw, $uses);
            if (!$parsed) {
                continue;
            }

            [$routePath, $controllerClass, $controllerMethod] = $parsed;

            // Build full path by prepending all active group prefixes
            $fullPath = $this->buildFullPath($prefixStack, $routePath);

            preg_match_all('/\{(\w+)\}/', $fullPath, $paramMatches);
            $pathParams = $this->resolvePathParamTypes(
                $paramMatches[1],
                $controllerClass,
                $controllerMethod
            );

            $requestClass = $this->findRequestClass($controllerClass, $controllerMethod);

            // A route is authenticated if any active group middleware contains an auth class
            $allMiddleware = array_merge(...($middlewareStack ?: [[]]));
            $authenticated = $this->isAuthMiddleware($allMiddleware);

            $routes[] = [
                'method' => $httpMethod,
                'uri' => $fullPath,
                'path' => $this->normaliseOpenApiPath($fullPath),
                'controllerClass' => $controllerClass,
                'controllerMethod' => $controllerMethod,
                'pathParams' => $pathParams,
                'requestClass' => $requestClass,
                'authenticated' => $authenticated,
                'tag' => $this->deriveTag($controllerClass),
                'summary' => $this->deriveSummary($controllerMethod, $controllerClass),
                'operationId' => $this->deriveOperationId($httpMethod, $fullPath, $controllerMethod),
                'description' => $this->deriveDescription($httpMethod, $controllerMethod),
            ];
        }

        return $routes;
    }

    /**
     * Collapse multi-line route calls into a single line each.
     * Handles cases like:
     *   $router->put(
     *       'path',
     *       [Controller::class, 'method']
     *   );
     */
    private function collapseMultilineCalls(string $src): string
    {
        // When a ->method( opens but the ) hasn't closed on the same line,
        // join subsequent lines until parens balance.
        $lines = explode("\n", $src);
        $result = [];
        $buffer = null;
        $depth = 0;

        foreach ($lines as $line) {
            if ($buffer === null) {
                // Start buffering when we see a route method call with an unclosed paren
                if (preg_match('/->(?:get|post|put|delete|patch)\s*\(/', $line)) {
                    $depth = substr_count($line, '(') - substr_count($line, ')');
                    $buffer = rtrim($line);
                    if ($depth <= 0) {
                        $result[] = $buffer;
                        $buffer = null;
                        $depth = 0;
                    }
                } else {
                    $result[] = $line;
                }
            } else {
                $depth += substr_count($line, '(') - substr_count($line, ')');
                $buffer .= ' ' . trim($line);
                if ($depth <= 0) {
                    $result[] = $buffer;
                    $buffer = null;
                    $depth = 0;
                }
            }
        }

        if ($buffer !== null) {
            $result[] = $buffer;
        }

        return implode("\n", $result);
    }

    /**
     * Combine the active group prefix stack with a route's own path segment.
     */
    private function buildFullPath(array $prefixStack, string $routePath): string
    {
        $parts = array_filter($prefixStack, fn($p) => $p !== '');
        $parts[] = ltrim($routePath, '/');
        $path = implode('/', array_map(fn($p) => trim($p, '/'), $parts));
        return '/' . ltrim($path, '/');
    }

    private function extractUses(string $src): array
    {
        $uses = [];
        preg_match_all('/^use\s+([\w\\\\]+)(?:\s+as\s+(\w+))?\s*;/m', $src, $m, PREG_SET_ORDER);
        foreach ($m as $u) {
            $fqcn = $u[1];
            $alias = $u[2] ?? basename(str_replace('\\', '/', $fqcn));
            $uses[$alias] = $fqcn;
        }
        return $uses;
    }

    /**
     * Parse the argument string of a route call into [path, controllerClass, method].
     */
    private function parseRouteArgs(string $argsRaw, array $uses): ?array
    {
        // Tokenise: extract the path string first
        if (!preg_match('/^[\'"]([^\'"]+)[\'"]/', $argsRaw, $pathMatch)) {
            return null;
        }

        $routePath = $pathMatch[1];
        $rest = substr($argsRaw, strlen($pathMatch[0]));
        $rest = ltrim($rest, " \t\n\r,");

        // Handler format 1: [ClassName::class, 'method']  or  [ClassName::class, "method"]
        if (preg_match('/^\[\s*([\w\\\\]+)::class\s*,\s*[\'"](\w+)[\'"]\s*\]/', $rest, $m)) {
            return [$routePath, $this->resolveClass($m[1], $uses), $m[2]];
        }

        // Handler format 2: ClassName::class, 'method'  (positional args)
        if (preg_match('/^([\w\\\\]+)::class\s*,\s*[\'"](\w+)[\'"]/', $rest, $m)) {
            return [$routePath, $this->resolveClass($m[1], $uses), $m[2]];
        }

        // Handler format 3: 'ClassName@method'  or  "ClassName@method"
        if (preg_match('/^[\'"]([\\w\\\\]+)@(\w+)[\'"]/', $rest, $m)) {
            return [$routePath, $this->resolveClass($m[1], $uses), $m[2]];
        }

        // Handler format 4: 'ClassName/method'
        if (preg_match('/^[\'"]([\\w\\\\]+)\/(\w+)[\'"]/', $rest, $m)) {
            return [$routePath, $this->resolveClass($m[1], $uses), $m[2]];
        }

        // Handler format 5: closure (skip)
        if (str_starts_with(ltrim($rest), 'function') || str_starts_with(ltrim($rest), 'fn(')) {
            return null;
        }

        return null;
    }

    private function resolveClass(string $shortName, array $uses): string
    {
        return $uses[$shortName] ?? $shortName;
    }

    /**
     * Resolve path parameter types from the controller method signature.
     */
    private function resolvePathParamTypes(array $paramNames, string $controllerClass, string $method): array
    {
        $params = [];
        $classMeta = $this->reflector->parseClass($controllerClass);
        $methodMeta = $classMeta['methods'][$method] ?? null;

        foreach ($paramNames as $name) {
            $type = 'string';

            if ($methodMeta) {
                foreach ($methodMeta['params'] as $p) {
                    if ($p['name'] === $name) {
                        $type = match (true) {
                            str_contains($p['type'], 'int') => 'int',
                            default => 'string',
                        };
                        break;
                    }
                }
            }

            $params[] = ['name' => $name, 'type' => $type];
        }

        return $params;
    }

    /**
     * Find the FormRequest class (if any) accepted by a controller method.
     */
    private function findRequestClass(string $controllerClass, string $method): ?string
    {
        $classMeta = $this->reflector->parseClass($controllerClass);
        $methodMeta = $classMeta['methods'][$method] ?? null;

        if (!$methodMeta) {
            return null;
        }

        foreach ($methodMeta['params'] as $p) {
            $type = $p['type'];

            // Match FormRequest subclasses (anything ending in Request that isn't the base Request)
            if (
                str_ends_with($type, 'Request')
                && !str_ends_with($type, 'Http\\Request')
                && $type !== 'Request'
            ) {
                return $type;
            }
        }

        return null;
    }

    private function normaliseOpenApiPath(string $path): string
    {
        // Ensure leading slash
        $path = '/' . ltrim($path, '/');

        // Convert {param} — already OpenAPI format, nothing to change
        return $path;
    }

    private function deriveTag(string $controllerClass): string
    {
        // Extract short controller name without namespace
        $short = class_basename($controllerClass);

        // Remove "Controller" suffix
        $short = preg_replace('/Controller$/', '', $short);

        // CamelCase → "Camel Case" for readability
        $short = preg_replace('/([A-Z])/', ' $1', $short);
        $short = trim($short);

        return $short ?: $controllerClass;
    }

    private function deriveSummary(string $method, string $controllerClass): string
    {
        $entity = preg_replace('/Controller$/', '', class_basename($controllerClass));

        $map = [
            'index' => "List {$entity}s",
            'show' => "Get {$entity}",
            'store' => "Create {$entity}",
            'update' => "Update {$entity}",
            'destroy' => "Delete {$entity}",
            'duplicate' => "Duplicate {$entity}",
            'bulkDelete' => "Bulk delete {$entity}s",
            'bulkUpdate' => "Bulk update {$entity}s",
            'bulkUpdateStatus' => "Bulk update {$entity} statuses",
            'bulkApprove' => "Bulk approve {$entity}s",
            'bulkClone' => "Bulk clone {$entity}s",
            'bulkSchedule' => "Bulk schedule {$entity}s",
            'bulkToggleActive' => "Bulk toggle active {$entity}s",
            'bulkArchive' => "Bulk archive {$entity}s",
            'bulkAssign' => "Bulk assign {$entity}s",
            'merge' => "Merge {$entity}s",
            'clone' => "Clone {$entity}",
            'approve' => "Approve {$entity}",
            'reject' => "Reject {$entity}",
            'publish' => "Publish {$entity}",
            'unpublish' => "Unpublish {$entity}",
            'archive' => "Archive {$entity}",
            'unarchive' => "Unarchive {$entity}",
            'restore' => "Restore {$entity}",
            'active' => "List active {$entity}s",
            'getActive' => "List active {$entity}s",
            'search' => "Search {$entity}s",
            'checkDelete' => "Check if {$entity} can be deleted",
            'alternatives' => "Get alternative {$entity}s",
            'upload' => "Upload {$entity}",
            'statistics' => "Get {$entity} statistics",
            'popular' => "Get popular {$entity}s",
            'featured' => "Get featured {$entity}s",
            'recent' => "Get recent {$entity}s",
            'tree' => "Get {$entity} tree",
            'pause' => "Pause {$entity}",
            'resume' => "Resume {$entity}",
            'cancel' => "Cancel {$entity}",
            'payments' => "List payments",
            'plans' => "List subscription plans",
            'createPlan' => "Create subscription plan",
            'updatePlan' => "Update subscription plan",
            'deletePlan' => "Delete subscription plan",
        ];

        if (isset($map[$method])) {
            return $map[$method];
        }

        // Humanise the method name as fallback
        $human = preg_replace('/([A-Z])/', ' $1', $method);
        return ucfirst(trim($human)) . ' ' . $entity;
    }

    private function deriveDescription(string $httpMethod, string $controllerMethod): string
    {
        // Add a brief description hint for common destructive/bulk operations
        $descriptions = [
            'bulkDelete' => 'Permanently deletes multiple records. This action cannot be undone.',
            'destroy' => 'Permanently deletes the resource. This action cannot be undone.',
            'merge' => 'Merges the source record into the target, then deletes the source.',
            'bulkArchive' => 'Archives multiple records, hiding them from default listings.',
            'publish' => 'Publishes the resource, making it publicly visible.',
            'unpublish' => 'Unpublishes the resource, hiding it from public listings.',
        ];

        return $descriptions[$controllerMethod] ?? '';
    }

    private function deriveOperationId(string $httpMethod, string $path, string $controllerMethod): string
    {
        // Build a stable, unique operation ID from method + path
        $cleanPath = preg_replace('/[{}\/]/', '_', $path);
        $cleanPath = preg_replace('/_+/', '_', trim($cleanPath, '_'));

        return strtolower($httpMethod) . '_' . $cleanPath;
    }

    /**
     * Determine whether a set of middleware class short-names implies authentication.
     * Matches AuthenticateWithToken and any class whose short name contains 'Auth' or 'Token'.
     */
    private function isAuthMiddleware(array $middlewareClasses): bool
    {
        $authPatterns = ['AuthenticateWithToken', 'Authenticate', 'Auth'];

        foreach ($middlewareClasses as $class) {
            $short = class_basename($class);
            foreach ($authPatterns as $pattern) {
                if (str_contains($short, $pattern)) {
                    return true;
                }
            }
        }

        return false;
    }
}

function class_basename(string $class): string
{
    $parts = explode('\\', $class);
    return end($parts);
}