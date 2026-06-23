<?php

namespace App\Tests\Unit\Resources\PublicContent;

use App\DTO\PublicContent\ContentRegion;
use App\DTO\PublicContent\PublicContentComponent;
use App\DTO\PublicContent\PublicContentDocument;
use App\Models\Site;
use App\Resources\PublicContent\PublicContentResource;
use App\Tests\Functional\Controllers\FunctionalTestCase;

final class PublicContentResourceTest extends FunctionalTestCase
{
    public function testItExposesCanonicalBlocksAndComposedComponents(): void
    {
        $result = (new PublicContentResource($this->document()))->toArray();

        self::assertSame('1.1', $result['content']['schema_version']);
        self::assertSame('heading', $result['content']['regions']['main']['blocks'][0]['type']);
        self::assertSame('<h2>Hello</h2>', $result['content']['regions']['main']['rendered_html']);
        self::assertSame('page-actions', $result['content']['components']['header'][0]['type']);
        self::assertSame(40, $result['content']['components']['header'][0]['priority']);
        self::assertTrue($result['content']['components']['header'][0]['stateful']);
        self::assertSame('/api/like', $result['content']['components']['header'][0]['endpoints']['like']);
    }

    public function testItExposesIslandMetadataForStatefulComponents(): void
    {
        $result = (new PublicContentResource($this->document()))->toArray();
        $component = $result['content']['components']['header'][0];

        self::assertSame('visible', $component['hydration']);
        self::assertStringContainsString('data-island="page-actions"', $component['html']);
        self::assertStringContainsString('data-component-id="page-actions"', $component['html']);
        self::assertStringContainsString('data-component-type="page-actions"', $component['html']);
        self::assertStringContainsString('data-stateful="true"', $component['html']);
        self::assertStringContainsString('data-hydration="visible"', $component['html']);
        self::assertStringContainsString('data-props=', $component['html']);
        self::assertStringContainsString('<div class="page-actions"></div>', $component['html']);
    }

    public function testItExposesResolvedDesignTokensAtTheTopLevel(): void
    {
        $result = (new PublicContentResource($this->document()))->toArray();

        self::assertArrayHasKey('design_tokens', $result);
        self::assertArrayHasKey('color', $result['design_tokens']);
        self::assertArrayHasKey('font', $result['design_tokens']);
        self::assertArrayHasKey('brand', $result['design_tokens']);
        self::assertArrayHasKey('primary', $result['design_tokens']['color']);
        self::assertArrayHasKey('heading', $result['design_tokens']['font']);
        self::assertArrayHasKey('heading_color', $result['design_tokens']['brand']);
    }

    public function testItExposesSiteBrandingMetadataInDesignTokens(): void
    {
        $site = Site::find($this->siteId);
        self::assertNotNull($site);

        $site->name = 'Example Publication';
        $site->logo = '/storage/logos/example.svg';
        $site->setSetting('tagline', 'Independent reporting');
        $site->save();

        $result = (new PublicContentResource($this->document()))->toArray();

        self::assertSame('Example Publication', $result['design_tokens']['brand']['site_name']);
        self::assertSame('Independent reporting', $result['design_tokens']['brand']['tagline']);
        self::assertSame('/storage/logos/example.svg', $result['design_tokens']['brand']['logo_url']);
    }

    public function testItSerializesPerSiteDesignTokenOverrides(): void
    {
        $site = Site::find($this->siteId);
        self::assertNotNull($site);

        $site->setSetting('design_tokens', [
            'color' => ['accent' => '#123456'],
            'brand' => [
                'heading_color' => '#222222',
                'newsletter_button_background' => '#333333',
            ],
        ]);
        $site->save();

        $result = (new PublicContentResource($this->document()))->toArray();

        self::assertSame('#123456', $result['design_tokens']['color']['accent']);
        self::assertSame('#222222', $result['design_tokens']['brand']['heading_color']);
        self::assertSame('#333333', $result['design_tokens']['brand']['newsletter_button_background']);
        self::assertArrayHasKey('primary', $result['design_tokens']['color']);
    }

    private function document(): PublicContentDocument
    {
        return new PublicContentDocument(
            id: 10,
            siteId: $this->siteId,
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
                        hydration: PublicContentComponent::HYDRATION_VISIBLE,
                    ),
                ],
            ],
        );
    }
}
