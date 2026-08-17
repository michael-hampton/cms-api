<?php

namespace App\Tests\Unit\DTO\PublicContent;

use App\DTO\PublicContent\PublicContentLocaleContext;
use App\DTO\PublicContent\PublicContentLocaleDefaultResult;
use PHPUnit\Framework\TestCase;

final class PublicContentLocaleContextDefaultTest extends TestCase
{
    public function test_context_with_no_language_is_missing(): void
    {
        $context = new PublicContentLocaleContext(language: null, region: 'GB');

        self::assertTrue($context->isMissing());
    }

    public function test_context_with_blank_language_is_missing(): void
    {
        $context = new PublicContentLocaleContext(language: '   ', region: 'GB');

        self::assertTrue($context->isMissing());
    }

    public function test_context_with_language_is_not_missing_regardless_of_region(): void
    {
        $context = new PublicContentLocaleContext(language: 'fr', region: null);

        self::assertFalse($context->isMissing());
    }

    public function test_missing_locale_gets_default_language_applied(): void
    {
        $context = new PublicContentLocaleContext();

        $result = $context->withDefaultLanguage('en');

        self::assertInstanceOf(PublicContentLocaleDefaultResult::class, $result);
        self::assertTrue($result->defaultApplied);
        self::assertSame('en', $result->context->language);
    }

    public function test_default_language_preserves_an_existing_region(): void
    {
        $context = new PublicContentLocaleContext(language: null, region: 'GB');

        $result = $context->withDefaultLanguage('en');

        self::assertTrue($result->defaultApplied);
        self::assertSame('en', $result->context->language);
        self::assertSame('GB', $result->context->region);
        self::assertSame('en-GB', $result->context->localeTag());
    }

    public function test_existing_locale_is_left_untouched(): void
    {
        $context = new PublicContentLocaleContext(language: 'fr', region: 'FR');

        $result = $context->withDefaultLanguage('en');

        self::assertFalse($result->defaultApplied);
        self::assertSame($context, $result->context);
        self::assertSame('fr', $result->context->language);
    }

    public function test_applied_and_unchanged_factories_set_expected_flag(): void
    {
        $context = new PublicContentLocaleContext(language: 'en');

        $applied = PublicContentLocaleDefaultResult::applied($context);
        $unchanged = PublicContentLocaleDefaultResult::unchanged($context);

        self::assertTrue($applied->defaultApplied);
        self::assertFalse($unchanged->defaultApplied);
        self::assertSame($context, $applied->context);
        self::assertSame($context, $unchanged->context);
    }
}
