<?php

namespace App\Tests\Unit\Services\PublicContent\Inheritance;

use App\Services\PublicContent\Inheritance\PublicContentSettingsMerger;
use PHPUnit\Framework\TestCase;

final class PublicContentSettingsMergerTest extends TestCase
{
    public function test_child_values_win_over_parent_values(): void
    {
        $merger = new PublicContentSettingsMerger();

        $merged = $merger->merge(['theme' => 'light'], ['theme' => 'dark']);

        self::assertSame(['theme' => 'dark'], $merged);
    }

    public function test_parent_only_keys_are_preserved(): void
    {
        $merger = new PublicContentSettingsMerger();

        $merged = $merger->merge(['theme' => 'light', 'logo' => 'a.png'], ['theme' => 'dark']);

        self::assertSame(['theme' => 'dark', 'logo' => 'a.png'], $merged);
    }

    public function test_null_child_values_do_not_erase_the_parent_value(): void
    {
        $merger = new PublicContentSettingsMerger();

        $merged = $merger->merge(['theme' => 'light'], ['theme' => null]);

        self::assertSame(['theme' => 'light'], $merged);
    }

    public function test_empty_string_child_values_do_not_erase_the_parent_value(): void
    {
        $merger = new PublicContentSettingsMerger();

        $merged = $merger->merge(['theme' => 'light'], ['theme' => '']);

        self::assertSame(['theme' => 'light'], $merged);
    }

    public function test_nested_arrays_are_merged_recursively(): void
    {
        $merger = new PublicContentSettingsMerger();

        $merged = $merger->merge(
            ['colors' => ['primary' => 'blue', 'secondary' => 'grey']],
            ['colors' => ['primary' => 'red']],
        );

        self::assertSame(['colors' => ['primary' => 'red', 'secondary' => 'grey']], $merged);
    }

    public function test_a_child_scalar_overwrites_a_parent_array_entirely(): void
    {
        $merger = new PublicContentSettingsMerger();

        $merged = $merger->merge(
            ['colors' => ['primary' => 'blue']],
            ['colors' => 'inherit'],
        );

        self::assertSame(['colors' => 'inherit'], $merged);
    }

    public function test_a_key_only_in_the_child_is_added(): void
    {
        $merger = new PublicContentSettingsMerger();

        $merged = $merger->merge([], ['theme' => 'dark']);

        self::assertSame(['theme' => 'dark'], $merged);
    }
}