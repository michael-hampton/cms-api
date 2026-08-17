<?php

namespace App\Services\PublicContent\Render;

use App\DTO\PublicContent\PublicContentLocaleContext;
use App\Events\PublicContent\PublicContentDefaultLocaleApplied;
use App\Framework\Events\EventDispatcher;

/**
 * Pre-shell step: fills in the single configured default locale when the
 * page's locale context arrives with no language, leaving an existing
 * locale untouched. Owns no locale policy beyond delegating to
 * {@see PublicContentLocaleContext::withDefaultLanguage()}; the default
 * value itself and the "missing" rule live on that shared type.
 *
 * Later locale/region resolution work will populate the language before
 * this step ever sees a missing context, making this step a no-op without
 * needing to be removed.
 */
final class PublicContentDefaultLocaleRenderStep implements PublicContentRenderStep
{
    public function __construct(
        private readonly EventDispatcher $events,
        private readonly string $defaultLanguage,
    ) {
    }

    public function name(): string
    {
        return 'default_locale';
    }

    public function handle(PublicContentRenderContext $context): PublicContentRenderContext
    {
        $current = $context->attributes['locale_context'] ?? new PublicContentLocaleContext();

        if (!$current instanceof PublicContentLocaleContext) {
            return $context;
        }

        $result = $current->withDefaultLanguage($this->defaultLanguage);

        $context->attributes['locale_context'] = $result->context;
        $context->attributes['default_locale_applied'] = $result->defaultApplied;

        if ($result->defaultApplied) {
            $this->events->dispatch(new PublicContentDefaultLocaleApplied(
                siteId: (int) ($context->attributes['site_id'] ?? 0),
                pageId: (int) ($context->attributes['page_id'] ?? 0),
                defaultLanguage: $this->defaultLanguage,
            ));
        }

        return $context;
    }
}
