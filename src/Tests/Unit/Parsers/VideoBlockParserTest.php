<?php

namespace App\Tests\Unit\Parsers;

use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Framework\Validation\Rules\UrlRule;
use App\Parsers\VideoBlockParser;
use PHPUnit\Framework\TestCase;

class VideoBlockParserTest extends TestCase
{
    public function testVideoParserGetType(): void
    {
        $parser = new VideoBlockParser();
        $this->assertSame('video', $parser->getType());
    }

    public function testVideoParserGetValidationRules(): void
    {
        $parser = new VideoBlockParser();
        $rules = $parser->getValidationRules();

        $this->assertArrayHasKey('url', $rules);
        $this->assertContainsOnlyInstancesOf(RequiredRule::class, array_filter($rules['url'], fn($r) => $r instanceof RequiredRule));
        $this->assertContainsOnlyInstancesOf(UrlRule::class, array_filter($rules['url'], fn($r) => $r instanceof UrlRule));

        $this->assertArrayHasKey('caption', $rules);
        $this->assertContainsOnlyInstancesOf(MaxLengthRule::class, array_filter($rules['caption'], fn($r) => $r instanceof MaxLengthRule));
    }

    public function testVideoParserParseYouTube(): void
    {
        $parser = new VideoBlockParser();
        $data = [
            'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'caption' => 'A classic music video.'
        ];
        $parsed = $parser->parse($data);

        $this->assertSame('youtube', $parsed['platform']);
        $this->assertSame('dQw4w9WgXcQ', $parsed['video_id']);
        $this->assertStringContainsString('youtube.com/embed/dQw4w9WgXcQ', $parsed['embed_url']);
        $this->assertTrue($parsed['has_caption']);
    }

    public function testVideoParserParseVimeo(): void
    {
        $parser = new VideoBlockParser();
        $data = [
            'url' => 'https://vimeo.com/123456789',
            'caption' => ''
        ];
        $parsed = $parser->parse($data);

        $this->assertSame('vimeo', $parsed['platform']);
        $this->assertSame('123456789', $parsed['video_id']);
        $this->assertStringContainsString('player.vimeo.com/video/123456789', $parsed['embed_url']);
        $this->assertFalse($parsed['has_caption']);
    }

    public function testVideoParserGenerateHtml(): void
    {
        $parser = new VideoBlockParser();

        // 1. YouTube iframe
        $youtubeData = [
            'url' => 'https://www.youtube.com/watch?v=test',
            'embed_url' => null,
            'platform' => 'youtube',
            'caption' => 'YouTube test',
            'has_caption' => true
        ];
        $htmlYoutube = $parser->generateHtml($youtubeData);

        $this->assertStringContainsString('<iframe src="https://www.youtube.com/embed/test"', $htmlYoutube);
        $this->assertStringContainsString('<div class="video-caption">YouTube test</div>', $htmlYoutube);

        // 2. Vimeo iframe
        $vimeoData = [
            'url' => 'https://vimeo.com/123456',
            'embed_url' => null,
            'platform' => 'vimeo',
            'caption' => 'Vimeo test',
            'has_caption' => true
        ];
        $htmlVimeo = $parser->generateHtml($vimeoData);

        $this->assertStringContainsString('<iframe src="https://player.vimeo.com/video/123456"', $htmlVimeo);
        $this->assertStringContainsString('<div class="video-caption">Vimeo test</div>', $htmlVimeo);

        // 3. Fallback link for unknown platform
        $fallbackData = [
            'url' => 'https://www.example.com/video.mp4',
            'embed_url' => null,
            'platform' => 'unknown',
            'caption' => 'Fallback test',
            'has_caption' => true
        ];
        $htmlFallback = $parser->generateHtml($fallbackData);

        $this->assertStringContainsString('<a href="https://www.example.com/video.mp4"', $htmlFallback);
        $this->assertStringContainsString('<div class="video-caption">Fallback test</div>', $htmlFallback);
    }

}