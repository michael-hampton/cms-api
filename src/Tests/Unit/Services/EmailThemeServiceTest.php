<?php
// tests/Unit/Services/EmailThemeServiceTest.php

namespace App\Tests\Unit\Services;

use App\Framework\Database\Database;
use App\Models\EmailTheme;
use App\Repositories\EmailThemeRepository;
use App\Services\EmailThemeService;
use App\Services\ImageUploadService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use Mockery;

class EmailThemeServiceTest extends FunctionalTestCase
{
    private $repository;
    private $imageUploadService;
    private $databaseMock;
    private $service;

    public function testCreateThemeGeneratesSlug()
    {
        $data = ['name' => 'Modern Theme'];
        $siteId = 1;

        $this->databaseMock->shouldReceive('transaction')
            ->once()
            ->andReturnUsing(fn($callback) => $callback());

//        $this->repository->shouldReceive('findBySlug')
//            ->with('modern-theme', $siteId)
//            ->once()
//            ->andReturn(null);

        $mockedTheme = Mockery::mock(EmailTheme::class);
        $mockedTheme->shouldReceive('fresh')->andReturn($mockedTheme);

        $mockedTheme->shouldReceive('relationLoaded')
            ->with('id')
            ->andReturn(true);

        $mockedTheme->shouldReceive('getRelation')
            ->with('id')
            ->andReturn(1);

        $this->repository->shouldReceive('find')
            ->with(1, ['assets', 'colors', 'fonts', 'settings'])
            ->once()
            ->andReturn($mockedTheme);

        $this->repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn($arg) => $arg['slug'] === 'modern-theme'))
            ->andReturn($mockedTheme);

        $result = $this->service->createTheme($data, $siteId);

        $this->assertInstanceOf(EmailTheme::class, $result);
    }

    public function testCannotDeleteDefaultTheme()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot delete default theme');

        $theme = Mockery::mock(EmailTheme::class)->makePartial();
        $theme->is_default = true;

        $this->repository->shouldReceive('find')
            ->with(1)
            ->once()
            ->andReturn($theme);

        $this->service->deleteTheme(1);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(EmailThemeRepository::class);
        $this->imageUploadService = Mockery::mock(ImageUploadService::class);
        $this->databaseMock = Mockery::mock(Database::class);

        $this->service = new EmailThemeService(
            $this->databaseMock,
            $this->repository,
            $this->imageUploadService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}