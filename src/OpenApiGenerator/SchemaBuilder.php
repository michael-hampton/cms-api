<?php

declare(strict_types=1);

namespace App\OpenApiGenerator;

/**
 * Builds OpenAPI schema component objects from field definitions
 * produced by RequestAnalyzer and naming conventions.
 *
 * Handles nested dot-notation paths, array wildcards, and $ref resolution.
 */
class SchemaBuilder
{
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
            // Strip wildcard indicators from required list so they match flat property keys
            $cleanRequired = array_map(fn($f) => str_replace('[]', '', $f), $required);
            $cleanRequired = array_values(array_unique(array_filter($cleanRequired)));
            $schema['required'] = $cleanRequired;
        }

        return $schema;
    }

    /**
     * Convert a flat dot-notation field map into a nested OpenAPI properties structure.
     *
     * Examples:
     *   'address.street' => {...}  →  address: { type: object, properties: { street: {...} } }
     *   'tags[]'         => {...}  →  tags: { type: array, items: {...} }
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
     *
     * Node shapes:
     *   Leaf:   ['__leaf' => <schema>]
     *   Array:  ['__array' => true, '__children' => [...]]
     *   Object: ['__object' => true, '__children' => [...]]
     */
    private function setNested(array &$tree, string $path, array $schema): void
    {
        // Array wildcard: items[] or items[].product_name
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

        // Dot-notation object: address.street
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

        // Plain leaf
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

    /**
     * Build a component schema stub for a resource (response shape).
     * Returns a named $ref-able schema with common ID + timestamps fields.
     * The caller (OpenApiBuilder::registerResponseComponents) enriches this
     * via mergeIntoResource() when request fields are available.
     */
    public function buildResourceSchema(string $resourceName): array
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

    /**
     * Build query parameter schemas from FormRequest fields.
     * Strips the [] wildcard suffix from array field names before emitting them
     * as query parameter names (e.g. 'tags[]' becomes 'tags[]' in the query string,
     * which is valid, but path-param exclusion must compare clean names).
     *
     * @return array<int, array> OpenAPI Parameter Objects
     */
    public function buildQueryParams(array $fields, array $required, array $pathParams = []): array
    {
        $params = [];
        // Build a set of path param names for quick exclusion
        $pathParamNames = array_column($pathParams, 'name');

        foreach ($fields as $name => $schema) {
            // Strip [] for the purpose of path param exclusion comparison
            $cleanName = str_replace('[]', '', $name);

            if (in_array($cleanName, $pathParamNames, true)) {
                continue;
            }

            // For array-type query params, use name[] convention
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

    /**
     * Merge response field hints from FormRequest into a resource schema.
     * Existing explicit definitions (e.g. 'id', 'created_at') are never overwritten.
     */
    public function mergeIntoResource(array $resourceSchema, array $requestFields): array
    {
        $existing = $resourceSchema['properties'] ?? [];
        $additional = $this->buildProperties($requestFields);

        // Existing keys win — we merge additional UNDER existing so they don't overwrite
        $merged = array_merge($additional, $existing);

        return array_merge($resourceSchema, ['properties' => $merged]);
    }
}