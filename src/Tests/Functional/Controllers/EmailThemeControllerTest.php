<?php
// tests/Functional/Controllers/EmailThemeControllerTest.php

namespace App\Tests\Functional\Controllers;

use App\Models\EmailTheme;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class EmailThemeControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testIndexReturnsThemesList()
    {
        $this->createEmailTheme();
        $this->createEmailTheme();

        $response = $this->getForSite('/api/email-themes');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('items', $data);
        $this->assertCount(2, $data['items']);
    }

    public function testIndexWithSearchQuery()
    {
        $this->createEmailTheme(['name' => 'Modern Theme', 'slug' => 'modern-theme']);
        $this->createEmailTheme(['name' => 'Classic Theme', 'slug' => 'classic-theme']);

        $response = $this->getForSite('/api/email-themes?q=modern');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data['items']);
        $this->assertEquals('Modern Theme', $data['items'][0]['name']);
    }

    public function testStoreCreatesNewTheme()
    {
        $themeData = [
            'name' => 'Brand New Theme',
            'description' => 'A fresh new theme',
            'colors' => [
                'primary' => '#667eea',
                'secondary' => '#764ba2'
            ],
            'is_active' => true
        ];

        $response = $this->postForSite('/api/email-themes', $themeData);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('Brand New Theme', $data['data']['theme']['name']);
        $this->assertEquals('brand-new-theme', $data['data']['theme']['slug']);
    }

    public function testStoreWithLogo()
    {
        $files = [
            'logo' => $this->createUploadedFile('logo.png', 'image/png'),
        ];

        $response = $this->postForSite('/api/email-themes', [
            'name' => 'Theme With Logo'
        ], $files);

        $this->assertEquals(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertNotNull($data['data']['theme']['assets']['logo']['asset_url'] ?? null);
    }

    public function testStoreValidatesRequiredFields()
    {
        $response = $this->postForSite('/api/email-themes', []);

        $this->assertEquals(422, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('errors', $data);
        $this->assertArrayHasKey('name', $data['errors']);
    }

    public function testShowReturnsThemeById()
    {
        $theme = $this->createEmailTheme(['name' => 'Test Theme']);

        $response = $this->getForSite("/api/email-themes/{$theme->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Test Theme', $data['data']['theme']['name']);
    }

    public function testShowReturnsThemeBySlug()
    {
        $this->createEmailTheme(['name' => 'Modern', 'slug' => 'modern']);

        $response = $this->getForSite('/api/email-themes/modern');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Modern', $data['data']['theme']['name']);
    }

    public function testShowReturns404ForNonexistent()
    {
        $response = $this->getForSite('/api/email-themes/999');

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testUpdateModifiesTheme()
    {
        $theme = $this->createEmailTheme(['name' => 'Old Theme']);

        $response = $this->putForSite("/api/email-themes/{$theme->id}", [
            'name' => 'Updated Theme',
            'description' => 'New description'
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Updated Theme', $data['data']['theme']['name']);
    }

    public function testDestroyDeletesTheme()
    {
        $theme = $this->createEmailTheme(['is_default' => false]);

        $response = $this->deleteForSite("/api/email-themes/{$theme->id}");

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNull(EmailTheme::find($theme->id));
    }

    public function testCannotDeleteDefaultTheme()
    {
        $theme = $this->createEmailTheme(['is_default' => true]);

        $response = $this->deleteForSite("/api/email-themes/{$theme->id}");

        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertStringContainsString('default', $data['error']);
    }

    public function testGetActiveReturnsOnlyActiveThemes()
    {
        $this->createEmailTheme(['is_active' => true, 'name' => 'Active Theme']);
        $this->createEmailTheme(['is_active' => false, 'name' => 'Inactive Theme']);

        $response = $this->getForSite('/api/email-themes/active');

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data['data']['themes']['data']);
        $this->assertEquals('Active Theme', $data['data']['themes']['data'][0]['name']);
    }

    public function testSetDefault()
    {
        $theme1 = $this->createEmailTheme(['is_default' => true]);
        $theme2 = $this->createEmailTheme(['is_default' => false]);

        $response = $this->postForSite("/api/email-themes/{$theme2->id}/set-default");

        $this->assertEquals(200, $response->getStatusCode());

        $theme1 = $theme1->fresh();
        $theme2 = $theme2->fresh();

        $this->assertFalse($theme1->is_default);
        $this->assertTrue($theme2->is_default);
    }

    public function testAlternativesReturnsOtherThemes()
    {
        $theme1 = $this->createEmailTheme(['name' => 'Theme 1']);
        $theme2 = $this->createEmailTheme();
        $theme3 = $this->createEmailTheme();

        $response = $this->getForSite("/api/email-themes/{$theme1->id}/alternatives");

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(2, $data['data']['themes']['data']);
    }

    public function testDuplicateThemeSuccessfully()
    {
        $theme = $this->createEmailTheme([
            'name' => 'Original Theme',
            'slug' => 'original-theme',
            'description' => 'Original description'
        ]);

        $response = $this->postForSite("/api/email-themes/{$theme->id}/duplicate");

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('Original Theme (Copy)', $data['data']['theme']['name']);
        $this->assertEquals('Original description', $data['data']['theme']['description']);
        $this->assertNotEquals($theme->slug, $data['data']['theme']['slug']);
    }

    public function testBulkDeleteSuccessfully()
    {
        $theme1 = $this->createEmailTheme(['is_default' => false]);
        $theme2 = $this->createEmailTheme(['is_default' => false]);

        $response = $this->postForSite('/api/email-themes/bulk-delete', [
            'ids' => [$theme1->id, $theme2->id]
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(2, $data['result']['deleted']);
        $this->assertCount(0, $data['result']['failed']);

        $this->assertNull(EmailTheme::find($theme1->id));
        $this->assertNull(EmailTheme::find($theme2->id));
    }

    public function testBulkDeleteFailsForDefaultTheme()
    {
        $theme = $this->createEmailTheme(['is_default' => true]);

        $response = $this->postForSite('/api/email-themes/bulk-delete', [
            'ids' => [$theme->id]
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertCount(0, $data['result']['deleted']);
        $this->assertCount(1, $data['result']['failed']);
    }
}