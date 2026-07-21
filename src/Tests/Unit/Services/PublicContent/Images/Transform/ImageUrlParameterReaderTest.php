<?php

namespace App\Tests\Unit\Services\PublicContent\Images\Transform;

use App\Services\PublicContent\Images\Transform\ImageTransformOptions;
use App\Services\PublicContent\Images\Transform\ImageUrlParameterReader;
use App\Services\PublicContent\Images\Transform\ImageUrlStyle;
use PHPUnit\Framework\TestCase;

final class ImageUrlParameterReaderTest extends TestCase
{
    public function test_reads_simple_width_and_quality(): void
    {
        $params = (new ImageUrlParameterReader())->read(
            'https://cdn.example.com/folder/photo-w800-q80.jpg',
        );

        self::assertSame(ImageUrlStyle::Simple, $params->style);
        self::assertSame(800, $params->width);
        self::assertSame(80, $params->quality);
        self::assertSame('https://cdn.example.com/folder/photo.jpg', $params->baseUrl);
    }

    public function test_reads_rich_crop_width_and_quality(): void
    {
        $params = (new ImageUrlParameterReader())->read(
            'https://cdn.example.com/v2/t:10,l:20,cw:300,ch:200,q:70,w:640/folder/photo.jpg.webp',
        );

        self::assertSame(ImageUrlStyle::Rich, $params->style);
        self::assertSame(640, $params->width);
        self::assertSame(70, $params->quality);
        self::assertSame(['t' => 10, 'l' => 20, 'cw' => 300, 'ch' => 200], $params->crop);
        self::assertSame('https://cdn.example.com/folder/photo.jpg', $params->baseUrl);
    }

    public function test_plain_url_has_no_style(): void
    {
        $params = (new ImageUrlParameterReader())->read('https://cdn.example.com/folder/photo.jpg');

        self::assertNull($params->style);
        self::assertNull($params->width);
        self::assertSame('https://cdn.example.com/folder/photo.jpg', $params->baseUrl);
    }
}
