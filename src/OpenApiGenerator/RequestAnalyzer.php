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
        $startLine = ($methodInfo['line'] ?? 1) - 1;

        // Find the method body by scanning for the opening brace
        $body = $this->extractMethodBody($lines, $startLine);
        if ($body === null) {
            return [];
        }

        return $this->parseReturnArray($body);
    }

    /**
     * Extract the method body (between braces) starting from the given line.
     */
    private function extractMethodBody(array $lines, int $startLine): ?string
    {
        $depth = 0;
        $started = false;
        $bodyLines = [];

        for ($i = $startLine; $i < count($lines); $i++) {
            $line = $lines[$i];

            foreach (str_split($line) as $char) {
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
     *
     * @return array<string, string>
     */
    private function parseReturnArray(string $body): array
    {
        // Extract the array returned: return [ ... ]; or return array( ... );
        if (!preg_match('/return\s*(\[|array\s*\()(.*?)(\]|\))\s*;/s', $body, $match)) {
            return [];
        }

        $arrayBody = $match[2];
        $rules = [];

        // Match key => value pairs. Values can be strings or arrays.
        // Pattern: 'field.*.name' => 'required|string|max:255'
        //      or: 'field' => ['required', 'string', Rule::exists(...)]
        $pattern = '/[\'"]([^\'"]+)[\'"]\s*=>\s*((?:\[.*?\]|\'[^\']*\'|"[^"]*"|\S+))/s';

        preg_match_all($pattern, $arrayBody, $matches, PREG_SET_ORDER);

        foreach ($matches as $m) {
            $key = $m[1];
            $value = trim($m[2]);

            if (str_starts_with($value, '[')) {
                // Array of rules — extract string values
                preg_match_all('/[\'"]([^\'"]+)[\'"]/', $value, $ruleMatches);
                $rules[$key] = implode('|', $ruleMatches[1]);
            } else {
                $rules[$key] = trim($value, '\'"');
            }
        }

        return $rules;
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

        foreach ($rawRules as $fieldPath => $ruleString) {
            // Handle wildcard array notation: items.*.name → items[].name
            $normalizedPath = $this->normalizePath($fieldPath);

            $ruleList = array_map('trim', explode('|', $ruleString));
            $schema = $this->rulesToSchema($ruleList);

            if (in_array('required', $ruleList, true) || in_array('required_without_all', $ruleList, true)) {
                $required[] = $normalizedPath;
            }

            $fields[$normalizedPath] = $schema;
        }

        return [
            'fields' => $fields,
            'required' => $required,
            'description' => null,
        ];
    }

    /**
     * Normalize dot-notation field paths.
     * items.*.name → items[items][name] style is simplified to just items.name for flat schemas.
     */
    private function normalizePath(string $path): string
    {
        // For OpenAPI, we'll represent array fields with [] suffix
        return str_replace('.*', '[]', $path);
    }

    /**
     * Convert a list of Laravel validation rule strings into an OpenAPI schema fragment.
     *
     * @param string[] $rules
     * @return array<string, mixed>
     */
    private function rulesToSchema(array $rules): array
    {
        $schema = [];
        $ruleMap = [];

        foreach ($rules as $rule) {
            if (str_contains($rule, ':')) {
                [$name, $params] = explode(':', $rule, 2);
                $ruleMap[$name] = explode(',', $params);
            } else {
                $ruleMap[$rule] = [];
            }
        }

        // Type resolution
        $schema['type'] = $this->resolveType($ruleMap);

        // Format hints
        if (isset($ruleMap['email'])) {
            $schema['format'] = 'email';
        } elseif (isset($ruleMap['url'])) {
            $schema['format'] = 'uri';
        } elseif (isset($ruleMap['date'])) {
            $schema['format'] = 'date';
        } elseif (isset($ruleMap['date_format'])) {
            $fmt = $ruleMap['date_format'][0] ?? '';
            $schema['format'] = $fmt === 'Y-m-d H:i:s' ? 'date-time' : 'date';
        } elseif (isset($ruleMap['uuid'])) {
            $schema['format'] = 'uuid';
        }

        // String constraints
        if (isset($ruleMap['min']) && $schema['type'] === 'string') {
            $schema['minLength'] = (int)($ruleMap['min'][0] ?? 0);
        }
        if (isset($ruleMap['max']) && $schema['type'] === 'string') {
            $schema['maxLength'] = (int)($ruleMap['max'][0] ?? 0);
        }

        // Numeric constraints
        if (isset($ruleMap['min']) && in_array($schema['type'], ['integer', 'number'], true)) {
            $schema['minimum'] = (int)($ruleMap['min'][0] ?? 0);
        }
        if (isset($ruleMap['max']) && in_array($schema['type'], ['integer', 'number'], true)) {
            $schema['maximum'] = (int)($ruleMap['max'][0] ?? 0);
        }
        if (isset($ruleMap['between'])) {
            $schema['minimum'] = (int)($ruleMap['between'][0] ?? 0);
            $schema['maximum'] = (int)($ruleMap['between'][1] ?? 0);
        }

        // Enum values
        if (isset($ruleMap['in'])) {
            $schema['enum'] = $ruleMap['in'];
        }

        // Array type
        if (isset($ruleMap['array']) || $schema['type'] === 'array') {
            $schema['type'] = 'array';
            $schema['items'] = ['type' => 'string']; // Default; refined by child rules
        }

        // Nullable
        if (isset($ruleMap['nullable'])) {
            $schema['nullable'] = true;
        }

        // Example hints from common field names (best-effort)
        $schema = array_merge($schema, $this->guessExample($ruleMap, $schema));

        return $schema;
    }

    /**
     * Determine the primary OpenAPI type from the rule set.
     */
    private function resolveType(array $ruleMap): string
    {
        if (isset($ruleMap['integer']) || isset($ruleMap['digits']) || isset($ruleMap['digits_between'])) {
            return 'integer';
        }
        if (isset($ruleMap['numeric']) || isset($ruleMap['decimal'])) {
            return 'number';
        }
        if (isset($ruleMap['boolean']) || isset($ruleMap['accepted']) || isset($ruleMap['declined'])) {
            return 'boolean';
        }
        if (isset($ruleMap['array'])) {
            return 'array';
        }
        if (isset($ruleMap['file']) || isset($ruleMap['image']) || isset($ruleMap['mimes'])) {
            return 'string'; // Files represented as binary string in OpenAPI
        }
        return 'string';
    }

    /**
     * Add an example value based on rule hints and type.
     */
    private function guessExample(array $ruleMap, array $schema): array
    {
        $extras = [];

        if (isset($ruleMap['email'])) {
            $extras['example'] = 'user@example.com';
        } elseif (isset($ruleMap['url'])) {
            $extras['example'] = 'https://example.com';
        } elseif (isset($ruleMap['uuid'])) {
            $extras['example'] = '550e8400-e29b-41d4-a716-446655440000';
        } elseif (isset($ruleMap['date'])) {
            $extras['example'] = '2024-01-15';
        } elseif ($schema['type'] === 'integer') {
            $extras['example'] = 1;
        } elseif ($schema['type'] === 'boolean') {
            $extras['example'] = true;
        }

        return $extras;
    }
}