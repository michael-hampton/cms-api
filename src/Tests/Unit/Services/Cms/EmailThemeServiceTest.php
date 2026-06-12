<?php

namespace App\Tests\Unit\Services\Cms;

use App\Framework\Database\Database;
use App\Framework\Http\UploadedFile;
use App\Models\NewsletterBrandingConfiguration;
use App\Repositories\Newsletters\EmailThemeRepository;
use App\Repositories\Newsletters\NewsletterBrandingRepository;
use App\Services\Cms\ImageUploadService;
use App\Services\Newsletter\EmailThemeService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;
use Mockery\MockInterface;

class EmailThemeServiceTest extends FunctionalTestCase
{
    private MockInterface $repository;
    private MockInterface $imageUploadService;
    private MockInterface $db;
    private EmailThemeService $service;
    private NewsletterBrandingRepository $newsletterBrandingRepository;

    // -------------------------------------------------------------------------
    // createTheme
    // -------------------------------------------------------------------------

    public function testCreateThemeGeneratesSlugFromName(): void
    {
        $data = ['name' => 'Modern Theme'];
        $siteId = 1;

        $this->db->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $mockedTheme = Mockery::mock(NewsletterBrandingConfiguration::class)->makePartial();
        $mockedTheme->id = 42;

        $this->repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn($arg) => $arg['slug'] === 'modern-theme' && $arg['site_id'] === $siteId))
            ->andReturn($mockedTheme);

        $this->repository->shouldReceive('slugExistsForSite');

        $result = $this->service->createTheme($data, $siteId);

        $this->assertInstanceOf(NewsletterBrandingConfiguration::class, $result);
    }

    public function testCreateThemeRespectsExplicitSlug(): void
    {
        $data = ['name' => 'Modern Theme', 'slug' => 'custom-slug'];
        $siteId = 1;

        $this->db->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $mockedTheme = Mockery::mock(NewsletterBrandingConfiguration::class)->makePartial();
        $mockedTheme->id = 5;

        $this->repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn($arg) => $arg['slug'] === 'custom-slug'))
            ->andReturn($mockedTheme);

        $this->repository->shouldReceive('slugExistsForSite');

        $result = $this->service->createTheme($data, $siteId);

        $this->assertInstanceOf(NewsletterBrandingConfiguration::class, $result);
    }

    public function testCreateThemeWrapsInTransaction(): void
    {
        $data = ['name' => 'Transactional Theme'];
        $siteId = 1;

        $this->db->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $mockedTheme = Mockery::mock(NewsletterBrandingConfiguration::class)->makePartial();
        $mockedTheme->id = 7;

        $this->repository->shouldReceive('create')->once()->andReturn($mockedTheme);
        $this->repository->shouldReceive('slugExistsForSite');

        $this->service->createTheme($data, $siteId);

        // Mockery's shouldReceive('transaction')->once() acts as the assertion.
        $this->addToAssertionCount(1);
    }

    public function testCreateThemeUploadsLogoWhenFileProvided(): void
    {
        $data = ['name' => 'Logo Theme'];
        $siteId = 1;

        $logoFile = Mockery::mock(UploadedFile::class);
        $logoFile->shouldReceive('isValid')->andReturn(true);

        $this->imageUploadService->shouldReceive('upload')
            ->once()
            ->with($logoFile)
            ->andReturn('/uploads/logo.png');

        $this->db->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $mockedTheme = Mockery::mock(NewsletterBrandingConfiguration::class)->makePartial();
        $mockedTheme->id = 8;

        $this->repository->shouldReceive('create')
            ->once()
            ->andReturn($mockedTheme);

        $this->repository->shouldReceive('slugExistsForSite');

        $this->service->createTheme($data, $siteId, $logoFile);

        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // deleteTheme
    // -------------------------------------------------------------------------

    public function testCannotDeleteDefaultTheme(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot delete the default theme. Please set another theme as default first.');

        $theme = Mockery::mock(NewsletterBrandingConfiguration::class)->makePartial();
        $theme->id = 1;
        $theme->is_default = true;

        $this->repository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($theme);

        $this->service->deleteTheme(1);
    }

    public function testDeleteThrowsWhenThemeNotFound(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Theme not found');

        $this->repository->shouldReceive('find')
            ->with(99)
            ->once()
            ->andReturn(null);

        $this->service->deleteTheme(99);
    }

    public function testDeleteThemeRemovesLogoAndCallsDelete(): void
    {
        $theme = Mockery::mock(NewsletterBrandingConfiguration::class)->makePartial();
        $theme->id = 3;
        $theme->is_default = false;

        $theme->shouldReceive('getAssets')
            ->once()
            ->andReturn([
                'logo' => [
                    'url' => '/uploads/old-logo.png',
                ],
            ]);

        $theme->shouldReceive('delete')
            ->once()
            ->andReturn(true);

        $this->repository->shouldReceive('find')
            ->with(3)
            ->once()
            ->andReturn($theme);

        $this->imageUploadService->shouldReceive('delete')
            ->once()
            ->with('/uploads/old-logo.png');

        $result = $this->service->deleteTheme(3);

        $this->assertTrue($result);
    }

    // -------------------------------------------------------------------------
    // updateTheme
    // -------------------------------------------------------------------------

    public function testUpdateThemeWrapsInTransaction(): void
    {
        $theme = Mockery::mock(NewsletterBrandingConfiguration::class)->makePartial();
        $theme->id = 1;
        $theme->name = 'Old Name';
        $theme->site_id = 1;

        $updated = Mockery::mock(NewsletterBrandingConfiguration::class);
        $updated->shouldReceive('fresh')->andReturn($updated);

        $this->repository->shouldReceive('find')->with(1)->twice()->andReturn($theme);
        $this->repository->shouldReceive('update')->once()->andReturn($updated);

        $this->db->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $this->service->updateTheme(1, ['description' => 'Changed']);

        $this->addToAssertionCount(1);
    }

    public function testUpdateThemeThrowsWhenNotFound(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Theme not found');

        $this->db->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $this->repository->shouldReceive('find')->with(404)->once()->andReturn(null);

        $this->service->updateTheme(404, ['name' => 'Ghost']);
    }

    public function testUpdateThemeRegeneratesSlugWhenNameChanges(): void
    {
        $theme = Mockery::mock(NewsletterBrandingConfiguration::class)->makePartial();
        $theme->id = 2;
        $theme->name = 'Old Name';
        $theme->site_id = 1;

        $updated = Mockery::mock(NewsletterBrandingConfiguration::class);
        $updated->shouldReceive('fresh')->andReturn($updated);

        $this->repository->shouldReceive('find')->with(2)->twice()->andReturn($theme);
        $this->repository->shouldReceive('update')
            ->once()
            ->with(2, Mockery::on(fn($d) => isset($d['slug']) && str_starts_with($d['slug'], 'new-name')))
            ->andReturn($updated);

        $this->repository->shouldReceive('slugExistsForSite');

        $this->db->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($cb) => $cb());

        $this->service->updateTheme(2, ['name' => 'New Name']);

        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // getThemeVariables
    // -------------------------------------------------------------------------

    public function testGetThemeVariablesThrowsWhenNotFound(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Theme not found');

        $this->repository->shouldReceive('find')
            ->with(999)
            ->once()
            ->andReturn(null);

        $this->service->getThemeVariables(999);
    }

    // -------------------------------------------------------------------------
    // setUp / tearDown
    // -------------------------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(EmailThemeRepository::class);
        $this->imageUploadService = Mockery::mock(ImageUploadService::class);
        $this->db = Mockery::mock(Database::class);
        $this->newsletterBrandingRepository = Mockery::mock(NewsletterBrandingRepository::class);

        $this->service = new EmailThemeService(
            $this->db,
            $this->repository,
            $this->newsletterBrandingRepository,
            $this->imageUploadService,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}