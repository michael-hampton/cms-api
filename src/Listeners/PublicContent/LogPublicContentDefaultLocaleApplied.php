<?php

declare(strict_types=1);

namespace App\Listeners\PublicContent;

use App\Events\PublicContent\PublicContentDefaultLocaleApplied;
use App\Framework\Support\Logger;

/**
 * Writes a log entry each time a page renders with the single default
 * locale filled in because none was resolved.
 *
 * This is the sole listener for PublicContentDefaultLocaleApplied.
 */
final class LogPublicContentDefaultLocaleApplied
{
    public function __construct(
        private readonly Logger $logger,
    ) {
    }

    public function handle(PublicContentDefaultLocaleApplied $event): void
    {
        $this->logger->info('Public content default locale applied', [
            'site_id' => $event->siteId,
            'page_id' => $event->pageId,
            'default_language' => $event->defaultLanguage,
        ]);
    }
}
