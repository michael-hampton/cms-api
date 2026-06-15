<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Models\TermsVersion;
use App\Repositories\OpenCollab\TermsVersionRepository;
use App\Services\OpenCollab\TermsAcceptanceRequirementService;
use Mockery;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class TermsAcceptanceRequirementServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_latest_material_version_is_required_even_when_newer_silent_version_is_visible(): void
    {
        $repository = Mockery::mock(TermsVersionRepository::class);
        $service = new TermsAcceptanceRequirementService($repository);
        $material = $this->terms(['id' => 10, 'semantic_version' => '1.0.0']);
        $silent = $this->terms(['id' => 11, 'semantic_version' => '1.0.1']);

        $repository->shouldReceive('latestPublishedForSite')->once()->with(5)->andReturn($silent);
        $repository->shouldReceive('latestMaterialPublishedForSite')->once()->with(5)->andReturn($material);

        $this->assertSame($silent, $service->currentVisibleVersion(5));
        $this->assertSame($material, $service->currentRequiredVersion(5));
    }

    public function test_first_published_version_is_required_when_no_material_version_exists(): void
    {
        $repository = Mockery::mock(TermsVersionRepository::class);
        $service = new TermsAcceptanceRequirementService($repository);
        $published = $this->terms(['id' => 1]);

        $repository->shouldReceive('latestMaterialPublishedForSite')->once()->with(5)->andReturnNull();
        $repository->shouldReceive('latestPublishedForSite')->once()->with(5)->andReturn($published);

        $this->assertSame($published, $service->currentRequiredVersion(5));
    }

    public function test_requires_acceptance_when_user_has_not_accepted_required_version(): void
    {
        $repository = Mockery::mock(TermsVersionRepository::class);
        $service = new TermsAcceptanceRequirementService($repository);
        $required = $this->terms(['id' => 10]);

        $repository->shouldReceive('latestMaterialPublishedForSite')->once()->with(5)->andReturn($required);
        $repository->shouldReceive('hasAccepted')->once()->with(20, 5, 10)->andReturnFalse();

        $this->assertTrue($service->requiresAcceptance(20, 5));
    }

    public function test_silent_version_does_not_require_reacceptance_after_material_version_was_accepted(): void
    {
        $repository = Mockery::mock(TermsVersionRepository::class);
        $service = new TermsAcceptanceRequirementService($repository);
        $required = $this->terms(['id' => 10, 'semantic_version' => '1.0.0']);

        $repository->shouldReceive('latestMaterialPublishedForSite')->once()->with(5)->andReturn($required);
        $repository->shouldReceive('hasAccepted')->once()->with(20, 5, 10)->andReturnTrue();

        $this->assertFalse($service->requiresAcceptance(20, 5));
    }

    private function terms(array $attributes): TermsVersion
    {
        $reflection = new ReflectionClass(TermsVersion::class);
        $terms = $reflection->newInstanceWithoutConstructor();
        $terms->forceFill($attributes);

        return $terms;
    }
}
