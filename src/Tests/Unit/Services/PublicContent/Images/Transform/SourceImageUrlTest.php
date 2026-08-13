<?php

namespace App\Tests\Unit\Services\PublicContent\Images\Transform;

use App\Services\PublicContent\Images\Transform\SourceImageUrl;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SourceImageUrlTest extends TestCase
{
    public function test_parses_origin_folder_filename_and_extension(): void
    {
        $parsed = SourceImageUrl::parse('https://cdn.example.com/folder/sub/photo.jpg');

        self::assertSame('https://cdn.example.com', $parsed->origin);
        self::assertSame('/folder/sub/', $parsed->folder);
        self::assertSame('photo', $parsed->filename);
        self::assertSame('jpg', $parsed->extension);
    }

    public function test_rejects_invalid_url_rather_than_silently_accepting(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('absolute http(s) URL');

        SourceImageUrl::parse('/storage/uploads/photo.jpg');
    }

    public function test_rejects_unsupported_extension(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('extension');

        SourceImageUrl::parse('https://cdn.example.com/folder/photo.bmp');
    }
}
