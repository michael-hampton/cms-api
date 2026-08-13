<?php

namespace App\Tests\Unit\Services\PublicContent\Images;

use App\Services\PublicContent\Images\PublicContentMissingImageFallback;
use PHPUnit\Framework\TestCase;

final class PublicContentMissingImageFallbackTest extends TestCase
{
    public function test_onerror_handler_clears_the_handler_and_swaps_in_the_fallback_url(): void
    {
        $fallback = new PublicContentMissingImageFallback();

        self::assertSame(
            "this.onerror=null;this.src='/public/images/fallback'",
            $fallback->onerrorHandler(),
        );
    }
}