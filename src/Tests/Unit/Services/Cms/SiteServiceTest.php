<?php

namespace App\Tests\Unit\Services\Cms;

use App\Models\Site;
use App\Repositories\Cms\SiteRepository;
use App\Services\Cms\SiteService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SiteServiceTest extends TestCase
{
    private SiteRepository|MockObject $repositoryMock;
    private SiteService $service;

    protected function setUp(): void
    {
        $this->repositoryMock = $this->createMock(SiteRepository::class);
        $this->service = new SiteService($this->repositoryMock);
    }

    public function testGetAllSitesReturnsArray(): void
    {
        // This test would need to mock static Site::all() which is complex
        // For now, we'll skip this or refactor to inject a site repository method
        $this->markTestSkipped('Requires refactoring to avoid static method dependency');
    }

    public function testGetSiteByIdReturnsArray(): void
    {
        $siteData = ['id' => 1, 'name' => 'Test Site', 'slug' => 'test-site'];
        $site = $this->createSiteMock($siteData);

        $this->repositoryMock
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($site);

        $result = $this->service->getSiteById(1);

        $this->assertIsArray($result);
        $this->assertEquals('Test Site', $result['name']);
    }

    public function testGetSiteByIdReturnsNullWhenNotFound(): void
    {
        $this->repositoryMock
            ->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $result = $this->service->getSiteById(999);

        $this->assertNull($result);
    }

    public function testGetSiteByDomainReturnsArray(): void
    {
        $siteData = ['id' => 1, 'name' => 'Test Site', 'domain' => 'example.com'];
        $site = $this->createSiteMock($siteData);

        $this->repositoryMock
            ->expects($this->once())
            ->method('findByDomain')
            ->with('example.com')
            ->willReturn($site);

        $result = $this->service->getSiteByDomain('example.com');

        $this->assertIsArray($result);
        $this->assertEquals('example.com', $result['domain']);
    }

    public function testCreateSiteWithValidData(): void
    {
        $data = [
            'name' => 'New Site',
            'slug' => 'new-site',
            'domain' => 'newsite.com'
        ];

        $site = $this->createSiteMock(array_merge($data, ['id' => 1]));

        $this->repositoryMock
            ->expects($this->once())
            ->method('create')
            ->with($data)
            ->willReturn($site);

        $result = $this->service->createSite($data);

        $this->assertIsArray($result);
        $this->assertEquals('New Site', $result['name']);
    }

    public function testUpdateSiteWithValidData(): void
    {
        $data = ['name' => 'Updated Site'];
        $site = $this->createSiteMock(['id' => 1, 'name' => 'Updated Site']);

        $this->repositoryMock
            ->expects($this->once())
            ->method('update')
            ->with(1, $data)
            ->willReturn($site);

        $result = $this->service->updateSite(1, $data);

        $this->assertIsArray($result);
        $this->assertEquals('Updated Site', $result['name']);
    }

    public function testUpdateContactInfo(): void
    {
        $contactData = [
            'contact_email' => 'test@example.com',
            'contact_phone' => '1234567890'
        ];

        $site = $this->createSiteMock(array_merge(['id' => 1], $contactData));

        $this->repositoryMock
            ->expects($this->once())
            ->method('updateContactInfo')
            ->with(1, $contactData)
            ->willReturn($site);

        $result = $this->service->updateContactInfo(1, $contactData);

        $this->assertIsArray($result);
        $this->assertEquals('test@example.com', $result['contact_email']);
    }

    public function testUpdateSocialMedia(): void
    {
        $socialData = [
            'facebook_url' => 'https://facebook.com/test',
            'twitter_url' => 'https://twitter.com/test'
        ];

        $site = $this->createSiteMock(array_merge(['id' => 1], $socialData));

        $this->repositoryMock
            ->expects($this->once())
            ->method('update')
            ->with(1, $socialData)
            ->willReturn($site);

        $result = $this->service->updateSocialMedia(1, $socialData);

        $this->assertIsArray($result);
        $this->assertEquals('https://facebook.com/test', $result['facebook_url']);
    }

    public function testUpdateLogo(): void
    {
        $logoPath = '/uploads/logos/logo.png';
        $site = $this->createSiteMock(['id' => 1, 'logo' => $logoPath]);

        $this->repositoryMock
            ->expects($this->once())
            ->method('update')
            ->with(1, ['logo' => $logoPath])
            ->willReturn($site);

        $result = $this->service->updateLogo(1, $logoPath);

        $this->assertIsArray($result);
        $this->assertEquals($logoPath, $result['logo']);
    }

    public function testUpdateFavicon(): void
    {
        $faviconPath = '/uploads/favicons/favicon.ico';
        $site = $this->createSiteMock(['id' => 1, 'favicon' => $faviconPath]);

        $this->repositoryMock
            ->expects($this->once())
            ->method('update')
            ->with(1, ['favicon' => $faviconPath])
            ->willReturn($site);

        $result = $this->service->updateFavicon(1, $faviconPath);

        $this->assertIsArray($result);
        $this->assertEquals($faviconPath, $result['favicon']);
    }

    public function testUpdateSettings(): void
    {
        $currentSettings = ['theme' => 'light'];
        $newSettings = ['sidebar' => 'enabled'];
        $mergedSettings = ['theme' => 'light', 'sidebar' => 'enabled'];

        $site = $this->createSiteMock([
            'id' => 1,
            'settings' => $currentSettings
        ]);

        $updatedSite = $this->createSiteMock([
            'id' => 1,
            'settings' => $mergedSettings
        ]);

        $this->repositoryMock
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($site);

        $this->repositoryMock
            ->expects($this->once())
            ->method('update')
            ->with(1, ['settings' => ['sidebar' => 'enabled']])
            ->willReturn($updatedSite);

        $result = $this->service->updateSettings(1, $newSettings);

        $this->assertIsArray($result);
        $this->assertEquals($mergedSettings, $result['settings']);
    }

    public function testUpdateSettingsThrowsExceptionWhenSiteNotFound(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Site not found');

        $this->repositoryMock
            ->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $this->service->updateSettings(999, ['key' => 'value']);
    }

    public function testToggleStatus(): void
    {
        $site = $this->createSiteMock(['id' => 1, 'is_active' => true]);

        $this->repositoryMock
            ->expects($this->once())
            ->method('update')
            ->with(1, ['is_active' => true])
            ->willReturn($site);

        $result = $this->service->toggleStatus(1, true);

        $this->assertIsArray($result);
        $this->assertTrue($result['is_active']);
    }

    public function testDeleteSite(): void
    {
        $site = $this->createSiteMock([
            'id' => 1,
            'is_default' => false
        ]);

        $this->repositoryMock
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($site);

        $this->repositoryMock
            ->expects($this->once())
            ->method('delete')
            ->with(1)
            ->willReturn(true);

        $result = $this->service->deleteSite(1);

        $this->assertTrue($result);
    }

    public function testDeleteSiteThrowsExceptionWhenNotFound(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Site not found');

        $this->repositoryMock
            ->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $this->service->deleteSite(999);
    }

    public function testDeleteSiteThrowsExceptionWhenDeletingDefaultSite(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot delete default site');

        $site = $this->createSiteMock([
            'id' => 1,
            'is_default' => true
        ]);

        $this->repositoryMock
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($site);

        $this->service->deleteSite(1);
    }

    private function createSiteMock(array $data): Site|MockObject
    {
        $site = $this->createMock(Site::class);

        $site->method('toArray')
            ->willReturn($data);

        if (isset($data['settings'])) {
            $site->settings = $data['settings'];
        }

        if (isset($data['is_default'])) {
            $site->method('isDefault')
                ->willReturn($data['is_default']);
        }

        return $site;
    }
}