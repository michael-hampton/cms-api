<?php

namespace App\Tests\Unit\Services\OpenCollab\Profile;

use App\Enums\Cms\CustomFieldContext;
use App\Enums\Cms\CustomFieldStorageType;
use App\Models\CustomFieldDefinition;
use App\Models\Site;
use App\Repositories\Cms\CustomFieldDefinitionRepository;
use App\Services\OpenCollab\ContributorProfileFieldConfigService;
use DomainException;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ContributorProfileFieldConfigService.
 *
 * Covers:
 *   - Listing fields delegates to the repository.
 *   - Admin can update allowed attributes on an unlocked field.
 *   - Admin cannot update a locked field.
 *   - Admin cannot make a locked field optional.
 *   - Admin cannot make an inactive field required.
 *   - Protected structural columns are silently stripped from updates.
 *   - Field not found throws DomainException.
 */
class ContributorProfileFieldConfigServiceTest extends TestCase
{
    private ContributorProfileFieldConfigService $service;

    /** @var CustomFieldDefinitionRepository&MockInterface */
    private MockInterface $definitionRepo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->definitionRepo = Mockery::mock(CustomFieldDefinitionRepository::class);
        $this->service        = new ContributorProfileFieldConfigService(
            definitionRepository: $this->definitionRepo,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // =========================================================================
    // fieldsForSite() / activeFieldsForSite()
    // =========================================================================

    public function test_fields_for_site_delegates_to_repository(): void
    {
        $site  = $this->makeSite(5);
        $items = [$this->makeDefinition('bio', isLocked: false)];

        $collection = collect($items);

        $this->definitionRepo
            ->shouldReceive('forSiteAndContext')
            ->once()
            ->with(5, CustomFieldContext::ContributorProfile->value)
            ->andReturn($collection);

        $result = $this->service->fieldsForSite($site);

        $this->assertSame($collection, $result);
    }

    public function test_active_fields_for_site_delegates_to_repository(): void
    {
        $site  = $this->makeSite(5);
        $items = [$this->makeDefinition('bio', isLocked: false)];

        $collection = collect($items);

        $this->definitionRepo
            ->shouldReceive('activeForSiteAndContext')
            ->once()
            ->with(5, CustomFieldContext::ContributorProfile->value)
            ->andReturn($collection);

        $result = $this->service->activeFieldsForSite($site);

        $this->assertSame($collection, $result);
    }

    // =========================================================================
    // updateDefinition()
    // =========================================================================

    public function test_admin_can_disable_unlocked_field(): void
    {
        $site       = $this->makeSite(1);
        $definition = $this->makeDefinition('bio', isLocked: false, isActive: true, isRequired: false);

        $this->definitionRepo
            ->shouldReceive('findForSiteContextAndKey')
            ->with(1, CustomFieldContext::ContributorProfile->value, 'bio')
            ->andReturn($definition);

        $definition->shouldReceive('update')
            ->once()
            ->with(Mockery::on(fn($d) => isset($d['is_active']) && $d['is_active'] === false));

        $definition->shouldReceive('fresh')->andReturn($definition);

        $this->service->updateDefinition($site, 'bio', ['is_active' => false]);

        $this->assertTrue(true); // Mockery verifies the call
    }

    public function test_admin_can_enable_unlocked_field(): void
    {
        $site       = $this->makeSite(1);
        $definition = $this->makeDefinition('bio', isLocked: false, isActive: false, isRequired: false);

        $this->definitionRepo
            ->shouldReceive('findForSiteContextAndKey')
            ->andReturn($definition);

        $definition->shouldReceive('update')
            ->once()
            ->with(Mockery::on(fn($d) => isset($d['is_active']) && $d['is_active'] === true));

        $definition->shouldReceive('fresh')->andReturn($definition);

        $this->service->updateDefinition($site, 'bio', ['is_active' => true]);

        $this->assertTrue(true);
    }

    public function test_admin_can_make_unlocked_active_field_required(): void
    {
        $site       = $this->makeSite(1);
        $definition = $this->makeDefinition('bio', isLocked: false, isActive: true, isRequired: false);

        $this->definitionRepo->shouldReceive('findForSiteContextAndKey')->andReturn($definition);

        $definition->shouldReceive('update')
            ->once()
            ->with(Mockery::on(fn($d) => ($d['is_required'] ?? null) === true));

        $definition->shouldReceive('fresh')->andReturn($definition);

        $this->service->updateDefinition($site, 'bio', ['is_required' => true]);

        $this->assertTrue(true);
    }

    public function test_admin_can_make_unlocked_field_optional(): void
    {
        $site       = $this->makeSite(1);
        $definition = $this->makeDefinition('bio', isLocked: false, isActive: true, isRequired: true);

        $this->definitionRepo->shouldReceive('findForSiteContextAndKey')->andReturn($definition);

        $definition->shouldReceive('update')
            ->once()
            ->with(Mockery::on(fn($d) => ($d['is_required'] ?? null) === false));

        $definition->shouldReceive('fresh')->andReturn($definition);

        $this->service->updateDefinition($site, 'bio', ['is_required' => false]);

        $this->assertTrue(true);
    }

    public function test_admin_cannot_disable_locked_field(): void
    {
        $site       = $this->makeSite(1);
        $definition = $this->makeDefinition('email', isLocked: true);

        $this->definitionRepo->shouldReceive('findForSiteContextAndKey')->andReturn($definition);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/required by the system/');

        $this->service->updateDefinition($site, 'email', ['is_active' => false]);
    }

    public function test_admin_cannot_make_locked_field_optional(): void
    {
        $site       = $this->makeSite(1);
        $definition = $this->makeDefinition('name', isLocked: true);

        $this->definitionRepo->shouldReceive('findForSiteContextAndKey')->andReturn($definition);

        $this->expectException(DomainException::class);

        $this->service->updateDefinition($site, 'name', ['is_required' => false]);
    }

    public function test_admin_cannot_make_inactive_field_required(): void
    {
        $site       = $this->makeSite(1);
        $definition = $this->makeDefinition('bio', isLocked: false, isActive: false, isRequired: false);

        $this->definitionRepo->shouldReceive('findForSiteContextAndKey')->andReturn($definition);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/inactive profile field cannot be required/');

        $this->service->updateDefinition($site, 'bio', ['is_active' => false, 'is_required' => true]);
    }

    public function test_field_not_found_throws_domain_exception(): void
    {
        $site = $this->makeSite(1);

        $this->definitionRepo
            ->shouldReceive('findForSiteContextAndKey')
            ->andReturn(null);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/not found/');

        $this->service->updateDefinition($site, 'does_not_exist', ['is_active' => false]);
    }

    public function test_protected_structural_columns_are_stripped_from_update(): void
    {
        $site       = $this->makeSite(1);
        $definition = $this->makeDefinition('bio', isLocked: false, isActive: true, isRequired: false);

        $this->definitionRepo->shouldReceive('findForSiteContextAndKey')->andReturn($definition);

        // Verify that only the allowed key is passed through.
        $definition->shouldReceive('update')
            ->once()
            ->with(Mockery::on(function (array $data) {
                $forbidden = ['key', 'context', 'storage_type', 'profile_column', 'is_locked', 'site_id'];
                foreach ($forbidden as $col) {
                    if (array_key_exists($col, $data)) {
                        return false;
                    }
                }
                return true;
            }));

        $definition->shouldReceive('fresh')->andReturn($definition);

        $this->service->updateDefinition($site, 'bio', [
            'name'         => 'Short bio',
            'key'          => 'hacked_key',        // should be stripped
            'context'      => 'page',               // should be stripped
            'storage_type' => 'custom_value',       // should be stripped
            'profile_column' => 'other_column',     // should be stripped
            'is_locked'    => false,                 // should be stripped
            'site_id'      => 999,                  // should be stripped
        ]);

        $this->assertTrue(true);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeSite(int $id): Site&MockInterface
    {
        $site     = Mockery::mock(Site::class)->makePartial();
        $site->id = $id;
        return $site;
    }

    private function makeDefinition(
        string $key,
        bool   $isLocked   = false,
        bool   $isActive   = true,
        bool   $isRequired = true,
    ): CustomFieldDefinition&MockInterface {
        $definition = Mockery::mock(CustomFieldDefinition::class)->makePartial();
        $definition->key         = $key;
        $definition->name        = ucfirst($key);
        $definition->is_active   = $isActive;
        $definition->is_required = $isRequired;
        $definition->is_locked   = $isLocked;
        $definition->context     = CustomFieldContext::ContributorProfile->value;
        $definition->storage_type = CustomFieldStorageType::ProfileColumn->value;
        $definition->shouldReceive('isLocked')->andReturn($isLocked);
        return $definition;
    }
}