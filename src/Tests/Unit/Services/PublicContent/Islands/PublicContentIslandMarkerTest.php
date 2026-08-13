<?php

namespace App\Tests\Unit\Services\PublicContent\Islands;

use App\Services\PublicContent\Islands\PublicContentIslandMarker;
use PHPUnit\Framework\TestCase;

final class PublicContentIslandMarkerTest extends TestCase
{
    public function test_marker_definition_lives_in_one_place(): void
    {
        $html = PublicContentIslandMarker::placeholder('recirculation');

        self::assertSame(
            '<public-content-island data-island-id="recirculation"></public-content-island>',
            $html,
        );
        self::assertSame('public-content-island', PublicContentIslandMarker::ELEMENT);
        self::assertContains('data-pods-island-marker', PublicContentIslandMarker::PARITY_ATTRIBUTES);
    }
}
