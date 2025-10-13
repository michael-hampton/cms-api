<?php

namespace App\Tests\Functional\Controllers;

class SiteControllerTest extends FunctionalTestCase
{
    public function testIndexReturnsAllSites(): void
    {
        $response = $this->getForSite('/api/sites');

        $this->assertResponseOk($response);
        $this->assertJsonResponse($response);

        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertGreaterThan(0, count($data));
    }

    public function testShowReturnsSpecificSite(): void
    {
        $response = $this->getForSite('/api/sites/' . $this->siteId);

        $this->assertResponseOk($response);
        $this->assertJsonResponse($response);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('id', $data['data']);
        $this->assertEquals($this->siteId, $data['data']['id']);
        $this->assertArrayHasKey('name', $data['data']);
        $this->assertArrayHasKey('slug', $data['data']);
    }

    public function testShowReturns404ForNonExistentSite(): void
    {
        $response = $this->getForSite('/api/sites/99999');

        $this->assertResponseStatus(404, $response);
    }

    public function testGetCurrentReturnsContextSite(): void
    {
        $response = $this->getForSite('/api/sites/current');

        $this->assertResponseOk($response);
        $this->assertJsonResponse($response);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('id', $data['data']);
        $this->assertEquals($this->siteId, $data['data']['id']);
    }

    public function testCreateSiteWithValidData(): void
    {
        $siteData = [
            'name' => 'New Test Site',
            'slug' => 'new-test-site-' . time(),
            'domain' => 'newtest' . time() . '.com',
            'is_active' => true
        ];

        $response = $this->postForSite('/api/sites', $siteData);

        $this->assertResponseStatus(201, $response);
        $this->assertJsonResponse($response);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('id', $data['data']);
        $this->assertEquals('New Test Site', $data['data']['name']);
        $this->assertEquals($siteData['slug'], $data['data']['slug']);
    }

    public function testCreateSiteWithoutRequiredFieldsReturns422(): void
    {
        $siteData = [
            'domain' => 'incomplete.com'
            // Missing required 'name' and 'slug'
        ];

        $response = $this->postForSite('/api/sites', $siteData);

        $this->assertResponseStatus(422, $response);
    }

    public function testUpdateSiteWithValidData(): void
    {
        $updateData = [
            'name' => 'Updated Site Name',
            'theme' => 'dark'
        ];

        $response = $this->putForSite('/api/sites/' . $this->siteId, $updateData);

        $this->assertResponseOk($response);
        $this->assertJsonResponse($response);

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Updated Site Name', $data['data']['name']);
        $this->assertEquals('dark', $data['data']['theme']);
    }

    public function testUpdateCurrentSite(): void
    {
        $updateData = [
            'name' => 'Updated Current Site'
        ];

        $response = $this->putForSite('/api/sites/current', $updateData);

        $this->assertResponseOk($response);
        $this->assertJsonResponse($response);

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('Updated Current Site', $data['data']['name']);
    }

    public function testGetContactInfo(): void
    {
        $response = $this->getForSite('/api/sites/contact');

        $this->assertResponseOk($response);
        $this->assertJsonResponse($response);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('email', $data['data']);
        $this->assertArrayHasKey('phone', $data['data']);
        $this->assertArrayHasKey('address', $data['data']);
        $this->assertArrayHasKey('social', $data['data']);
    }

    public function testUpdateContactInfo(): void
    {
        $contactData = [
            'contact_email' => 'contact@example.com',
            'contact_phone' => '+44 123 456 7890',
            'contact_address_line1' => '123 Test Street',
            'contact_city' => 'London',
            'contact_postcode' => 'SW1A 1AA',
            'contact_country' => 'UK'
        ];

        $response = $this->putForSite('/api/sites/contact', $contactData);

        $this->assertResponseOk($response);
        $this->assertJsonResponse($response);

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('contact@example.com', $data['data']['contact_email']);
        $this->assertEquals('+44 123 456 7890', $data['data']['contact_phone']);
        $this->assertEquals('123 Test Street', $data['data']['contact_address_line1']);
    }

    public function testUpdateContactInfoWithInvalidEmailReturns422(): void
    {
        $contactData = [
            'contact_email' => 'not-an-email'
        ];

        $response = $this->putForSite('/api/sites/contact', $contactData);

        $this->assertResponseStatus(422, $response);
    }

    public function testUpdateSocialMedia(): void
    {
        $socialData = [
            'facebook_url' => 'https://facebook.com/testsite',
            'twitter_url' => 'https://twitter.com/testsite',
            'instagram_url' => 'https://instagram.com/testsite',
            'linkedin_url' => 'https://linkedin.com/company/testsite'
        ];

        $response = $this->putForSite('/api/sites/social', $socialData);

        $this->assertResponseOk($response);
        $this->assertJsonResponse($response);

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('https://facebook.com/testsite', $data['data']['facebook_url']);
        $this->assertEquals('https://twitter.com/testsite', $data['data']['twitter_url']);
    }

    public function testUpdateSocialMediaWithInvalidUrlReturns422(): void
    {
        $socialData = [
            'facebook_url' => 'not-a-url'
        ];

        $response = $this->putForSite('/api/sites/social', $socialData);

        $this->assertResponseStatus(422, $response);
    }

    public function testUploadLogo(): void
    {
        $logoFile = $this->createUploadedFile('test-logo.png', 'image/png');

        $response = $this->postForSite('/api/sites/logo', [], ['logo' => $logoFile]);

        $this->assertResponseOk($response);
        $this->assertJsonResponse($response);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('logo', $data['data']);
        $this->assertStringContainsString('/uploads/logos/', $data['data']['logo']);
    }

    public function testUploadLogoWithoutFileReturns422(): void
    {
        $response = $this->postForSite('/api/sites/logo', []);

        $this->assertResponseStatus(422, $response);
    }

    public function testUploadLogoWithInvalidFileTypeReturns422(): void
    {
        // Create a text file instead of image
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmpFile, 'not an image');

        $invalidFile = [
            'name' => 'test.txt',
            'type' => 'text/plain',
            'tmp_name' => $tmpFile,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmpFile)
        ];

        $response = $this->postForSite('/api/sites/logo', [], ['logo' => $invalidFile]);

        $this->assertResponseStatus(422, $response);

        unlink($tmpFile);
    }

    public function testUploadLogoWithOversizedFileReturns422(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        // Create file larger than 2MB
        file_put_contents($tmpFile, str_repeat('x', 3 * 1024 * 1024));

        $oversizedFile = [
            'name' => 'large.png',
            'type' => 'image/png',
            'tmp_name' => $tmpFile,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmpFile)
        ];

        $response = $this->postForSite('/api/sites/logo', [], ['logo' => $oversizedFile]);

        $this->assertResponseStatus(422, $response);

        unlink($tmpFile);
    }

    public function testUploadFavicon(): void
    {
        $faviconFile = $this->createUploadedFile('favicon.ico', 'image/x-icon');

        $response = $this->postForSite('/api/sites/favicon', [], ['favicon' => $faviconFile]);

        $this->assertResponseOk($response);
        $this->assertJsonResponse($response);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('favicon', $data['data']);
        $this->assertStringContainsString('/uploads/favicons/', $data['data']['favicon']);
    }

    public function testUploadFaviconWithoutFileReturns422(): void
    {
        $response = $this->postForSite('/api/sites/favicon', []);

        $this->assertResponseStatus(422, $response);
    }

    public function testUpdateSettings(): void
    {
        $settings = [
            'maintenance_mode' => false,
            'analytics_id' => 'UA-123456-7',
            'custom_css' => '.custom { color: red; }'
        ];

        $response = $this->putForSite('/api/sites/settings', $settings);

        $this->assertResponseOk($response);
        $this->assertJsonResponse($response);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('settings', $data['data']);
        $this->assertIsString($data['data']['settings']);
    }

    public function testToggleStatus(): void
    {
        $response = $this->putForSite('/api/sites/' . $this->siteId . '/status', [
            'is_active' => false
        ]);

        $this->assertResponseOk($response);
        $this->assertJsonResponse($response);

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['data']['is_active']);

        // Toggle back to active
        $response = $this->putForSite('/api/sites/' . $this->siteId . '/status', [
            'is_active' => true
        ]);

        $this->assertResponseOk($response);
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['data']['is_active']);
    }

    public function testDeleteSite(): void
    {
        // Create a new site to delete (not the default one)
        $siteData = [
            'name' => 'Site To Delete',
            'slug' => 'site-to-delete-' . time(),
            'is_active' => true,
            'is_default' => false
        ];

        $createResponse = $this->postForSite('/api/sites', $siteData);
        $createdSite = json_decode($createResponse->getContent(), true);

        $response = $this->deleteForSite('/api/sites/' . $createdSite['data']['id']);

        $this->assertResponseOk($response);
        $this->assertJsonResponse($response);

        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('message', $data['data']);
        $this->assertEquals('Site deleted successfully', $data['data']['message']);
    }

    public function testDeleteDefaultSiteReturnsError(): void
    {
        // Assuming siteId 1 is default, or create a default site
        $response = $this->deleteForSite('/api/sites/' . $this->siteId);

        // Should return error if trying to delete default site
        $this->assertResponseStatus(500, $response);
    }

    public function testUnauthenticatedUserCannotAccessProtectedRoutes(): void
    {
        $this->unauthenticate();

        $response = $this->getForSiteUnauthenticated('/api/sites/current');

        // Assuming authentication is required
        $this->assertResponseStatus(401, $response);
    }
}