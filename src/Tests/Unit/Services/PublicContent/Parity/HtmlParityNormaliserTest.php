<?php

namespace App\Tests\Unit\Services\PublicContent\Parity;

use App\Services\PublicContent\Parity\HtmlParityNormaliser;
use PHPUnit\Framework\TestCase;

final class HtmlParityNormaliserTest extends TestCase
{
    public function testParserUsesPinnedBrowserStyleErrorRecoverySurface(): void
    {
        $normaliser = new HtmlParityNormaliser();

        $recovered = $normaliser->normalise('<ul><li>One<li>Two</ul>');
        $explicit = $normaliser->normalise('<ul><li>One</li><li>Two</li></ul>');

        self::assertSame(HtmlParityNormaliser::PARSER_VERSION, $recovered->passReports['parse_to_dom']['parser_version']);
        self::assertSame($explicit->html, $recovered->html);
    }

    /** @dataProvider noiseHandledByPassProvider */
    public function testNoiseHandledByPassDoesNotSurviveNormalisation(string $passName, string $left, string $right): void
    {
        $normaliser = new HtmlParityNormaliser();

        $leftResult = $normaliser->normalise($left);
        $rightResult = $normaliser->normalise($right);

        self::assertArrayHasKey($passName, $leftResult->passReports);
        self::assertSame($leftResult->html, $rightResult->html);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function noiseHandledByPassProvider(): iterable
    {
        yield 'whitespace outside pre code and textarea' => [
            'normalise_whitespace',
            '<p>Hello    world</p><pre>Hello    world</pre><code>$a    =    1;</code><textarea>A    B</textarea>',
            '<p>Hello world</p><pre>Hello    world</pre><code>$a    =    1;</code><textarea>A    B</textarea>',
        ];

        yield 'attribute order' => [
            'sort_attributes',
            '<a title="Example" class="button" href="/docs">Docs</a>',
            '<a class="button" href="/docs" title="Example">Docs</a>',
        ];

        yield 'comments' => [
            'strip_comments',
            '<article><!-- rendered by legacy --><p>Hello</p></article>',
            '<article><p>Hello</p></article>',
        ];

        yield 'pods only island markers' => [
            'strip_pods_only_island_markers',
            '<section><span data-pods-only-island-marker="1"></span><p>Hello</p></section>',
            '<section><p>Hello</p></section>',
        ];

        yield 'hreflang alternate ordering' => [
            'sort_hreflang_alternates',
            '<div><link rel="alternate" hreflang="fr" href="/fr"><link rel="alternate" hreflang="en" href="/en"></div>',
            '<div><link rel="alternate" hreflang="en" href="/en"><link rel="alternate" hreflang="fr" href="/fr"></div>',
        ];

        yield 'url normalisation' => [
            'normalise_urls',
            '<form action=" HTTPS://Example.COM:443/a/./b/../c?z=2&a=1 "><img src="HTTP://CDN.Example.COM:80/assets/./logo.png"><a href="/docs/./intro?b=2&a=1">Read</a></form>',
            '<form action="https://example.com/a/c?a=1&z=2"><img src="http://cdn.example.com/assets/logo.png"><a href="/docs/intro?a=1&b=2">Read</a></form>',
        ];
    }

    public function testSignedPublicImageUrlNormalisesToRawUploadPath(): void
    {
        $path = '/uploads/images/2026-04-13/example.png';
        $signed = $this->signedPublicImagePath($path);
        $normaliser = new HtmlParityNormaliser();

        self::assertSame(
            $normaliser->normalise('<img src="http://localhost:5001' . $path . '">')->html,
            $normaliser->normalise('<img src="' . $signed . '">')->html,
        );
    }

    public function testSignedPublicImageUrlNormalisesToRawStorageUploadPath(): void
    {
        $path = '/storage/uploads/images/imported/2026-06-19/example.jpg';
        $signed = $this->signedPublicImagePath($path);
        $normaliser = new HtmlParityNormaliser();

        self::assertSame(
            $normaliser->normalise('<img src="http://localhost:5001' . $path . '">')->html,
            $normaliser->normalise('<img src="' . $signed . '">')->html,
        );
    }

    public function testEachPassIsIndependentlyDisableable(): void
    {
        $enabled = new HtmlParityNormaliser();
        $disabled = new HtmlParityNormaliser(['sort_attributes']);

        $left = '<a title="Example" class="button" href="/docs">Docs</a>';
        $right = '<a class="button" href="/docs" title="Example">Docs</a>';

        self::assertSame($enabled->normalise($left)->html, $enabled->normalise($right)->html);
        self::assertNotSame($disabled->normalise($left)->html, $disabled->normalise($right)->html);
        self::assertFalse($disabled->normalise($left)->passReports['sort_attributes']['enabled']);
    }

    public function testEachPassReportsUnderItsOwnName(): void
    {
        $result = (new HtmlParityNormaliser())->normalise(
            '<article><!-- comment --><a title="Example" class="button" href=" HTTPS://Example.COM:443/a/./b?z=2&a=1 ">Docs</a></article>',
        );

        foreach ([
            'parse_to_dom',
            'strip_pods_only_island_markers',
            'strip_comments',
            'normalise_whitespace',
            'sort_attributes',
            'sort_hreflang_alternates',
            'normalise_urls',
        ] as $passName) {
            self::assertArrayHasKey($passName, $result->passReports);
            self::assertSame($passName, $result->passReports[$passName]['name']);
        }
    }

    private function signedPublicImagePath(string $path): string
    {
        $token = rtrim(strtr(base64_encode('v1:' . $path), '+/', '-_'), '=');

        return '/public/images/' . $token . '.test-signature';
    }
}
