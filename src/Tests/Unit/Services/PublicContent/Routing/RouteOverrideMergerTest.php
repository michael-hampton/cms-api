<?php

namespace App\Tests\Unit\Services\PublicContent\Routing;

use App\Services\PublicContent\Routing\RouteOverrideMerger;
use PHPUnit\Framework\TestCase;

final class RouteOverrideMergerTest extends TestCase
{
    public function test_shallow_one_level_merge_override_wins_on_simple_values_and_new_keys(): void
    {
        $merger = new RouteOverrideMerger();

        $merged = $merger->merge(
            ['title' => 'base', 'template' => 'default', 'keep' => 'yes'],
            ['title' => 'override', 'extra' => 'new'],
        );

        self::assertSame([
            'title' => 'override',
            'template' => 'default',
            'keep' => 'yes',
            'extra' => 'new',
        ], $merged);
    }

    public function test_named_routing_param_lists_merge_entry_by_entry_by_name(): void
    {
        $merger = new RouteOverrideMerger();

        $merged = $merger->merge(
            [
                'other_routing_params' => [
                    ['name' => 'alpha', 'value' => '1'],
                    ['name' => 'beta', 'value' => '2'],
                ],
                'fcsis_routing_params' => [
                    ['name' => 'slot', 'value' => 'home'],
                ],
            ],
            [
                'other_routing_params' => [
                    ['name' => 'beta', 'value' => 'replaced'],
                    ['name' => 'gamma', 'value' => '3'],
                ],
                'fcsis_routing_params' => [
                    ['name' => 'slot', 'value' => 'category'],
                ],
            ],
        );

        self::assertSame([
            ['name' => 'alpha', 'value' => '1'],
            ['name' => 'beta', 'value' => 'replaced'],
            ['name' => 'gamma', 'value' => '3'],
        ], $merged['other_routing_params']);
        self::assertSame([
            ['name' => 'slot', 'value' => 'category'],
        ], $merged['fcsis_routing_params']);
    }

    /**
     * Pins Flexi shallow merge: nested structures other than the two named
     * routing-param lists are replaced wholesale, never deep-merged.
     */
    public function test_other_nested_structures_are_replaced_wholesale_not_deep_merged(): void
    {
        $merger = new RouteOverrideMerger();

        $merged = $merger->merge(
            [
                'widgets' => ['a' => 1, 'b' => 2],
                'flags' => ['x' => true],
            ],
            [
                'widgets' => ['c' => 3],
            ],
        );

        self::assertSame(['c' => 3], $merged['widgets']);
        self::assertSame(['x' => true], $merged['flags']);
    }
}
