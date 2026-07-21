<?php

namespace App\Tests\Unit\Services\PublicContent\Routing;

use App\DTO\PublicContent\ResolvedPublicContentRoute;
use App\Enums\PublicContent\PublicContentPageKind;
use App\Services\PublicContent\Routing\PublicContentPageKindClassifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PublicContentPageKindClassifierTest extends TestCase
{
    private PublicContentPageKindClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classifier = new PublicContentPageKindClassifier();
    }

    #[DataProvider('knownTargetProvider')]
    public function test_known_targets_map_to_kinds(string $target, PublicContentPageKind $expected): void
    {
        $kind = $this->classifier->classify(new ResolvedPublicContentRoute(
            target: $target,
            address: '/doc/1',
        ));

        self::assertSame($expected, $kind);
    }

    public static function knownTargetProvider(): array
    {
        return [
            'article_view' => ['article_view', PublicContentPageKind::Article],
            'article' => ['article', PublicContentPageKind::Article],
            'homepage' => ['homepage', PublicContentPageKind::Homepage],
            'home' => ['home', PublicContentPageKind::Homepage],
            'category' => ['category', PublicContentPageKind::Category],
            'review' => ['review', PublicContentPageKind::Review],
            'buying_guide' => ['buying_guide', PublicContentPageKind::BuyingGuide],
            'buying-guide' => ['buying-guide', PublicContentPageKind::BuyingGuide],
            'content' => ['content', PublicContentPageKind::Content],
            'landing-page' => ['landing-page', PublicContentPageKind::LandingPage],
            'landing_page' => ['landing_page', PublicContentPageKind::LandingPage],
        ];
    }

    public function test_unrecognised_target_is_unknown(): void
    {
        $kind = $this->classifier->classify(new ResolvedPublicContentRoute(
            target: 'weird_target',
            address: '/x',
        ));

        self::assertSame(PublicContentPageKind::Unknown, $kind);
    }

    public function test_target_without_address_is_invalid(): void
    {
        $kind = $this->classifier->classify(new ResolvedPublicContentRoute(
            target: 'category',
            address: null,
        ));

        self::assertSame(PublicContentPageKind::Invalid, $kind);
    }

    public function test_article_view_without_address_or_slug_type_is_invalid(): void
    {
        $kind = $this->classifier->classify(new ResolvedPublicContentRoute(
            target: 'article_view',
        ));

        self::assertSame(PublicContentPageKind::Invalid, $kind);
    }

    public function test_article_view_with_slug_and_article_type_is_valid(): void
    {
        $kind = $this->classifier->classify(new ResolvedPublicContentRoute(
            target: 'article_view',
            slug: 'my-story',
            articleType: 'feature',
        ));

        self::assertSame(PublicContentPageKind::Article, $kind);
    }

    public function test_from_page_type_maps_buying_guide(): void
    {
        self::assertSame(
            PublicContentPageKind::BuyingGuide,
            $this->classifier->fromPageType('buying-guide'),
        );
    }
}
