<?php
// App\Services\PublicContent\Widgets\DatabasePublicContentWidgetDefinitionClassProvider.php

namespace App\Services\PublicContent\Widgets;

use App\Repositories\PublicContent\ConfigDocumentRepository;

/**
 * The registry has no single "current site" at boot time, so this
 * resolves the union of widget_definitions across every site's document.
 * Whether a given widget actually shows for a given page/site is still
 * decided per-request by supports()/eligibility, not by this class.
 */
final class DatabasePublicContentWidgetDefinitionClassProvider implements PublicContentWidgetDefinitionClassProvider
{
    private const string TYPE = 'public_content';

    private ?array $cachedClasses = null;

    public function __construct(
        private readonly ConfigDocumentRepository $configDocuments,
    ) {
    }

    public function has(): bool
    {
        return $this->configDocuments->allByType(self::TYPE)->isNotEmpty();
    }

    /** @return list<class-string> */
    public function all(): array
    {
        if ($this->cachedClasses !== null) {
            return $this->cachedClasses;
        }

        $classes = [];

        foreach ($this->configDocuments->allByType(self::TYPE) as $document) {
            $definitions = $document->payload['widget_definitions'] ?? [];

            if (!is_array($definitions)) {
                continue;
            }

            foreach ($definitions as $className) {
                if (is_string($className) && $className !== '') {
                    $classes[$className] = true;
                }
            }
        }

        return $this->cachedClasses = array_keys($classes);
    }
}