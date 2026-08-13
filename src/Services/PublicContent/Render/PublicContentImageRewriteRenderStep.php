<?php

namespace App\Services\PublicContent\Render;

use App\Services\PublicContent\Images\PublicContentImageUrlTransformer;

/**
 * Post-shell step: rewrite image URLs in finished HTML. Owns no policy beyond
 * delegating to {@see PublicContentImageUrlTransformer}.
 */
final class PublicContentImageRewriteRenderStep implements PublicContentRenderStep
{
    public function __construct(
        private readonly PublicContentImageUrlTransformer $imageUrls,
    ) {
    }

    public function name(): string
    {
        return 'image_rewrite';
    }

    public function handle(PublicContentRenderContext $context): PublicContentRenderContext
    {
        $siteKey = (string) ($context->attributes['site_key'] ?? '');

        if ($siteKey === '' || $context->shellHtml === '') {
            return $context;
        }

        $context->shellHtml = $this->imageUrls->transformHtml($context->shellHtml, $siteKey);

        return $context;
    }
}
