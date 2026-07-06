<?php

namespace App\Tests\Unit\Framework\Support\Config\Json;

use App\Framework\Support\Config\Json\DuplicateKeyAwareJsonParser;
use App\Framework\Support\Config\Json\JsonSyntaxException;
use PHPUnit\Framework\TestCase;

final class DuplicateKeyAwareJsonParserTest extends TestCase
{
    public function test_parses_a_simple_flat_object(): void
    {
        $pairs = DuplicateKeyAwareJsonParser::parseObjectPairs('{"a": 1, "b": "two", "c": true}');

        $this->assertSame([
            ['a', 1],
            ['b', 'two'],
            ['c', true],
        ], $pairs);
    }

    public function test_preserves_duplicate_keys_instead_of_collapsing(): void
    {
        $pairs = DuplicateKeyAwareJsonParser::parseObjectPairs('{"a": 1, "a": 2}');

        $this->assertSame([
            ['a', 1],
            ['a', 2],
        ], $pairs);
    }

    public function test_handles_empty_object(): void
    {
        $this->assertSame([], DuplicateKeyAwareJsonParser::parseObjectPairs('{}'));
    }

    public function test_handles_nested_objects_and_arrays_as_opaque_values(): void
    {
        $pairs = DuplicateKeyAwareJsonParser::parseObjectPairs('{"nested": {"x": 1, "x": 2}, "list": [1, 2, 3]}');

        // Duplicate detection only applies at the top level; nested
        // duplicates are just whatever json_decode does with them.
        $this->assertSame('nested', $pairs[0][0]);
        $this->assertSame(['x' => 2], $pairs[0][1]);
        $this->assertSame('list', $pairs[1][0]);
        $this->assertSame([1, 2, 3], $pairs[1][1]);
    }

    public function test_handles_keys_and_string_values_containing_commas_braces_and_colons(): void
    {
        $pairs = DuplicateKeyAwareJsonParser::parseObjectPairs(
            '{"tricky": "a value, with: commas {and} braces"}',
        );

        $this->assertSame([['tricky', 'a value, with: commas {and} braces']], $pairs);
    }

    public function test_handles_escaped_quotes_in_keys_and_values(): void
    {
        $pairs = DuplicateKeyAwareJsonParser::parseObjectPairs('{"key\\"with\\"quotes": "val\\"ue"}');

        $this->assertSame([['key"with"quotes', 'val"ue']], $pairs);
    }

    public function test_handles_null_values(): void
    {
        $pairs = DuplicateKeyAwareJsonParser::parseObjectPairs('{"a": null}');

        $this->assertSame([['a', null]], $pairs);
    }

    public function test_rejects_non_object_root(): void
    {
        $this->expectException(JsonSyntaxException::class);
        DuplicateKeyAwareJsonParser::parseObjectPairs('[1, 2, 3]');
    }

    public function test_rejects_empty_input(): void
    {
        $this->expectException(JsonSyntaxException::class);
        DuplicateKeyAwareJsonParser::parseObjectPairs('');
    }

    public function test_rejects_unbalanced_braces(): void
    {
        $this->expectException(JsonSyntaxException::class);
        DuplicateKeyAwareJsonParser::parseObjectPairs('{"a": 1');
    }

    public function test_rejects_unterminated_string(): void
    {
        $this->expectException(JsonSyntaxException::class);
        DuplicateKeyAwareJsonParser::parseObjectPairs('{"a": "unterminated}');
    }

    public function test_rejects_malformed_entry_missing_colon(): void
    {
        $this->expectException(JsonSyntaxException::class);
        DuplicateKeyAwareJsonParser::parseObjectPairs('{"a" 1}');
    }

    public function test_rejects_trailing_comma(): void
    {
        $this->expectException(JsonSyntaxException::class);
        DuplicateKeyAwareJsonParser::parseObjectPairs('{"a": 1,}');
    }

    public function test_rejects_invalid_value_json(): void
    {
        $this->expectException(JsonSyntaxException::class);
        DuplicateKeyAwareJsonParser::parseObjectPairs('{"a": undefined}');
    }
}