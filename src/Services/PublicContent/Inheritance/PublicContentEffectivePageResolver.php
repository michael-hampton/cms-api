<?php

namespace App\Services\PublicContent\Inheritance;

use App\DTO\PublicContent\Inheritance\EffectivePublicContentPage;
use App\Models\Page;
use App\Models\PageSettings;
use App\Repositories\PublicContent\PublicContentPageRepository;

/**
 * Walks published parents via page_settings.parent_page and merges
 * parent → child (child wins). Cycle-safe with a visited set; depth bound 5.
 */
final class PublicContentEffectivePageResolver
{
    public const int MAX_DEPTH = 5;

    public function __construct(
        private readonly PublicContentPageRepository $pages,
        private readonly PublicContentSettingsMerger $merger = new PublicContentSettingsMerger(),
    ) {
    }

    public function resolve(Page $page): EffectivePublicContentPage
    {
        $siteId = (int) $page->site_id;
        $pageId = (int) $page->id;

        $chain = [];
        $visited = [];
        $current = $page;
        $cycleDetected = false;
        $truncatedByDepth = false;

        for ($depth = 0; $depth <= self::MAX_DEPTH; $depth++) {
            $id = (int) $current->id;

            if (isset($visited[$id])) {
                $cycleDetected = true;
                break;
            }

            $visited[$id] = true;
            $chain[] = $current;

            if ($depth === self::MAX_DEPTH) {
                // Bound reached: stop walking further parents.
                $parentRef = $this->parentReference($current);
                if ($parentRef !== null) {
                    $truncatedByDepth = true;
                }
                break;
            }

            $parent = $this->resolveParent($siteId, $current);
            if ($parent === null) {
                break;
            }

            $current = $parent;
        }

        // Merge root → leaf so child wins.
        $settings = [];
        $ancestorIds = [];
        $rootFirst = array_reverse($chain);

        foreach ($rootFirst as $index => $node) {
            $nodeSettings = $this->settingsArray($node);
            $settings = $this->merger->merge($settings, $nodeSettings);

            if ($index < count($rootFirst) - 1) {
                $ancestorIds[] = (int) $node->id;
            }
        }

        return new EffectivePublicContentPage(
            pageId: $pageId,
            siteId: $siteId,
            settings: $settings,
            ancestorPageIds: $ancestorIds,
            depth: max(0, count($chain) - 1),
            truncatedByDepth: $truncatedByDepth,
            cycleDetected: $cycleDetected,
        );
    }

    private function resolveParent(int $siteId, Page $page): ?Page
    {
        $parentRef = $this->parentReference($page);
        if ($parentRef === null) {
            return null;
        }

        if (is_numeric($parentRef)) {
            return $this->pages->findPublishedById((int) $parentRef, $siteId, ['settings']);
        }

        return $this->pages->findPublishedBySlug($siteId, (string) $parentRef, ['settings']);
    }

    private function parentReference(Page $page): int|string|null
    {
        $settings = $page->settings;

        if (!$settings instanceof PageSettings && !is_object($settings)) {
            return null;
        }

        $parent = $settings->parent_page ?? null;

        if ($parent === null || $parent === '' || $parent === false) {
            return null;
        }

        if (is_int($parent) || (is_string($parent) && ctype_digit($parent))) {
            $id = (int) $parent;
            return $id > 0 ? $id : null;
        }

        $slug = trim((string) $parent);

        return $slug !== '' ? $slug : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsArray(Page $page): array
    {
        $settings = $page->settings;

        if ($settings instanceof PageSettings) {
            return array_filter(
                $settings->toArray(),
                static fn(mixed $value, string $key): bool => !in_array($key, ['id', 'page_id', 'created_at', 'updated_at'], true),
                ARRAY_FILTER_USE_BOTH,
            );
        }

        if (is_object($settings) && method_exists($settings, 'toArray')) {
            /** @var array<string, mixed> $array */
            $array = $settings->toArray();
            unset($array['id'], $array['page_id'], $array['created_at'], $array['updated_at']);

            return $array;
        }

        return [];
    }
}
