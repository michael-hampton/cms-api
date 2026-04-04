<?php

namespace App\Framework\View;

use Exception;

class SimpleTemplateEngine implements ViewEngineInterface
{
    private array $sections = [];
    private ?string $parentTemplate = null;
    private $viewsPath;
    private $cache = [];
    private $globals = [];

    public function __construct(string $viewsPath = 'views')
    {
        $this->viewsPath = rtrim($viewsPath, '/');
    }

    private function compileJsonDirective(string $template): string
    {
        // Matches @json($variable) - non-greedy match
        return preg_replace_callback(
            '/@json\s*\(\s*(.+?)\s*\)/U',
            function ($matches) {
                $expression = trim($matches[1]);

                // Build safe PHP echo expression
                return "<?php echo htmlspecialchars(json_encode($expression, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>";
            },
            $template
        );
    }

    public function render(string $template, array $data = []): string
    {
        $templatePath = $this->findTemplate($template);

        if (!$templatePath) {
            throw new Exception("Template '{$template}' not found");
        }

        $data = array_merge($this->globals, $data);

        // Simple template caching
        $cacheKey = md5($templatePath . json_encode($data, JSON_PARTIAL_OUTPUT_ON_ERROR));
//        if (isset($this->cache[$cacheKey])) {
//            return $this->cache[$cacheKey];
//        }

        $content = file_get_contents($templatePath);

        $rendered = $this->compileTemplate($content, $data);

        $this->cache[$cacheKey] = $rendered;

        return $rendered;
    }

    public function exists(string $template): bool
    {
        return $this->findTemplate($template) !== null;
    }

    public function share(array $data): void
    {
        $this->globals = array_merge($this->globals, $data);
    }

    private function findTemplate(string $template): ?string
    {
        $template = str_replace('.', '/', $template);

        $extensions = ['.php', '.html', '.tpl'];

        foreach ($extensions as $ext) {
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

    private function compileTemplate(string $template, array $data): string
    {
        extract($data, EXTR_OVERWRITE);

        // Collect sections and resolve @extends first
        $template = $this->compileSections($template);
        $template = $this->compileExtends($template, $data);

        // If no @extends, compile the template directly
        // (compileExtends handles compilation internally when a layout is found)
        if ($this->parentTemplate === null) {
            $template = $this->compileJsonDirective($template);
            $template = $this->compilePrintStatements($template);
            $template = $this->compileConditionals($template);
            $template = $this->compileLoops($template);
            $template = $this->compileIncludes($template, $data);
            $template = $this->compileAssets($template);
            $template = str_replace('@csrf', '<?php echo csrf_field(); ?>', $template);
            $template = preg_replace('/@method\([\'"](.+?)[\'"]\)/', '<?php echo method_field(\'$1\'); ?>', $template);
        }

        ob_start();
        eval('?>' . $template);
        return ob_get_clean();
    }

    private function compilePrintStatements(string $template): string
    {
        $template = preg_replace('/\{\{\s*(.+?)\s*\}\}/', '<?php echo htmlspecialchars($1); ?>', $template);

        $template = preg_replace('/\{\!!\s*(.+?)\s*\!\!\}/', '<?php echo $1; ?>', $template);

        return $template;
    }

    private function compileAssets(string $template): string
    {
        // @css('file.css')
        $template = preg_replace(
            '/@css\s*\(\s*[\'"](.*?)[\'"]\s*\)/',
            '<?php echo \'<link rel="stylesheet" href="\' . asset(\'$1\', \'css\') . \'">\'; ?>',
            $template
        );

        // @js('file.js')
        $template = preg_replace(
            '/@js\s*\(\s*[\'"](.*?)[\'"]\s*\)/',
            '<?php echo \'<script src="\' . asset(\'$1\', \'js\') . \'"></script>\'; ?>',
            $template
        );


        return $template;
    }

    private function compileConditionals(string $template): string
    {
        // @if(condition)
        $template = preg_replace('/@if\s*\((.*?)\)/', '<?php if ($1): ?>', $template);

        // @elseif(condition)
        $template = preg_replace('/@elseif\s*\((.*?)\)/', '<?php elseif ($1): ?>', $template);

        // @else
        $template = preg_replace('/@else/', '<?php else: ?>', $template);

        // @endif
        $template = preg_replace('/@endif/', '<?php endif; ?>', $template);

        return $template;
    }

    private function compileLoops(string $template): string
    {
        // @foreach(...)
        $template = preg_replace('/@foreach\s*\((.*?)\)/', '<?php foreach ($1): ?>', $template);

        // @endforeach
        $template = preg_replace('/@endforeach/', '<?php endforeach; ?>', $template);

        // @for(...)
        $template = preg_replace('/@for\s*\((.*?)\)/', '<?php for ($1): ?>', $template);

        // @endfor
        $template = preg_replace('/@endfor/', '<?php endfor; ?>', $template);

        // @while(...)
        $template = preg_replace('/@while\s*\((.*?)\)/', '<?php while ($1): ?>', $template);

        // @endwhile
        $template = preg_replace('/@endwhile/', '<?php endwhile; ?>', $template);

        return $template;
    }

    private function compileIncludes(string $template, array $data = []): string
    {
        $pattern = '/@include\s*\(\s*[\'"](.*?)[\'"]\s*(?:,\s*(\[(?:[^[\]]|(?2))*\]))?\s*\)/s';

        return preg_replace_callback(
        // The regex now uses a recursive subpattern (?R) or a balanced bracket matcher
            '/@include\s*\(\s*[\'"](.*?)[\'"]\s*(?:,\s*(\[(?:[^[\]]|(?2))*\]))?\s*\)/s',
            function ($matches) use ($data) {
                $view = $matches[1];
                $file = $this->findTemplate($view);

                if (!$file) {
                    return "";
                }

                $contents = file_get_contents($file);

                if (!empty($matches[2])) {
                    $arrayExpression = $matches[2];

                    // ... keep your existing compilation and base64 logic ...
                    $compiled = $this->compileJsonDirective($contents);
                    $compiled = $this->compilePrintStatements($compiled);
                    $compiled = $this->compileConditionals($compiled);
                    $compiled = $this->compileLoops($compiled);
                    $compiled = $this->compileIncludes($compiled, $data);
                    $compiled = $this->compileAssets($compiled);
                    $compiled = str_replace('@csrf', '<?php echo csrf_field(); ?>', $compiled);
                    $compiled = preg_replace('/@method\([\'"](.+?)[\'"]\)/', '<?php echo method_field(\'$1\'); ?>', $compiled);
                    $compiled = $this->compileSections($compiled);
                    $compiled = $this->compileExtends($compiled, $data);

                    $encodedCompiled = base64_encode($compiled);

                    return "<?php 
                \$__extraVars = {$arrayExpression};
                \$__includeData = array_merge(get_defined_vars(), \$__extraVars);
                unset(\$__includeData['__extraVars'], \$__includeData['__includeData']);
                extract(\$__includeData, EXTR_OVERWRITE);
                eval('?>' . base64_decode('{$encodedCompiled}'));
            ?>";
                }

                return $this->compileTemplate($contents, $data);
            },
            $template
        );
    }


    private function compileSections(string $template): string
    {

        // Inline: @section('name', expression)
        $template = preg_replace_callback(
            '/@section\s*\(\s*[\'"](.*?)[\'"]\s*,\s*((?:[^()]*|\((?:[^()]*|\([^()]*\))*\))*)\s*\)[ \t]*\n?/',
            function ($matches) {
                $name = $matches[1];
                $value = trim($matches[2]);

                if (preg_match('/^[\'"](.*)[\'"]\s*$/s', $value, $stringMatch)) {
                    $inner = $stringMatch[1];
                    $inner = stripslashes($inner); // <-- here
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
        if (preg_match('/@extends\s*\(\s*[\'"](.*?)[\'"]\s*\)/', $template, $matches)) {
            $this->parentTemplate = $matches[1];
            $template = preg_replace('/@extends\s*\(\s*[\'"](.*?)[\'"]\s*\)/', '', $template);

            $parentFile = $this->findTemplate($this->parentTemplate);

            if ($parentFile) {
                $parentContent = file_get_contents($parentFile);

                // Inject sections into @yield slots
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

                // Handle nested @extends (layouts that extend layouts)
                if (preg_match('/@extends/', $parentContent)) {
                    return $this->compileExtends($parentContent, $data);
                }

                // NOW compile the fully-assembled layout through the whole pipeline
                $parentContent = $this->compileJsonDirective($parentContent);
                $parentContent = $this->compilePrintStatements($parentContent);
                $parentContent = $this->compileConditionals($parentContent);
                $parentContent = $this->compileLoops($parentContent);
                $parentContent = $this->compileIncludes($parentContent, $data);
                $parentContent = $this->compileAssets($parentContent);
                $parentContent = str_replace('@csrf', '<?php echo csrf_field(); ?>', $parentContent);
                $parentContent = preg_replace('/@method\([\'"](.+?)[\'"]\)/', '<?php echo method_field(\'$1\'); ?>', $parentContent);

                return $parentContent;
            }
        }

        return $template;
    }

    public function partial(string $template, array $data = []): string
    {
        return $this->render($template, $data);
    }

    function public_path(string $path = ''): string
    {
        return __DIR__ . '/../public' . ($path ? '/' . ltrim($path, '/') : '');
    }


    public function maskEmail(string $email): string
    {
        // Split into local part and domain
        [$local, $domain] = explode('@', $email, 2);

        if (strlen($local) <= 2) {
            // For very short emails, just mask with a single *
            $maskedLocal = substr($local, 0, 1) . '*';
        } else {
            // Show first and last char of local part, mask the rest
            $maskedLocal = substr($local, 0, 1) . str_repeat('*', strlen($local) - 2) . substr($local, -1);
        }

        return $maskedLocal . '@' . $domain;
    }

}