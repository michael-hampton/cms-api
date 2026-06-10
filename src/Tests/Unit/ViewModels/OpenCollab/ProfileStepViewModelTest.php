<?php

namespace App\Tests\Unit\ViewModels\OpenCollab;

use App\Models\CustomFieldDefinition;
use App\ViewModels\OpenCollab\ProfileFieldSectionViewModel;
use App\ViewModels\OpenCollab\ProfileStepViewModel;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;


class ProfileStepViewModelTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    // ── Ticket 2: custom fields appear in additionalSections ──────────────────

    public function test_known_fields_do_not_appear_in_additional_sections(): void
    {
        $fields = [
            $this->makeDefinition('bio', 'textarea'),
            $this->makeDefinition('display_name', 'text'),
        ];

        $vm = ProfileStepViewModel::fromFields($fields, null);

        $this->assertEmpty($vm->additionalSections);
    }

    public function test_custom_field_appears_in_additional_sections(): void
    {
        $fields = [
            $this->makeDefinition('bio', 'textarea'),
            $this->makeDefinition('custom_why_join', 'textarea'), // unknown key
        ];

        $vm = ProfileStepViewModel::fromFields($fields, null);

        $this->assertCount(1, $vm->additionalSections);
        $this->assertInstanceOf(ProfileFieldSectionViewModel::class, $vm->additionalSections[0]);
    }

    public function test_additional_section_contains_the_custom_field(): void
    {
        $fields = [
            $this->makeDefinition('bio', 'textarea'),
            $this->makeDefinition('custom_why_join', 'textarea'),
        ];

        $vm = ProfileStepViewModel::fromFields($fields, null);

        $section = $vm->additionalSections[0];
        $this->assertCount(1, $section->fields);
        $this->assertSame('custom_why_join', $section->fields[0]->key);
    }

    public function test_multiple_custom_fields_all_appear_in_additional_sections(): void
    {
        $fields = [
            $this->makeDefinition('bio', 'textarea'),
            $this->makeDefinition('custom_field_a', 'text'),
            $this->makeDefinition('custom_field_b', 'text'),
        ];

        $vm = ProfileStepViewModel::fromFields($fields, null);

        $this->assertCount(1, $vm->additionalSections);
        $this->assertCount(2, $vm->additionalSections[0]->fields);
    }

    public function test_no_additional_sections_when_all_fields_are_known(): void
    {
        $knownKeys = [
            'display_name', 'bio', 'avatar', 'tax_country', 'timezone',
            'expertise', 'portfolio_url', 'writing_samples',
            'linkedin_url', 'instagram_url', 'tiktok_url',
        ];

        $fields = array_map(fn(string $key) => $this->makeDefinition($key, 'text'), $knownKeys);

        $vm = ProfileStepViewModel::fromFields($fields, null);

        $this->assertEmpty($vm->additionalSections);
    }

    public function test_custom_field_is_accessible_via_field_accessor(): void
    {
        $fields = [
            $this->makeDefinition('custom_why_join', 'text'),
        ];

        $vm = ProfileStepViewModel::fromFields($fields, null);

        $this->assertNotNull($vm->field('custom_why_join'));
        $this->assertSame('custom_why_join', $vm->field('custom_why_join')->key);
    }

    public function test_known_field_is_not_in_additional_sections_but_is_accessible(): void
    {
        $fields = [
            $this->makeDefinition('bio', 'textarea'),
        ];

        $vm = ProfileStepViewModel::fromFields($fields, null);

        $this->assertEmpty($vm->additionalSections);
        $this->assertNotNull($vm->bioField());
    }

    public function test_frontend_fields_array_contains_all_fields_including_custom(): void
    {
        $fields = [
            $this->makeDefinition('bio', 'textarea'),
            $this->makeDefinition('custom_why_join', 'text'),
        ];

        $vm = ProfileStepViewModel::fromFields($fields, null);

        $keys = array_column($vm->frontendFields, 'key');
        $this->assertContains('bio', $keys);
        $this->assertContains('custom_why_join', $keys);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeDefinition(string $key, string $type): CustomFieldDefinition
    {
        /** @var CustomFieldDefinition&MockInterface $definition */
        $definition = Mockery::mock(CustomFieldDefinition::class)->makePartial();

        $definition->shouldReceive('getAttribute')->with('key')->andReturn($key);
        $definition->shouldReceive('getAttribute')->with('type')->andReturn($type);
        $definition->shouldReceive('getAttribute')->with('name')->andReturn(ucfirst(str_replace('_', ' ', $key)));
        $definition->shouldReceive('getAttribute')->with('description')->andReturn('');
        $definition->shouldReceive('getAttribute')->with('placeholder')->andReturn('');
        $definition->shouldReceive('getAttribute')->with('is_required')->andReturn(false);
        $definition->shouldReceive('getAttribute')->with('default_value')->andReturn('');
        $definition->shouldReceive('getAttribute')->with('options')->andReturn(null);
        $definition->shouldReceive('getAttribute')->with('profile_column')->andReturn($key);

        // Allow attribute access via property syntax
        $definition->key         = $key;
        $definition->type        = $type;
        $definition->name        = ucfirst(str_replace('_', ' ', $key));
        $definition->description = '';
        $definition->placeholder = '';
        $definition->is_required = false;
        $definition->default_value = '';
        $definition->options     = null;
        $definition->profile_column = $key;

        return $definition;
    }
}