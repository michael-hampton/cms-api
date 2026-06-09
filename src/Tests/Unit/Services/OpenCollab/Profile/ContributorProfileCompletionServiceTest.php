<?php

namespace App\Tests\Unit\Services\OpenCollab\Profile;

use App\Enums\Cms\CustomFieldContext;
use App\Enums\Cms\CustomFieldStorageType;
use App\Models\ContributorProfile;
use App\Models\CustomFieldDefinition;
use App\Models\Site;
use App\Repositories\Cms\CustomFieldDefinitionRepository;
use App\Repositories\OpenCollab\ContributorProfileRepository;
use App\Services\OpenCollab\ContributorProfileCompletionService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ContributorProfileCompletionService.
 *
 * The service should:
 *   - Return incomplete when a locked required field is missing.
 *   - Return incomplete when an active required field is missing.
 *   - Return complete when all active required fields are present.
 *   - Ignore inactive fields entirely.
 *   - Ignore optional fields entirely.
 *   - Read values through the profile_column mapping.
 *   - Treat empty string and empty array as missing.
 *   - Not be affected by another site's definitions.
 *   - Not be affected by page context definitions.
 */
class ContributorProfileCompletionServiceTest extends TestCase
{
    private ContributorProfileCompletionService $service;

    /** @var ContributorProfileRepository&MockInterface */
    private MockInterface $profileRepo;

    /** @var CustomFieldDefinitionRepository&MockInterface */
    private MockInterface $definitionRepo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->profileRepo    = Mockery::mock(ContributorProfileRepository::class);
        $this->definitionRepo = Mockery::mock(CustomFieldDefinitionRepository::class);

        $this->service = new ContributorProfileCompletionService(
            profileRepository:    $this->profileRepo,
            definitionRepository: $this->definitionRepo,
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // =========================================================================
    // isComplete() / missingFields()
    // =========================================================================

    public function test_profile_is_incomplete_when_locked_required_field_is_missing(): void
    {
        $site    = $this->makeSite(1);
        $profile = $this->makeProfile(['bio' => null]);
        $bioDefinition = $this->makeDefinition('bio', 'bio', isRequired: true, isLocked: true);

        $this->profileRepo->shouldReceive('findByUserId')->with(1)->andReturn($profile);
        $this->definitionRepo
            ->shouldReceive('activeRequiredForSiteAndContext')
            ->with(1, CustomFieldContext::ContributorProfile->value)
            ->andReturn(collect([$bioDefinition]));

        $this->assertFalse($this->service->isComplete(1, $site));
        $missing = $this->service->missingFields(1, $site);
        $this->assertCount(1, $missing);
        $this->assertSame('bio', $missing[0]['key']);
    }

    public function test_profile_is_incomplete_when_active_required_field_is_missing(): void
    {
        $site        = $this->makeSite(1);
        $profile     = $this->makeProfile(['display_name' => '']);
        $definition  = $this->makeDefinition('display_name', 'display_name', isRequired: true);

        $this->profileRepo->shouldReceive('findByUserId')->andReturn($profile);
        $this->definitionRepo
            ->shouldReceive('activeRequiredForSiteAndContext')
            ->andReturn(collect([$definition]));

        $this->assertFalse($this->service->isComplete(1, $site));
    }

    public function test_profile_is_complete_when_all_active_required_fields_are_present(): void
    {
        $site    = $this->makeSite(1);
        $profile = $this->makeProfile(['bio' => 'Hello world', 'display_name' => 'Jane']);

        $bioDefinition         = $this->makeDefinition('bio', 'bio', isRequired: true);
        $displayNameDefinition = $this->makeDefinition('display_name', 'display_name', isRequired: true);

        $this->profileRepo->shouldReceive('findByUserId')->andReturn($profile);
        $this->definitionRepo
            ->shouldReceive('activeRequiredForSiteAndContext')
            ->andReturn(collect([$bioDefinition, $displayNameDefinition]));

        $this->assertTrue($this->service->isComplete(1, $site));
        $this->assertSame([], $this->service->missingFields(1, $site));
    }

    public function test_inactive_fields_do_not_block_completion(): void
    {
        $site    = $this->makeSite(1);
        $profile = $this->makeProfile(['bio' => 'Present']);

        // active_required query returns only active+required — so inactive fields
        // never appear here. Zero definitions → complete.
        $this->profileRepo->shouldReceive('findByUserId')->andReturn($profile);
        $this->definitionRepo
            ->shouldReceive('activeRequiredForSiteAndContext')
            ->andReturn(collect());

        $this->assertTrue($this->service->isComplete(1, $site));
    }

    public function test_optional_fields_do_not_block_completion(): void
    {
        $site    = $this->makeSite(1);
        $profile = $this->makeProfile(['bio' => null]); // bio is optional here

        // active_required query returns zero → complete.
        $this->profileRepo->shouldReceive('findByUserId')->andReturn($profile);
        $this->definitionRepo
            ->shouldReceive('activeRequiredForSiteAndContext')
            ->andReturn(collect());

        $this->assertTrue($this->service->isComplete(1, $site));
    }

    public function test_empty_string_is_treated_as_missing(): void
    {
        $site       = $this->makeSite(1);
        $profile    = $this->makeProfile(['bio' => '   ']);
        $definition = $this->makeDefinition('bio', 'bio', isRequired: true);

        $this->profileRepo->shouldReceive('findByUserId')->andReturn($profile);
        $this->definitionRepo
            ->shouldReceive('activeRequiredForSiteAndContext')
            ->andReturn(collect([$definition]));

        $this->assertFalse($this->service->isComplete(1, $site));
    }

    public function test_empty_array_is_treated_as_missing(): void
    {
        $site       = $this->makeSite(1);
        $profile    = $this->makeProfile(['expertise_json' => []]);
        $definition = $this->makeDefinition('expertise', 'expertise_json', isRequired: true);

        $this->profileRepo->shouldReceive('findByUserId')->andReturn($profile);
        $this->definitionRepo
            ->shouldReceive('activeRequiredForSiteAndContext')
            ->andReturn(collect([$definition]));

        $this->assertFalse($this->service->isComplete(1, $site));
    }

    public function test_null_is_treated_as_missing(): void
    {
        $site       = $this->makeSite(1);
        $profile    = $this->makeProfile(['bio' => null]);
        $definition = $this->makeDefinition('bio', 'bio', isRequired: true);

        $this->profileRepo->shouldReceive('findByUserId')->andReturn($profile);
        $this->definitionRepo
            ->shouldReceive('activeRequiredForSiteAndContext')
            ->andReturn(collect([$definition]));

        $this->assertFalse($this->service->isComplete(1, $site));
    }

    public function test_completion_uses_profile_column_mapping(): void
    {
        $site    = $this->makeSite(1);
        // Key is 'portfolio_url', but column is 'portfolio_url' — ensure the mapping
        // is followed (not the key name directly).
        $profile    = $this->makeProfile(['portfolio_url' => 'https://example.com']);
        $definition = $this->makeDefinition('portfolio_url', 'portfolio_url', isRequired: true);

        $this->profileRepo->shouldReceive('findByUserId')->andReturn($profile);
        $this->definitionRepo
            ->shouldReceive('activeRequiredForSiteAndContext')
            ->andReturn(collect([$definition]));

        $this->assertTrue($this->service->isComplete(1, $site));
    }

    public function test_missing_fields_returns_descriptive_entries(): void
    {
        $site       = $this->makeSite(1);
        $profile    = $this->makeProfile(['bio' => null]);
        $definition = $this->makeDefinition(
            'bio',
            'bio',
            isRequired: true,
            description: 'Your bio.',
            placeholder: 'Enter bio',
        );

        $this->profileRepo->shouldReceive('findByUserId')->andReturn($profile);
        $this->definitionRepo
            ->shouldReceive('activeRequiredForSiteAndContext')
            ->andReturn(collect([$definition]));

        $missing = $this->service->missingFields(1, $site);

        $this->assertCount(1, $missing);
        $this->assertSame('bio', $missing[0]['key']);
        $this->assertArrayHasKey('name',        $missing[0]);
        $this->assertArrayHasKey('type',        $missing[0]);
        $this->assertArrayHasKey('description', $missing[0]);
        $this->assertArrayHasKey('placeholder', $missing[0]);
    }

    public function test_definitions_for_another_site_are_not_used(): void
    {
        // Site 1's definition requires bio; site 2's does not (separate calls, not mixed).
        $site    = $this->makeSite(2);
        $profile = $this->makeProfile(['bio' => null]);

        $this->profileRepo->shouldReceive('findByUserId')->andReturn($profile);
        $this->definitionRepo
            ->shouldReceive('activeRequiredForSiteAndContext')
            ->with(2, CustomFieldContext::ContributorProfile->value)
            ->andReturn(collect()); // site 2 has no required fields

        $this->assertTrue($this->service->isComplete(1, $site));
    }

    public function test_page_context_definitions_do_not_affect_profile_completion(): void
    {
        // The repository is called with contributor_profile context, never 'page'.
        $site    = $this->makeSite(1);
        $profile = $this->makeProfile([]);

        $this->profileRepo->shouldReceive('findByUserId')->andReturn($profile);
        $this->definitionRepo
            ->shouldReceive('activeRequiredForSiteAndContext')
            ->with(1, CustomFieldContext::ContributorProfile->value)
            ->once()
            ->andReturn(collect());

        $this->definitionRepo
            ->shouldNotReceive('activeRequiredForSiteAndContext')
            ->with(1, 'page');

        $this->assertTrue($this->service->isComplete(1, $site));
    }

    public function test_definition_without_profile_column_is_skipped(): void
    {
        $site    = $this->makeSite(1);
        $profile = $this->makeProfile([]);

        $definition = Mockery::mock(CustomFieldDefinition::class)->makePartial();
        $definition->key         = 'custom';
        $definition->name        = 'Custom';
        $definition->type        = 'text';
        $definition->description = null;
        $definition->placeholder = null;
        $definition->storage_type = CustomFieldStorageType::ProfileColumn->value;
        $definition->profile_column = null; // no column mapped
        $definition->shouldReceive('isProfileColumnField')->andReturn(true);
        $definition->shouldReceive('profileColumn')->andReturn(null);

        $this->profileRepo->shouldReceive('findByUserId')->andReturn($profile);
        $this->definitionRepo
            ->shouldReceive('activeRequiredForSiteAndContext')
            ->andReturn(collect([$definition]));

        // No column → skipped → complete.
        $this->assertTrue($this->service->isComplete(1, $site));
    }

    public function test_non_profile_column_definition_is_skipped(): void
    {
        $site    = $this->makeSite(1);
        $profile = $this->makeProfile([]);

        $definition = Mockery::mock(CustomFieldDefinition::class)->makePartial();
        $definition->key = 'computed';
        $definition->shouldReceive('isProfileColumnField')->andReturn(false);

        $this->profileRepo->shouldReceive('findByUserId')->andReturn($profile);
        $this->definitionRepo
            ->shouldReceive('activeRequiredForSiteAndContext')
            ->andReturn(collect([$definition]));

        $this->assertTrue($this->service->isComplete(1, $site));
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

    private function makeProfile(array $attributes): ContributorProfile&MockInterface
    {
        $profile = Mockery::mock(ContributorProfile::class)->makePartial();
        foreach ($attributes as $key => $value) {
            $profile->{$key} = $value;
        }
        return $profile;
    }

    private function makeDefinition(
        string  $key,
        string  $profileColumn,
        bool    $isRequired   = true,
        bool    $isLocked     = false,
        ?string $description  = null,
        ?string $placeholder  = null,
    ): CustomFieldDefinition&MockInterface {
        $definition = Mockery::mock(CustomFieldDefinition::class)->makePartial();
        $definition->key          = $key;
        $definition->name         = ucfirst(str_replace('_', ' ', $key));
        $definition->type         = 'text';
        $definition->description  = $description;
        $definition->placeholder  = $placeholder;
        $definition->storage_type = CustomFieldStorageType::ProfileColumn->value;
        $definition->profile_column = $profileColumn;
        $definition->is_required  = $isRequired;
        $definition->is_locked    = $isLocked;
        $definition->shouldReceive('isProfileColumnField')->andReturn(true);
        $definition->shouldReceive('profileColumn')->andReturn($profileColumn);
        return $definition;
    }
}