<?php

namespace App\Tests\Unit\Services\PublicContent\Islands;

use App\Services\PublicContent\Islands\PublicContentIslandFiller;
use App\Services\PublicContent\Islands\PublicContentIslandMarker;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PublicContentIslandFillerTest extends TestCase
{
    public function test_fills_markers_honouring_reserved_element_shape(): void
    {
        $shell = '<main><h1>Title</h1>'
            . PublicContentIslandMarker::placeholder('recirculation')
            . PublicContentIslandMarker::placeholder('comments')
            . '</main>';

        $html = (new PublicContentIslandFiller())->fill($shell, [
            'recirculation' => '<aside>More reads</aside>',
            'comments' => '<section>Talk</section>',
        ]);

        self::assertStringContainsString('<aside>More reads</aside>', $html);
        self::assertStringContainsString('<section>Talk</section>', $html);
        self::assertStringNotContainsString(PublicContentIslandMarker::ELEMENT, $html);
    }

    public function test_missing_fragment_uses_defined_fallback(): void
    {
        $shell = PublicContentIslandMarker::placeholder('ads');
        $html = (new PublicContentIslandFiller())->fill($shell, []);

        self::assertSame(PublicContentIslandFiller::MISSING_FALLBACK, trim($html));
    }

    public function test_single_island_failure_does_not_blank_page(): void
    {
        $shell = '<div>'
            . PublicContentIslandMarker::placeholder('ok')
            . PublicContentIslandMarker::placeholder('broken')
            . '</div>';

        $html = (new PublicContentIslandFiller())->fill($shell, [
            'ok' => '<p>Good</p>',
            'broken' => static function (): string {
                throw new RuntimeException('island exploded');
            },
        ]);

        self::assertStringContainsString('<p>Good</p>', $html);
        self::assertStringContainsString(PublicContentIslandFiller::FAILED_FALLBACK, $html);
        self::assertStringNotContainsString('island exploded', $html);
    }
}
