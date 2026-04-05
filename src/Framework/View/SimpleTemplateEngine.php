<?php

namespace App\Framework\View;

use Exception;

class SimpleTemplateEngine implements ViewEngineInterface
{
    private array $sections = [];
    private ?string $parentTemplate = null;
    private array $cache = [];
    private array $globals = [];
    private string $viewsPath;
    private array $includeDataStore = [];

    public function __construct(string $viewsPath = 'views')
    {
        $this->viewsPath = rtrim($viewsPath, '/');
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    public function render(string $template, array $data = []): string
    {
        $templatePath = $this->findTemplate($template);

        if (!$templatePath) {
            throw new Exception("Template '{$template}' not found");
        }

        $data = array_merge($this->globals, $data);
        $cacheKey = md5($templatePath . json_encode($data, JSON_PARTIAL_OUTPUT_ON_ERROR));

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $content = file_get_contents($templatePath);
        $result = $this->compileTemplate($content, $data);

        $this->cache[$cacheKey] = $result;

        return $result;
    }

    public function exists(string $template): bool
    {
        return $this->findTemplate($template) !== null;
    }

    public function share(array $data): void
    {
        $this->globals = array_merge($this->globals, $data);
    }

    public function partial(string $template, array $data = []): string
    {
        return $this->render($template, $data);
    }

    public function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);

        if (strlen($local) <= 2) {
            $maskedLocal = substr($local, 0, 1) . '*';
        } else {
            $maskedLocal = substr($local, 0, 1)
                . str_repeat('*', strlen($local) - 2)
                . substr($local, -1);
        }

        return $maskedLocal . '@' . $domain;
    }

    public function public_path(string $path = ''): string
    {
        return __DIR__ . '/../public' . ($path ? '/' . ltrim($path, '/') : '');
    }

    // -------------------------------------------------------------------------
    // Core compilation
    // -------------------------------------------------------------------------

    private function compileTemplate(string $template, array $data): string
    {
        $this->sections = [];
        $this->parentTemplate = null;

        $template = $this->compileSections($template);
        $template = $this->compileExtends($template, $data);

        if ($this->parentTemplate === null) {
            $template = $this->compilePipeline($template, $data);
        }

        ob_start();
        $fn = \Closure::bind(function () use ($template, $data) {
            extract($data, EXTR_OVERWRITE);
            eval('?>' . $template);
        }, $this);
        $fn();

        return ob_get_clean();
    }

    /**
     * Runs a template string through the full directive pipeline.
     * Centralising this avoids duplication across compileTemplate,
     * compileExtends, and the include closure.
     *
     * NOTE: compileIncludes is intentionally NOT called here at compile time.
     * @include directives are left as-is in the compiled string and resolved
     * at runtime inside their own isolated closures. Calling compileIncludes
     * at compile time caused infinite recursion because each included file
     * would itself be compiled, triggering further includes, and so on.
     */
    private function compilePipeline(string $template, array $data): string
    {
        $template = $this->compileJsonDirective($template);
        $template = $this->compilePrintStatements($template);
        $template = $this->compileConditionals($template);
        $template = $this->compileLoops($template);

        $template = $this->compileClassDirective($template);
        $template = $this->compileStyleDirective($template);
        $template = $this->compileIncludeWhen($template);

        $template = $this->compileIncludes($template, $data);
        $template = $this->compileAssets($template);
        $template = $this->compileAuth($template);
        $template = $this->compileSession($template);
        $template = str_replace('@csrf', '<?php echo csrf_field(); ?>', $template);
        $template = preg_replace(
            '/@method\([\'"](.+?)[\'"]\)/',
            '<?php echo method_field(\'$1\'); ?>',
            $template
        );

        return $template;
    }

    // -------------------------------------------------------------------------
    // Directive compilers
    // -------------------------------------------------------------------------

    private function compileJsonDirective(string $template): string
    {
        return preg_replace_callback(
            '/@json\s*\(\s*(.+?)\s*\)/U',
            function ($matches) {
                $expression = trim($matches[1]);
                return "<?php echo htmlspecialchars("
                    . "json_encode({$expression}, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)"
                    . ", ENT_QUOTES, 'UTF-8'); ?>";
            },
            $template
        );
    }

    private function compilePrintStatements(string $template): string
    {
        $template = preg_replace('/\{\{\s*(.+?)\s*\}\}/', '<?php echo htmlspecialchars($1); ?>', $template);
        $template = preg_replace('/\{\!!\s*(.+?)\s*\!\!\}/', '<?php echo $1; ?>', $template);
        return $template;
    }

    private function compileAssets(string $template): string
    {
        $template = preg_replace(
            '/@css\s*\(\s*[\'"](.*?)[\'"]\s*\)/',
            '<?php echo \'<link rel="stylesheet" href="\' . asset(\'$1\', \'css\') . \'">\'; ?>',
            $template
        );

        $template = preg_replace(
            '/@js\s*\(\s*[\'"](.*?)[\'"]\s*\)/',
            '<?php echo \'<script src="\' . asset(\'$1\', \'js\') . \'"></script>\'; ?>',
            $template
        );

        return $template;
    }

    private function compileConditionals(string $template): string
    {
        $template = preg_replace('/@if\s*\((.*?)\)/', '<?php if ($1): ?>', $template);
        $template = preg_replace('/@elseif\s*\((.*?)\)/', '<?php elseif ($1): ?>', $template);
        // (?!if) prevents @else matching the leading chars of @elseif
        $template = preg_replace('/@else(?!if)/', '<?php else: ?>', $template);
        $template = preg_replace('/@endif/', '<?php endif; ?>', $template);
        return $template;
    }

    private function compileLoops(string $template): string
    {
        $template = preg_replace('/@foreach\s*\((.*?)\)/', '<?php foreach ($1): ?>', $template);
        $template = preg_replace('/@endforeach/', '<?php endforeach; ?>', $template);
        $template = preg_replace('/@for\s*\((.*?)\)/', '<?php for ($1): ?>', $template);
        $template = preg_replace('/@endfor/', '<?php endfor; ?>', $template);
        $template = preg_replace('/@while\s*\((.*?)\)/', '<?php while ($1): ?>', $template);
        $template = preg_replace('/@endwhile/', '<?php endwhile; ?>', $template);
        return $template;
    }

    /**
     * @auth / @endauth — renders block only when a member is authenticated.
     * @guest / @endguest — renders block only when no member is authenticated.
     *
     * Delegates to the same MemberAuth::check() already used across the app.
     */
    private function compileAuth(string $template): string
    {
        $template = preg_replace(
            '/@auth/',
            '<?php if (\App\Framework\Authorization\MemberAuth::check()): ?>',
            $template
        );
        $template = preg_replace('/@endauth/', '<?php endif; ?>', $template);

        $template = preg_replace(
            '/@guest/',
            '<?php if (!\App\Framework\Authorization\MemberAuth::check()): ?>',
            $template
        );
        $template = preg_replace('/@endguest/', '<?php endif; ?>', $template);

        return $template;
    }

    /**
     * @session('key') / @endsession — renders block only when the session key exists.
     *
     * Usage:
     * @session('flash_message')
     *     <p>{{ $_SESSION['flash_message'] }}</p>
     * @endsession
     */
    private function compileSession(string $template): string
    {
        $template = preg_replace_callback(
            '/@session\s*\(\s*[\'"](.*?)[\'"]\s*\)/',
            function ($matches) {
                $key = addslashes($matches[1]);
                return "<?php if (!empty(\$_SESSION['{$key}'])): ?>";
            },
            $template
        );
        $template = preg_replace('/@endsession/', '<?php endif; ?>', $template);

        return $template;
    }

    private function compileIncludes(string $template, array $data = []): string
    {
        return preg_replace_callback(
            '/@include\s*\(\s*[\'"](.*?)[\'"]\s*(?:,\s*(\[(?:[^[\]]|(?2))*\]))?\s*\)/s',
            function ($matches) use ($data) {
                $view = $matches[1];
                $file = $this->findTemplate($view);

                if (!$file) {
                    return "<!-- @include '{$matches[1]}' not found -->";
                }

                $contents = file_get_contents($file);
                $encodedContents = base64_encode($contents);
                $hasExplicitVars = !empty($matches[2]);

                // Store data in the engine and reference it by key.
                // Avoids serialization entirely — no PDO/closure/resource errors.
                $dataKey = uniqid('id_', true);
                $this->includeDataStore[$dataKey] = $data;

                if ($hasExplicitVars) {
                    $arrayExpression = $matches[2];

                    return <<<PHP
                <?php echo (function(\$__engine, \$__dataKey, \$__extraVars, \$__encodedContents) {
                    \$__baseData   = \$__engine->getIncludeData(\$__dataKey);
                    \$__mergedData = array_merge(\$__baseData, \$__extraVars);
                    \$__contents   = base64_decode(\$__encodedContents);
                    \$__compiled   = \$__engine->compileIncludeContents(\$__contents, \$__mergedData);
                    extract(\$__mergedData, EXTR_OVERWRITE);
                    unset(\$__engine, \$__dataKey, \$__baseData, \$__extraVars, \$__encodedContents, \$__mergedData, \$__contents);
                    ob_start();
                    eval('?>' . \$__compiled);
                    return ob_get_clean();
                })(
                    \$this,
                    '{$dataKey}',
                    {$arrayExpression},
                    '{$encodedContents}'
                ); ?>
                PHP;
                }

                return <<<PHP
            <?php echo (function(\$__engine, \$__dataKey, \$__encodedContents) {
                \$__baseData = \$__engine->getIncludeData(\$__dataKey);
                \$__contents = base64_decode(\$__encodedContents);
                \$__compiled = \$__engine->compileIncludeContents(\$__contents, \$__baseData);
                extract(\$__baseData, EXTR_OVERWRITE);
                unset(\$__engine, \$__dataKey, \$__baseData, \$__encodedContents, \$__contents);
                ob_start();
                eval('?>' . \$__compiled);
                return ob_get_clean();
            })(
                \$this,
                '{$dataKey}',
                '{$encodedContents}'
            ); ?>
            PHP;
            },
            $template
        );
    }

// Called at runtime from inside the include closure.
// Public because the closure accesses it via $__engine, not $this.
    public function getIncludeData(string $key): array
    {
        return $this->includeDataStore[$key] ?? [];
    }

    /**
     * Compiles raw include file contents through the directive pipeline.
     * Called at runtime from inside the include closure, not at compile time,
     * to avoid the infinite recursion that pre-compilation caused.
     *
     * Public visibility is required because the closure calls it via $__engine
     * which holds a reference to $this but is not bound via bindTo().
     */
    public function compileIncludeContents(string $contents, array $data): string
    {
        // Save engine state so nested includes don't wipe parent sections
        $savedSections = $this->sections;
        $savedParentTemplate = $this->parentTemplate;

        $compiled = $this->compileSections($contents);
        $compiled = $this->compileExtends($compiled, $data);
        $compiled = $this->compilePipeline($compiled, $data);

        // Restore — the include's sections are local to it
        $this->sections = $savedSections;
        $this->parentTemplate = $savedParentTemplate;

        return $compiled;
    }

    /**
     * @class(['class-name' => $condition, 'other' => true])
     *
     * Renders a class="..." attribute with only the classes whose condition is truthy.
     *
     * Usage:
     *   <div @class(['active' => $isActive, 'disabled' => $isDisabled, 'btn'])>
     *
     * String keys are class names with conditions; integer keys are always included.
     */
    private function compileClassDirective(string $template): string
    {
        return preg_replace_callback(
            '/@class\s*\(\s*(\[(?:[^[\]]|(?1))*\])\s*\)/',
            function ($matches) {
                $arrayExpression = $matches[1];
                return <<<PHP
                    <?php
                    echo 'class="' . htmlspecialchars(implode(' ', array_keys(array_filter(
                        array_combine(
                            array_map(fn(\$k, \$v) => is_int(\$k) ? \$v : \$k, array_keys({$arrayExpression}), {$arrayExpression}),
                            array_map(fn(\$k, \$v) => is_int(\$k) ? true : \$v,  array_keys({$arrayExpression}), {$arrayExpression})
                        )
                    )))) . '"';
                    ?>
                    PHP;
            },
            $template
        );
    }

    /**
     * @style(['property' => 'value', 'color' => $color])
     *
     * Renders a style="..." attribute from an associative array.
     * Entries with falsy values are omitted.
     *
     * Usage:
     *   <div @style(['color' => $color, 'display' => $show ? 'block' : false])>
     */
    private function compileStyleDirective(string $template): string
    {
        return preg_replace_callback(
            '/@style\s*\(\s*(\[(?:[^[\]]|(?1))*\])\s*\)/',
            function ($matches) {
                $arrayExpression = $matches[1];
                return <<<PHP
                    <?php
                    \$__styles = [];
                    foreach ({$arrayExpression} as \$__prop => \$__val) {
                        if (\$__val !== false && \$__val !== null) {
                            \$__styles[] = htmlspecialchars(\$__prop) . ':' . htmlspecialchars(\$__val);
                        }
                    }
                    echo 'style="' . implode(';', \$__styles) . '"';
                    unset(\$__styles, \$__prop, \$__val);
                    ?>
                    PHP;
            },
            $template
        );
    }

    /**
     * @includeWhen($condition, 'view', ['key' => 'value'])
     *
     * Renders the include only when $condition is truthy.
     * Compiles to an @include so the same isolation logic applies.
     */
    private function compileIncludeWhen(string $template): string
    {
        return preg_replace_callback(
            '/@includeWhen\s*\(\s*(.+?)\s*,\s*[\'"](.*?)[\'"]\s*(?:,\s*(\[(?:[^[\]]|(?3))*\]))?\s*\)/s',
            function ($matches) {
                $condition = $matches[1];
                $view = $matches[2];
                $extraVars = !empty($matches[3]) ? ', ' . $matches[3] : '';

                // Reuse @include compilation by generating an @include directive
                // and running it back through compileIncludes at runtime.
                return "<?php if ({$condition}): ?>"
                    . "@include('{$view}'{$extraVars})"
                    . "<?php endif; ?>";
            },
            $template
        );
    }

    private function compileSections(string $template): string
    {
        // Inline: @section('name', 'string literal')
        // Match the delimiter explicitly so we only strip that quote character,
        // avoiding the blunt stripslashes() that mangled legitimate backslashes.
        $template = preg_replace_callback(
            '/@section\s*\(\s*[\'"](.+?)[\'"]\s*,\s*((?:[^()]*|\((?:[^()]*|\([^()]*\))*\))*)\s*\)[ \t]*\n?/',
            function ($matches) {
                $name = $matches[1];
                $value = trim($matches[2]);

                if (preg_match('/^([\'"])(.*)\1\s*$/s', $value, $stringMatch)) {
                    $inner = $stringMatch[2];
                    $inner = $this->compilePrintStatements($inner);
                    $this->sections[$name] = $inner;
                } else {
                    $this->sections[$name] = '<?php echo ' . $value . '; ?>';
                }

                return '';
            },
            $template
        );

        // Block: @section('name') ... @endsection
        return preg_replace_callback(
            '/@section\s*\(\s*[\'"](.*?)[\'"]\s*\)(.*?)@endsection/s',
            function ($matches) {
                $this->sections[$matches[1]] = $matches[2];
                return '';
            },
            $template
        );
    }

    private function compileYields(string $template): string
    {
        return preg_replace_callback(
            '/@yield\s*\(\s*[\'"](.*?)[\'"]\s*(?:,\s*((?:[^()]*|\((?:[^()]*|\((?:[^()]*|\([^()]*\))*\))*\))*))?\s*\)/',
            function ($matches) {
                $name = $matches[1];

                if (isset($this->sections[$name])) {
                    return $this->sections[$name];
                }

                if (!empty($matches[2])) {
                    ob_start();
                    eval('?><?php echo ' . trim($matches[2]) . '; ?>');
                    return ob_get_clean();
                }

                return '';
            },
            $template
        );
    }

    private function compileExtends(string $template, array $data): string
    {
        if (!preg_match('/@extends\s*\(\s*[\'"](.*?)[\'"]\s*\)/', $template, $matches)) {
            return $template;
        }

        $this->parentTemplate = $matches[1];

        // Strip the @extends directive itself.
        $template = preg_replace('/@extends\s*\(\s*[\'"](.*?)[\'"]\s*\)/', '', $template);

        // Any PHP outside @section blocks in the child (e.g. variable assignments,
        // computed values) must be executed before the parent layout is assembled
        // so those values are available when @yield slots are filled.
        // We strip all @section content from a copy and eval the remainder.
        $preamble = preg_replace(
            '/@section\s*\(\s*[\'"](.*?)[\'"]\s*\)(.*?)@endsection/s',
            '',
            $template
        );
        $preamble = preg_replace(
            '/@section\s*\(\s*[\'"].+?[\'"]\s*,\s*(?:[^()]*|\((?:[^()]*|\([^()]*\))*\))*\s*\)[ \t]*\n?/',
            '',
            $preamble
        );
        $preamble = trim($preamble);

        if ($preamble !== '') {
            $preamble = $this->compilePipeline($preamble, $data);
            $fn = \Closure::bind(function () use ($preamble, $data) {
                extract($data, EXTR_OVERWRITE);
                eval('?>' . $preamble);
            }, $this);
            $fn();
        }

        $parentFile = $this->findTemplate($this->parentTemplate);

        if (!$parentFile) {
            return $template;
        }

        $parentContent = file_get_contents($parentFile);

        // Inject collected sections into @yield slots.
        $parentContent = preg_replace_callback(
            '/@yield\s*\(\s*[\'"](.*?)[\'"]\s*(?:,\s*((?:[^()]*|\((?:[^()]*|\([^()]*\))*\))*))?\s*\)/',
            function ($m) {
                if (isset($this->sections[$m[1]])) {
                    return $this->sections[$m[1]];
                }

                if (!empty($m[2])) {
                    ob_start();
                    eval('?><?php echo ' . trim($m[2]) . '; ?>');
                    return ob_get_clean();
                }

                return '';
            },
            $parentContent
        );

        // Handle layouts that themselves extend another layout.
        if (preg_match('/@extends/', $parentContent)) {
            return $this->compileExtends($parentContent, $data);
        }

        return $this->compilePipeline($parentContent, $data);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function findTemplate(string $template): ?string
    {
        $template = str_replace('.', '/', $template);

        foreach (['.php', '.html', '.tpl'] as $ext) {
            $path = $this->viewsPath . '/' . $template . $ext;
            if (file_exists($path)) {
                return $path;
            }
        }

        $path = __DIR__ . '/../../' . $this->viewsPath . '/' . $template . '.php';
        if (file_exists($path)) {
            return $path;
        }

        return null;
    }

    private function serializeData(array $data): string
    {
        // Strip anything that can't survive serialization (closures, resources).
        $safe = array_filter($data, fn($v) => !($v instanceof \Closure) && !is_resource($v));
        return base64_encode(serialize($safe));
    }
}