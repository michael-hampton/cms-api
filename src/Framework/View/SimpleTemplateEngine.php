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

    public function render(string $template, array $data = []): string
    {
        $templatePath = $this->findTemplate($template);

        if (!$templatePath) {
            throw new Exception("Template '{$template}' not found");
        }

        $data = array_merge($this->globals, $data);

        // Simple template caching
        $cacheKey = md5($templatePath . json_encode($data, JSON_PARTIAL_OUTPUT_ON_ERROR));
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

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

        return null;
    }

    private function compileTemplate(string $template, array $data): string
    {
        // Extract variables
        extract($data, EXTR_OVERWRITE);

        // Compile template syntax
        $template = $this->compilePrintStatements($template);
        $template = $this->compileConditionals($template);
        $template = $this->compileLoops($template);
        $template = $this->compileIncludes($template, $data);
        $template = $this->compileAssets($template);

        // Handle sections and layouts
        $template = $this->compileSections($template);         // Collect sections
        $template = $this->compileExtends($template, $data);   // Apply parent layout

        // Execute PHP code
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
            '<?php echo \'<link rel="stylesheet" href="\' . $this->asset(\'$1\') . \'">\'; ?>',
            $template
        );

        // @js('file.js')
        $template = preg_replace(
            '/@js\s*\(\s*[\'"](.*?)[\'"]\s*\)/',
            '<?php echo \'<script src="\' . $this->asset(\'$1\') . \'"></script>\'; ?>',
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
        return preg_replace_callback(
        // Match: @include('file', [optional variables])
            '/@include\s*\(\s*[\'"](.*?)[\'"]\s*(?:,\s*(\[[^\)]*\]))?\s*\)/',
            function ($matches) use ($data) {
                $view = $matches[1];
                $extra = [];

                // Parse optional variables array
                if (!empty($matches[2])) {
                    $extra = (function ($data) use ($matches) {
                        // extract current variables so they can be used in the eval
                        extract($data, EXTR_SKIP);
                        return eval('return ' . $matches[2] . ';');
                    })($data);
                }

                $file = $this->findTemplate($view);

                if (!$file) {
                    return "<!-- include not found: {$view} -->";
                }

                $contents = file_get_contents($file);

                // Merge parent data with include-specific variables
                $mergedData = array_merge($data, $extra);

                // Compile included template — it can define @section/@yield
                $compiled = $this->compileTemplate($contents, $mergedData);

                return $compiled;

            },
            $template
        );
    }


    private function compileSections(string $template): string
    {
        return preg_replace_callback('/@section\s*\(\s*[\'"](.*?)[\'"]\s*\)(.*?)@endsection/s', function ($matches) {
            $this->sections[$matches[1]] = $matches[2]; // store raw content
            return ''; // remove from child template
        }, $template);
    }

    private function compileYields(string $template): string
    {
        return preg_replace_callback('/@yield\s*\(\s*[\'"](.*?)[\'"]\s*\)/', function ($matches) {
            $name = $matches[1];
            return $this->sections[$name] ?? '';
        }, $template);
    }

    private function compileExtends(string $template, array $data): string
    {
        if (preg_match('/@extends\s*\(\s*[\'"](.*?)[\'"]\s*\)/', $template, $matches)) {
            $this->parentTemplate = $matches[1];
            $template = preg_replace('/@extends\s*\(\s*[\'"](.*?)[\'"]\s*\)/', '', $template);

            $parentFile = __DIR__ . '/views/' . $this->parentTemplate . '.php';
            if (file_exists($parentFile)) {
                $parentContent = file_get_contents($parentFile);

                // Replace yields with sections collected from child
                $parentContent = preg_replace_callback('/@yield\s*\(\s*[\'"](.*?)[\'"]\s*\)/', function ($m) {
                    return $this->sections[$m[1]] ?? '';
                }, $parentContent);

                // Recursively process parent layout (if it has its own @extends)
                return $this->compileExtends($parentContent, $data);
            }
        }

        return $template;
    }

    public function partial(string $template, array $data = []): string
    {
        return $this->render($template, $data);
    }

    function asset(string $path, string $type = 'css'): string
    {
        // Determine folder based on type
        $folder = match ($type) {
            'css' => 'css',
            'js' => 'js',
            default => '',
        };

        // Build URL
        $url = '/public/' . ($folder ? $folder . '/' : '') . ltrim($path, '/');

        // Build filesystem path for cache-busting
        $file = __DIR__ . '/../../public/' . ($folder ? $folder . '/' : '') . ltrim($path, '/');

        // Add cache-busting if file exists
        if (file_exists($file)) {
            $url .= '?v=' . filemtime($file);
        }

        return $url;
    }

    function public_path(string $path = ''): string
    {
        return __DIR__ . '/../public' . ($path ? '/' . ltrim($path, '/') : '');
    }
}