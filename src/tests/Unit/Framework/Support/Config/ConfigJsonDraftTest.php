<?php

namespace App\Tests\Unit\Framework\Support\Config;

use App\Framework\Support\Config\ConfigJsonDraft;
use PHPUnit\Framework\TestCase;

final class ConfigJsonDraftTest extends TestCase
{
    public function test_valid_json_and_valid_configuration(): void
    {
        $draft = ConfigJsonDraft::fromJsonText('{"a": 1, "b": 2}');

        $this->assertTrue($draft->isValidSyntax);
        $this->assertNull($draft->syntaxError);
        $this->assertSame([], $draft->validationErrors);
        $this->assertTrue($draft->isValidConfiguration());
        $this->assertSame(['a' => 1, 'b' => 2], $draft->model->toArray());
    }

    public function test_invalid_json_syntax_preserves_raw_text_and_reports_syntax_error_only(): void
    {
        $rawText = '{"a": 1, "b": }';
        $draft = ConfigJsonDraft::fromJsonText($rawText);

        $this->assertFalse($draft->isValidSyntax);
        $this->assertNotNull($draft->syntaxError);
        $this->assertNull($draft->model);
        $this->assertSame([], $draft->validationErrors);
        $this->assertFalse($draft->isValidConfiguration());
        $this->assertSame($rawText, $draft->rawText, 'raw text must be preserved exactly, even when invalid');
    }

    public function test_valid_json_but_invalid_configuration_is_a_distinct_state_from_invalid_syntax(): void
    {
        $draft = ConfigJsonDraft::fromJsonText('{"": 1}');

        $this->assertTrue($draft->isValidSyntax, 'this is syntactically valid JSON');
        $this->assertNull($draft->syntaxError);
        $this->assertNotNull($draft->model, 'model is still built so the UI can show it even though it is invalid');
        $this->assertNotEmpty($draft->validationErrors);
        $this->assertFalse($draft->isValidConfiguration());
    }

    public function test_duplicate_keys_are_surfaced_as_validation_errors_not_silently_collapsed(): void
    {
        $draft = ConfigJsonDraft::fromJsonText('{"a": 1, "a": 2}');

        $this->assertTrue($draft->isValidSyntax);
        $this->assertSame(2, $draft->model->size(), 'both entries must survive the parse');
        $this->assertTrue($draft->model->hasDuplicateKeys());
        $this->assertNotEmpty($draft->validationErrors);
        $this->assertFalse($draft->isValidConfiguration());
    }

    public function test_raw_text_is_always_preserved_regardless_of_validity(): void
    {
        $rawText = "{\n  \"a\": 1,\n  \"a\": 2\n}";
        $draft = ConfigJsonDraft::fromJsonText($rawText);

        $this->assertSame($rawText, $draft->rawText);
    }

    public function test_to_array_shape(): void
    {
        $draft = ConfigJsonDraft::fromJsonText('{"a": 1}');

        $this->assertSame([
            'isValidSyntax' => true,
            'syntaxError' => null,
            'isValidConfiguration' => true,
            'validationErrors' => [],
        ], $draft->toArray());
    }
}