<?php

declare(strict_types=1);

namespace App\OpenApiGenerator;

/**
 * Assembles the final OpenAPI 3.0.0 specification document
 * from routes, request schemas, response schemas, and component definitions.
 */
class OpenApiBuilder
{
    private Reflector $reflector;
    private RequestAnalyzer $requestAnalyzer;
    private ResponseInferer $responseInferer;
    private SchemaBuilder $schemaBuilder;

    private array $components = ['schemas' => [], 'parameters' => []];
    private array $paths = [];
    private array $tags = [];

    public function __construct(
        Reflector       $reflector,
        RequestAnalyzer $requestAnalyzer,
        ResponseInferer $responseInferer,
        SchemaBuilder   $schemaBuilder
    )
    {
        $this->reflector = $reflector;
        $this->requestAnalyzer = $requestAnalyzer;
        $this->responseInferer = $responseInferer;
        $this->schemaBuilder = $schemaBuilder;
    }

    /**
     * Build the complete OpenAPI spec from a list of routes.
     *
     * @param array[] $routes From RouteParser::parse()
     * @param array $config title, version, description, servers
     * @return array Complete OpenAPI 3.0 document
     */
    public function build(array $routes, array $config): array
    {
        foreach ($routes as $route) {
            $this->processRoute($route);
        }

        return [
            'openapi' => '3.0.0',
            'info' => [
                'title' => $config['title'] ?? 'API Documentation',
                'version' => $config['version'] ?? '1.0.0',
                'description' => $config['description'] ?? '',
            ],
            'servers' => $this->buildServers($config),
            'tags' => array_values($this->tags),
            'paths' => $this->paths,
            'components' => $this->buildComponents(),
        ];
    }

    // ── Route processing ───────────────────────────────────────────────────────

    private function processRoute(array $route): void
    {
        $rawUri = $route['path'] ?? $route['uri'] ?? null;
        if ($rawUri === null) {
            return;
        }

        // Normalise key names from RouteParser (camelCase) to what we use internally
        $route['action'] = $route['action'] ?? $route['controllerMethod'] ?? '';
        $route['controller'] = $route['controller'] ?? $route['controllerClass'] ?? '';
        $route['path_params'] = $route['path_params'] ?? $route['pathParams'] ?? [];
        $route['form_request'] = $route['form_request'] ?? $route['requestClass'] ?? null;
        $route['authenticated'] = $route['authenticated'] ?? false;

        $uri = $this->normalizeUri($rawUri);
        $httpMethod = strtolower($route['method']);

        if (!isset($this->paths[$uri])) {
            $this->paths[$uri] = [];
        }

        $this->paths[$uri][$httpMethod] = $this->buildOperation($route);
    }

    private function buildOperation(array $route): array
    {
        $tag = $route['tag'] ?? 'General';
        $httpMethod = strtoupper($route['method']); // resolved here, passed to helpers

        $this->registerTag($tag);

        $methodInfo = $this->resolveMethodInfo($route);
        $parameters = $this->buildParameters($route, $methodInfo, $httpMethod);

        // FIX: $httpMethod was previously an undefined variable in this scope.
        $requestBody = $this->buildRequestBody($route, $httpMethod);
        $response = $this->responseInferer->infer($route, $methodInfo);

        $this->registerResponseComponents($route, $response);

        $operation = [
            'tags' => [$tag],
            'summary' => $route['summary'] ?? $this->generateSummary($route),
            'operationId' => $route['operation_id'] ?? $this->generateOperationId($route),
            'parameters' => $parameters,
        ];

        if ($route['authenticated'] ?? false) {
            $operation['security'] = [['bearerAuth' => []]];
        }

        if (!empty($route['description'])) {
            $operation['description'] = $route['description'];
        }

        if ($requestBody !== null) {
            $operation['requestBody'] = $requestBody;
        }

        $operation['responses'] = $this->buildResponses($response, $route, $httpMethod);

        return $operation;
    }

    // ── Parameters ─────────────────────────────────────────────────────────────

    /**
     * @return array<int, array> OpenAPI Parameter Objects
     */
    private function buildParameters(array $route, array $methodInfo, string $httpMethod): array
    {
        $params = [];

        foreach ($route['path_params'] ?? [] as $param) {
            $params[] = [
                'name' => $param['name'],
                'in' => 'path',
                'required' => true,
                'schema' => ['type' => $param['type'] ?? 'string'],
            ];
        }

        // Query params from FormRequest for read-only methods
        if (in_array($httpMethod, ['GET', 'DELETE'], true) && !empty($route['form_request'])) {
            $analyzed = $this->requestAnalyzer->analyze($route['form_request']);
            $queryParams = $this->schemaBuilder->buildQueryParams(
                $analyzed['fields'],
                $analyzed['required'],
                $route['path_params'] ?? []
            );
            $params = array_merge($params, $queryParams);
        }

        if ($this->isPaginatedAction($route)) {
            $params = array_merge($params, $this->paginationParameters());
        }

        // All index/search methods support the SearchEngine filter/sort/search params
        if ($this->isSearchEngineAction($route)) {
            $params = array_merge($params, $this->searchEngineParameters());
        }

        // Deduplicate by name — later entries (more specific) win
        $seen = [];
        $deduped = [];
        foreach (array_reverse($params) as $p) {
            if (!isset($seen[$p['name']])) {
                $seen[$p['name']] = true;
                $deduped[] = $p;
            }
        }

        return array_reverse($deduped);
    }

    private function isPaginatedAction(array $route): bool
    {
        return in_array($route['controllerMethod'] ?? $route['action'] ?? '', ['index', 'list', 'search', 'paginate'], true);
    }

    /**
     * Any GET index-style method is assumed to be backed by SearchEngine.
     * This covers: index, list, all, search, and any other listing action on a GET route.
     */
    private function isSearchEngineAction(array $route): bool
    {
        $method = $route['controllerMethod'] ?? $route['action'] ?? '';
        return in_array($method, ['index', 'list', 'all', 'search', 'paginate', 'active', 'featured', 'recent', 'popular'], true)
            && ($route['method'] ?? '') === 'GET';
    }

    private function paginationParameters(): array
    {
        return [
            [
                'name' => 'page',
                'in' => 'query',
                'required' => false,
                'schema' => ['type' => 'integer', 'minimum' => 1, 'example' => 1],
            ],
            [
                'name' => 'per_page',
                'in' => 'query',
                'required' => false,
                'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000, 'example' => 20],
                'description' => 'Number of results per page (max 1000)',
            ],
        ];
    }

    /**
     * Standard SearchEngine query parameters emitted for all index-style endpoints.
     * These mirror SearchCriteria / SearchCriteriaParser conventions.
     */
    private function searchEngineParameters(): array
    {
        return [
            [
                'name' => 'q',
                'in' => 'query',
                'required' => false,
                'description' => 'Full-text search query applied across searchable columns',
                'schema' => ['type' => 'string', 'example' => 'keyword'],
            ],
            [
                'name' => 'sort_by',
                'in' => 'query',
                'required' => false,
                'description' => 'Field to sort results by. Available values depend on the resource.',
                'schema' => ['type' => 'string', 'example' => 'created_at'],
            ],
            [
                'name' => 'sort_order',
                'in' => 'query',
                'required' => false,
                'description' => 'Sort direction',
                'schema' => ['type' => 'string', 'enum' => ['asc', 'desc'], 'default' => 'asc'],
            ],
            [
                'name' => 'status',
                'in' => 'query',
                'required' => false,
                'description' => 'Filter by status',
                'schema' => ['type' => 'string'],
            ],
            [
                'name' => 'site_id',
                'in' => 'query',
                'required' => false,
                'description' => 'Scope results to a specific site',
                'schema' => ['type' => 'integer'],
            ],
        ];
    }

    // ── Request body ───────────────────────────────────────────────────────────

    private function buildRequestBody(array $route, string $httpMethod): ?array
    {
        if (!in_array($httpMethod, ['POST', 'PUT', 'PATCH'], true)) {
            return null;
        }

        if (empty($route['form_request'])) {
            return [
                'required' => false,
                'content' => [
                    'application/json' => ['schema' => ['type' => 'object']],
                ],
            ];
        }

        $analyzed = $this->requestAnalyzer->analyze($route['form_request']);
        $schema = $this->schemaBuilder->buildRequestSchema($analyzed);
        $shortName = class_basename($route['form_request']);
        $componentKey = $shortName . 'Body';

        $this->components['schemas'][$componentKey] = $schema;

        return [
            'required' => true,
            'content' => [
                'application/json' => [
                    'schema' => ['$ref' => '#/components/schemas/' . $componentKey],
                ],
            ],
        ];
    }

    // ── Responses ──────────────────────────────────────────────────────────────

    private function buildResponses(array $response, array $route, string $httpMethod): array
    {
        $responses = [];

        $successStatus = (string)$response['status'];
        $successEntry = ['description' => $response['description']];

        if ($response['schema'] !== null) {
            $successEntry['content'] = [
                'application/json' => ['schema' => $response['schema']],
            ];
        }

        $responses[$successStatus] = $successEntry;

        // 422 for all write operations that carry a FormRequest
        if (
            in_array($httpMethod, ['POST', 'PUT', 'PATCH'], true)
            || ($httpMethod === 'DELETE' && !empty($route['form_request']))
        ) {
            $responses['422'] = [
                'description' => 'Validation error',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ValidationError'],
                    ],
                ],
            ];
        }

        // 404 for routes with ID-style path params
        $hasIdParam = !empty(array_filter(
            $route['path_params'] ?? [],
            fn($p) => in_array($p['name'], ['id', 'uuid'], true) || str_ends_with($p['name'], '_id')
        ));

        if ($hasIdParam) {
            $responses['404'] = ['description' => 'Resource not found'];
        }

        // 401 for all routes (standard)
        $responses['401'] = ['description' => 'Unauthenticated'];

        // 403 for authenticated routes — authorization is distinct from authentication
        if ($route['authenticated'] ?? false) {
            $responses['403'] = ['description' => 'Forbidden — insufficient permissions'];
        }

        return $responses;
    }

    // ── Components ─────────────────────────────────────────────────────────────

    /**
     * Register a resource schema component, enriched with request fields where available.
     * For store/update operations we merge the request body fields into the response schema
     * so that the component reflects the actual shape of the resource.
     */
    private function registerResponseComponents(array $route, array $response): void
    {
        $resource = $this->guessResourceName($route);

        if (!isset($this->components['schemas'][$resource])) {
            $baseSchema = $this->schemaBuilder->buildResourceSchema($resource);

            // For write operations, enrich the resource schema with request fields
            $httpMethod = strtoupper($route['method']);
            if (
                in_array($httpMethod, ['POST', 'PUT', 'PATCH'], true)
                && !empty($route['form_request'])
            ) {
                $analyzed = $this->requestAnalyzer->analyze($route['form_request']);
                if (!empty($analyzed['fields'])) {
                    $baseSchema = $this->schemaBuilder->mergeIntoResource($baseSchema, $analyzed['fields']);
                }
            }

            $this->components['schemas'][$resource] = $baseSchema;
        }
    }

    private function buildComponents(): array
    {
        $this->components['schemas']['ValidationError'] = $this->validationErrorSchema();
        $this->components['schemas']['ErrorResponse'] = $this->errorResponseSchema();

        ksort($this->components['schemas']);

        $this->components['securitySchemes']['bearerAuth'] = [
            'type' => 'http',
            'scheme' => 'bearer',
            'bearerFormat' => 'Token',
            'description' => 'Bearer token issued by the authentication endpoint.',
        ];

        return $this->components;
    }

    private function validationErrorSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'message' => ['type' => 'string', 'example' => 'The given data was invalid.'],
                'errors' => [
                    'type' => 'object',
                    'additionalProperties' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                    ],
                    'example' => ['field' => ['The field is required.']],
                ],
            ],
        ];
    }

    private function errorResponseSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'message' => ['type' => 'string'],
            ],
        ];
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function resolveMethodInfo(array $route): array
    {
        $controller = $route['controller'] ?? null;
        $action = $route['action'] ?? null;

        if ($controller === null || $action === null) {
            return [];
        }

        $file = $this->reflector->findFile($controller);
        if ($file === null) {
            return [];
        }

        $classInfo = $this->reflector->parseFile($file);
        return $classInfo['methods'][$action] ?? [];
    }

    private function registerTag(string $tag): void
    {
        if (!isset($this->tags[$tag])) {
            $this->tags[$tag] = ['name' => $tag];
        }
    }

    private function normalizeUri(string $uri): string
    {
        $uri = '/' . ltrim(rtrim($uri, '/'), '/');
        return $uri === '/' ? '/' : $uri;
    }

    private function generateSummary(array $route): string
    {
        $action = $route['action'] ?? '';
        $resource = $this->guessResourceName($route);

        return match ($action) {
            'index' => "List {$resource} resources",
            'show' => "Get a {$resource}",
            'store' => "Create a {$resource}",
            'update' => "Update a {$resource}",
            'destroy' => "Delete a {$resource}",
            'create' => "Show create form for {$resource}",
            'edit' => "Show edit form for {$resource}",
            default => ucfirst(str_replace('_', ' ', $action)) . " {$resource}",
        };
    }

    private function generateOperationId(array $route): string
    {
        $method = strtolower($route['method']);
        $raw = $route['path'] ?? $route['uri'] ?? '';
        $uri = preg_replace('/[^a-zA-Z0-9]/', '_', trim($raw, '/'));
        $uri = preg_replace('/_+/', '_', $uri);
        return $method . '_' . trim($uri, '_');
    }

    private function guessResourceName(array $route): string
    {
        $controller = $route['controller'] ?? $route['controllerClass'] ?? '';
        if ($controller !== '') {
            $short = preg_replace('/Controller$/', '', class_basename($controller));
            if ($short !== '') {
                return $short;
            }
        }

        $raw = $route['path'] ?? $route['uri'] ?? '';
        $uri = trim($raw, '/');
        $segments = array_filter(explode('/', $uri), fn($s) => !str_starts_with($s, '{'));
        $last = end($segments);
        return $last !== false ? ucfirst((string)$last) : 'Resource';
    }

    private function buildServers(array $config): array
    {
        return [
            ['url' => $config['base_url'] ?? 'http://localhost', 'description' => 'Default server'],
        ];
    }
}