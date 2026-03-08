<?php

namespace App\Services\Newsletter\Layout;

use App\Repositories\Cms\SiteRepository;
use App\Services\Newsletter\DTOs\NewsletterRenderContext;

/**
 * Resolves {{variable}} placeholders in raw block data arrays before
 * the data is handed to BlockDataFactory and EmailBlockRendererRegistry.
 *
 * Supported syntax:
 *   {{newsletter.title}}
 *   {{newsletter.brand_color|default:#000000}}
 *   {{site.name}}
 *
 * Namespaces:
 *   newsletter.*  — properties on the Newsletter model
 *   site.*        — properties on the Site resolved from context
 *
 * Rules:
 *   - Only string values in the block data tree are processed.
 *   - Nested arrays are traversed recursively.
 *   - If a variable cannot be resolved and no default is given, the
 *     placeholder is left intact so it is visible during debugging.
 *   - If a variable resolves to null and a default is given, the
 *     default is used.
 *   - Resolution is context-free (no DB calls, no side effects).
 */
class LayoutBlockVariableResolver
{
    /**
     * Variable pattern: {{ namespace.property }} or {{ namespace.property|default:value }}
     */
    private const PATTERN = '/\{\{\s*([a-zA-Z_][a-zA-Z0-9_.]*)\s*(?:\|default:([^}]*))?\}\}/';

    public function __construct(private readonly SiteRepository $siteRepository)
    {
    }

    /**
     * Resolve all variable placeholders in a single block's data array.
     *
     * @param array $blockData Raw data array from the layout definition.
     * @param array $variables Flat key→value map of resolved variables.
     * @return array            Block data with placeholders replaced.
     */
    public function resolveBlock(array $blockData, array $variables): array
    {
        return $this->resolveArray($blockData, $variables);
    }

    private function resolveArray(array $data, array $variables): array
    {
        $resolved = [];

        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $resolved[$key] = $this->resolveString($value, $variables);
            } elseif (is_array($value)) {
                $resolved[$key] = $this->resolveArray($value, $variables);
            } else {
                $resolved[$key] = $value;
            }
        }

        return $resolved;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function resolveString(string $value, array $variables): string
    {
        return preg_replace_callback(
            self::PATTERN,
            function (array $matches) use ($variables): string {
                $variableName = trim($matches[1]);
                $default = isset($matches[2]) ? trim($matches[2]) : null;

                $resolved = $variables[$variableName] ?? null;

                if ($resolved !== null && $resolved !== '') {
                    return (string)$resolved;
                }

                if ($default !== null) {
                    return $default;
                }

                // Leave placeholder intact — visible during debugging
                return $matches[0];
            },
            $value
        );
    }

    /**
     * Build the variable map from a render context.
     * This is the only place that knows which context fields map to which
     * variable names — keeping the concern out of the resolver loop itself.
     *
     * @param NewsletterRenderContext $context
     * @return array<string, string|null>
     */
    public function buildVariableMap(NewsletterRenderContext $context): array
    {
        $newsletter = $context->newsletter;
        $site = $this->siteRepository->find($context->siteId);

        $map = [];

        // newsletter.* namespace — expose scalar model attributes
        foreach ($this->newsletterAttributes($newsletter) as $key => $value) {
            $map["newsletter.{$key}"] = $value;
        }

        // site.* namespace
        foreach ($this->siteAttributes($site) as $key => $value) {
            $map["site.{$key}"] = $value;
        }

        return $map;
    }

    /**
     * Expose scalar newsletter attributes as key → value pairs.
     * Only scalar types are exposed — no relations, no arrays.
     */
    private function newsletterAttributes(object $newsletter): array
    {
        $attrs = [];
        $candidates = [
            'id', 'title', 'slug', 'template', 'interval',
            'brand_color', 'brand_secondary_color',
        ];

        foreach ($candidates as $attr) {
            $value = $newsletter->{$attr};
            if ($value !== null && !is_array($value) && !is_object($value)) {
                $attrs[$attr] = (string)$value;
            }
        }

        // Also expose any design_config scalar values under newsletter.*
        $designConfig = $newsletter->design_config;
        if (is_array($designConfig)) {
            foreach ($designConfig as $key => $value) {
                if (is_scalar($value)) {
                    $attrs["design_config.{$key}"] = (string)$value;
                }
            }
        }

        return $attrs;
    }

    /**
     * Expose site attributes as key → value pairs.
     * Accepts null so callers do not need to guard against missing site.
     */
    private function siteAttributes(?object $site): array
    {
        if ($site === null) {
            return [];
        }

        $attrs = [];
        $candidates = ['id', 'name', 'url', 'domain'];

        foreach ($candidates as $attr) {
            $value = $site->{$attr};
            if ($value !== null && !is_array($value) && !is_object($value)) {
                $attrs[$attr] = (string)$value;
            }
        }

        return $attrs;
    }
}