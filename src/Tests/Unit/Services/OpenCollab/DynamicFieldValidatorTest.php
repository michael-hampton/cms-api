<?php

namespace App\Tests\Unit\Services\OpenCollab;

use App\Framework\Support\Collection;
use App\Models\CustomFieldDefinition;
use App\Services\OpenCollab\DynamicFieldValidator;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

class DynamicFieldValidatorTest extends TestCase
{
    private DynamicFieldValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new DynamicFieldValidator();
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    // ── Required field checks ─────────────────────────────────────────────────

    public function test_required_field_with_empty_string_returns_error(): void
    {
        $definitions = new Collection([
            $this->makeDefinition('bio', 'text', required: true),
        ]);

        $errors = $this->validator->validate($definitions, ['bio' => '']);

        $this->assertArrayHasKey('bio', $errors);
    }

    public function test_required_field_with_null_returns_error(): void
    {
        $definitions = new Collection([
            $this->makeDefinition('bio', 'text', required: true),
        ]);

        $errors = $this->validator->validate($definitions, ['bio' => null]);

        $this->assertArrayHasKey('bio', $errors);
    }

    public function test_required_field_missing_from_submitted_returns_error(): void
    {
        $definitions = new Collection([
            $this->makeDefinition('bio', 'text', required: true),
        ]);

        $errors = $this->validator->validate($definitions, []);

        $this->assertArrayHasKey('bio', $errors);
    }

    public function test_required_field_with_value_passes(): void
    {
        $definitions = new Collection([
            $this->makeDefinition('bio', 'text', required: true),
        ]);

        $errors = $this->validator->validate($definitions, ['bio' => 'I write about finance.']);

        $this->assertEmpty($errors);
    }

    public function test_optional_field_missing_from_submitted_passes(): void
    {
        $definitions = new Collection([
            $this->makeDefinition('bio', 'text', required: false),
        ]);

        $errors = $this->validator->validate($definitions, []);

        $this->assertEmpty($errors);
    }

    public function test_optional_field_with_empty_string_passes(): void
    {
        $definitions = new Collection([
            $this->makeDefinition('portfolio_url', 'url', required: false),
        ]);

        $errors = $this->validator->validate($definitions, ['portfolio_url' => '']);

        $this->assertEmpty($errors);
    }

    // ── Email validation ──────────────────────────────────────────────────────

    public function test_invalid_email_returns_error(): void
    {
        $definitions = new Collection([
            $this->makeDefinition('contact_email', 'email', required: false),
        ]);

        $errors = $this->validator->validate($definitions, ['contact_email' => 'not-an-email']);

        $this->assertArrayHasKey('contact_email', $errors);
    }

    public function test_valid_email_passes(): void
    {
        $definitions = new Collection([
            $this->makeDefinition('contact_email', 'email', required: false),
        ]);

        $errors = $this->validator->validate($definitions, ['contact_email' => 'user@example.com']);

        $this->assertEmpty($errors);
    }

    // ── URL validation ────────────────────────────────────────────────────────

    public function test_invalid_url_returns_error(): void
    {
        $definitions = new Collection([
            $this->makeDefinition('portfolio_url', 'url', required: false),
        ]);

        $errors = $this->validator->validate($definitions, ['portfolio_url' => 'not a url']);

        $this->assertArrayHasKey('portfolio_url', $errors);
    }

    public function test_valid_url_passes(): void
    {
        $definitions = new Collection([
            $this->makeDefinition('portfolio_url', 'url', required: false),
        ]);

        $errors = $this->validator->validate($definitions, ['portfolio_url' => 'https://example.com']);

        $this->assertEmpty($errors);
    }

    // ── Number validation ─────────────────────────────────────────────────────

    public function test_non_numeric_value_for_number_field_returns_error(): void
    {
        $definitions = new Collection([
            $this->makeDefinition('years_experience', 'number', required: false),
        ]);

        $errors = $this->validator->validate($definitions, ['years_experience' => 'five']);

        $this->assertArrayHasKey('years_experience', $errors);
    }

    public function test_numeric_string_for_number_field_passes(): void
    {
        $definitions = new Collection([
            $this->makeDefinition('years_experience', 'number', required: false),
        ]);

        $errors = $this->validator->validate($definitions, ['years_experience' => '5']);

        $this->assertEmpty($errors);
    }

    // ── Select validation ─────────────────────────────────────────────────────

    public function test_invalid_select_option_returns_error(): void
    {
        $definition = $this->makeDefinitionWithOptions('tax_country', 'select', required: false, options: [
            ['label' => 'United Kingdom', 'value' => 'GB'],
            ['label' => 'United States', 'value' => 'US'],
        ]);

        $definitions = new Collection([$definition]);

        $errors = $this->validator->validate($definitions, ['tax_country' => 'XX']);

        $this->assertArrayHasKey('tax_country', $errors);
    }

    public function test_valid_select_option_passes(): void
    {
        $definition = $this->makeDefinitionWithOptions('tax_country', 'select', required: false, options: [
            ['label' => 'United Kingdom', 'value' => 'GB'],
            ['label' => 'United States', 'value' => 'US'],
        ]);

        $definitions = new Collection([$definition]);

        $errors = $this->validator->validate($definitions, ['tax_country' => 'GB']);

        $this->assertEmpty($errors);
    }

    // ── Multi-select validation ───────────────────────────────────────────────

    public function test_non_array_value_for_multi_select_returns_error(): void
    {
        $definition = $this->makeDefinitionWithOptions('expertise', 'multi_select', required: false, options: [
            ['label' => 'News', 'value' => 'news'],
        ]);

        $definitions = new Collection([$definition]);

        $errors = $this->validator->validate($definitions, ['expertise' => 'news']);

        $this->assertArrayHasKey('expertise', $errors);
    }

    public function test_multi_select_with_invalid_option_returns_error(): void
    {
        $definition = $this->makeDefinitionWithOptions('expertise', 'multi_select', required: false, options: [
            ['label' => 'News', 'value' => 'news'],
        ]);

        $definitions = new Collection([$definition]);

        $errors = $this->validator->validate($definitions, ['expertise' => ['news', 'invalid_option']]);

        $this->assertArrayHasKey('expertise', $errors);
    }

    public function test_multi_select_with_valid_options_passes(): void
    {
        $definition = $this->makeDefinitionWithOptions('expertise', 'multi_select', required: false, options: [
            ['label' => 'News', 'value' => 'news'],
            ['label' => 'Reviews', 'value' => 'reviews'],
        ]);

        $definitions = new Collection([$definition]);

        $errors = $this->validator->validate($definitions, ['expertise' => ['news', 'reviews']]);

        $this->assertEmpty($errors);
    }

    public function test_required_multi_select_with_empty_array_returns_error(): void
    {
        $definition = $this->makeDefinitionWithOptions('expertise', 'multi_select', required: true, options: [
            ['label' => 'News', 'value' => 'news'],
        ]);

        $definitions = new Collection([$definition]);

        $errors = $this->validator->validate($definitions, ['expertise' => []]);

        $this->assertArrayHasKey('expertise', $errors);
    }

    // ── Multiple fields ───────────────────────────────────────────────────────

    public function test_multiple_invalid_fields_all_return_errors(): void
    {
        $definitions = new Collection([
            $this->makeDefinition('bio', 'text', required: true),
            $this->makeDefinition('portfolio_url', 'url', required: false),
        ]);

        $errors = $this->validator->validate($definitions, [
            'bio'           => '',
            'portfolio_url' => 'not-a-url',
        ]);

        $this->assertArrayHasKey('bio', $errors);
        $this->assertArrayHasKey('portfolio_url', $errors);
    }

    public function test_valid_and_invalid_fields_only_returns_errors_for_invalid(): void
    {
        $definitions = new Collection([
            $this->makeDefinition('bio', 'text', required: true),
            $this->makeDefinition('portfolio_url', 'url', required: false),
        ]);

        $errors = $this->validator->validate($definitions, [
            'bio'           => 'I write about tech.',
            'portfolio_url' => 'not-a-url',
        ]);

        $this->assertArrayNotHasKey('bio', $errors);
        $this->assertArrayHasKey('portfolio_url', $errors);
    }

    public function test_all_valid_fields_returns_empty_array(): void
    {
        $definitions = new Collection([
            $this->makeDefinition('bio', 'text', required: true),
            $this->makeDefinition('portfolio_url', 'url', required: false),
        ]);

        $errors = $this->validator->validate($definitions, [
            'bio'           => 'I write about tech.',
            'portfolio_url' => 'https://example.com',
        ]);

        $this->assertEmpty($errors);
    }

    public function test_empty_definitions_always_passes(): void
    {
        $definitions = new Collection([]);

        $errors = $this->validator->validate($definitions, ['bio' => 'anything']);

        $this->assertEmpty($errors);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeDefinition(string $key, string $type, bool $required): CustomFieldDefinition
    {
        /** @var CustomFieldDefinition&MockInterface $definition */
        $definition = Mockery::mock(CustomFieldDefinition::class)->makePartial();

        $definition->key         = $key;
        $definition->type        = $type;
        $definition->name        = ucfirst(str_replace('_', ' ', $key));
        $definition->is_required = $required;

        $definition->shouldReceive('getOptionsAttribute')->andReturn(null);

        return $definition;
    }

    private function makeDefinitionWithOptions(
        string $key,
        string $type,
        bool   $required,
        array  $options,
    ): CustomFieldDefinition {
        /** @var CustomFieldDefinition&MockInterface $definition */
        $definition = Mockery::mock(CustomFieldDefinition::class)->makePartial();

        $definition->key         = $key;
        $definition->type        = $type;
        $definition->name        = ucfirst(str_replace('_', ' ', $key));
        $definition->is_required = $required;

        $definition->shouldReceive('getOptionsAttribute')->andReturn($options);

        return $definition;
    }
}