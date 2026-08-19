<?php

namespace App\Tests\Unit\Services\PublicContent\Theming;

use App\Models\Site;
use App\Repositories\Cms\SiteRepository;
use App\Services\PublicContent\Theming\PublicContentDesignTokenProvider;
use App\Services\PublicContent\Theming\PublicContentDesignTokenSource;
use Mockery;
use PHPUnit\Framework\TestCase;

final class PublicContentDesignTokenProviderTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testItReturnsDefaultTokensWhenSiteDoesNotExist(): void
    {
        $tokens = $this->provider(null)->forSite(PHP_INT_MAX);

        self::assertSame('#1f2937', $tokens['color']['primary']);
        self::assertSame('#2563eb', $tokens['color']['accent']);
        self::assertSame('Georgia, serif', $tokens['font']['heading']);
        self::assertSame('8px', $tokens['radius']['medium']);
        self::assertSame('1200px', $tokens['content']['max_width']);
    }

    public function testItMergesTheConfiguredSiteBaselineOverDefaults(): void
    {
        $tokens = $this->provider($this->site(slug: 'guitar-world'))->forSite(7);

        self::assertSame('#303036', $tokens['color']['primary']);
        self::assertSame('#991b1b', $tokens['color']['accent']);
        self::assertSame('Arial Black, Arial, sans-serif', $tokens['font']['heading']);
        self::assertSame('uppercase', $tokens['brand']['heading_transform']);
        self::assertSame('#27272a', $tokens['brand']['newsletter_button_background']);
        self::assertSame('3rem', $tokens['spacing']['section']);
    }

    public function testSiteSettingsRecursivelyOverrideBaselineWithoutRemovingDefaults(): void
    {
        $site = $this->site(slug: 'guitar-world', settings: [
            'design_tokens' => [
                'color' => ['accent' => '#123456'],
                'content' => ['max_width' => '1440px'],
                'brand' => ['newsletter_button_background' => '#333333'],
            ],
        ]);

        $tokens = $this->provider($site)->forSite(7);

        self::assertSame('#123456', $tokens['color']['accent']);
        self::assertSame('1440px', $tokens['content']['max_width']);
        self::assertSame('#333333', $tokens['brand']['newsletter_button_background']);
        self::assertSame('#303036', $tokens['brand']['heading_color']);
        self::assertSame('Arial, sans-serif', $tokens['font']['body']);
        self::assertSame('3rem', $tokens['spacing']['section']);
    }

    public function testItFlattensNestedTokensIntoCssVariables(): void
    {
        $site = $this->site(settings: [
            'design_tokens' => [
                'brand' => [
                    'newsletter_button_background' => '#333333',
                ],
            ],
        ]);

        $variables = $this->provider($site)->cssVariablesForSite(7);

        self::assertSame('#333333', $variables['--brand-newsletter-button-background']);
        self::assertArrayHasKey('--color-primary', $variables);
        self::assertArrayHasKey('--font-heading', $variables);
        self::assertArrayHasKey('--radius-large', $variables);
        self::assertArrayHasKey('--spacing-section', $variables);
        self::assertArrayHasKey('--content-max-width', $variables);
    }

    public function testItIncludesSiteNameTaglineAndLogoInResolvedBranding(): void
    {
        $site = $this->site(
            name: 'Example Publication',
            logo: '/storage/logos/example.svg',
            settings: ['tagline' => 'Independent reporting'],
        );

        $tokens = $this->provider($site)->forSite(7);

        self::assertSame('Example Publication', $tokens['brand']['site_name']);
        self::assertSame('Independent reporting', $tokens['brand']['tagline']);
        self::assertSame('/storage/logos/example.svg', $tokens['brand']['logo_url']);
    }

    public function testBrandingMetadataIsNotFlattenedIntoCssVariables(): void
    {
        $site = $this->site(
            name: 'Example Publication',
            logo: '/storage/logos/example.svg',
            settings: ['tagline' => 'Independent reporting'],
        );

        $variables = $this->provider($site)->cssVariablesForSite(7);

        self::assertArrayNotHasKey('--brand-site-name', $variables);
        self::assertArrayNotHasKey('--brand-tagline', $variables);
        self::assertArrayNotHasKey('--brand-logo-url', $variables);
        self::assertArrayHasKey('--brand-heading-color', $variables);
    }

    public function testItReturnsEmptyLogoMetadataWhenNoLogoIsConfigured(): void
    {
        $site = $this->site(logo: null);
        $site->logo_image_id = null;

        $tokens = $this->provider($site)->forSite(7);

        self::assertSame('', $tokens['brand']['logo_url']);
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function site(
        string $slug = 'example',
        array $settings = [],
        string $name = 'Example',
        ?string $logo = null,
    ): Site {
        $site = Mockery::mock(Site::class)->makePartial();
        $site->id = 7;
        $site->slug = $slug;
        $site->name = $name;
        $site->logo = $logo;
        $site->logo_image_id = null;
        $site->settings = $settings;

        return $site;
    }

    private function provider(?Site $site): PublicContentDesignTokenProvider
    {
        $siteRepository = Mockery::mock(SiteRepository::class);
        $siteRepository->shouldReceive('find')->andReturn($site);

        $designTokens = Mockery::mock(PublicContentDesignTokenSource::class);
        $designTokens->shouldReceive('defaults')->andReturn([
            'color' => ['primary' => '#1f2937', 'accent' => '#2563eb'],
            'font' => ['heading' => 'Georgia, serif', 'body' => 'Arial, sans-serif'],
            'radius' => ['medium' => '8px', 'large' => '12px'],
            'content' => ['max_width' => '1200px'],
            'spacing' => ['section' => '2rem'],
            'brand' => ['heading_color' => '#303036'],
        ]);
        $designTokens->shouldReceive('overrides')->andReturnUsing(
            static fn(int $siteId): array => ($site?->slug === 'guitar-world') ? [
                'color' => ['primary' => '#303036', 'accent' => '#991b1b'],
                'font' => ['heading' => 'Arial Black, Arial, sans-serif'],
                'brand' => [
                    'heading_transform' => 'uppercase',
                    'newsletter_button_background' => '#27272a',
                    'heading_color' => '#303036',
                ],
                'spacing' => ['section' => '3rem'],
            ] : [],
        );

        return new PublicContentDesignTokenProvider($siteRepository, $designTokens);
    }
}
