<?php

namespace App\Tests\Unit\Services\PublicContent\Theming;

use App\Models\Site;
use App\Services\PublicContent\Theming\PublicContentDesignTokenProvider;
use App\Tests\Functional\Controllers\FunctionalTestCase;

final class PublicContentDesignTokenProviderTest extends FunctionalTestCase
{
    public function test_it_returns_css_variables_for_resolved_tokens(): void
    {
        $provider = new PublicContentDesignTokenProvider();

        $variables = $provider->cssVariablesForSite($this->siteId);

        $this->assertArrayHasKey('--color-primary', $variables);
        $this->assertArrayHasKey('--font-heading', $variables);
        $this->assertArrayHasKey('--radius-large', $variables);
        $this->assertArrayHasKey('--spacing-section', $variables);
    }

    public function test_site_settings_override_default_design_tokens(): void
    {
        $site = Site::find($this->siteId);
        $this->assertNotNull($site);

        $site->setSetting('design_tokens', [
            'color' => [
                'accent' => '#123456',
            ],
            'content' => [
                'max_width' => '1440px',
            ],
        ]);
        $site->save();

        $provider = new PublicContentDesignTokenProvider();

        $tokens = $provider->forSite($this->siteId);
        $variables = $provider->cssVariablesForSite($this->siteId);

        $this->assertSame('#123456', $tokens['color']['accent']);
        $this->assertSame('1440px', $tokens['content']['max_width']);
        $this->assertSame('#123456', $variables['--color-accent']);
        $this->assertSame('1440px', $variables['--content-max-width']);
        $this->assertArrayHasKey('--color-primary', $variables);
    }
}
