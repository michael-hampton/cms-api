<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Services\OpenCollab\ReadabilityAnalyser;
use PHPUnit\Framework\TestCase;

class ReadabilityAnalyserTest extends TestCase
{
    private $analyser;

    public function test_it_returns_zero_for_empty_content()
    {
        $this->assertEquals(0.0, $this->analyser->analyse(''));
        $this->assertEquals(0.0, $this->analyser->analyse('   '));
    }

    public function test_it_scores_simple_text_as_very_easy()
    {
        // "The cat sat on the mat."
        // 6 words, 1 sentence, 6 syllables. ASL=6, ASW=1.
        // Score = 206.835 - (1.015 * 6) - (84.6 * 1) = 116.145 (capped at 100)
        $score = $this->analyser->analyse('The cat sat on the mat.');
        $this->assertGreaterThanOrEqual(90, $score);
    }

    public function test_it_scores_complex_text_lower()
    {
        $complexText = "The socioeconomic implications of institutionalized infrastructure are overwhelmingly substantial.";
        $score = $this->analyser->analyse($complexText);

        $this->assertLessThan(40, $score);
    }

    public function test_it_strips_html_before_analysis()
    {
        $html = "<h1>Title</h1><p>This is a simple sentence.</p>";
        $text = "Title This is a simple sentence.";

        $this->assertEquals(
            $this->analyser->analyse($text),
            $this->analyser->analyse($html)
        );
    }

    protected function setUp(): void
    {
        $this->analyser = new ReadabilityAnalyser();
    }
}