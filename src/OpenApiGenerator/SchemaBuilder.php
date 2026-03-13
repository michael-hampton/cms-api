<?php

declare(strict_types=1);

namespace App\OpenApiGenerator;

/**
 * Builds OpenAPI schema component objects from field definitions
 * produced by RequestAnalyzer, ResourceInspector, and naming conventions.
 *
 * Handles nested dot-notation paths, array wildcards, and $ref resolution.
 *
 * Resource-first strategy:
 *   When a JsonResource is available for the given resource name, its toArray()
 *   fields are used as the authoritative response schema instead of the generic
 *   id/created_at/updated_at stub.  Request fields are still merged in for
 *   write-operation routes where the resource schema has gaps.
 */
class SchemaBuilder
{
    private ?ResourceInspector $resourceInspector;

    public function __construct(?ResourceInspector $resourceInspector = null)
    {
        $this->resourceInspector = $resourceInspector;
    }

    // ── Request schema ────────────────────────────────────────────────────────

    /**
     * Build a request body schema from analyzed FormRequest fields.
     *
     * @param array{fields: array<string, array>, required: string[], description: string|null} $analyzed
     * @return array<string, mixed> OpenAPI Schema Object
     */
    public function buildRequestSchema(array $analyzed): array
    {
        $fields = $analyzed['fields'];
        $required = $analyzed['required'];

        if (empty($fields)) {
            return ['type' => 'object'];
        }

        $properties = $this->buildProperties($fields);
        $schema = ['type' => 'object', 'properties' => $properties];

        if (!empty($required)) {
            $cleanRequired = array_map(fn($f) => str_replace('[]', '', $f), $required);
            $cleanRequired = array_values(array_unique(array_filter($cleanRequired)));
            $schema['required'] = $cleanRequired;
        }

        return $schema;
    }

    // ── Resource schema ───────────────────────────────────────────────────────

    /**
     * Build a component schema for a resource.
     *
     * Priority:
     *   1. Parsed from the matching JsonResource class (ResourceInspector)
     *   2. Generic stub with id + timestamps
     *
     * @param string $resourceName e.g. "Campaign"
     * @param string $tag Controller tag for namespaced resource lookup
     */
    public function buildResourceSchema(string $resourceName, string $tag = ''): array
    {
        if ($this->resourceInspector !== null) {
            $inspected = $this->resourceInspector->inspectResource($resourceName, $tag);
            if ($inspected !== null) {
                return $inspected;
            }
        }

        return $this->genericResourceStub();
    }

    /**
     * Generic fallback stub when no Resource class is found.
     */
    private function genericResourceStub(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'example' => 1],
                'created_at' => ['type' => 'string', 'format' => 'date-time', 'example' => '2024-01-15T10:00:00Z'],
                'updated_at' => ['type' => 'string', 'format' => 'date-time', 'example' => '2024-01-15T10:00:00Z'],
            ],
        ];
    }

    // ── Query param schema ────────────────────────────────────────────────────

    /**
     * Build query parameter schemas from FormRequest fields.
     *
     * @return array<int, array> OpenAPI Parameter Objects
     */
    public function buildQueryParams(array $fields, array $required, array $pathParams = []): array
    {
        $params = [];
        $pathParamNames = array_column($pathParams, 'name');

        foreach ($fields as $name => $schema) {
            $cleanName = str_replace('[]', '', $name);

            if (in_array($cleanName, $pathParamNames, true)) {
                continue;
            }

            $paramName = ($schema['type'] ?? 'string') === 'array'
                ? $cleanName . '[]'
                : $cleanName;

            $params[] = [
                'name' => $paramName,
                'in' => 'query',
                'required' => in_array($name, $required, true) || in_array($cleanName, $required, true),
                'schema' => $schema,
            ];
        }

        return $params;
    }

    // ── Merge helpers ─────────────────────────────────────────────────────────

    /**
     * Merge response field hints from FormRequest into a resource schema.
     *
     * When the resource schema came from a JsonResource, existing keys from that
     * source always win — request fields only fill *gaps* in the response shape.
     * When the resource schema is the generic stub, request fields are also merged
     * in so the component reflects the actual write shape.
     */
    public function mergeIntoResource(array $resourceSchema, array $requestFields): array
    {
        $existing = $resourceSchema['properties'] ?? [];
        $additional = $this->buildProperties($requestFields);

        // Existing keys win
        $merged = array_merge($additional, $existing);

        return array_merge($resourceSchema, ['properties' => $merged]);
    }

    // ── Nested property building ───────────────────────────────────────────────

    /**
     * Convert a flat dot-notation field map into a nested OpenAPI properties structure.
     *
     * @param array<string, array> $fields
     * @return array<string, mixed>
     */
    private function buildProperties(array $fields): array
    {
        $tree = [];

        foreach ($fields as $path => $schema) {
            $this->setNested($tree, $path, $schema);
        }

        return $this->renderTree($tree);
    }

    /**
     * Set a value in a nested tree using dot/bracket path notation.
     */
    private function setNested(array &$tree, string $path, array $schema): void
    {
        if (str_contains($path, '[]')) {
            [$before, $after] = explode('[]', $path, 2);
            $key = trim($before, '.');
            $sub = ltrim($after, '.');

            if (!array_key_exists($key, $tree) || !isset($tree[$key]['__array'])) {
                $tree[$key] = ['__array' => true, '__children' => []];
            }

            if ($sub !== '') {
                $ref = &$tree[$key]['__children'];
                $this->setNested($ref, $sub, $schema);
                unset($ref);
            } else {
                $tree[$key]['__leaf'] = $schema;
            }

            return;
        }

        if (str_contains($path, '.')) {
            [$key, $rest] = explode('.', $path, 2);

            if (!array_key_exists($key, $tree) || !isset($tree[$key]['__object'])) {
                $tree[$key] = ['__object' => true, '__children' => []];
            }

            $ref = &$tree[$key]['__children'];
            $this->setNested($ref, $rest, $schema);
            unset($ref);
            return;
        }

        $tree[$path] = ['__leaf' => $schema];
    }

    /**
     * Render the internal node tree into an OpenAPI properties map.
     *
     * @return array<string, mixed>
     */
    private function renderTree(array $tree): array
    {
        $properties = [];

        foreach ($tree as $key => $node) {
            if (isset($node['__array'])) {
                $children = $node['__children'] ?? [];
                if (!empty($children)) {
                    $items = ['type' => 'object', 'properties' => $this->renderTree($children)];
                } elseif (isset($node['__leaf'])) {
                    $items = $node['__leaf'];
                } else {
                    $items = ['type' => 'string'];
                }
                $properties[$key] = ['type' => 'array', 'items' => $items];
                continue;
            }

            if (isset($node['__object'])) {
                $properties[$key] = [
                    'type' => 'object',
                    'properties' => $this->renderTree($node['__children'] ?? []),
                ];
                continue;
            }

            if (isset($node['__leaf'])) {
                $properties[$key] = $node['__leaf'];
            }
        }

        return $properties;
    }
}