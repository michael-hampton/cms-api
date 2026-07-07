<?php

namespace App\Tests\Unit\Services\PublicContent\Theming;

use App\Models\Site;
use App\Repositories\Cms\SiteRepository;
use App\Services\PublicContent\Theming\PublicContentDesignTokenProvider;
use App\Services\PublicContent\Theming\PublicContentDesignTokenSource;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

final class PublicContentDesignTokenProviderTest extends FunctionalTestCase
{
    private PublicContentDesignTokenProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the SiteRepository to securely wrap database model lookups
        $siteRepository = Mockery::mock(SiteRepository::class);
        $siteRepository->shouldReceive('find')->andReturnUsing(fn($id) => Site::find($id));

        // Mock the DesignTokenSource with structural mock behaviors mapping to explicit expectations
        $designTokens = Mockery::mock(PublicContentDesignTokenSource::class);
        $designTokens->shouldReceive('defaults')->andReturn([
            'color'   => ['primary' => '#1f2937', 'accent' => '#2563eb'],
            'font'    => ['heading' => 'Georgia, serif', 'body' => 'Arial, sans-serif'],
            'radius'  => ['medium' => '8px', 'large' => '12px'],
            'content' => ['max_width' => '1200px'],
            'spacing' => ['section' => '2rem'],
            'brand'   => ['heading_color' => '#303036'],
        ]);

        $designTokens->shouldReceive('overrides')->andReturnUsing(fn($siteId) =>
        (Site::find($siteId)?->slug === 'guitar-world') ? [
            'color'   => ['primary' => '#303036', 'accent' => '#991b1b'],
            'font'    => ['heading' => 'Arial Black, Arial, sans-serif'],
            'brand'   => [
                'heading_transform' => 'uppercase',
                'newsletter_button_background' => '#27272a',
                'heading_color' => '#303036'
            ],
            'spacing' => ['section' => '3rem'],
        ] : []
        );

        $this->provider = new PublicContentDesignTokenProvider($siteRepository, $designTokens);
    }

    public function testItReturnsDefaultTokensWhenSiteDoesNotExist(): void
    {
        $tokens = $this->provider->forSite(PHP_INT_MAX);

        self::assertSame('#1f2937', $tokens['color']['primary']);
        self::assertSame('#2563eb', $tokens['color']['accent']);
        self::assertSame('Georgia, serif', $tokens['font']['heading']);
        self::assertSame('8px', $tokens['radius']['medium']);
        self::assertSame('1200px', $tokens['content']['max_width']);
    }

    public function testItMergesTheConfiguredSiteBaselineOverDefaults(): void
    {
        $site = Site::find($this->siteId);
        self::assertNotNull($site);

        $site->slug = 'guitar-world';
        $site->save();

        $tokens = $this->provider->forSite($this->siteId);

        self::assertSame('#303036', $tokens['color']['primary']);
        self::assertSame('#991b1b', $tokens['color']['accent']);
        self::assertSame('Arial Black, Arial, sans-serif', $tokens['font']['heading']);
        self::assertSame('uppercase', $tokens['brand']['heading_transform']);
        self::assertSame('#27272a', $tokens['brand']['newsletter_button_background']);
        self::assertSame('3rem', $tokens['spacing']['section']);
    }

    public function testSiteSettingsRecursivelyOverrideBaselineWithoutRemovingDefaults(): void
    {
        $site = Site::find($this->siteId);
        self::assertNotNull($site);

        $site->slug = 'guitar-world';
        $site->setSetting('design_tokens', [
            'color' => ['accent' => '#123456'],
            'content' => ['max_width' => '1440px'],
            'brand' => ['newsletter_button_background' => '#333333'],
        ]);
        $site->save();

        $tokens = $this->provider->forSite($this->siteId);

        self::assertSame('#123456', $tokens['color']['accent']);
        self::assertSame('1440px', $tokens['content']['max_width']);
        self::assertSame('#333333', $tokens['brand']['newsletter_button_background']);
        self::assertSame('#303036', $tokens['brand']['heading_color']);
        self::assertSame('Arial, sans-serif', $tokens['font']['body']);
        self::assertSame('3rem', $tokens['spacing']['section']);
    }

    public function testItFlattensNestedTokensIntoCssVariables(): void
    {
        $site = Site::find($this->siteId);
        self::assertNotNull($site);

        $site->setSetting('design_tokens', [
            'brand' => [
                'newsletter_button_background' => '#333333',
            ],
        ]);
        $site->save();

        $variables = $this->provider->cssVariablesForSite($this->siteId);

        self::assertSame('#333333', $variables['--brand-newsletter-button-background']);
        self::assertArrayHasKey('--color-primary', $variables);
        self::assertArrayHasKey('--font-heading', $variables);
        self::assertArrayHasKey('--radius-large', $variables);
        self::assertArrayHasKey('--spacing-section', $variables);
        self::assertArrayHasKey('--content-max-width', $variables);
    }

    public function testItIncludesSiteNameTaglineAndLogoInResolvedBranding(): void
    {
        $site = Site::find($this->siteId);
        self::assertNotNull($site);

        $site->name = 'Example Publication';
        $site->logo = '/storage/logos/example.svg';
        $site->setSetting('tagline', 'Independent reporting');
        $site->save();

        $tokens = $this->provider->forSite($this->siteId);

        self::assertSame('Example Publication', $tokens['brand']['site_name']);
        self::assertSame('Independent reporting', $tokens['brand']['tagline']);
        self::assertSame('/storage/logos/example.svg', $tokens['brand']['logo_url']);
    }

    public function testBrandingMetadataIsNotFlattenedIntoCssVariables(): void
    {
        $site = Site::find($this->siteId);
        self::assertNotNull($site);

        $site->name = 'Example Publication';
        $site->logo = '/storage/logos/example.svg';
        $site->setSetting('tagline', 'Independent reporting');
        $site->save();

        $variables = $this->provider->cssVariablesForSite($this->siteId);

        self::assertArrayNotHasKey('--brand-site-name', $variables);
        self::assertArrayNotHasKey('--brand-tagline', $variables);
        self::assertArrayNotHasKey('--brand-logo-url', $variables);
        self::assertArrayHasKey('--brand-heading-color', $variables);
    }

    public function testItReturnsEmptyLogoMetadataWhenNoLogoIsConfigured(): void
    {
        $site = Site::find($this->siteId);
        self::assertNotNull($site);

        $site->logo = null;
        $site->logo_image_id = null;
        $site->save();

        $tokens = $this->provider->forSite($this->siteId);

        self::assertSame('', $tokens['brand']['logo_url']);
    }
}