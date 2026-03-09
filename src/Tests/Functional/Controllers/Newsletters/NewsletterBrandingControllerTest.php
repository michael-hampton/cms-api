<?php

namespace App\Tests\Functional\Controllers\Newsletters;

use App\Models\Newsletter;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class NewsletterBrandingControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    // ── Helpers ────────────────────────────────────────────────────────────────

    public function test_get_branding_returns_200_for_existing_newsletter(): void
    {
        $newsletter = $this->createNewsletter(['site_id' => $this->siteId]);

        $response = $this->getForSite("/api/newsletters/{$newsletter->id}/branding");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('branding', $data);
    }

    public function test_get_branding_returns_null_design_config_when_not_set(): void
    {
        $newsletter = $this->createNewsletter(['site_id' => $this->siteId]);

        $response = $this->getForSite("/api/newsletters/{$newsletter->id}/branding");

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('design_config', $data['branding']);
        $this->assertNull($data['branding']['design_config']);
    }

    // ── GET branding ───────────────────────────────────────────────────────────

    public function test_get_branding_returns_404_for_nonexistent_newsletter(): void
    {
        $response = $this->getForSite('/api/newsletters/99999/branding');

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_save_branding_returns_200_on_success(): void
    {
        $newsletter = $this->createNewsletter(['site_id' => $this->siteId]);
        $payload = $this->makeBrandingPayload();

        $response = $this->postForSite(
            "/api/newsletters/{$newsletter->id}/branding",
            $payload
        );

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
    }

    private function makeBrandingPayload(array $overrides = []): array
    {
        return array_merge([
            'branding_json' => [
                'logo_url' => 'https://example.com/logo.png',
                'header_text' => 'Your weekly style edit',
                'footer_text' => '© 2025 Brand Corp',
                'theme_json' => [
                    'primary_color' => '#4f46e5',
                    'secondary_color' => '#7c3aed',
                    'text_color' => '#ffffff',
                ],
                'custom_css' => '.newsletter-wrapper { font-size: 16px; }',
                'design_config' => $this->makeDesignConfig(),
            ],
        ], $overrides);
    }

    // ── SAVE branding ──────────────────────────────────────────────────────────

    private function makeDesignConfig(): array
    {
        return [
            'chrome' => [
                'header_background' => '#000000',
                'header_text_color' => '#ffffff',
                'show_nav' => true,
                'nav_links' => [
                    ['label' => 'FASHION', 'url' => '{{site.url}}/fashion'],
                    ['label' => 'BEAUTY', 'url' => '{{site.url}}/beauty'],
                ],
            ],
            'editorial' => [
                'note' => 'This week we explore the new season.',
                'editor_name' => 'Sophie Ashworth',
                'editor_title' => 'Fashion & Style Director',
                'editor_image_url' => 'https://example.com/editors/sophie.jpg',
            ],
            'footer' => [
                'background' => '#f5f5f5',
                'text_color' => '#888888',
                'address' => '750 N. San Vicente Blvd, West Hollywood, CA 90069',
                'social_links' => [
                    ['platform' => 'instagram', 'url' => 'https://instagram.com/brand'],
                    ['platform' => 'tiktok', 'url' => 'https://tiktok.com/@brand'],
                ],
                'legal_links' => [
                    ['label' => 'UNSUBSCRIBE', 'url' => '{{UNSUBSCRIBE_URL}}'],
                    ['label' => 'PRIVACY', 'url' => 'https://example.com/privacy'],
                ],
            ],
        ];
    }

    public function test_save_branding_persists_design_config_on_newsletter(): void
    {
        $newsletter = $this->createNewsletter(['site_id' => $this->siteId]);
        $payload = $this->makeBrandingPayload();

        $this->postForSite(
            "/api/newsletters/{$newsletter->id}/branding",
            $payload
        );

        $refreshed = Newsletter::find($newsletter->id);
        $this->assertNotNull($refreshed->design_config);

        $dc = is_string($refreshed->design_config)
            ? json_decode($refreshed->design_config, true)
            : $refreshed->design_config;

        $this->assertEquals('#000000', $dc['chrome']['header_background']);
        $this->assertEquals('#ffffff', $dc['chrome']['header_text_color']);
        $this->assertTrue($dc['chrome']['show_nav']);
        $this->assertCount(2, $dc['chrome']['nav_links']);
        $this->assertEquals('FASHION', $dc['chrome']['nav_links'][0]['label']);
        $this->assertEquals('Sophie Ashworth', $dc['editorial']['editor_name']);
        $this->assertEquals('Fashion & Style Director', $dc['editorial']['editor_title']);
        $this->assertEquals('#f5f5f5', $dc['footer']['background']);
        $this->assertCount(2, $dc['footer']['social_links']);
        $this->assertCount(2, $dc['footer']['legal_links']);
    }

    public function test_save_branding_persists_chrome_nav_links(): void
    {
        $newsletter = $this->createNewsletter(['site_id' => $this->siteId]);
        $config = $this->makeDesignConfig();
        $config['chrome']['nav_links'] = [
            ['label' => 'STYLE', 'url' => '{{site.url}}/style'],
            ['label' => 'CULTURE', 'url' => '{{site.url}}/culture'],
            ['label' => 'LIVING', 'url' => '{{site.url}}/living'],
        ];

        $this->postForSite("/api/newsletters/{$newsletter->id}/branding", [
            'branding_json' => ['design_config' => $config],
        ]);

        $dc = Newsletter::find($newsletter->id)->design_config;
        $this->assertCount(3, $dc['chrome']['nav_links']);
        $this->assertEquals('CULTURE', $dc['chrome']['nav_links'][1]['label']);
    }

    public function test_save_branding_persists_footer_social_links(): void
    {
        $newsletter = $this->createNewsletter(['site_id' => $this->siteId]);
        $config = $this->makeDesignConfig();
        $config['footer']['social_links'] = [
            ['platform' => 'instagram', 'url' => 'https://instagram.com/brand'],
            ['platform' => 'pinterest', 'url' => 'https://pinterest.com/brand'],
            ['platform' => 'youtube', 'url' => 'https://youtube.com/brand'],
        ];

        $this->postForSite("/api/newsletters/{$newsletter->id}/branding", [
            'branding_json' => ['design_config' => $config],
        ]);

        $dc = Newsletter::find($newsletter->id)->design_config;
        $this->assertCount(3, $dc['footer']['social_links']);
        $this->assertEquals('pinterest', $dc['footer']['social_links'][1]['platform']);
    }

    public function test_save_branding_persists_footer_legal_links(): void
    {
        $newsletter = $this->createNewsletter(['site_id' => $this->siteId]);
        $config = $this->makeDesignConfig();
        $config['footer']['legal_links'] = [
            ['label' => 'UNSUBSCRIBE', 'url' => '{{UNSUBSCRIBE_URL}}'],
            ['label' => 'MANAGE', 'url' => '{{MANAGE_URL}}'],
            ['label' => 'PRIVACY', 'url' => 'https://example.com/privacy'],
        ];

        $this->postForSite("/api/newsletters/{$newsletter->id}/branding", [
            'branding_json' => ['design_config' => $config],
        ]);

        $dc = Newsletter::find($newsletter->id)->design_config;
        $this->assertCount(3, $dc['footer']['legal_links']);
        $this->assertEquals('{{MANAGE_URL}}', $dc['footer']['legal_links'][1]['url']);
    }

    public function test_save_branding_allows_empty_nav_links(): void
    {
        $newsletter = $this->createNewsletter(['site_id' => $this->siteId]);
        $config = $this->makeDesignConfig();
        $config['chrome']['nav_links'] = [];

        $this->postForSite("/api/newsletters/{$newsletter->id}/branding", [
            'branding_json' => ['design_config' => $config],
        ]);

        $dc = Newsletter::find($newsletter->id)->design_config;
        $this->assertIsArray($dc['chrome']['nav_links']);
        $this->assertCount(0, $dc['chrome']['nav_links']);
    }

    public function test_save_branding_allows_null_design_config(): void
    {
        $newsletter = $this->createNewsletter(['site_id' => $this->siteId]);

        $response = $this->postForSite("/api/newsletters/{$newsletter->id}/branding", [
            'branding_json' => [
                'logo_url' => 'https://example.com/logo.png',
                'design_config' => null,
            ],
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $refreshed = Newsletter::find($newsletter->id);
        $this->assertNull($refreshed->design_config);
    }

    public function test_save_branding_returns_404_for_nonexistent_newsletter(): void
    {
        $response = $this->postForSite('/api/newsletters/99999/branding', $this->makeBrandingPayload());

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_save_branding_returns_404_for_newsletter_from_different_site(): void
    {
        $otherSite = \App\Models\Site::create(['name' => 'Other', 'slug' => 'other-branding-post']);
        $newsletter = Newsletter::create([
            'title' => 'Foreign Newsletter',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $otherSite->id,
        ]);

        $response = $this->postForSite(
            "/api/newsletters/{$newsletter->id}/branding",
            $this->makeBrandingPayload()
        );

        $this->assertEquals(404, $response->getStatusCode());
    }

    // ── Round-trip ─────────────────────────────────────────────────────────────

    public function test_get_branding_returns_persisted_design_config(): void
    {
        $newsletter = $this->createNewsletter(['site_id' => $this->siteId]);
        $payload = $this->makeBrandingPayload();

        $this->postForSite("/api/newsletters/{$newsletter->id}/branding", $payload);

        $response = $this->getForSite("/api/newsletters/{$newsletter->id}/branding");
        $data = json_decode($response->getContent(), true);

        $dc = $data['branding']['design_config'];
        $this->assertNotNull($dc);
        $this->assertEquals('#000000', $dc['chrome']['header_background']);
        $this->assertEquals('Sophie Ashworth', $dc['editorial']['editor_name']);
        $this->assertEquals('instagram', $dc['footer']['social_links'][0]['platform']);
        $this->assertEquals('{{UNSUBSCRIBE_URL}}', $dc['footer']['legal_links'][0]['url']);
    }

    public function test_save_branding_overwrites_previous_design_config(): void
    {
        $newsletter = $this->createNewsletter(['site_id' => $this->siteId]);

        // First save
        $this->postForSite("/api/newsletters/{$newsletter->id}/branding", [
            'branding_json' => ['design_config' => $this->makeDesignConfig()],
        ]);

        // Second save with different data
        $updated = $this->makeDesignConfig();
        $updated['chrome']['header_background'] = '#ff0000';
        $updated['editorial']['editor_name'] = 'Emma Blake';

        $this->postForSite("/api/newsletters/{$newsletter->id}/branding", [
            'branding_json' => ['design_config' => $updated],
        ]);

        $dc = Newsletter::find($newsletter->id)->design_config;
        $this->assertEquals('#ff0000', $dc['chrome']['header_background']);
        $this->assertEquals('Emma Blake', $dc['editorial']['editor_name']);
    }

    // ── Version history ────────────────────────────────────────────────────────

    public function test_get_branding_versions_returns_200(): void
    {
        $newsletter = $this->createNewsletter(['site_id' => $this->siteId]);

        $response = $this->getForSite("/api/newsletters/{$newsletter->id}/branding/versions");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('versions', $data);
        $this->assertIsArray($data['versions']);
    }

    public function test_save_branding_creates_version_history_entry(): void
    {
        $newsletter = $this->createNewsletter(['site_id' => $this->siteId]);

        $this->postForSite("/api/newsletters/{$newsletter->id}/branding", $this->makeBrandingPayload());

        $response = $this->getForSite("/api/newsletters/{$newsletter->id}/branding/versions");
        $data = json_decode($response->getContent(), true);

        $this->assertGreaterThanOrEqual(1, count($data['versions']));
    }

    // ── Restore version ────────────────────────────────────────────────────────

    public function test_restore_branding_version_returns_404_for_nonexistent_version(): void
    {
        $newsletter = $this->createNewsletter(['site_id' => $this->siteId]);

        $response = $this->postForSite(
            "/api/newsletters/{$newsletter->id}/branding/restore/99999"
        );

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_save_validates_logo_url_must_be_valid_url(): void
    {
        $newsletter = $this->createNewsletter();

        $response = $this->postForSite("/api/newsletters/{$newsletter->id}/branding", [
            'logo_url' => 'not-a-url',
        ]);

        $data = json_decode($response->getContent(), true);

        $this->assertResponseStatus(422, $response);
        $this->assertArrayHasKey('logo_url', $data['errors']);
    }

    public function test_save_accepts_valid_url_for_logo(): void
    {
        $newsletter = $this->createNewsletter();

        $response = $this->postForSite("/api/newsletters/{$newsletter->id}/branding", [
            'logo_url' => 'https://cdn.example.com/logo.svg',
        ]);

        $this->assertResponseStatus(200, $response);
    }

    public function test_save_validates_theme_json_primary_color_max_length(): void
    {
        $newsletter = $this->createNewsletter();

        $response = $this->postForSite("/api/newsletters/{$newsletter->id}/branding", [
            'theme_json' => [
                'primary_color' => '#ff000000', // 9 chars — exceeds max:7
            ],
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertResponseStatus(422, $response);
        $this->assertArrayHasKey('theme_json.primary_color', $data['errors']);
    }

    public function test_save_validates_theme_json_secondary_color_max_length(): void
    {
        $newsletter = $this->createNewsletter();

        $response = $this->postForSite("/api/newsletters/{$newsletter->id}/branding", [
            'theme_json' => [
                'secondary_color' => '#aabbccdd', // too long
            ],
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertResponseStatus(422, $response);
        $this->assertArrayHasKey('theme_json.secondary_color', $data['errors']);
    }

    public function test_save_validates_theme_json_text_color_max_length(): void
    {
        $newsletter = $this->createNewsletter();

        $response = $this->postForSite("/api/newsletters/{$newsletter->id}/branding", [
            'theme_json' => [
                'text_color' => '#ffffffff',
            ],
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertResponseStatus(422, $response);
        $this->assertArrayHasKey('theme_json.text_color', $data['errors']);
    }

    public function test_save_accepts_valid_theme_json(): void
    {
        $newsletter = $this->createNewsletter();

        $response = $this->postForSite("/api/newsletters/{$newsletter->id}/branding", [
            'theme_json' => [
                'primary_color' => '#ff0000',
                'secondary_color' => '#00ff00',
                'text_color' => '#333333',
            ],
        ]);

        $this->assertResponseStatus(200, $response);
    }

    public function test_save_allows_all_optional_fields_to_be_absent(): void
    {
        $newsletter = $this->createNewsletter();

        // SaveNewsletterBrandingRequest has no required fields
        $response = $this->postForSite("/api/newsletters/{$newsletter->id}/branding", []);

        $this->assertResponseStatus(200, $response);
    }

}