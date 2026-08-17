<?php

namespace App\DTO\PublicContent;

/**
 * Outcome of filling in the single configured default locale for a page.
 * Named factories make "was the default applied?" explicit at every call
 * site rather than leaving it implicit in a boolean flag.
 */
final readonly class PublicContentLocaleDefaultResult
{
    private function __construct(
        public PublicContentLocaleContext $context,
        public bool $defaultApplied,
    ) {
    }

    public static function applied(PublicContentLocaleContext $context): self
    {
        return new self($context, true);
    }

    public static function unchanged(PublicContentLocaleContext $context): self
    {
        return new self($context, false);
    }
}
