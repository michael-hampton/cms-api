<?php

namespace App\Tests\Unit\Resources\PublicContent;

use App\DTO\PublicContent\ContentRegion;
use App\DTO\PublicContent\PublicContentDocument;
use App\Resources\PublicContent\PublicContentResource;
use PHPUnit\Framework\TestCase;

final class PublicContentRestrictedResourceTest extends TestCase
{
    public function testRestrictedDocumentExposesAccessAndSafeNamedRegion(): void
    {
        $document = new PublicContentDocument(
            id: 10,
            siteId: 7,
            slug: 'premium-story',
            type: 'article',
            title: 'Premium story',
            summary: 'Safe teaser',
            seo: [],
            taxonomy: ['categories' => [], 'tags' => []],
            regions: [
                new ContentRegion('main', [], '<p>Safe teaser</p>'),
            ],
            access: [
                'can_view' => false,
                'reason' => 'subscription_required',
            ],
        );

        $resource = (new PublicContentResource($document))->toArray();

        self::assertFalse($resource['access']['can_view']);
        self::assertSame('subscription_required', $resource['access']['reason']);
        self::assertSame('<p>Safe teaser</p>', $resource['content']['regions']['main']['rendered_html']);
        self::assertSame([], $resource['content']['regions']['main']['blocks']);
    }
}
