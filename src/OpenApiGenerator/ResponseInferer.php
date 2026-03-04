<?php

declare(strict_types=1);

namespace App\OpenApiGenerator;

/**
 * Infers HTTP response schemas from controller method return types,
 * docblocks, and naming conventions.
 */
class ResponseInferer
{
    /** Common status codes per HTTP method */
    private const METHOD_STATUS = [
        'GET' => 200,
        'POST' => 201,
        'PUT' => 200,
        'PATCH' => 200,
        'DELETE' => 204,
    ];
    /** Methods that typically return no body */
    private const NO_BODY_METHODS = ['DELETE'];
    private Reflector $reflector;

    public function __construct(Reflector $reflector)
    {
        $this->reflector = $reflector;
    }

    /**
     * Infer the response definition for a given route + controller method.
     *
     * @param array $route Route definition from RouteParser
     * @param array $methodInfo Method info from Reflector::parseFile()
     * @return array{
     *     status: int,
     *     description: string,
     *     schema: array|null,
     *     paginated: bool,
     *     collection: bool
     * }
     */
    public function infer(array $route, array $methodInfo): array
    {
        $httpMethod = strtoupper($route['method']);
        $actionName = $route['action'] ?? '';
        $status = self::METHOD_STATUS[$httpMethod] ?? 200;
        $returnType = $methodInfo['return_type'] ?? null;
        $docblock = $methodInfo['docblock'] ?? null;

        $paginated = $this->isPaginated($actionName, $docblock, $returnType);
        $collection = $paginated || $this->isCollection($actionName, $returnType);
        $resource = $this->guessResourceName($route);

        if (in_array($httpMethod, self::NO_BODY_METHODS, true)) {
            return [
                'status' => 204,
                'description' => 'No content',
                'schema' => null,
                'paginated' => false,
                'collection' => false,
            ];
        }

        $schema = $this->buildSchema($resource, $collection, $paginated, $returnType, $docblock);

        return [
            'status' => $status,
            'description' => $this->describeStatus($status, $collection, $resource),
            'schema' => $schema,
            'paginated' => $paginated,
            'collection' => $collection,
        ];
    }

    /**
     * Determine whether the response is paginated.
     */
    private function isPaginated(string $action, ?string $docblock, ?string $returnType): bool
    {
        if ($returnType !== null && str_contains(strtolower($returnType), 'paginator')) {
            return true;
        }
        if ($docblock !== null && str_contains(strtolower($docblock), 'paginat')) {
            return true;
        }
        return in_array($action, ['index', 'search', 'list', 'paginate'], true);
    }

    /**
     * Determine whether the response is a collection (array of items).
     */
    private function isCollection(string $action, ?string $returnType): bool
    {
        if ($returnType !== null) {
            $lower = strtolower($returnType);
            if (str_contains($lower, 'collection') || str_contains($lower, '[]')) {
                return true;
            }
        }
        return in_array($action, ['index', 'list', 'search', 'all'], true);
    }

    /**
     * Guess a human-readable resource name from the route definition.
     */
    private function guessResourceName(array $route): string
    {
        // Try to derive from controller name: UserController → User
        $controller = $route['controller'] ?? '';
        if ($controller !== '') {
            $short = preg_replace('/Controller$/', '', class_basename($controller));
            if ($short !== '') {
                return $short;
            }
        }

        // Fallback: derive from URI segments
        $uri = trim($route['uri'] ?? '', '/');
        $segments = array_filter(explode('/', $uri), fn($s) => !str_starts_with($s, '{'));
        $last = end($segments);
        return $last !== false ? ucfirst((string)$last) : 'Resource';
    }

    /**
     * Build an OpenAPI schema object for the response body.
     *
     * @return array<string, mixed>
     */
    private function buildSchema(
        string  $resource,
        bool    $collection,
        bool    $paginated,
        ?string $returnType,
        ?string $docblock
    ): array
    {
        $ref = ['$ref' => '#/components/schemas/' . $resource];

        if ($paginated) {
            return $this->paginatedWrapper($ref, $resource);
        }

        if ($collection) {
            return $this->collectionWrapper($ref, $resource);
        }

        // Check if return type hints a simple scalar
        if ($returnType !== null) {
            $scalarType = $this->mapScalarType($returnType);
            if ($scalarType !== null) {
                return ['type' => $scalarType];
            }
        }

        // Single resource — wrap in standard data envelope
        return $this->singleWrapper($ref, $resource);
    }

    /**
     * Standard paginated envelope with Laravel-style pagination meta.
     */
    private function paginatedWrapper(array $ref, string $resource): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'data' => [
                    'type' => 'array',
                    'items' => $ref,
                ],
                'meta' => [
                    'type' => 'object',
                    'properties' => [
                        'current_page' => ['type' => 'integer', 'example' => 1],
                        'last_page' => ['type' => 'integer', 'example' => 10],
                        'per_page' => ['type' => 'integer', 'example' => 15],
                        'total' => ['type' => 'integer', 'example' => 150],
                    ],
                ],
                'links' => [
                    'type' => 'object',
                    'properties' => [
                        'first' => ['type' => 'string', 'format' => 'uri', 'nullable' => true],
                        'last' => ['type' => 'string', 'format' => 'uri', 'nullable' => true],
                        'prev' => ['type' => 'string', 'format' => 'uri', 'nullable' => true],
                        'next' => ['type' => 'string', 'format' => 'uri', 'nullable' => true],
                    ],
                ],
            ],
        ];
    }

    /**
     * Standard collection envelope: { data: [ ...resources ] }
     */
    private function collectionWrapper(array $ref, string $resource): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'data' => [
                    'type' => 'array',
                    'items' => $ref,
                ],
            ],
        ];
    }

    /**
     * Map PHP return type hints to OpenAPI scalar types.
     */
    private function mapScalarType(string $type): ?string
    {
        return match (strtolower(trim($type, '?'))) {
            'int', 'integer' => 'integer',
            'float', 'double' => 'number',
            'bool', 'boolean' => 'boolean',
            'string' => 'string',
            'void' => null,
            default => null,
        };
    }

    /**
     * Standard single-resource envelope: { data: { ...resource } }
     */
    private function singleWrapper(array $ref, string $resource): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'data' => $ref,
            ],
        ];
    }

    /**
     * Build a human-readable description for the response status.
     */
    private function describeStatus(int $status, bool $collection, string $resource): string
    {
        return match ($status) {
            200 => $collection
                ? "A list of {$resource} resources"
                : "The {$resource} resource",
            201 => "The created {$resource} resource",
            204 => 'No content',
            default => "Response with status {$status}",
        };
    }
}