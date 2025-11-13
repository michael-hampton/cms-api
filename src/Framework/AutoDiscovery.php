<?php

namespace App\Framework;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

class AutoDiscovery
{
    private static string $baseDir = __DIR__ . '/..';

    public static function discoverModels(?string $baseDir = null, bool $useAutoload = true): array
    {
        return self::discoverByDir($baseDir, 'Models', $useAutoload);
    }

    public static function discoverControllers(?string $baseDir = null, bool $useAutoload = true): array
    {
        return self::discoverByDir($baseDir, 'Controllers', $useAutoload);
    }

    public static function discoverServices(?string $baseDir = null, bool $useAutoload = true): array
    {
        return self::discoverByDir($baseDir, 'Services', $useAutoload);
    }

    public static function discoverRepositories(?string $baseDir = null, bool $useAutoload = true): array
    {
        return self::discoverByDir($baseDir, 'Repositories', $useAutoload);
    }

    /**
     * Discover classes in a specific directory
     */
    public static function discoverByDir(?string $baseDir, string $subDir, bool $useAutoload = true): array
    {
        $baseDir = rtrim($baseDir ?? dirname(__DIR__), '/\\');
        $dir = $baseDir . DIRECTORY_SEPARATOR . $subDir;
        $found = [];

        if (!is_dir($dir)) {
            return $found;
        }

        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($it as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();

            // 1) Try to derive FQCN from path (PSR-4 / typical "src -> App\" mapping)
            $fqcnFromPath = self::fqcnFromPath($baseDir, $path);

            if ($fqcnFromPath && (class_exists($fqcnFromPath) || interface_exists($fqcnFromPath) || trait_exists($fqcnFromPath))) {
                try {
                    $ref = new ReflectionClass($fqcnFromPath);
                    if ($ref->getFileName() &&
                        realpath($ref->getFileName()) === realpath($path) &&
                        !$ref->isAbstract() &&
                        !$ref->isInterface() &&
                        !$ref->isTrait()
                    ) {
                        $found[] = $fqcnFromPath;
                        continue;
                    }
                } catch (\ReflectionException $e) {
                    // fall back to parsing
                }
            }

            // 2) Token-parse the file for declared FQCN(s)
            $candidates = self::getClassesFromFile($path);
            foreach ($candidates as $fqcn) {
                // try autoloading (if configured) — class_exists will trigger Composer autoloader
                if (class_exists($fqcn) || interface_exists($fqcn) || trait_exists($fqcn)) {
                    try {
                        $ref = new ReflectionClass($fqcn);
                        if (
                            $ref->getFileName() &&
                            realpath($ref->getFileName()) === realpath($path) &&
                            !$ref->isAbstract() &&
                            !$ref->isInterface() &&
                            !$ref->isTrait()
                        ) {
                            $found[] = $fqcn;
                        }
                    } catch (\ReflectionException $e) {
                        continue;
                    }
                }
            }

            // 3) Last resort: if not found and autoload disabled, require the file and detect new classes.
            if (!$useAutoload) {
                $before = get_declared_classes();
                try {
                    require_once $path;
                } catch (\Throwable $e) {
                    // requiring may fail: skip
                    continue;
                }
                $after = get_declared_classes();
                $new = array_diff($after, $before);
                foreach ($new as $c) {
                    try {
                        $ref = new ReflectionClass($c);
                        if (realpath($ref->getFileName()) === realpath($path) &&
                            !$ref->isAbstract() &&
                            !$ref->isTrait() &&
                            !$ref->isInterface()) {
                            $found[] = $c;
                        }
                    } catch (\ReflectionException $e) {
                        // ignore
                    }
                }
            }
        }

        // unique and stable
        return array_values(array_unique($found));
    }

    /**
     * Build an FQCN from a file path using the common "src -> App\" PSR-4 convention.
     * Returns null if it doesn't look like a src file.
     */
    private static function fqcnFromPath(string $baseDir, string $path): ?string
    {
        $baseReal = realpath($baseDir);
        $pathReal = realpath($path);
        if (!$baseReal || !$pathReal || strpos($pathReal, $baseReal) !== 0) {
            return null;
        }

        $relative = ltrim(substr($pathReal, strlen($baseReal)), DIRECTORY_SEPARATOR);
        // strip extension
        $relative = preg_replace('#\.php$#i', '', $relative);
        // convert separators to namespace separators
        $parts = preg_split('#[\\/\\\\]#', $relative);
        if (!$parts) {
            return null;
        }

        // assume src/ -> App\ mapping (common in Symfony)
        array_unshift($parts, 'App');
        return implode('\\', $parts);
    }

    /**
     * Token-parse a file to find declared class/interface/trait names (FQCN).
     * Skips anonymous classes and collects multiple declarations per file.
     *
     * Returns an array of FQCN strings.
     */
    private static function getClassesFromFile(string $path): array
    {
        $src = file_get_contents($path);
        if ($src === false) {
            return [];
        }

        $tokens = token_get_all($src);
        $ns = '';
        $classes = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (!is_array($token)) {
                continue;
            }

            // namespace
            if ($token[0] === T_NAMESPACE) {
                $ns = '';
                $j = $i + 1;
                // collect namespace parts (T_STRING, T_NS_SEPARATOR)
                while (isset($tokens[$j]) && is_array($tokens[$j]) &&
                    ($tokens[$j][0] === T_STRING || $tokens[$j][0] === T_NS_SEPARATOR)) {
                    $ns .= $tokens[$j][1];
                    $j++;
                }
                // keep ns as last seen; this covers both "namespace X;" and "namespace X { ... }"
                continue;
            }

            // class / interface / trait
            if ($token[0] === T_CLASS || $token[0] === T_INTERFACE || $token[0] === T_TRAIT) {
                // skip anonymous classes: look backwards for T_NEW
                $isAnonymous = false;
                $k = $i - 1;
                while ($k >= 0) {
                    if (!is_array($tokens[$k])) {
                        $k--;
                        continue;
                    }
                    if ($tokens[$k][0] === T_WHITESPACE || $tokens[$k][0] === T_COMMENT || $tokens[$k][0] === T_DOC_COMMENT) {
                        $k--;
                        continue;
                    }
                    if ($tokens[$k][0] === T_NEW) {
                        $isAnonymous = true;
                    }
                    break;
                }
                if ($isAnonymous) {
                    continue;
                }

                // next non-whitespace token should be the name
                $j = $i + 1;
                while (isset($tokens[$j]) && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                    $j++;
                }
                if (isset($tokens[$j]) && is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                    $className = $tokens[$j][1];
                    $classes[] = $ns ? ($ns . '\\' . $className) : $className;
                }
            }
        }

        return $classes;
    }
}