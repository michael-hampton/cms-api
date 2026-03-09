<?php

declare(strict_types=1);

namespace App\OpenApiGenerator;

/**
 * Parses FormRequest classes and extracts validation rules,
 * converting them into structured schema definitions for OpenAPI.
 */
class RequestAnalyzer
{
    private Reflector $reflector;

    public function __construct(Reflector $reflector)
    {
        $this->reflector = $reflector;
    }

    /**
     * Analyze a FormRequest class and return structured field definitions.
     *
     * @return array{fields: array<string, array>, required: string[], description: string|null}
     */
    public function analyze(string $fqcn): array
    {
        $file = $this->reflector->findFile($fqcn);
        if ($file === null) {
            return ['fields' => [], 'required' => [], 'description' => null];
        }

        $classInfo = $this->reflector->parseFile($file);
        $rulesMethod = $classInfo['methods']['rules'] ?? null;

        if ($rulesMethod === null) {
            return ['fields' => [], 'required' => [], 'description' => null];
        }

        $rawRules = $this->extractRulesFromMethod($file, $rulesMethod);
        return $this->parseRules($rawRules);
    }

    /**
     * Extract the raw rules array from the rules() method body by reading the source.
     * Uses the line number from Reflector (populated via token_get_all) so we always
     * read the correct method body.
     *
     * @return array<string, string|array> key => rule string or array of rules
     */
    private function extractRulesFromMethod(string $file, array $methodInfo): array
    {
        $source = file_get_contents($file);
        if ($source === false) {
            return [];
        }

        $lines = explode("\n", $source);

        // 'line' is the 1-based line of the `function` keyword; convert to 0-based index.
        $startLine = max(0, ($methodInfo['line'] ?? 1) - 1);

        $body = $this->extractMethodBody($lines, $startLine);
        if ($body === null) {
            return [];
        }

        return $this->parseReturnArray($body);
    }

    /**
     * Extract the method body (between braces) starting from the given line.
     * Tracks brace depth while ignoring braces inside string literals so that
     * route-style strings like '/users/{id}' don't corrupt the depth counter.
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

                // Toggle string tracking (skip escaped quotes)
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

    /**
     * Parse a PHP return [...] array from method body source.
     * Handles:
     *   - String pipe rules:  'field' => 'required|string|max:255'
     *   - Array rules:        'field' => ['required', 'string', 'max:255']
     *   - new Rule() objects: skipped gracefully, field still appears as 'string'
     *   - Nested wildcards:   'items.*.name' => 'required|string'
     *
     * @return array<string, string>
     */
    private function parseReturnArray(string $body): array
    {
        // Isolate the returned array body: return [ ... ]; or return array( ... );
        if (!preg_match('/return\s*(\[|array\s*\()(.*?)(\]|\))\s*;/s', $body, $match)) {
            return [];
        }

        $arrayBody = $match[2];
        $rules = [];

        // Split into individual key => value entries respecting nested bracket depth
        $entries = $this->splitArrayEntries($arrayBody);

        foreach ($entries as $entry) {
            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }

            // Extract key
            if (!preg_match('/^[\'"]([^\'"]+)[\'"]\s*=>/', $entry, $keyMatch)) {
                continue;
            }

            $key = $keyMatch[1];
            $valueRaw = trim(substr($entry, strlen($keyMatch[0])));

            if (str_starts_with($valueRaw, '[') || str_starts_with($valueRaw, 'array(')) {
                // Array of rules: ['required', 'string', 'max:255', new SomeRule()]
                preg_match_all('/[\'"]([^\'"]+)[\'"]/', $valueRaw, $ruleMatches);
                $rules[$key] = implode('|', $ruleMatches[1]);
            } else {
                // String rule or single token
                $rules[$key] = trim($valueRaw, '\'"');
            }
        }

        return $rules;
    }

    /**
     * Split a flat array body string into individual key => value entries,
     * respecting nested brackets/parens so that array values are not split mid-entry.
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
     * Convert raw validation rules into OpenAPI-compatible schema definitions.
     *
     * @param array<string, string> $rawRules
     * @return array{fields: array<string, array>, required: string[], description: string|null}
     */
    private function parseRules(array $rawRules): array
    {
        $fields = [];
        $required = [];
        // Track which fields have 'sometimes' — they are never required even if 'required' is present
        $sometimesFields = [];

        foreach ($rawRules as $fieldPath => $ruleString) {
            $normalizedPath = $this->normalizePath($fieldPath);
            $ruleList = array_map('trim', explode('|', $ruleString));

            if (in_array('sometimes', $ruleList, true)) {
                $sometimesFields[] = $normalizedPath;
            }

            $schema = $this->rulesToSchema($ruleList, $fieldPath);
            $fields[$normalizedPath] = $schema;

            $isRequired = in_array('required', $ruleList, true)
                || in_array('required_without_all', $ruleList, true);

            if ($isRequired) {
                $required[] = $normalizedPath;
            }
        }

        // Remove 'sometimes' fields from required — they are conditionally present
        $required = array_values(array_diff($required, $sometimesFields));

        return [
            'fields' => $fields,
            'required' => $required,
            'description' => null,
        ];
    }

    /**
     * Normalize dot-notation field paths for OpenAPI.
     * items.*.name → items[].name (array of objects)
     */
    private function normalizePath(string $path): string
    {
        return str_replace('.*', '[]', $path);
    }

    /**
     * Convert a list of validation rule strings into an OpenAPI schema fragment.
     * Handles all rules defined in the custom validation layer.
     *
     * @param string[] $rules
     * @param string $fieldName Original field name for description hints
     * @return array<string, mixed>
     */
    private function rulesToSchema(array $rules, string $fieldName = ''): array
    {
        $schema = [];
        $ruleMap = $this->indexRules($rules);

        // ── Type ─────────────────────────────────────────────────────────────
        $schema['type'] = $this->resolveType($ruleMap);

        // ── Format hints ─────────────────────────────────────────────────────
        if (isset($ruleMap['email'])) {
            $schema['format'] = 'email';
        } elseif (isset($ruleMap['url'])) {
            $schema['format'] = 'uri';
        } elseif (isset($ruleMap['date']) || isset($ruleMap['date_rule'])) {
            $schema['format'] = 'date';
        } elseif (isset($ruleMap['date_format'])) {
            $fmt = $ruleMap['date_format'][0] ?? '';
            $schema['format'] = ($fmt === 'Y-m-d H:i:s' || $fmt === 'Y-m-d\TH:i:s') ? 'date-time' : 'date';
        } elseif (isset($ruleMap['uuid'])) {
            $schema['format'] = 'uuid';
        }

        // ── String constraints ────────────────────────────────────────────────
        if ($schema['type'] === 'string') {
            if (isset($ruleMap['min'])) {
                $schema['minLength'] = (int)($ruleMap['min'][0] ?? 0);
            }
            if (isset($ruleMap['max'])) {
                $schema['maxLength'] = (int)($ruleMap['max'][0] ?? 0);
            }
            if (isset($ruleMap['min_length_rule'])) {
                $schema['minLength'] = (int)($ruleMap['min_length_rule'][0] ?? 0);
            }
            if (isset($ruleMap['max_length_rule'])) {
                $schema['maxLength'] = (int)($ruleMap['max_length_rule'][0] ?? 0);
            }
        }

        // ── Numeric constraints ───────────────────────────────────────────────
        if (in_array($schema['type'], ['integer', 'number'], true)) {
            if (isset($ruleMap['min'])) {
                $schema['minimum'] = (float)($ruleMap['min'][0] ?? 0);
            }
            if (isset($ruleMap['max'])) {
                $schema['maximum'] = (float)($ruleMap['max'][0] ?? 0);
            }
            if (isset($ruleMap['min_number'])) {
                $schema['minimum'] = (float)($ruleMap['min_number'][0] ?? 0);
            }
            if (isset($ruleMap['max_rule'])) {
                $schema['maximum'] = (float)($ruleMap['max_rule'][0] ?? 0);
            }
            if (isset($ruleMap['min_rule'])) {
                $schema['minimum'] = (float)($ruleMap['min_rule'][0] ?? 0);
            }
        }

        if (isset($ruleMap['between'])) {
            $schema['minimum'] = (float)($ruleMap['between'][0] ?? 0);
            $schema['maximum'] = (float)($ruleMap['between'][1] ?? 0);
        }

        // ── Array constraints ─────────────────────────────────────────────────
        if ($schema['type'] === 'array') {
            $schema['items'] = ['type' => 'string']; // refined by child rules in SchemaBuilder
            if (isset($ruleMap['min'])) {
                $schema['minItems'] = (int)($ruleMap['min'][0] ?? 0);
            }
            if (isset($ruleMap['max'])) {
                $schema['maxItems'] = (int)($ruleMap['max'][0] ?? 0);
            }
        }

        // ── Enum / in ─────────────────────────────────────────────────────────
        if (isset($ruleMap['in'])) {
            $schema['enum'] = $ruleMap['in'];
            // Coerce enum values to the resolved type
            if ($schema['type'] === 'integer') {
                $schema['enum'] = array_map('intval', $schema['enum']);
            }
        }

        // ── Regex pattern ─────────────────────────────────────────────────────
        if (isset($ruleMap['regex'])) {
            $pattern = $ruleMap['regex'][0] ?? '';
            // Strip PHP regex delimiters (e.g. /^[a-z]+$/ → ^[a-z]+$)
            if (strlen($pattern) >= 2) {
                $delimiter = $pattern[0];
                $lastDelim = strrpos($pattern, $delimiter);
                if ($lastDelim > 0) {
                    $pattern = substr($pattern, 1, $lastDelim - 1);
                }
            }
            $schema['pattern'] = $pattern;
        }

        // ── Nullable ──────────────────────────────────────────────────────────
        if (isset($ruleMap['nullable'])) {
            $schema['nullable'] = true;
        }

        // ── Confirmed field ───────────────────────────────────────────────────
        // The `confirmed` rule means the field must be submitted twice (field + field_confirmation).
        // We annotate this in the description so the API consumer knows.
        if (isset($ruleMap['confirmed'])) {
            $base = str_replace('[]', '', $fieldName);
            $schema['description'] = "Must match the `{$base}_confirmation` field.";
        }

        // ── after: date comparison ─────────────────────────────────────────────
        if (isset($ruleMap['after'])) {
            $compareField = $ruleMap['after'][0] ?? '';
            $note = $compareField ? "Must be a date after `{$compareField}`." : 'Must be a future date.';
            $schema['format'] = $schema['format'] ?? 'date';
            $schema['description'] = isset($schema['description'])
                ? $schema['description'] . ' ' . $note
                : $note;
        }

        // ── exists: FK reference ──────────────────────────────────────────────
        // We can infer the type is an ID (integer) when the column is 'id' or ends in '_id'.
        if (isset($ruleMap['exists'])) {
            $col = $ruleMap['exists'][1] ?? 'id';
            if ($col === 'id' || str_ends_with($col, '_id')) {
                // Only override if we haven't already resolved a more specific type
                if ($schema['type'] === 'string') {
                    $schema['type'] = 'integer';
                }
            }
            $table = $ruleMap['exists'][0] ?? '';
            if ($table) {
                $schema['description'] = isset($schema['description'])
                    ? $schema['description'] . " Must exist in `{$table}`."
                    : "Must exist in `{$table}`.";
            }
        }

        // ── required_if / required_with ────────────────────────────────────────
        // These are conditional required rules. We can't fully express them in OpenAPI 3.0
        // without oneOf/if-then-else, but we annotate the description for clarity.
        if (isset($ruleMap['required_if'])) {
            $otherField = $ruleMap['required_if'][0] ?? '';
            $otherValue = $ruleMap['required_if'][1] ?? '';
            $note = "Required when `{$otherField}` is `{$otherValue}`.";
            $schema['description'] = isset($schema['description'])
                ? $schema['description'] . ' ' . $note
                : $note;
        }

        if (isset($ruleMap['required_with'])) {
            $otherFields = implode('`, `', $ruleMap['required_with']);
            $note = "Required when any of `{$otherFields}` is present.";
            $schema['description'] = isset($schema['description'])
                ? $schema['description'] . ' ' . $note
                : $note;
        }

        // ── Example inference ──────────────────────────────────────────────────
        $schema = array_merge($schema, $this->guessExample($ruleMap, $schema, $fieldName));

        return $schema;
    }

    /**
     * Index rule list into ['ruleName' => [params...]] for O(1) lookups.
     *
     * @param string[] $rules
     * @return array<string, string[]>
     */
    private function indexRules(array $rules): array
    {
        $indexed = [];
        foreach ($rules as $rule) {
            $rule = trim($rule);
            if ($rule === '') {
                continue;
            }
            if (str_contains($rule, ':')) {
                [$name, $params] = explode(':', $rule, 2);
                $indexed[trim($name)] = explode(',', $params);
            } else {
                $indexed[$rule] = [];
            }
        }
        return $indexed;
    }

    /**
     * Determine the primary OpenAPI type from the indexed rule set.
     */
    private function resolveType(array $ruleMap): string
    {
        if (isset($ruleMap['integer']) || isset($ruleMap['digits']) || isset($ruleMap['digits_between'])) {
            return 'integer';
        }
        if (isset($ruleMap['numeric']) || isset($ruleMap['decimal']) || isset($ruleMap['min_number'])) {
            return 'number';
        }
        if (isset($ruleMap['boolean']) || isset($ruleMap['accepted']) || isset($ruleMap['declined'])) {
            return 'boolean';
        }
        if (isset($ruleMap['array'])) {
            return 'array';
        }
        if (isset($ruleMap['file']) || isset($ruleMap['image']) || isset($ruleMap['mimes'])) {
            // Files are represented as binary strings in OpenAPI
            return 'string';
        }
        // exists on an id column → likely integer, but we handle that in rulesToSchema
        return 'string';
    }

    /**
     * Add an example value based on rule hints, field type, and field name.
     */
    private function guessExample(array $ruleMap, array $schema, string $fieldName = ''): array
    {
        $extras = [];
        $name = strtolower(str_replace(['[]', '.', '*'], ['', '_', ''], $fieldName));

        // Rule-based examples (highest priority)
        if (isset($ruleMap['email'])) {
            $extras['example'] = 'user@example.com';
        } elseif (isset($ruleMap['url'])) {
            $extras['example'] = 'https://example.com';
        } elseif (isset($ruleMap['uuid'])) {
            $extras['example'] = '550e8400-e29b-41d4-a716-446655440000';
        } elseif (isset($ruleMap['date']) || isset($ruleMap['date_rule'])) {
            $extras['example'] = '2024-01-15';
        } elseif (isset($ruleMap['in']) && !empty($ruleMap['in'])) {
            // Pick the first allowed value as the example
            $extras['example'] = $ruleMap['in'][0];
        } elseif ($schema['type'] === 'boolean' || isset($ruleMap['accepted'])) {
            $extras['example'] = true;
        } elseif ($schema['type'] === 'integer') {
            // Heuristic: ID fields get a realistic example
            $extras['example'] = str_ends_with($name, '_id') || $name === 'id' ? 1 : 10;
        } elseif ($schema['type'] === 'number') {
            $extras['example'] = 99.99;
        } elseif ($schema['type'] === 'array') {
            $extras['example'] = [];
        } elseif ($schema['type'] === 'string') {
            // Field-name heuristics for common string fields
            $extras['example'] = match (true) {
                str_contains($name, 'name') => 'Example Name',
                str_contains($name, 'title') => 'Example Title',
                str_contains($name, 'description') => 'Example description text.',
                str_contains($name, 'phone') => '+44 7911 123456',
                str_contains($name, 'address') => '123 Main Street',
                str_contains($name, 'code') => 'ABC123',
                str_contains($name, 'color') => '#FFFFFF',
                str_contains($name, 'colour') => '#FFFFFF',
                str_contains($name, 'slug') => 'example-slug',
                str_contains($name, 'token') => 'abc123token',
                str_contains($name, 'password') => 'secret123',
                default => 'string',
            };
        }

        return $extras;
    }
}