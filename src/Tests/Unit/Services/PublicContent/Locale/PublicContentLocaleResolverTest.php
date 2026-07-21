<?php

namespace App\Tests\Unit\Services\PublicContent\Locale;

use App\Models\Territory;
use App\Services\PublicContent\Locale\PublicContentLocaleResolver;
use Mockery;
use PHPUnit\Framework\TestCase;

final class PublicContentLocaleResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_splits_locale_tag_into_language_and_region(): void
    {
        $territory = Mockery::mock(Territory::class)->makePartial();
        $territory->code = 'en-GB';

        $context = (new PublicContentLocaleResolver())->fromTerritory($territory);

        self::assertSame('en', $context->language);
        self::assertSame('GB', $context->region);
        self::assertSame('en-GB', $context->localeTag());
    }

    public function test_region_only_code(): void
    {
        $territory = Mockery::mock(Territory::class)->makePartial();
        $territory->code = 'US';

        $context = (new PublicContentLocaleResolver())->fromTerritory($territory);

        self::assertNull($context->language);
        self::assertSame('US', $context->region);
    }
}
