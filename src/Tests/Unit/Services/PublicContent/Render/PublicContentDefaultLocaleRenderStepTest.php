<?php

namespace App\Tests\Unit\Services\PublicContent\Render;

use App\DTO\PublicContent\PublicContentLocaleContext;
use App\Events\PublicContent\PublicContentDefaultLocaleApplied;
use App\Framework\Events\EventDispatcher;
use App\Services\PublicContent\Render\PublicContentDefaultLocaleRenderStep;
use App\Services\PublicContent\Render\PublicContentRenderContext;
use App\Services\PublicContent\Render\PublicContentRenderPipeline;
use PHPUnit\Framework\TestCase;

final class PublicContentDefaultLocaleRenderStepTest extends TestCase
{
    public function test_missing_locale_is_filled_with_default_and_reported(): void
    {
        $events = $this->createMock(EventDispatcher::class);
        $events->expects($this->once())
            ->method('dispatch')
            ->with(self::callback(static function (PublicContentDefaultLocaleApplied $event): bool {
                return $event->siteId === 4
                    && $event->pageId === 123
                    && $event->defaultLanguage === 'en';
            }));

        $step = new PublicContentDefaultLocaleRenderStep($events, 'en');

        $context = $step->handle(new PublicContentRenderContext(attributes: [
            'locale_context' => new PublicContentLocaleContext(),
            'site_id' => 4,
            'page_id' => 123,
        ]));

        $localeContext = $context->attributes['locale_context'];

        self::assertInstanceOf(PublicContentLocaleContext::class, $localeContext);
        self::assertSame('en', $localeContext->language);
        self::assertTrue($context->attributes['default_locale_applied']);
    }

    public function test_existing_locale_is_left_untouched_and_no_event_dispatched(): void
    {
        $events = $this->createMock(EventDispatcher::class);
        $events->expects($this->never())->method('dispatch');

        $step = new PublicContentDefaultLocaleRenderStep($events, 'en');

        $context = $step->handle(new PublicContentRenderContext(attributes: [
            'locale_context' => new PublicContentLocaleContext(language: 'fr', region: 'FR'),
            'site_id' => 4,
            'page_id' => 123,
        ]));

        $localeContext = $context->attributes['locale_context'];

        self::assertInstanceOf(PublicContentLocaleContext::class, $localeContext);
        self::assertSame('fr', $localeContext->language);
        self::assertSame('FR', $localeContext->region);
        self::assertFalse($context->attributes['default_locale_applied']);
    }

    public function test_missing_context_attribute_defaults_to_an_empty_locale_context(): void
    {
        $events = $this->createMock(EventDispatcher::class);
        $events->expects($this->once())->method('dispatch');

        $step = new PublicContentDefaultLocaleRenderStep($events, 'en');

        $context = $step->handle(new PublicContentRenderContext());

        self::assertSame('en', $context->attributes['locale_context']->language);
        self::assertTrue($context->attributes['default_locale_applied']);
    }

    public function test_step_runs_as_a_pre_step_ahead_of_shell_build_in_the_pipeline(): void
    {
        $events = $this->createMock(EventDispatcher::class);
        $events->expects($this->once())->method('dispatch');

        $pipeline = new PublicContentRenderPipeline();
        $pipeline->registerPre(new PublicContentDefaultLocaleRenderStep($events, 'en'));

        $result = $pipeline->run(
            new PublicContentRenderContext(attributes: [
                'locale_context' => new PublicContentLocaleContext(),
                'site_id' => 1,
                'page_id' => 2,
            ]),
            static function (PublicContentRenderContext $ctx): PublicContentRenderContext {
                // Shell build reads the already-normalised locale.
                $ctx->attributes['shell_locale_seen'] = $ctx->attributes['locale_context']->language;

                return $ctx;
            },
        );

        self::assertSame(['default_locale', 'build_shell'], $result->orderedStepNames());
        self::assertSame('en', $result->attributes['shell_locale_seen']);
    }
}
