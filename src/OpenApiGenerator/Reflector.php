<?php

declare(strict_types=1);

namespace App\OpenApiGenerator;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Reflector
 *
 * Reads PHP source files without autoloading/executing them.
 * Uses regex + token-aware parsing to extract:
 *   - Namespace, class name, method signatures
 *   - Use statements (imports)
 *   - Doc comments
 *   - Return type hints
 *   - Parameter types
 */
class Reflector
{
    private string $srcPath;

    /** @var array<string, array> file-path => parsed metadata cache */
    private array $cache = [];

    /** @var array<string, string> FQCN => file path */
    private array $classMap = [];

    public function __construct(string $srcPath)
    {
        $this->srcPath = rtrim($srcPath, '/');
        $this->buildClassMap();
    }

    // ── Public API ────────────────────────────────────────────────────────────

    private function buildClassMap(): void
    {
        foreach ($this->allFiles() as $file) {
            $src = file_get_contents($file);

            $ns = '';
            $class = '';

            if (preg_match('/^namespace\s+([\w\\\\]+)\s*;/m', $src, $m)) {
                $ns = $m[1];
            }
            if (preg_match('/(?:class|interface|trait)\s+(\w+)/m', $src, $m)) {
                $class = $m[1];
            }

            if ($class) {
                $fqcn = $ns ? "{$ns}\\{$class}" : $class;
                $this->classMap[$fqcn] = $file;
            }
        }
    }

    /**
     * Return all PHP files under the src path.
     */
    public function allFiles(): array
    {
        $files = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->srcPath));
        foreach ($it as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        return $files;
    }

    /**
     * Parse a class by FQCN or short name.
     */
    public function parseClass(string $className): array
    {
        $file = $this->findFile($className);
        if (!$file) {
            return [];
        }
        return $this->parseFile($file);
    }

    /**
     * Find the file containing a given short or fully-qualified class name.
     */
    public function findFile(string $className): ?string
    {
        // Direct FQCN hit
        if (isset($this->classMap[$className])) {
            return $this->classMap[$className];
        }

        // Try matching just the short name
        foreach ($this->classMap as $fqcn => $path) {
            if (str_ends_with($fqcn, '\\' . ltrim($className, '\\'))) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Parse a PHP file and return metadata:
     * [
     *   'namespace' => string,
     *   'class'     => string,
     *   'fqcn'      => string,
     *   'uses'      => ['Alias' => 'FQCN'],
     *   'methods'   => [
     *      'methodName' => [
     *        'params'  => [['name'=>..,'type'=>..,'required'=>..], ...],
     *        'return'  => string|null,
     *        'doc'     => string,
     *        'visibility' => 'public'|'protected'|'private',
     *      ]
     *   ]
     * ]
     */
    public function parseFile(string $filePath): array
    {
        if (isset($this->cache[$filePath])) {
            return $this->cache[$filePath];
        }

        $src = file_get_contents($filePath);
        if ($src === false) {
            return [];
        }

        $result = [
            'namespace' => '',
            'class' => '',
            'fqcn' => '',
            'uses' => [],
            'methods' => [],
        ];

        // Namespace
        if (preg_match('/^namespace\s+([\w\\\\]+)\s*;/m', $src, $m)) {
            $result['namespace'] = $m[1];
        }

        // Class name (skip abstract / interface / trait for controllers)
        if (preg_match('/(?:class|interface|trait)\s+(\w+)/m', $src, $m)) {
            $result['class'] = $m[1];
            $result['fqcn'] = $result['namespace']
                ? $result['namespace'] . '\\' . $result['class']
                : $result['class'];
        }

        // Use statements
        preg_match_all('/^use\s+([\w\\\\]+)(?:\s+as\s+(\w+))?\s*;/m', $src, $useMatches, PREG_SET_ORDER);
        foreach ($useMatches as $u) {
            $fqcn = $u[1];
            $alias = $u[2] ?? basename(str_replace('\\', '/', $fqcn));
            $result['uses'][$alias] = $fqcn;
        }

        // Methods — extract via token_get_all for accuracy
        $result['methods'] = $this->extractMethods($src, $result['uses']);

        $this->cache[$filePath] = $result;
        return $result;
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function extractMethods(string $src, array $uses): array
    {
        $methods = [];

        // Match method signatures with optional doc comment above
        $pattern = '/
            (?:\/\*\*(.*?)\*\/\s*)?          # optional docblock
            (public|protected|private)\s+     # visibility
            (?:static\s+)?                    # optional static
            function\s+(\w+)\s*              # function name
            \(([^)]*(?:\([^)]*\)[^)]*)*)\)   # parameter list (handles defaults with parens)
            (?:\s*:\s*([\w\\\\|?]+))?         # optional return type
        /xs';

        preg_match_all($pattern, $src, $matches, PREG_SET_ORDER);

        foreach ($matches as $m) {
            $doc = trim($m[1] ?? '');
            $visibility = $m[2];
            $name = $m[3];
            $paramStr = trim($m[4]);
            $returnType = trim($m[5] ?? '');

            $methods[$name] = [
                'visibility' => $visibility,
                'params' => $this->parseParams($paramStr, $uses),
                'return' => $returnType ?: null,
                'doc' => $this->cleanDoc($doc),
            ];
        }

        return $methods;
    }

    private function parseParams(string $paramStr, array $uses): array
    {
        if (trim($paramStr) === '') {
            return [];
        }

        $params = [];

        // Split by comma, but only at top-level (not inside <> or [])
        $parts = $this->splitParams($paramStr);

        foreach ($parts as $part) {
            $part = trim($part);
            if (!$part) {
                continue;
            }

            $hasDefault = str_contains($part, '=');
            $required = !$hasDefault;

            // Strip default value
            if ($hasDefault) {
                $part = trim(explode('=', $part)[0]);
            }

            // Extract type hint and variable name
            if (preg_match('/^(?:([\w\\\\|?]+)\s+)?\$(\w+)$/', trim($part), $m)) {
                $rawType = $m[1] ?? '';
                $varName = $m[2];

                // Nullable type
                $nullable = str_starts_with($rawType, '?');
                $rawType = ltrim($rawType, '?');

                $resolvedType = $rawType
                    ? $this->resolveType($rawType, $uses)
                    : 'mixed';

                $params[] = [
                    'name' => $varName,
                    'type' => $resolvedType,
                    'nullable' => $nullable || !$required,
                    'required' => $required && !$nullable,
                ];
            }
        }

        return $params;
    }

    private function splitParams(string $str): array
    {
        $parts = [];
        $depth = 0;
        $buffer = '';

        for ($i = 0; $i < strlen($str); $i++) {
            $ch = $str[$i];
            if (in_array($ch, ['(', '[', '<'])) {
                $depth++;
            } elseif (in_array($ch, [')', ']', '>'])) {
                $depth--;
            } elseif ($ch === ',' && $depth === 0) {
                $parts[] = $buffer;
                $buffer = '';
                continue;
            }
            $buffer .= $ch;
        }

        if (trim($buffer) !== '') {
            $parts[] = $buffer;
        }

        return $parts;
    }

    /**
     * Resolve a type alias (from use statements) to a FQCN.
     */
    public function resolveType(string $type, array $uses): string
    {
        if (str_starts_with($type, '\\')) {
            return ltrim($type, '\\');
        }

        $base = explode('\\', $type)[0];
        if (isset($uses[$base])) {
            $rest = substr($type, strlen($base));
            return $uses[$base] . $rest;
        }

        return $type;
    }

    private function cleanDoc(string $raw): string
    {
        if (!$raw) {
            return '';
        }

        $lines = explode("\n", $raw);
        $clean = [];

        foreach ($lines as $line) {
            $line = trim($line);
            $line = ltrim($line, '* ');
            if ($line && !str_starts_with($line, '@')) {
                $clean[] = $line;
            }
        }

        return implode(' ', array_filter($clean));
    }
}