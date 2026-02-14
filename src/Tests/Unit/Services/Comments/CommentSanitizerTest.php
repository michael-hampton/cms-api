<?php

namespace App\Tests\Unit\Services\Comments;

use App\Services\Members\Comments\CommentSanitizer;
use PHPUnit\Framework\TestCase;

class CommentSanitizerTest extends TestCase
{
    private CommentSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new CommentSanitizer();
    }

    public function testSanitizeRemovesScripts()
    {
        $content = 'Hello <script>alert("xss")</script> world!';
        $result = $this->sanitizer->sanitize($content);

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('Hello', $result);
        $this->assertStringContainsString('world!', $result);
    }

    public function testSanitizeAllowsSafeTags()
    {
        $content = 'Hello <strong>bold</strong> and <em>italic</em> text';
        $result = $this->sanitizer->sanitize($content);

        $this->assertStringContainsString('<strong>bold</strong>', $result);
        $this->assertStringContainsString('<em>italic</em>', $result);
    }

    public function testSanitizeRemovesUnsafeTags()
    {
        $content = 'Hello <div>div</div> <span>span</span>';
        $result = $this->sanitizer->sanitize($content);

        $this->assertStringNotContainsString('<div>', $result);
        $this->assertStringNotContainsString('<span>', $result);
        $this->assertStringContainsString('div', $result);
        $this->assertStringContainsString('span', $result);
    }

    public function testSanitizeSanitizesUrlsInLinks()
    {
        $content = '<a href="javascript:alert(\'xss\')">Click</a>';
        $result = $this->sanitizer->sanitize($content);

        $this->assertStringNotContainsString('javascript:', $result);
    }

    public function testSanitizeTrimsWhitespace()
    {
        $content = '   Hello world!   ';
        $result = $this->sanitizer->sanitize($content);

        $this->assertEquals('Hello world!', $result);
    }

    public function testSanitizeHandlesEmptyString()
    {
        $result = $this->sanitizer->sanitize('');
        $this->assertEquals('', $result);
    }

    public function testSanitizeAllowsParagraphsAndBreaks()
    {
        $content = '<p>Paragraph</p><br>Break';
        $result = $this->sanitizer->sanitize($content);

        $this->assertStringContainsString('<p>Paragraph</p>', $result);
        $this->assertStringContainsString('<br>', $result);
    }

    public function testSanitizeAllowsLinks()
    {
        $content = '<a href="https://example.com">Link</a>';
        $result = $this->sanitizer->sanitize($content);

        $this->assertStringContainsString('<a href="https://example.com"', $result);
    }
}