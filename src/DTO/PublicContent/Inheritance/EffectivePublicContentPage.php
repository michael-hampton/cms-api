<?php

namespace App\DTO\PublicContent\Inheritance;

/**
 * Effective page settings after walking the published parent chain.
 *
 * @param array<string, mixed> $settings
 * @param list<int> $ancestorPageIds Root-first then leaf (parents before child).
 */
final readonly class EffectivePublicContentPage
{
    /**
     * @param array<string, mixed> $settings
     * @param list<int> $ancestorPageIds
     */
    public function __construct(
        public int $pageId,
        public int $siteId,
        public array $settings,
        public array $ancestorPageIds = [],
        public int $depth = 0,
        public bool $truncatedByDepth = false,
        public bool $cycleDetected = false,
    ) {
    }

    public function template(): ?string
    {
        $template = $this->settings['template'] ?? null;

        if (!is_string($template) || trim($template) === '') {
            return null;
        }

        return $template;
    }
}
