<?php

namespace App\Tests\Unit\Services\PublicContent\Theming;

use App\Models\Site;
use App\Services\PublicContent\Theming\PublicContentDesignTokenProvider;
use App\Tests\Functional\Controllers\FunctionalTestCase;

final class PublicContentDesignTokenProviderTest extends FunctionalTestCase
{
    private PublicContentDesignTokenProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new PublicContentDesignTokenProvider();
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
