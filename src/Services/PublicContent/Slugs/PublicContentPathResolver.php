<?php

namespace App\Services\PublicContent\Slugs;

use App\DTO\PublicContent\ResolvedPublicContentPath;
use App\Models\Category;
use App\Models\Page;
use App\Models\Site;

final class PublicContentPathResolver
{
    /**
     * @return list<ResolvedPublicContentPath>
     */
    public function resolveCandidates(int $siteId, string $path): array
    {
        $path = $this->normalisePath($path);
        if ($path === '') {
            return [];
        }

        $candidates = [
            new ResolvedPublicContentPath(
                path: $path,
                slug: $path,
                categorySlug: null,
                subcategorySlug: null,
                pageType: null,
                matchedPattern: '{path}',
            ),
        ];

        foreach ($this->patternsForSite($siteId) as $definition) {
            $resolved = $this->matchPattern($definition, $path);
            if (!$resolved) {
                continue;
            }

            $key = implode('|', [
                $resolved->slug,
                $resolved->categorySlug ?? '',
                $resolved->subcategorySlug ?? '',
                $resolved->pageType ?? '',
                $resolved->matchedPattern,
            ]);

            $candidates[$key] = $resolved;
        }

        return array_values($candidates);
    }

    public function canonicalPath(Page $page, ?ResolvedPublicContentPath $resolvedPath = null): string
    {
        if ($resolvedPath && $resolvedPath->matchedPattern !== '{path}') {
            return $resolvedPath->path;
        }

        return $this->canonicalPathForPage($page);
    }

    public function canonicalPathForPage(Page $page): string
    {
        $siteId = (int) ($page->site_id ?? 0);

        foreach ($this->patternsForSite($siteId) as $definition) {
            if ($definition['page_type'] !== null && $definition['page_type'] !== (string) $page->page_type) {
                continue;
            }

            $path = $this->buildPathFromPattern($definition['pattern'], $page);
            if ($path !== null) {
                return $path;
            }
        }

        return $this->normalisePath((string) $page->slug);
    }

    /**
     * @return list<array{name:string,pattern:string,page_type:?string,priority:int}>
     */
    private function patternsForSite(int $siteId): array
    {
        $site = Site::find($siteId);
        $patterns = [];

        if ($site instanceof Site) {
            $settings = is_string($site->settings)
                ? json_decode($site->settings, true)
                : ($site->settings ?? []);

            if (is_array($settings)) {
                $patterns = $settings['public_content_slug_patterns']
                    ?? ($settings['public_content']['slug_patterns'] ?? []);
            }
        }

        if ($patterns === []) {
            $patterns = config('public_content.slug_patterns', []);
        }

        if (!is_array($patterns) || $patterns === []) {
            $patterns = [
                'flat' => '{slug}',
                'category_prefix' => 'category/{slug}',
                'category_slug' => '{category}/{slug}',
                'category_subcategory_slug' => '{category}/{subcategory}/{slug}',
            ];
        }

        return $this->normalisePatternDefinitions($patterns);
    }

    /**
     * @param array<int|string, mixed> $patterns
     * @return list<array{name:string,pattern:string,page_type:?string,priority:int}>
     */
    private function normalisePatternDefinitions(array $patterns): array
    {
        $definitions = [];

        foreach ($patterns as $name => $definition) {
            if (is_string($definition)) {
                $definitions[] = [
                    'name' => is_string($name) ? $name : $definition,
                    'pattern' => $this->normalisePath($definition),
                    'page_type' => null,
                    'priority' => 100,
                ];

                continue;
            }

            if (!is_array($definition)) {
                continue;
            }

            $pattern = $definition['pattern'] ?? null;
            if (!is_string($pattern) || trim($pattern) === '') {
                continue;
            }

            $definitions[] = [
                'name' => (string) ($definition['name'] ?? (is_string($name) ? $name : $pattern)),
                'pattern' => $this->normalisePath($pattern),
                'page_type' => isset($definition['page_type']) ? (string) $definition['page_type'] : null,
                'priority' => (int) ($definition['priority'] ?? 100),
            ];
        }

        usort($definitions, static function (array $a, array $b): int {
            if ($a['priority'] !== $b['priority']) {
                return $b['priority'] <=> $a['priority'];
            }

            $aStatic = substr_count((string) preg_replace('/\{[^}]+\}/', '', $a['pattern']), '/');
            $bStatic = substr_count((string) preg_replace('/\{[^}]+\}/', '', $b['pattern']), '/');

            return $bStatic <=> $aStatic;
        });

        return $definitions;
    }

    /**
     * @param array{name:string,pattern:string,page_type:?string,priority:int} $definition
     */
    private function matchPattern(array $definition, string $path): ?ResolvedPublicContentPath
    {
        $patternSegments = $this->segments($definition['pattern']);
        $pathSegments = $this->segments($path);

        if (count($patternSegments) !== count($pathSegments)) {
            return null;
        }

        $values = [];

        foreach ($patternSegments as $index => $segment) {
            if (preg_match('/^\{([a-zA-Z0-9_]+)\}$/', $segment, $matches)) {
                $values[$matches[1]] = $pathSegments[$index];
                continue;
            }

            if ($segment !== $pathSegments[$index]) {
                return null;
            }
        }

        $slug = $values['slug'] ?? end($pathSegments);
        if (!is_string($slug) || $slug === '') {
            return null;
        }

        return new ResolvedPublicContentPath(
            path: $path,
            slug: $slug,
            categorySlug: isset($values['category']) ? (string) $values['category'] : null,
            subcategorySlug: isset($values['subcategory']) ? (string) $values['subcategory'] : null,
            pageType: $definition['page_type'],
            matchedPattern: $definition['pattern'],
        );
    }

    private function buildPathFromPattern(string $pattern, Page $page): ?string
    {
        $categorySlugs = $this->categorySlugsForPage($page);
        $values = [
            'slug' => $this->normaliseSegment((string) $page->slug),
            'category' => $categorySlugs['category'],
            'subcategory' => $categorySlugs['subcategory'],
        ];

        $segments = [];
        foreach ($this->segments($pattern) as $segment) {
            if (preg_match('/^\{([a-zA-Z0-9_]+)\}$/', $segment, $matches)) {
                $value = $values[$matches[1]] ?? null;
                if (!is_string($value) || $value === '') {
                    return null;
                }

                $segments[] = $value;
                continue;
            }

            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    /**
     * @return array{category:?string,subcategory:?string}
     */
    private function categorySlugsForPage(Page $page): array
    {
        $categories = $page->categories ?? null;
        if (!$categories || !method_exists($categories, 'all')) {
            return ['category' => null, 'subcategory' => null];
        }

        $root = null;
        $child = null;

        foreach ($categories->all() as $category) {
            if (!$category instanceof Category) {
                continue;
            }

            if (!empty($category->parent_id)) {
                $child ??= $category;
                $parent = $category->parent();
                if ($parent instanceof Category) {
                    $root ??= $parent;
                }
                continue;
            }

            $root ??= $category;
        }

        return [
            'category' => $root ? $this->normaliseSegment((string) $root->slug) : null,
            'subcategory' => $child ? $this->normaliseSegment((string) $child->slug) : null,
        ];
    }

    /**
     * @return list<string>
     */
    private function segments(string $path): array
    {
        return array_values(array_filter(explode('/', $this->normalisePath($path)), static fn(string $segment): bool => $segment !== ''));
    }

    private function normalisePath(string $path): string
    {
        $path = trim($path);
        $path = trim($path, '/');

        if ($path === '') {
            return '';
        }

        $segments = array_map(
            static fn(string $segment): string => rawurldecode($segment),
            array_values(array_filter(explode('/', $path), static fn(string $segment): bool => $segment !== '')),
        );

        return implode('/', $segments);
    }

    private function normaliseSegment(string $segment): string
    {
        return trim(rawurldecode($segment), '/');
    }
}
