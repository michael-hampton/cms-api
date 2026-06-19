<?php

namespace App\Tests\Unit\Resources\PublicContent;

use App\DTO\PublicContent\ContentRegion;
use App\DTO\PublicContent\PublicContentComponent;
use App\DTO\PublicContent\PublicContentDocument;
use App\Resources\PublicContent\PublicContentResource;
use PHPUnit\Framework\TestCase;

final class PublicContentResourceTest extends TestCase
{
    public function testItExposesCanonicalBlocksComposedComponentsAndDesignTokens(): void
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
                ]], '<h2>Hello</h2>'),
                'sidebar' => new ContentRegion('sidebar', [], ''),
            ],
            components: [
                'header' => [
                    new PublicContentComponent(
                        id: 'page-actions',
                        type: 'page-actions',
                        region: 'header',
                        priority: 40,
                        html: '<div class="page-actions"></div>',
                        scripts: ['page-actions.js'],
                        endpoints: ['like' => '/api/like'],
                        stateful: true,
                    ),
                ],
            ],
        );

        $result = (new PublicContentResource($document))->toArray();

        self::assertSame('1.1', $result['content']['schema_version']);
        self::assertSame('heading', $result['content']['regions']['main']['blocks'][0]['type']);
        self::assertSame('<h2>Hello</h2>', $result['content']['regions']['main']['rendered_html']);
        self::assertSame('page-actions', $result['content']['components']['header'][0]['type']);
        self::assertSame(40, $result['content']['components']['header'][0]['priority']);
        self::assertTrue($result['content']['components']['header'][0]['stateful']);
        self::assertSame('/api/like', $result['content']['components']['header'][0]['endpoints']['like']);
        self::assertArrayHasKey('design_tokens', $result);
        self::assertArrayHasKey('color', $result['design_tokens']);
        self::assertArrayHasKey('primary', $result['design_tokens']['color']);
    }
}
