<?php

namespace App\Services\PublicContent\Layout;

use App\DTO\PublicContent\Inheritance\EffectivePublicContentPage;
use App\DTO\PublicContent\Layout\PublicContentLayoutResolution;
use App\Enums\PublicContent\LayoutResolutionSource;
use App\Services\PublicContent\Config\PublicContentConfigSource;

/**
 * Layout precedence:
 * 1. page_settings.template when set and not empty/default-unset
 * 2. site catch-all from config key layout.default_template
 * 3. otherwise typed NoLayoutResolved
 *
 * page_type is never used as a silent layout fallback.
 */
final class PublicContentLayoutPrecedenceResolver
{
    private const array UNSET_SENTINELS = ['', 'default', 'default-unset', 'none', 'null'];

    public function __construct(
        private readonly PublicContentConfigSource $config,
    ) {
    }

    public function resolve(EffectivePublicContentPage $effective): PublicContentLayoutResolution
    {
        $pageTemplate = $this->normaliseTemplate($effective->template());

        if ($pageTemplate !== null) {
            return PublicContentLayoutResolution::resolved(
                $pageTemplate,
                LayoutResolutionSource::PageSettings,
            );
        }

        $siteDefault = $this->normaliseTemplate(
            $this->config->get($effective->siteId, 'layout.default_template', null),
        );

        if ($siteDefault !== null) {
            return PublicContentLayoutResolution::resolved(
                $siteDefault,
                LayoutResolutionSource::SiteDefault,
            );
        }

        return PublicContentLayoutResolution::none();
    }

    private function normaliseTemplate(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        if (in_array(strtolower($trimmed), self::UNSET_SENTINELS, true)) {
            return null;
        }

        return $trimmed;
    }
}
