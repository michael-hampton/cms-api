<?php

namespace App\Tests\Unit\Services;

use App\Framework\Http\UploadedFile;
use App\Models\Merchant;
use App\Repositories\Product\MerchantRepository;
use App\Services\Cms\ImageUploadService;
use App\Services\Product\MerchantService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use Mockery;

class MerchantServiceTest extends FunctionalTestCase
{
    use CreatesTestData;

    protected $repository;
    protected $imageUploadService;
    protected MerchantService $service;

    public function testCreateMerchantWithLogo()
    {
        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('isValid')->andReturn(true);

        $this->imageUploadService->shouldReceive('uploadToPath')
            ->once()
            ->with($file, Mockery::type('string'))
            ->andReturn('merchants/2025-01/logo.png');

        $merchant = new Merchant(['id' => 1, 'name' => 'Test Merchant', 'logo' => 'merchants/2025-01/logo.png']);

        $this->repository->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['logo'] === 'merchants/2025-01/logo.png';
            }))
            ->andReturn($merchant);

        $this->repository->shouldReceive('syncUrls')->never();
        $this->repository->shouldReceive('syncSites')->never();

        $data = ['name' => 'Test Merchant', 'is_active' => true];
        $result = $this->service->createMerchant($data, $file);

        $this->assertEquals('Test Merchant', $result->name);
        $this->assertEquals('merchants/2025-01/logo.png', $result->logo);
    }

    public function testCreateMerchantWithUrls()
    {
        $data = [
            'name' => 'Test Merchant',
            'urls' => [
                ['url' => 'https://primary.com', 'is_primary' => true],
                ['url' => 'https://secondary.com', 'is_primary' => false],
            ]
        ];

        $merchant = new Merchant(['id' => 1, 'name' => 'Test Merchant']);

        $this->repository->shouldReceive('create')
            ->once()
            ->andReturn($merchant);

        $this->repository->shouldReceive('syncUrls')
            ->once()
            ->with(1, Mockery::type('array'));

        $this->repository->shouldReceive('syncSites')->never();

        $result = $this->service->createMerchant($data);

        $this->assertEquals('Test Merchant', $result->name);
    }

    public function testCreateMerchantWithSites()
    {
        $data = [
            'name' => 'Test Merchant',
            'site_ids' => [1, 2, 3]
        ];

        $merchant = new Merchant(['id' => 1, 'name' => 'Test Merchant']);

        $this->repository->shouldReceive('create')
            ->once()
            ->andReturn($merchant);

        $this->repository->shouldReceive('syncSites')
            ->once()
            ->with(1, [1, 2, 3]);

        $this->repository->shouldReceive('syncUrls')->never();

        $result = $this->service->createMerchant($data);

        $this->assertEquals('Test Merchant', $result->name);
    }

    public function testUpdateMerchantWithLogo()
    {
        $merchant = new Merchant(['id' => 1, 'name' => 'Old Name', 'logo' => 'old-logo.png']);

        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('isValid')->andReturn(true);

        $this->repository->shouldReceive('find')
            ->with(1)
            ->andReturn($merchant);

        $this->imageUploadService->shouldReceive('uploadToPath')
            ->once()
            ->with($file, Mockery::type('string'), 'old-logo.png')
            ->andReturn('merchants/2025-01/new-logo.png');

        $this->repository->shouldReceive('update')
            ->once()
            ->andReturn($merchant);

        $this->repository->shouldReceive('syncUrls')->never();
        $this->repository->shouldReceive('syncSites')->never();

        $data = ['name' => 'New Name'];
        $result = $this->service->updateMerchant(1, $data, $file);

        $this->assertNotNull($result);
    }

    public function testUpdateMerchantReturnsNullWhenNotFound()
    {
        $this->repository->shouldReceive('find')
            ->with(999)
            ->once()
            ->andReturn(null);

        $result = $this->service->updateMerchant(999, ['name' => 'Test']);

        $this->assertNull($result);
    }

    public function testDeleteMerchantDeletesLogo()
    {
        $merchant = new Merchant(['id' => 1, 'name' => 'Test', 'logo' => 'merchants/logo.png']);

        $this->repository->shouldReceive('find')
            ->with(1)
            ->andReturn($merchant);

        $this->imageUploadService->shouldReceive('delete')
            ->once()
            ->with('merchants/logo.png');

        $this->repository->shouldReceive('deleteUrls')
            ->with(1)
            ->once();

        $this->repository->shouldReceive('delete')
            ->with(1)
            ->andReturn(true);

        $result = $this->service->deleteMerchant(1);

        $this->assertTrue($result);
    }

    public function testDeleteMerchantReturnsFalseWhenNotFound()
    {
        $this->repository->shouldReceive('find')
            ->with(999)
            ->andReturn(null);

        $result = $this->service->deleteMerchant(999);

        $this->assertFalse($result);
    }

    public function testToggleStatusTogglesActive()
    {
        $merchant = new Merchant(['id' => 1, 'is_active' => true]);

        $this->repository->shouldReceive('find')
            ->with(1)
            ->andReturn($merchant);

        $this->repository->shouldReceive('update')
            ->once()
            ->with(1, ['is_active' => false])
            ->andReturn($merchant);

        $result = $this->service->toggleStatus(1);

        $this->assertNotNull($result);
    }

    public function testToggleStatusReturnsNullWhenNotFound()
    {
        $this->repository->shouldReceive('find')
            ->with(999)
            ->andReturn(null);

        $result = $this->service->toggleStatus(999);

        $this->assertNull($result);
    }

    public function testBulkUpdateStatus()
    {
        $this->repository->shouldReceive('bulkUpdateStatus')
            ->once()
            ->with([1, 2, 3], false)
            ->andReturn(3);

        $result = $this->service->bulkUpdateStatus([1, 2, 3], false);

        $this->assertEquals(3, $result);
    }

    public function testBulkDeleteDeletesLogosAndMerchants()
    {
        $merchant1 = new Merchant(['id' => 1, 'logo' => 'logo1.png']);
        $merchant2 = new Merchant(['id' => 2, 'logo' => 'logo2.png']);

        $this->repository->shouldReceive('find')
            ->with(1)
            ->andReturn($merchant1);

        $this->repository->shouldReceive('find')
            ->with(2)
            ->andReturn($merchant2);

        $this->imageUploadService->shouldReceive('delete')
            ->with('logo1.png')
            ->once();

        $this->imageUploadService->shouldReceive('delete')
            ->with('logo2.png')
            ->once();

        $this->repository->shouldReceive('bulkDelete')
            ->once()
            ->with([1, 2])
            ->andReturn(2);

        $result = $this->service->bulkDelete([1, 2]);

        $this->assertEquals(2, $result);
    }

    public function testGetAllMerchants()
    {
        $merchants = collect([
            new Merchant(['id' => 1, 'name' => 'Merchant 1']),
            new Merchant(['id' => 2, 'name' => 'Merchant 2']),
        ]);

        $this->repository->shouldReceive('all')
            ->once()
            ->andReturn($merchants);

        $result = $this->service->getAllMerchants();

        $this->assertCount(2, $result);
    }

    public function testGetActiveMerchants()
    {
        $merchants = collect([
            new Merchant(['id' => 1, 'name' => 'Merchant 1', 'is_active' => true]),
        ]);

        $this->repository->shouldReceive('getActive')
            ->once()
            ->andReturn($merchants);

        $result = $this->service->getActiveMerchants();

        $this->assertCount(1, $result);
    }

    public function testGetMerchant()
    {
        $merchant = new Merchant(['id' => 1, 'name' => 'Test Merchant']);

        $this->repository->shouldReceive('find')
            ->with(1, ['contact', 'urls', 'sites', 'productFeeds'])
            ->once()
            ->andReturn($merchant);

        $result = $this->service->getMerchant(1);

        $this->assertEquals('Test Merchant', $result->name);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(MerchantRepository::class);
        $this->imageUploadService = Mockery::mock(ImageUploadService::class);

        $this->imageUploadService->shouldReceive('setAllowedMimeTypes')->andReturnSelf();
        $this->imageUploadService->shouldReceive('setMaxFileSize')->andReturnSelf();

        $this->service = new MerchantService($this->repository, $this->imageUploadService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}