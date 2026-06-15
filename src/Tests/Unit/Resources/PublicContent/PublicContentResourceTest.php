<?php

namespace App\Tests\Unit\Resources\PublicContent;

use App\DTO\PublicContent\ContentRegion;
use App\DTO\PublicContent\PublicContentDocument;
use App\Resources\PublicContent\PublicContentResource;
use PHPUnit\Framework\TestCase;

final class PublicContentResourceTest extends TestCase
{
    public function test_it_exposes_structured_blocks_as_canonical_with_rendered_html_fallback(): void
    {
        $document = new PublicContentDocument(
            id: 10,
            siteId: 2,
            slug: 'example-page',
            type: 'article',
            title: 'Example page',
            summary: 'Example summary',
            seo: ['meta_title' => 'Example page'],
            taxonomy: ['categories' => [], 'tags' => []],
            regions: [
                'main' => new ContentRegion('main', [[
                    'id' => 99,
                    'type' => 'heading',
                    'order' => 1,
                    'data' => ['text' => 'Hello'],
                    'rendered_html' => '<h2>Hello</h2>',
                ]], '<h2>Hello</h2>'),
                'sidebar' => new ContentRegion('sidebar', [], ''),
            ],
        );

        $result = (new PublicContentResource($document))->toArray();

        self::assertSame('1.0', $result['content']['schema_version']);
        self::assertSame('heading', $result['content']['regions']['main']['blocks'][0]['type']);
        self::assertSame(['text' => 'Hello'], $result['content']['regions']['main']['blocks'][0]['data']);
        self::assertSame('<h2>Hello</h2>', $result['content']['regions']['main']['rendered_html']);
        self::assertSame([], $result['content']['regions']['sidebar']['blocks']);
    }
}
