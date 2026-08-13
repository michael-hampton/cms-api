<?php

namespace App\Tests\Unit\Services\PublicContent\Render;

use App\Services\PublicContent\Render\PublicContentRenderContext;
use App\Services\PublicContent\Render\PublicContentRenderPipeline;
use App\Services\PublicContent\Render\PublicContentRenderStep;
use PHPUnit\Framework\TestCase;

final class PublicContentRenderPipelineTest extends TestCase
{
    public function test_pre_and_post_slots_run_in_explicit_order_around_shell_build(): void
    {
        $pipeline = new PublicContentRenderPipeline();
        $pipeline->registerPre($this->step('locale-stub', static function (PublicContentRenderContext $ctx): PublicContentRenderContext {
            $ctx->attributes['locale'] = 'en-GB';

            return $ctx;
        }));
        $pipeline->registerPost($this->step('link-rewrite-stub', static function (PublicContentRenderContext $ctx): PublicContentRenderContext {
            $ctx->shellHtml .= '<!--links-->';

            return $ctx;
        }));
        $pipeline->registerPost($this->step('image-rewrite-stub', static function (PublicContentRenderContext $ctx): PublicContentRenderContext {
            $ctx->shellHtml .= '<!--images-->';

            return $ctx;
        }));

        $result = $pipeline->run(
            new PublicContentRenderContext(),
            static function (PublicContentRenderContext $ctx): PublicContentRenderContext {
                $ctx->shellHtml = '<html>shell</html>';

                return $ctx;
            },
        );

        self::assertSame(
            ['locale-stub', 'build_shell', 'link-rewrite-stub', 'image-rewrite-stub'],
            $result->orderedStepNames(),
        );
        self::assertSame('en-GB', $result->attributes['locale']);
        self::assertSame('<html>shell</html><!--links--><!--images-->', $result->shellHtml);
        self::assertSame(['locale-stub'], $pipeline->preStepNames());
        self::assertSame(['link-rewrite-stub', 'image-rewrite-stub'], $pipeline->postStepNames());
    }

    private function step(string $name, callable $handle): PublicContentRenderStep
    {
        return new class ($name, $handle) implements PublicContentRenderStep {
            public function __construct(
                private readonly string $stepName,
                private readonly mixed $handler,
            ) {
            }

            public function name(): string
            {
                return $this->stepName;
            }

            public function handle(PublicContentRenderContext $context): PublicContentRenderContext
            {
                return ($this->handler)($context);
            }
        };
    }
}
