<?php

namespace App\Tests\Unit\Services\PublicContent\Locale;

use App\DTO\PublicContent\Locale\LocaleEdgeRedirectRules;
use App\DTO\PublicContent\Locale\LocaleRule;
use App\DTO\PublicContent\Locale\LocaleRulesArtefact;
use App\Enums\PublicContent\EdgeRedirectReason;
use App\Services\PublicContent\Locale\PublicContentEdgeRedirectResolver;
use PHPUnit\Framework\TestCase;

final class PublicContentEdgeRedirectResolverTest extends TestCase
{
    public function test_disabled_locale_redirects_to_fallback(): void
    {
        $outcome = $this->resolver()->resolve('/ca/news');

        self::assertTrue($outcome->shouldRedirect);
        self::assertSame(EdgeRedirectReason::DisabledLocale, $outcome->reason);
        self::assertSame('/', $outcome->targetPath);
    }

    public function test_doubled_region_collapses(): void
    {
        $outcome = $this->resolver()->resolve('/uk/uk/news/story');

        self::assertTrue($outcome->shouldRedirect);
        self::assertSame(EdgeRedirectReason::DoubledRegion, $outcome->reason);
        self::assertSame('/uk/news/story', $outcome->targetPath);
    }

    public function test_global_home_redirects_to_regional_when_preferred(): void
    {
        $outcome = $this->resolver()->resolve('/', 'GB');

        self::assertTrue($outcome->shouldRedirect);
        self::assertSame(EdgeRedirectReason::RegionalHome, $outcome->reason);
        self::assertSame('/uk', $outcome->targetPath);
    }

    public function test_regional_home_redirects_to_global_when_not_preferred(): void
    {
        $resolver = $this->resolver(new LocaleEdgeRedirectRules(
            preferRegionalHome: false,
            globalHomePath: '/',
        ));

        $outcome = $resolver->resolve('/uk');

        self::assertTrue($outcome->shouldRedirect);
        self::assertSame(EdgeRedirectReason::GlobalHome, $outcome->reason);
        self::assertSame('/', $outcome->targetPath);
    }

    public function test_no_redirect_for_normal_regional_path(): void
    {
        $outcome = $this->resolver()->resolve('/uk/news/story');

        self::assertFalse($outcome->shouldRedirect);
        self::assertSame(EdgeRedirectReason::None, $outcome->reason);
        self::assertNull($outcome->targetPath);
    }

    private function resolver(?LocaleEdgeRedirectRules $edge = null): PublicContentEdgeRedirectResolver
    {
        $artefact = new LocaleRulesArtefact(
            schemaVersion: 1,
            locales: [
                new LocaleRule('en-GB', 'en', 'GB', 'uk', true),
                new LocaleRule('en-US', 'en', 'US', 'us', true),
                new LocaleRule('en-CA', 'en', 'CA', 'ca', false),
            ],
            sourcePath: 'test',
            artefactVersion: 'test-v1',
            edgeRedirects: $edge ?? new LocaleEdgeRedirectRules(),
        );

        return new PublicContentEdgeRedirectResolver($artefact);
    }
}
