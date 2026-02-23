<?php

namespace App\Tests\Unit\Services\Newsletters;

use App\Services\Newsletter\Branding\CssSanitizer;
use PHPUnit\Framework\TestCase;

class CssSanitizerTest extends TestCase
{
    private CssSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new CssSanitizer();
    }

    public function test_allows_listed_properties(): void
    {
        $css = '.title { color: red; font-size: 16px; padding: 10px; }';

        $result = $this->sanitizer->sanitize($css);

        $this->assertStringContainsString('color', $result);
        $this->assertStringContainsString('font-size', $result);
        $this->assertStringContainsString('padding', $result);
    }

    public function test_blocks_animation_property(): void
    {
        $css = '.title { animation: spin 1s infinite; color: red; }';

        $result = $this->sanitizer->sanitize($css);

        $this->assertStringNotContainsString('animation', $result);
        $this->assertStringContainsString('color', $result);
    }

    public function test_blocks_javascript_in_url(): void
    {
        $css = '.bg { background-color: url("javascript:alert(1)"); }';

        $result = $this->sanitizer->sanitize($css);

        $this->assertStringNotContainsString('javascript', $result);
    }

    public function test_blocks_expression_calls(): void
    {
        $css = '.el { width: expression(alert(1)); color: blue; }';

        $result = $this->sanitizer->sanitize($css);

        $this->assertStringNotContainsString('expression', $result);
        $this->assertStringContainsString('color', $result);
    }

    public function test_strips_script_tags(): void
    {
        $css = '<script>alert("xss")</script>.title { color: red; }';

        $result = $this->sanitizer->sanitize($css);

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('color', $result);
    }

    public function test_blocks_global_body_selector(): void
    {
        $css = 'body { background-color: red; } .title { color: blue; }';

        $result = $this->sanitizer->sanitize($css);

        $this->assertStringNotContainsString('body', $result);
        $this->assertStringContainsString('color', $result);
    }

    public function test_blocks_html_global_selector(): void
    {
        $css = 'html { font-size: 20px; } .text { font-size: 14px; }';

        $result = $this->sanitizer->sanitize($css);

        // html global selector blocked
        $this->assertStringNotContainsString("html {\n", $result);
    }

    public function test_blocks_position_fixed(): void
    {
        $css = '.overlay { position: fixed; color: red; }';

        $result = $this->sanitizer->sanitize($css);

        $this->assertStringNotContainsString('fixed', $result);
    }

    public function test_blocks_at_import(): void
    {
        $css = '@import url("https://evil.com/malicious.css"); .title { color: red; }';

        $result = $this->sanitizer->sanitize($css);

        $this->assertStringNotContainsString('@import', $result);
    }

    public function test_strips_css_comments(): void
    {
        $css = '/* @import evil */ .title { /* color: red; hijack */ color: blue; }';

        $result = $this->sanitizer->sanitize($css);

        $this->assertStringNotContainsString('@import', $result);
        $this->assertStringContainsString('color', $result);
    }

    public function test_sanitize_and_scope_wraps_selectors_with_newsletter_id(): void
    {
        $css = '.title { color: red; }';

        $result = $this->sanitizer->sanitizeAndScope($css, 42);

        $this->assertStringContainsString('#newsletter-42 .title', $result);
        $this->assertStringContainsString('color', $result);
    }

    public function test_sanitize_and_scope_does_not_double_scope(): void
    {
        $css = '.a { color: blue; } .b { padding: 10px; }';

        $result = $this->sanitizer->sanitizeAndScope($css, 5);

        // Each rule should be scoped once
        $this->assertEquals(2, substr_count($result, '#newsletter-5'));
    }

    public function test_returns_empty_string_for_empty_css(): void
    {
        $result = $this->sanitizer->sanitize('');
        $this->assertSame('', $result);
    }

    public function test_blocks_only_disallowed_properties_preserves_allowed(): void
    {
        $css = '.card { color: black; margin: 10px; transition: all 0.3s; font-size: 14px; }';

        $result = $this->sanitizer->sanitize($css);

        $this->assertStringContainsString('color', $result);
        $this->assertStringContainsString('margin', $result);
        $this->assertStringContainsString('font-size', $result);
        $this->assertStringNotContainsString('transition', $result);
    }
}