<?php

namespace App\Tests\Unit\Repositories\Cms;

use App\Models\Site;
use App\Repositories\Cms\SiteRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class SiteRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private SiteRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new SiteRepository($this->database);
    }

    public function test_find_returns_site_when_exists(): void
    {
        // Arrange
        $site = $this->createSite(['name' => 'Test Site']);

        // Act
        $found = $this->repository->find($site->id);

        // Assert
        $this->assertNotNull($found);
        $this->assertEquals($site->id, $found->id);
        $this->assertEquals('Test Site', $found->name);
    }

    public function test_find_returns_null_when_not_exists(): void
    {
        // Act
        $found = $this->repository->find(99999);

        // Assert
        $this->assertNull($found);
    }

    public function test_find_by_domain_returns_site(): void
    {
        // Arrange
        $site = $this->createSite(['domain' => 'example.com']);

        // Act
        $found = $this->repository->findByDomain('example.com');

        // Assert
        $this->assertNotNull($found);
        $this->assertEquals($site->id, $found->id);
        $this->assertEquals('example.com', $found->domain);
    }

    public function test_find_by_domain_returns_null_when_not_exists(): void
    {
        // Act
        $found = $this->repository->findByDomain('nonexistent.com');

        // Assert
        $this->assertNull($found);
    }

    public function test_update_modifies_site(): void
    {
        // Arrange
        $site = $this->createSite(['name' => 'Original Name']);

        // Act
        $updated = $this->repository->update($site->id, ['name' => 'Updated Name']);

        // Assert
        $this->assertEquals('Updated Name', $updated->name);

        $fresh = $this->fresh($site);
        $this->assertEquals('Updated Name', $fresh->name);
    }

    public function test_update_throws_exception_when_site_not_found(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Site not found');

        $this->repository->update(99999, ['name' => 'New Name']);
    }

    public function test_update_contact_info_updates_site(): void
    {
        // Arrange
        $site = $this->createSite();

        // Act
        $updated = $this->repository->updateContactInfo($site->id, [
            'contact_email' => 'contact@example.com',
            'contact_phone' => '123-456-7890',
        ]);

        // Assert
        $this->assertEquals('contact@example.com', $updated->contact_email);
        $this->assertEquals('123-456-7890', $updated->contact_phone);
    }

    public function test_create_saves_new_site(): void
    {
        // Arrange
        $data = [
            'name' => 'New Site',
            'slug' => 'new-site-' . uniqid(),
            'domain' => 'newsite.com',
            'is_active' => true,
        ];

        // Act
        $site = $this->repository->create($data);

        // Assert
        $this->assertNotNull($site);
        $this->assertEquals('New Site', $site->name);
        $this->assertEquals('newsite.com', $site->domain);

        $this->assertDatabaseHas('sites', [
            'name' => 'New Site',
            'domain' => 'newsite.com',
        ]);
    }

    public function test_delete_removes_site(): void
    {
        // Arrange
        $site = $this->createSite();

        // Act
        $result = $this->repository->delete($site->id);

        // Assert
        $this->assertTrue($result);
        $this->assertNull(Site::find($site->id));
    }

    public function test_delete_throws_exception_when_site_not_found(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Site not found');

        $this->repository->delete(99999);
    }

    public function test_find_all_returns_all_sites(): void
    {
        // Arrange
        $site1 = $this->createSite(['name' => 'Site 1']);
        $site2 = $this->createSite(['name' => 'Site 2']);

        // Act
        $sites = $this->repository->findAll();

        // Assert
        $this->assertIsArray($sites);
        $this->assertGreaterThanOrEqual(2, count($sites));
    }

    public function test_find_active_returns_only_active_sites(): void
    {
        // Arrange
        $active1 = $this->createSite(['name' => 'Active 1', 'is_active' => true]);
        $active2 = $this->createSite(['name' => 'Active 2', 'is_active' => true]);
        $inactive = $this->createSite(['name' => 'Inactive', 'is_active' => false]);

        // Act
        $sites = $this->repository->findActive();

        // Assert
        $this->assertIsArray($sites);
        $this->assertGreaterThanOrEqual(2, count($sites));

        foreach ($sites as $site) {
            $this->assertEquals(1, $site['is_active']);
        }
    }

    public function test_find_default_returns_default_site(): void
    {
        // Arrange
        $defaultSite = $this->createSite(['name' => 'Default Site', 'is_default' => true]);
        $regularSite = $this->createSite(['name' => 'Regular Site', 'is_default' => false]);

        // Act
        $found = $this->repository->findDefault();

        // Assert
        $this->assertNotNull($found);
        $this->assertEquals(1, $found->is_default);
    }

    public function test_exists_by_domain_returns_true_when_exists(): void
    {
        // Arrange
        $site = $this->createSite(['domain' => 'existing.com']);

        // Act
        $exists = $this->repository->existsByDomain('existing.com');

        // Assert
        $this->assertTrue($exists);
    }

    public function test_exists_by_domain_returns_false_when_not_exists(): void
    {
        // Act
        $exists = $this->repository->existsByDomain('nonexistent.com');

        // Assert
        $this->assertFalse($exists);
    }

    public function test_exists_by_domain_excludes_specified_id(): void
    {
        // Arrange
        $site = $this->createSite(['domain' => 'test.com']);

        // Act
        $exists = $this->repository->existsByDomain('test.com', $site->id);

        // Assert
        $this->assertFalse($exists);
    }

    public function test_exists_by_slug_returns_true_when_exists(): void
    {
        // Arrange
        $site = $this->createSite(['slug' => 'existing-slug']);

        // Act
        $exists = $this->repository->existsBySlug('existing-slug');

        // Assert
        $this->assertTrue($exists);
    }

    public function test_exists_by_slug_returns_false_when_not_exists(): void
    {
        // Act
        $exists = $this->repository->existsBySlug('nonexistent-slug');

        // Assert
        $this->assertFalse($exists);
    }

    public function test_exists_by_slug_excludes_specified_id(): void
    {
        // Arrange
        $site = $this->createSite(['slug' => 'test-slug']);

        // Act
        $exists = $this->repository->existsBySlug('test-slug', $site->id);

        // Assert
        $this->assertFalse($exists);
    }
}