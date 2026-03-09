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
     *        'line'    => int,   // 1-based line number of function keyword
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

        // Methods — extract via token_get_all for accuracy, including line numbers
        $result['methods'] = $this->extractMethods($src, $result['uses']);

        $this->cache[$filePath] = $result;
        return $result;
    }

    // ── Private ───────────────────────────────────────────────────────────────

    /**
     * Extract method metadata using token_get_all for accurate line tracking.
     *
     * This replaces the old regex-only approach which could not reliably track
     * line numbers — causing RequestAnalyzer to read the wrong method body.
     */
    private function extractMethods(string $src, array $uses): array
    {
        $methods = [];
        $tokens = token_get_all($src);
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) {
                continue;
            }

            $funcLine = $tokens[$i][2];

            // Collect visibility (scan backwards from T_FUNCTION, skipping whitespace)
            $visibility = 'public';
            for ($j = $i - 1; $j >= 0; $j--) {
                if (!is_array($tokens[$j])) {
                    break;
                }
                if ($tokens[$j][0] === T_WHITESPACE) {
                    continue;
                }
                if (in_array($tokens[$j][0], [T_PUBLIC, T_PROTECTED, T_PRIVATE], true)) {
                    $visibility = $tokens[$j][1];
                }
                break;
            }

            // Skip anonymous functions: next non-whitespace after T_FUNCTION must be T_STRING
            $nameIdx = $i + 1;
            while ($nameIdx < $count && is_array($tokens[$nameIdx]) && $tokens[$nameIdx][0] === T_WHITESPACE) {
                $nameIdx++;
            }

            if (!is_array($tokens[$nameIdx]) || $tokens[$nameIdx][0] !== T_STRING) {
                continue; // anonymous function, skip
            }

            $name = $tokens[$nameIdx][1];

            // Collect doc comment (scan backwards past whitespace/attributes)
            $doc = '';
            for ($j = $i - 1; $j >= 0; $j--) {
                if (!is_array($tokens[$j])) {
                    break;
                }
                if ($tokens[$j][0] === T_WHITESPACE) {
                    continue;
                }
                // PHP 8 attributes: #[Something] — skip the attribute tokens
                if ($tokens[$j][0] === T_ATTRIBUTE || $tokens[$j][1] === ']') {
                    // Skip until we find the matching #[
                    while ($j >= 0 && !(is_array($tokens[$j]) && $tokens[$j][0] === T_ATTRIBUTE)) {
                        $j--;
                    }
                    continue;
                }
                if ($tokens[$j][0] === T_DOC_COMMENT) {
                    $doc = $this->cleanDoc($tokens[$j][1]);
                }
                break;
            }

            // Parse parameter list — find opening ( and closing )
            $paramStr = $this->extractParamString($tokens, $nameIdx + 1, $count);

            // Parse return type — find : after the closing )
            $returnType = $this->extractReturnType($tokens, $nameIdx + 1, $count);

            $methods[$name] = [
                'visibility' => $visibility,
                'params' => $this->parseParams($paramStr, $uses),
                'return' => $returnType ?: null,
                'doc' => $doc,
                'line' => $funcLine,
            ];
        }

        return $methods;
    }

    /**
     * Extract the raw parameter string from between the ( ) of a function signature.
     */
    private function extractParamString(array $tokens, int $start, int $count): string
    {
        // Advance to opening paren
        $i = $start;
        while ($i < $count && $tokens[$i] !== '(' && !(is_array($tokens[$i]) && $tokens[$i][1] === '(')) {
            $i++;
        }

        $depth = 0;
        $parts = [];
        for (; $i < $count; $i++) {
            $tok = $tokens[$i];
            $val = is_array($tok) ? $tok[1] : $tok;

            if ($val === '(') {
                $depth++;
                if ($depth === 1) {
                    continue; // skip the opening paren itself
                }
            } elseif ($val === ')') {
                $depth--;
                if ($depth === 0) {
                    break;
                }
            }

            if ($depth >= 1) {
                $parts[] = $val;
            }
        }

        return implode('', $parts);
    }

    /**
     * Extract the return type hint from after the closing ) of a function signature.
     */
    private function extractReturnType(array $tokens, int $start, int $count): string
    {
        // First skip past the parameter list
        $i = $start;
        $depth = 0;
        $foundOpen = false;
        for (; $i < $count; $i++) {
            $tok = $tokens[$i];
            $val = is_array($tok) ? $tok[1] : $tok;
            if ($val === '(') {
                $depth++;
                $foundOpen = true;
            } elseif ($val === ')') {
                $depth--;
                if ($foundOpen && $depth === 0) {
                    $i++;
                    break;
                }
            }
        }

        // Now look for : followed by type name, stopping at { or ;
        $returnParts = [];
        for (; $i < $count; $i++) {
            $tok = $tokens[$i];
            $val = is_array($tok) ? $tok[1] : $tok;

            if ($val === '{' || $val === ';') {
                break;
            }
            if ($val === ':' && empty($returnParts)) {
                continue; // skip the colon itself
            }
            if (is_array($tok) && $tok[0] === T_WHITESPACE) {
                continue;
            }
            if ($val !== ':') {
                $returnParts[] = $val;
            }
        }

        return trim(implode('', $returnParts));
    }

    private function parseParams(string $paramStr, array $uses): array
    {
        if (trim($paramStr) === '') {
            return [];
        }

        $params = [];
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