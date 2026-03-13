<?php

declare(strict_types=1);

namespace App\OpenApiGenerator;

/**
 * ResourceInspector
 *
 * Locates a JsonResource class that corresponds to a given controller/route resource name,
 * then parses its toArray() method body to extract field names and infer OpenAPI types.
 *
 * Discovery order:
 *   1. App\Resources\{ResourceName}Resource
 *   2. App\Resources\{Tag}\{ResourceName}Resource   (namespaced variants)
 *   3. Falls back gracefully — caller gets null and uses the generic stub.
 *
 * Parsing strategy:
 *   - Reads the toArray() source body via Reflector (already token-parsed).
 *   - Extracts array keys and their value expressions.
 *   - Infers OpenAPI types from value expressions (getAttribute calls, casts, suffixes).
 *   - Handles whenLoaded() relations as nullable sub-objects.
 *   - Does NOT execute code — purely static source analysis.
 */
class ResourceInspector
{
    private Reflector $reflector;

    /** @var array<string, array|null> Cache: resourceName => parsed schema or null */
    private array $cache = [];

    public function __construct(Reflector $reflector)
    {
        $this->reflector = $reflector;
    }

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Attempt to locate and parse the Resource for a given resource name.
     *
     * @param string $resourceName e.g. "Campaign", "NewsletterIssue"
     * @param string $tag Optional controller tag for namespaced lookup
     * @return array|null           OpenAPI Schema Object, or null if no resource found
     */
    public function inspectResource(string $resourceName, string $tag = ''): ?array
    {
        $cacheKey = $resourceName . ':' . $tag;
        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        $candidates = $this->buildCandidateClassNames($resourceName, $tag);

        foreach ($candidates as $fqcn) {
            // Primary: ask Reflector (uses pre-built class map)
            $file = $this->reflector->findFile($fqcn);

            // Fallback: probe the filesystem directly from the FQCN path.
            // This handles cases where Reflector was constructed with a src path
            // that doesn't cover the Resources directory.
            if ($file === null) {
                $file = $this->probeFilesystem($fqcn);
            }

            if ($file === null) {
                continue;
            }

            $schema = $this->parseResourceFile($file, $fqcn);
            if ($schema !== null) {
                $this->cache[$cacheKey] = $schema;
                return $schema;
            }
        }

        $this->cache[$cacheKey] = null;
        return null;
    }

    /**
     * Build an ordered list of FQCN candidates to try.
     */
    private function buildCandidateClassNames(string $resourceName, string $tag): array
    {
        $candidates = [];

        // 1. Direct flat match: App\Resources\CampaignResource
        $candidates[] = 'App\\Resources\\' . $resourceName . 'Resource';

        // 2. Tag-namespaced variants.
        //    $tag arrives as a human-readable display string from RouteParser::deriveTag(),
        //    e.g. "Newsletter Issue" or "Cms" — strip spaces to get a namespace segment.
        if ($tag !== '') {
            $nsTag = str_replace(' ', '', $tag);  // "Newsletter Issue" → "NewsletterIssue"

            $candidates[] = 'App\\Resources\\' . $nsTag . '\\' . $resourceName . 'Resource';

            // Also try just the first word of the tag as a namespace ("Newsletter")
            $firstWord = explode(' ', $tag)[0];
            if ($firstWord !== $nsTag) {
                $candidates[] = 'App\\Resources\\' . $firstWord . '\\' . $resourceName . 'Resource';
            }
        }

        // 3. Laravel-conventional Http\Resources namespace
        $candidates[] = 'App\\Http\\Resources\\' . $resourceName . 'Resource';

        if ($tag !== '') {
            $nsTag = str_replace(' ', '', $tag);
            $candidates[] = 'App\\Http\\Resources\\' . $nsTag . '\\' . $resourceName . 'Resource';
        }

        // 4. Grid / list variants
        $candidates[] = 'App\\Resources\\' . $resourceName . 'GridResource';
        $candidates[] = 'App\\Resources\\' . $resourceName . 'ListResource';

        return array_unique($candidates);
    }

    // ── Discovery ─────────────────────────────────────────────────────────────

    /**
     * Attempt to locate a class file by converting its FQCN to a filesystem path.
     * Tries both app/ and src/ as common base directories.
     *
     * e.g. App\Resources\CampaignResource → {base}/app/Resources/CampaignResource.php
     */
    private function probeFilesystem(string $fqcn): ?string
    {
        // Strip the top-level namespace segment (App → app, or keep as-is)
        $parts = explode('\\', $fqcn);
        array_shift($parts); // drop "App"

        $relativePath = implode(DIRECTORY_SEPARATOR, $parts) . '.php';

        // Common base directories to probe
        $bases = [
            __DIR__ . '/../',
            __DIR__ . '/../',
        ];

        foreach ($bases as $base) {
            $candidate = $base . DIRECTORY_SEPARATOR . $relativePath;
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    // ── Parsing ───────────────────────────────────────────────────────────────

    /**
     * Parse a Resource file and extract its toArray() schema.
     */
    private function parseResourceFile(string $file, string $fqcn): ?array
    {
        $classInfo = $this->reflector->parseFile($file);
        $toArrayMethod = $classInfo['methods']['toArray'] ?? null;

        if ($toArrayMethod === null) {
            return null;
        }

        $source = file_get_contents($file);
        if ($source === false) {
            return null;
        }

        $lines = explode("\n", $source);
        $startLine = max(0, ($toArrayMethod['line'] ?? 1) - 1);
        $body = $this->extractMethodBody($lines, $startLine);

        if ($body === null) {
            return null;
        }

        $properties = $this->extractProperties($body);

        if (empty($properties)) {
            return null;
        }

        return [
            'type' => 'object',
            'properties' => $properties,
        ];
    }

    /**
     * Extract the method body between the first { and its matching }.
     * String-aware so that braces inside quotes don't corrupt the depth counter.
     */
    private function extractMethodBody(array $lines, int $startLine): ?string
    {
        $depth = 0;
        $started = false;
        $bodyLines = [];
        $inString = false;
        $stringChar = '';

        for ($i = $startLine; $i < count($lines); $i++) {
            $line = $lines[$i];
            $len = strlen($line);

            for ($j = 0; $j < $len; $j++) {
                $char = $line[$j];
                $prev = $j > 0 ? $line[$j - 1] : '';

                if (!$inString && ($char === '"' || $char === "'")) {
                    $inString = true;
                    $stringChar = $char;
                    continue;
                }
                if ($inString && $char === $stringChar && $prev !== '\\') {
                    $inString = false;
                    continue;
                }
                if ($inString) {
                    continue;
                }

                if ($char === '{') {
                    $depth++;
                    $started = true;
                } elseif ($char === '}') {
                    $depth--;
                    if ($started && $depth === 0) {
                        $bodyLines[] = $line;
                        return implode("\n", $bodyLines);
                    }
                }
            }

            if ($started) {
                $bodyLines[] = $line;
            }
        }

        return null;
    }

    // ── Property extraction ───────────────────────────────────────────────────

    /**
     * Extract return array entries from the toArray() body and convert them to
     * OpenAPI property definitions.
     *
     * Handles the common patterns:
     *   'field'  => $this->getAttribute('field')
     *   'field'  => $this->resource['field']
     *   'field'  => $this->field
     *   'field'  => $this->getAttribute('field')?->format('Y-m-d H:i:s')
     *   'field'  => $this->whenLoaded('relation', ...)
     *   'field'  => SomeResource::make(...)->toArray()
     *   'count'  => $this->getCount()
     *
     * @return array<string, array> OpenAPI property map
     */
    private function extractProperties(string $body): array
    {
        // Find the start of the returned array using depth-aware extraction
        // instead of a greedy regex — prevents nested arrays from corrupting the match.
        $arrayBody = $this->extractReturnArrayBody($body);
        if ($arrayBody === null) {
            return [];
        }

        $entries = $this->splitArrayEntries($arrayBody);
        $properties = [];

        foreach ($entries as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }

            if (!preg_match('/^[\'"]([^\'"]+)[\'"]\s*=>/', $entry, $keyMatch)) {
                continue;
            }

            $key = $keyMatch[1];
            $valueRaw = trim(substr($entry, strlen($keyMatch[0])));

            $properties[$key] = $this->inferPropertySchema($key, $valueRaw);
        }

        return $properties;
    }

    /**
     * Extract the content between the outer [ ] of the return statement,
     * using depth-aware scanning so nested arrays don't break the match.
     */
    private function extractReturnArrayBody(string $body): ?string
    {
        // Find 'return' followed by optional whitespace and then '['
        $returnPos = strpos($body, 'return');
        if ($returnPos === false) {
            // Try array() form
            $returnPos = strpos($body, 'return');
        }

        if ($returnPos === false) {
            return null;
        }

        // Scan forward from 'return' to find the opening bracket
        $len = strlen($body);
        $arrayStart = null;
        $useParens = false;

        for ($i = $returnPos + 6; $i < $len; $i++) {
            $ch = $body[$i];
            if ($ch === '[') {
                $arrayStart = $i;
                break;
            }
            if ($ch === '(' && substr($body, $returnPos + 6, $i - $returnPos - 6 + 1) === 'array(') {
                // return array(...)
                $arrayStart = $i;
                $useParens = true;
                break;
            }
            // Skip whitespace and 'array' keyword
            if (trim($ch) === '' || ctype_alpha($ch)) {
                continue;
            }
            break;
        }

        // Simpler: just find first [ or ( after 'return'
        if ($arrayStart === null) {
            for ($i = $returnPos + 6; $i < $len; $i++) {
                if ($body[$i] === '[') {
                    $arrayStart = $i;
                    break;
                }
                if ($body[$i] === '(' && strpos(substr($body, $returnPos, $i - $returnPos), 'array') !== false) {
                    $arrayStart = $i;
                    $useParens = true;
                    break;
                }
            }
        }

        if ($arrayStart === null) {
            return null;
        }

        $open = $useParens ? '(' : '[';
        $close = $useParens ? ')' : ']';
        $depth = 0;
        $inString = false;
        $stringChar = '';
        $result = '';

        for ($i = $arrayStart; $i < $len; $i++) {
            $ch = $body[$i];
            $prev = $i > 0 ? $body[$i - 1] : '';

            if (!$inString && ($ch === '"' || $ch === "'")) {
                $inString = true;
                $stringChar = $ch;
                $result .= $ch;
                continue;
            }
            if ($inString && $ch === $stringChar && $prev !== '\\') {
                $inString = false;
                $result .= $ch;
                continue;
            }
            if ($inString) {
                $result .= $ch;
                continue;
            }

            if ($ch === $open) {
                $depth++;
                if ($depth === 1) {
                    continue; // skip the outer bracket itself
                }
            } elseif ($ch === $close) {
                $depth--;
                if ($depth === 0) {
                    break; // done
                }
            }

            if ($depth >= 1) {
                $result .= $ch;
            }
        }

        return $result !== '' ? $result : null;
    }

    /**
     * Split a flat array body into individual key => value entries,
     * respecting nested bracket/paren depth.
     */
    private function splitArrayEntries(string $body): array
    {
        $entries = [];
        $depth = 0;
        $buffer = '';
        $len = strlen($body);
        $inString = false;
        $stringChar = '';

        for ($i = 0; $i < $len; $i++) {
            $char = $body[$i];
            $prev = $i > 0 ? $body[$i - 1] : '';

            if (!$inString && ($char === '"' || $char === "'")) {
                $inString = true;
                $stringChar = $char;
                $buffer .= $char;
                continue;
            }
            if ($inString && $char === $stringChar && $prev !== '\\') {
                $inString = false;
                $buffer .= $char;
                continue;
            }
            if ($inString) {
                $buffer .= $char;
                continue;
            }

            if ($char === '[' || $char === '(') {
                $depth++;
                $buffer .= $char;
                continue;
            }
            if ($char === ']' || $char === ')') {
                $depth--;
                $buffer .= $char;
                continue;
            }
            if ($char === ',' && $depth === 0) {
                $entries[] = $buffer;
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        if (trim($buffer) !== '') {
            $entries[] = $buffer;
        }

        return $entries;
    }

    /**
     * Infer an OpenAPI schema fragment for a single property based on its key name
     * and the raw value expression from the source.
     */
    private function inferPropertySchema(string $key, string $value): array
    {
        // whenLoaded — relationship, potentially array or object
        if (str_contains($value, 'whenLoaded')) {
            return $this->inferRelationSchema($key, $value);
        }

        // SomeResource::make(...)->toArray() or SomeResource::collection(...)
        if (preg_match('/(\w+Resource)::(?:make|collection)/', $value)) {
            return ['type' => 'object', 'description' => 'Nested resource object'];
        }

        // Null-safe date format: ->format('Y-m-d H:i:s') or ->format('Y-m-d')
        if (str_contains($value, "->format('Y-m-d H:i:s')") || str_contains($value, "->format('Y-m-d\TH:i:s')")) {
            return ['type' => 'string', 'format' => 'date-time', 'example' => '2024-01-15T10:00:00Z', 'nullable' => true];
        }
        if (str_contains($value, "->format('Y-m-d')")) {
            return ['type' => 'string', 'format' => 'date', 'example' => '2024-01-15', 'nullable' => true];
        }

        // collect(...)->map(...)->toArray() — array of objects
        if (str_contains($value, '->map(') || str_contains($value, '->toArray()')) {
            return ['type' => 'array', 'items' => ['type' => 'object']];
        }

        // Boolean literals or expressions
        if (preg_match('/^(true|false)$/', trim($value))) {
            return ['type' => 'boolean', 'example' => trim($value) === 'true'];
        }

        // Arithmetic / computed numeric (has operators)
        if (preg_match('/[\+\-\*\/]/', $value) && !str_contains($value, "'")) {
            return ['type' => 'number'];
        }

        // Type inference by key name suffix
        return $this->inferByKeyName($key, $value);
    }

    /**
     * Infer schema for a whenLoaded() relation field.
     */
    private function inferRelationSchema(string $key, string $value): array
    {
        // If the closure returns ->map(...) it's a collection
        if (str_contains($value, '->map(') || str_contains($value, '->toArray()')) {
            return [
                'type' => 'array',
                'items' => ['type' => 'object'],
                'nullable' => true,
                'description' => "Loaded relation: {$key}",
            ];
        }

        return [
            'type' => 'object',
            'nullable' => true,
            'description' => "Loaded relation: {$key}",
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Infer OpenAPI type from the field key name alone.
     * This is the last-resort heuristic when the value expression gives no clues.
     */
    private function inferByKeyName(string $key, string $value): array
    {
        $lower = strtolower($key);

        // IDs
        if ($key === 'id' || str_ends_with($lower, '_id')) {
            return ['type' => 'integer', 'example' => 1];
        }

        // Booleans
        if (str_starts_with($lower, 'is_') || str_starts_with($lower, 'has_') || str_starts_with($lower, 'can_')) {
            return ['type' => 'boolean', 'example' => true];
        }

        // Counts / numbers
        if (str_ends_with($lower, '_count') || str_ends_with($lower, '_quantity') || $lower === 'count') {
            return ['type' => 'integer', 'example' => 0];
        }

        // Money / prices
        if (str_contains($lower, 'price') || str_contains($lower, 'amount') || str_contains($lower, 'total')
            || str_contains($lower, 'cost') || str_contains($lower, 'fee') || str_contains($lower, 'discount')) {
            return ['type' => 'number', 'format' => 'float', 'example' => 0.00];
        }

        // Dates
        if (str_ends_with($lower, '_at') || str_ends_with($lower, '_date') || $lower === 'created_at' || $lower === 'updated_at') {
            return ['type' => 'string', 'format' => 'date-time', 'example' => '2024-01-15T10:00:00Z', 'nullable' => true];
        }

        // URLs
        if (str_ends_with($lower, '_url') || $lower === 'url' || $lower === 'avatar' || $lower === 'logo') {
            return ['type' => 'string', 'format' => 'uri', 'example' => 'https://example.com'];
        }

        // Email
        if (str_contains($lower, 'email')) {
            return ['type' => 'string', 'format' => 'email', 'example' => 'user@example.com'];
        }

        // Slug
        if ($lower === 'slug') {
            return ['type' => 'string', 'example' => 'example-slug'];
        }

        // Status / type — likely enum string
        if ($lower === 'status' || $lower === 'type' || str_ends_with($lower, '_status') || str_ends_with($lower, '_type')) {
            return ['type' => 'string', 'example' => 'active'];
        }

        // Sort order / priority
        if ($lower === 'sort_order' || $lower === 'priority' || $lower === 'position') {
            return ['type' => 'integer', 'example' => 0];
        }

        // JSON / array fields
        if (str_ends_with($lower, '_json') || str_ends_with($lower, '_data')
            || $lower === 'metadata' || $lower === 'settings' || str_ends_with($lower, '_config')) {
            // If the value expression uses ?-> it's nullable
            $nullable = str_contains($value, '?->') || str_contains($value, 'null');
            return ['type' => 'object', 'nullable' => $nullable];
        }

        // Generic arrays
        if (str_ends_with($lower, 's') && str_contains($value, '[]')) {
            return ['type' => 'array', 'items' => ['type' => 'string']];
        }

        // Nullable string (value has ?->)
        if (str_contains($value, '?->') || str_contains($value, '?? null') || str_contains($value, "?? ''")) {
            return ['type' => 'string', 'nullable' => true];
        }

        // Default: string
        return ['type' => 'string'];
    }
}