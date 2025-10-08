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
        $this->assertSame(4, $parsed['word_count']);
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
        $parsedData = [
            'url' => 'https://www.youtube.com/watch?v=test',
            'embed_url' => 'https://www.youtube.com/embed/test',
            'platform' => 'youtube',
            'caption' => 'The final test.',
            'formatted_caption' => 'The final test.',
            'has_caption' => true
        ];
        $html = $parser->generateHtml($parsedData);

        $this->assertStringContainsString('<div class="video-block video-platform-youtube">', $html);
        $this->assertStringContainsString('<iframe src="https://www.youtube.com/embed/test"', $html);
        $this->assertStringContainsString('<div class="video-caption">The final test.</div>', $html);

        // Test fallback
        $parsedData['embed_url'] = null;
        $htmlFallback = $parser->generateHtml($parsedData);
        $this->assertStringContainsString('<div class="video-fallback">', $htmlFallback);
        $this->assertStringContainsString('<a href="https://www.youtube.com/watch?v=test"', $htmlFallback);
    }
}