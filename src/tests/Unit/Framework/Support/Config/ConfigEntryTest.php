<?php

namespace App\Tests\Unit\Framework\Support\Config;

use App\Framework\Support\Config\ConfigEntry;
use PHPUnit\Framework\TestCase;

final class ConfigEntryTest extends TestCase
{
    public function test_generates_an_id_when_none_given(): void
    {
        $entry = new ConfigEntry('site.name', 'Acme');

        $this->assertNotSame('', $entry->id);
    }

    public function test_two_entries_get_different_generated_ids(): void
    {
        $a = new ConfigEntry('site.name', 'Acme');
        $b = new ConfigEntry('site.name', 'Acme');

        $this->assertNotSame($a->id, $b->id);
    }

    public function test_accepts_an_explicit_id_for_deserialisation(): void
    {
        $entry = new ConfigEntry('site.name', 'Acme', 'fixed-id-123');

        $this->assertSame('fixed-id-123', $entry->id);
    }

    public function test_with_key_preserves_id_and_value_but_changes_key(): void
    {
        $original = new ConfigEntry('site.name', 'Acme');
        $renamed = $original->withKey('site.title');

        $this->assertSame($original->id, $renamed->id);
        $this->assertSame('Acme', $renamed->value);
        $this->assertSame('site.title', $renamed->key);
        $this->assertSame('site.name', $original->key, 'original entry must be untouched');
    }

    public function test_with_value_preserves_id_and_key_but_changes_value(): void
    {
        $original = new ConfigEntry('site.name', 'Acme');
        $updated = $original->withValue('Acme Corp');

        $this->assertSame($original->id, $updated->id);
        $this->assertSame('site.name', $updated->key);
        $this->assertSame('Acme Corp', $updated->value);
        $this->assertSame('Acme', $original->value, 'original entry must be untouched');
    }

    public function test_to_array_shape(): void
    {
        $entry = new ConfigEntry('site.name', 'Acme', 'id-1');

        $this->assertSame([
            'id' => 'id-1',
            'key' => 'site.name',
            'value' => 'Acme',
        ], $entry->toArray());
    }

    public function test_value_can_be_any_scalar_or_array_type(): void
    {
        $this->assertSame(42, (new ConfigEntry('k', 42))->value);
        $this->assertSame(true, (new ConfigEntry('k', true))->value);
        $this->assertSame(null, (new ConfigEntry('k', null))->value);
        $this->assertSame(['a', 'b'], (new ConfigEntry('k', ['a', 'b']))->value);
    }
}