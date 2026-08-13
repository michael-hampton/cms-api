<?php

namespace App\Tests\Unit\Services\PublicContent\Images\Transform;

use App\Services\PublicContent\Images\Transform\SupportedImageFormat;
use PHPUnit\Framework\TestCase;

final class SupportedImageFormatTest extends TestCase
{
    public function test_pattern_joins_every_case_value_with_a_pipe(): void
    {
        self::assertSame('jpg|jpeg|png|gif|webp', SupportedImageFormat::pattern());
    }

    public function test_pattern_is_usable_as_a_regex_alternation(): void
    {
        $regex = '/\.(' . SupportedImageFormat::pattern() . ')$/i';

        self::assertSame(1, preg_match($regex, 'photo.WEBP'));
        self::assertSame(0, preg_match($regex, 'document.pdf'));
    }
}