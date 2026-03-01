<?php

namespace App\DTO\Newsletters;

/**
 * Result returned by NewsletterContentResolver::resolve().
 *
 * Carries both the rendered HTML and the page list so that callers
 * never need to re-fetch pages that the resolver already retrieved
 * internally (e.g. for AutoPages newsletters).
 *
 * pages is null for non-AutoPages content types — callers must
 * treat null as "no page list available", not as an error.
 */
final class NewsletterResolveResult
{
    /**
     * @param string $html Rendered newsletter HTML (may be empty string).
     * @param array|null $pages Mapped page array from getPagesForNewsletter(),
     *                          or null when the content type has no pages concept.
     *                          Each element: ['id', 'title', 'subtitle', 'slug']
     */
    public function __construct(
        public readonly string $html,
        public readonly ?array $pages,
    )
    {
    }

    public static function withPages(string $html, array $pages): self
    {
        return new self($html, $pages);
    }

    public static function withoutPages(string $html): self
    {
        return new self($html, null);
    }
}