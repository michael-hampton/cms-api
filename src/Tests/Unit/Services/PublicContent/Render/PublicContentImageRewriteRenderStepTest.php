<?php

namespace App\Tests\Unit\Services\PublicContent\Render;

use App\Services\PublicContent\Images\PublicContentImageUrlResolver;
use App\Services\PublicContent\Images\PublicContentImageUrlSigner;
use App\Services\PublicContent\Images\PublicContentImageUrlTransformer;
use App\Services\PublicContent\Render\PublicContentImageRewriteRenderStep;
use App\Services\PublicContent\Render\PublicContentRenderContext;
use App\Services\PublicContent\Render\PublicContentRenderPipeline;
use PHPUnit\Framework\TestCase;

final class PublicContentImageRewriteRenderStepTest extends TestCase
{
    public function test_pipeline_runs_image_rewrite_after_shell_build(): void
    {
        $pipeline = new PublicContentRenderPipeline();
        $pipeline->registerPost(new PublicContentImageRewriteRenderStep(
            new PublicContentImageUrlTransformer(
                new PublicContentImageUrlResolver(new PublicContentImageUrlSigner()),
            ),
        ));

        $result = $pipeline->run(
            new PublicContentRenderContext(attributes: ['site_key' => 'guitar-world']),
            static function (PublicContentRenderContext $ctx): PublicContentRenderContext {
                $ctx->shellHtml = '<p>shell</p>';

                return $ctx;
            },
        );

        self::assertSame(['build_shell', 'image_rewrite'], $result->orderedStepNames());
        self::assertSame('<p>shell</p>', $result->shellHtml);
    }
}
