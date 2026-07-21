<?php

namespace App\DTO\PublicContent;

/**
 * A resolved route as handed to {@see \App\Services\PublicContent\Routing\PublicContentPageKindClassifier}.
 *
 * `target` is the free-form route target string (e.g. article_view). Validity
 * expectations are checked at read time: a target without an address, or an
 * article_view without a document address or both slug and article type, is invalid.
 */
final readonly class ResolvedPublicContentRoute
{
    public function __construct(
        public string $target,
        public ?string $address = null,
        public ?string $slug = null,
        public ?string $articleType = null,
        public ?string $pageType = null,
    ) {
    }

    public function hasAddress(): bool
    {
        return $this->address !== null && $this->address !== '';
    }

    public function hasSlugAndArticleType(): bool
    {
        return $this->slug !== null && $this->slug !== ''
            && $this->articleType !== null && $this->articleType !== '';
    }
}
