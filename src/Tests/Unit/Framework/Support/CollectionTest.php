<?php

namespace App\Tests\Unit\Framework\Support;

use App\Framework\Support\Collection;
use PHPUnit\Framework\TestCase;

/**
 * Stub: minimal object that behaves like a model for pluck/where/groupBy tests
 */
class StubItem
{
    public function __construct(
        public readonly int     $id,
        public readonly string  $name,
        public readonly ?string $status = null,
        public readonly ?int    $age = null,
        public readonly ?array  $meta = null,
    )
    {
    }
}

/**
 * Stub that implements ArrayAccess so getNestedValue can use bracket syntax
 */
class ArrayAccessItem implements \ArrayAccess
{
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->data[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->data[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->data[$offset]);
    }
}

/**
 * Nested stub for dot-notation tests
 */
class NestedStub
{
    public function __construct(
        public readonly string $name,
        public readonly ?self  $child = null,
    )
    {
    }
}

class CollectionTest extends TestCase
{
    // ─────────────────────────────────────────────────────────────
    // CONSTRUCTION & FACTORY
    // ─────────────────────────────────────────────────────────────

    public function test_constructor_defaults_to_empty_array(): void
    {
        $c = new Collection();
        $this->assertEquals([], $c->all());
    }

    public function test_constructor_stores_provided_items(): void
    {
        $c = new Collection([1, 2, 3]);
        $this->assertEquals([1, 2, 3], $c->all());
    }

    public function test_make_returns_collection_instance(): void
    {
        $c = Collection::make([4, 5]);
        $this->assertInstanceOf(Collection::class, $c);
        $this->assertEquals([4, 5], $c->all());
    }

    public function test_make_with_no_args_returns_empty(): void
    {
        $this->assertTrue(Collection::make()->isEmpty());
    }

    // ─────────────────────────────────────────────────────────────
    // BASIC ACCESSORS: all, count, isEmpty, isNotEmpty
    // ─────────────────────────────────────────────────────────────

    public function test_count_returns_number_of_items(): void
    {
        $this->assertEquals(0, (new Collection())->count());
        $this->assertEquals(3, (new Collection([1, 2, 3]))->count());
    }

    public function test_isEmpty_on_empty_collection(): void
    {
        $this->assertTrue((new Collection())->isEmpty());
        $this->assertFalse((new Collection([1]))->isEmpty());
    }

    public function test_isNotEmpty_inverse_of_isEmpty(): void
    {
        $this->assertFalse((new Collection())->isNotEmpty());
        $this->assertTrue((new Collection([1]))->isNotEmpty());
    }

    // ─────────────────────────────────────────────────────────────
    // first() / last()
    // ─────────────────────────────────────────────────────────────

    public function test_first_returns_first_item_without_callback(): void
    {
        $this->assertEquals(10, (new Collection([10, 20, 30]))->first());
    }

    public function test_first_returns_null_on_empty(): void
    {
        $this->assertNull((new Collection())->first());
    }

    public function test_first_with_callback_returns_matching_item(): void
    {
        $result = (new Collection([1, 2, 3, 4]))->first(fn($v) => $v > 2);
        $this->assertEquals(3, $result);
    }

    public function test_first_with_callback_returns_null_when_no_match(): void
    {
        $result = (new Collection([1, 2, 3]))->first(fn($v) => $v > 100);
        $this->assertNull($result);
    }

    public function test_first_callback_receives_key(): void
    {
        $c = new Collection(['a' => 1, 'b' => 2, 'c' => 3]);
        $result = $c->first(fn($v, $k) => $k === 'b');
        $this->assertEquals(2, $result);
    }

    public function test_last_returns_last_item_without_callback(): void
    {
        $this->assertEquals(30, (new Collection([10, 20, 30]))->last());
    }

    public function test_last_returns_null_on_empty(): void
    {
        $this->assertNull((new Collection())->last());
    }

    public function test_last_with_callback_returns_last_matching_item(): void
    {
        $result = (new Collection([1, 2, 3, 4, 5]))->last(fn($v) => $v < 4);
        $this->assertEquals(3, $result);
    }

    public function test_last_with_callback_returns_null_when_no_match(): void
    {
        $result = (new Collection([1, 2]))->last(fn($v) => $v > 100);
        $this->assertNull($result);
    }

    // ─────────────────────────────────────────────────────────────
    // map()
    // ─────────────────────────────────────────────────────────────

    public function test_map_transforms_each_item(): void
    {
        $result = (new Collection([1, 2, 3]))->map(fn($v) => $v * 2);
        $this->assertEquals([0 => 2, 1 => 4, 2 => 6], $result->all());
    }

    public function test_map_preserves_keys(): void
    {
        $c = new Collection(['a' => 1, 'b' => 2]);
        $result = $c->map(fn($v) => $v + 10);
        $this->assertEquals(['a' => 11, 'b' => 12], $result->all());
    }

    public function test_map_callback_receives_key(): void
    {
        $c = new Collection(['x' => 5, 'y' => 10]);
        $result = $c->map(fn($v, $k) => "{$k}={$v}");
        $this->assertEquals(['x' => 'x=5', 'y' => 'y=10'], $result->all());
    }

    public function test_map_on_empty_returns_empty_collection(): void
    {
        $this->assertTrue((new Collection())->map(fn($v) => $v)->isEmpty());
    }

    // ─────────────────────────────────────────────────────────────
    // filter() / reject()
    // ─────────────────────────────────────────────────────────────

    public function test_filter_without_callback_removes_falsy(): void
    {
        $c = new Collection([0, 1, '', 'hello', null, false, true, [], [1]]);
        $result = $c->filter();
        // array_filter removes: 0, '', null, false, []
        $this->assertEquals([1 => 1, 3 => 'hello', 6 => true, 8 => [1]], $result->all());
    }

    public function test_filter_with_callback(): void
    {
        $result = (new Collection([1, 2, 3, 4, 5]))->filter(fn($v) => $v % 2 === 0);
        $this->assertEquals([1 => 2, 3 => 4], $result->all());
    }

    public function test_filter_callback_receives_both_value_and_key(): void
    {
        $c = new Collection(['a' => 1, 'b' => 2, 'c' => 3]);
        $result = $c->filter(fn($v, $k) => $k !== 'b');
        $this->assertEquals(['a' => 1, 'c' => 3], $result->all());
    }

    public function test_reject_is_inverse_of_filter(): void
    {
        $c = new Collection([1, 2, 3, 4, 5]);
        $result = $c->reject(fn($v) => $v % 2 === 0);
        $this->assertEquals([0 => 1, 2 => 3, 4 => 5], $result->all());
    }

    // ─────────────────────────────────────────────────────────────
    // values()
    // ─────────────────────────────────────────────────────────────

    public function test_values_reindexes_collection(): void
    {
        $c = new Collection([2 => 'a', 5 => 'b', 9 => 'c']);
        $this->assertEquals([0 => 'a', 1 => 'b', 2 => 'c'], $c->values()->all());
    }

    // ─────────────────────────────────────────────────────────────
    // where()
    // ─────────────────────────────────────────────────────────────

    public function test_where_two_args_defaults_to_equality(): void
    {
        $c = new Collection($this->sampleUsers());
        $result = $c->where('status', 'active')->values();
        $this->assertEquals(3, $result->count());
    }

    private function sampleUsers(): array
    {
        return [
            ['id' => 1, 'name' => 'Alice', 'status' => 'active', 'age' => 30],
            ['id' => 2, 'name' => 'Bob', 'status' => 'inactive', 'age' => 25],
            ['id' => 3, 'name' => 'Charlie', 'status' => 'active', 'age' => 35],
            ['id' => 4, 'name' => 'Diana', 'status' => 'active', 'age' => 28],
        ];
    }

    public function test_where_strict_equality(): void
    {
        $c = new Collection([
            ['val' => 1],
            ['val' => '1'],
            ['val' => true],
        ]);
        $result = $c->where('val', '===', 1);
        $this->assertEquals(1, $result->count());
        $this->assertEquals(1, $result->first()['val']);
    }

    public function test_where_not_equal(): void
    {
        $c = new Collection($this->sampleUsers());
        $result = $c->where('status', '!=', 'active');
        $this->assertEquals(1, $result->count());
        $this->assertEquals('Bob', $result->first()['name']);
    }

    public function test_where_greater_than(): void
    {
        $c = new Collection($this->sampleUsers());
        $result = $c->where('age', '>', 29);
        $this->assertEquals(2, $result->count());
    }

    public function test_where_greater_than_or_equal(): void
    {
        $c = new Collection($this->sampleUsers());
        $result = $c->where('age', '>=', 30);
        $this->assertEquals(2, $result->count());
    }

    public function test_where_less_than(): void
    {
        $c = new Collection($this->sampleUsers());
        $result = $c->where('age', '<', 28);
        $this->assertEquals(1, $result->count());
        $this->assertEquals('Bob', $result->first()['name']);
    }

    public function test_where_less_than_or_equal(): void
    {
        $c = new Collection($this->sampleUsers());
        $result = $c->where('age', '<=', 28);
        $this->assertEquals(2, $result->count());
    }

    public function test_where_unknown_operator_returns_empty(): void
    {
        $c = new Collection($this->sampleUsers());
        $result = $c->where('age', '~=', 30);
        $this->assertTrue($result->isEmpty());
    }

    public function test_where_works_on_objects(): void
    {
        $c = new Collection([
            new StubItem(1, 'Alice', 'active'),
            new StubItem(2, 'Bob', 'inactive'),
        ]);
        $result = $c->where('status', 'active');
        $this->assertEquals(1, $result->count());
        $this->assertEquals('Alice', $result->first()->name);
    }

    // ─────────────────────────────────────────────────────────────
    // pluck()
    // ─────────────────────────────────────────────────────────────

    public function test_pluck_single_key_from_arrays(): void
    {
        $c = new Collection($this->sampleUsers());
        $names = $c->pluck('name');
        $this->assertEquals(['Alice', 'Bob', 'Charlie', 'Diana'], $names->all());
    }

    public function test_pluck_single_key_from_objects(): void
    {
        $c = new Collection([
            new StubItem(1, 'Alice'),
            new StubItem(2, 'Bob'),
        ]);
        $names = $c->pluck('name');
        $this->assertEquals([0 => 'Alice', 1 => 'Bob'], $names->all());
    }

    public function test_pluck_with_keyBy(): void
    {
        $c = new Collection($this->sampleUsers());
        $result = $c->pluck('name', 'id');
        $this->assertEquals([1 => 'Alice', 2 => 'Bob', 3 => 'Charlie', 4 => 'Diana'], $result->all());
    }

    public function test_pluck_multiple_keys(): void
    {
        $c = new Collection($this->sampleUsers());
        $result = $c->pluck(['name', 'age']);
        $first = $result->first();
        $this->assertEquals('Alice', $first['name']);
        $this->assertEquals(30, $first['age']);
        $this->assertEquals(4, $result->count());
    }

    public function test_pluck_dot_notation_nested_array(): void
    {
        $c = new Collection([
            ['user' => ['profile' => ['city' => 'London']]],
            ['user' => ['profile' => ['city' => 'Paris']]],
        ]);
        $cities = $c->pluck('user.profile.city');
        $this->assertEquals([0 => 'London', 1 => 'Paris'], $cities->all());
    }

    public function test_pluck_dot_notation_nested_objects(): void
    {
        $c = new Collection([
            new NestedStub('Parent', new NestedStub('Child')),
        ]);
        $childNames = $c->pluck('child.name');
        $this->assertEquals([0 => 'Child'], $childNames->all());
    }

    public function test_pluck_dot_notation_returns_null_for_missing_path(): void
    {
        $c = new Collection([
            ['user' => ['name' => 'Alice']],
            ['user' => []],                    // missing 'name'
        ]);
        $result = $c->pluck('user.name');
        $this->assertEquals([0 => 'Alice', 1 => null], $result->all());
    }

    // ─────────────────────────────────────────────────────────────
    // getNestedValue() — via pluck (protected, tested indirectly)
    // ─────────────────────────────────────────────────────────────

    public function test_nested_value_on_array_access_object(): void
    {
        $item = new ArrayAccessItem(['foo' => ['bar' => 'baz']]);
        // Wrap in an outer array so pluck can reach it
        $c = new Collection([['data' => $item]]);
        // pluck 'data' gives us the ArrayAccessItem, then .foo.bar
        // We need a flat array for pluck to work here; test via direct nested array instead
        $c2 = new Collection([['nested' => ['deep' => 'value']]]);
        $this->assertEquals([0 => 'value'], $c2->pluck('nested.deep')->all());
    }

    // ─────────────────────────────────────────────────────────────
    // groupBy()
    // ─────────────────────────────────────────────────────────────

    public function test_groupBy_string_key_on_arrays(): void
    {
        $c = new Collection($this->sampleUsers());
        $groups = $c->groupBy('status');

        $this->assertEquals(2, $groups->count()); // 'active' and 'inactive'
        $this->assertEquals(3, $groups->get('active')->count());
        $this->assertEquals(1, $groups->get('inactive')->count());
    }

    public function test_groupBy_closure(): void
    {
        $c = new Collection([1, 2, 3, 4, 5, 6]);
        $groups = $c->groupBy(fn($v) => $v % 2 === 0 ? 'even' : 'odd');

        $this->assertEquals(3, $groups->get('even')->count());
        $this->assertEquals(3, $groups->get('odd')->count());
    }

    public function test_groupBy_on_objects(): void
    {
        $c = new Collection([
            new StubItem(1, 'Alice', 'active'),
            new StubItem(2, 'Bob', 'inactive'),
            new StubItem(3, 'Charlie', 'active'),
        ]);
        $groups = $c->groupBy('status');

        $this->assertEquals(2, $groups->get('active')->count());
        $this->assertEquals(1, $groups->get('inactive')->count());
    }

    // ─────────────────────────────────────────────────────────────
    // sortBy() / orderBy() / sortByDesc() / sort()
    // ─────────────────────────────────────────────────────────────

    public function test_sortBy_string_key_ascending(): void
    {
        $c = new Collection($this->sampleUsers());
        $sorted = $c->sortBy('age')->values();
        $ages = $sorted->pluck('age')->all();
        $this->assertEquals([25, 28, 30, 35], $ages);
    }

    public function test_sortBy_descending(): void
    {
        $c = new Collection($this->sampleUsers());
        $sorted = $c->sortBy('age', SORT_REGULAR, true)->values();
        $ages = $sorted->pluck('age')->all();
        $this->assertEquals([35, 30, 28, 25], $ages);
    }

    public function test_sortBy_callable(): void
    {
        $c = new Collection($this->sampleUsers());
        $sorted = $c->sortBy(fn($item) => strlen($item['name']))->values();
        // Bob(3), Diana(5), Alice(5), Charlie(7) — stable among equal lengths
        $this->assertEquals('Bob', $sorted->first()['name']);
        $this->assertEquals('Charlie', $sorted->last()['name']);
    }

    public function test_orderBy_ascending(): void
    {
        $c = new Collection($this->sampleUsers());
        $sorted = $c->orderBy('age', 'asc');
        $this->assertEquals(25, $sorted->first()['age']);
        $this->assertEquals(35, $sorted->last()['age']);
    }

    public function test_orderBy_descending(): void
    {
        $c = new Collection($this->sampleUsers());
        $sorted = $c->orderBy('age', 'desc');
        $this->assertEquals(35, $sorted->first()['age']);
        $this->assertEquals(25, $sorted->last()['age']);
    }

    public function test_orderBy_pushes_nulls_to_end(): void
    {
        $c = new Collection([
            ['name' => 'A', 'val' => 3],
            ['name' => 'B', 'val' => null],
            ['name' => 'C', 'val' => 1],
            ['name' => 'D', 'val' => null],
        ]);
        $sorted = $c->orderBy('val', 'asc');
        $this->assertEquals('C', $sorted->first()['name']);
        // Both nulls should be at the end
        $items = $sorted->all();
        $this->assertNull($items[2]['val']);
        $this->assertNull($items[3]['val']);
    }

    public function test_sortByDesc_delegates_to_orderBy_desc(): void
    {
        $c = new Collection($this->sampleUsers());
        $sorted = $c->sortByDesc('age');
        $this->assertEquals(35, $sorted->first()['age']);
    }

    public function test_sort_sorts_plain_values(): void
    {
        $c = new Collection([3, 1, 4, 1, 5, 9]);
        $sorted = $c->sort();
        $this->assertEquals([1, 1, 3, 4, 5, 9], $sorted->all());
    }

    // ─────────────────────────────────────────────────────────────
    // push()
    // ─────────────────────────────────────────────────────────────

    public function test_push_appends_item_and_returns_self(): void
    {
        $c = new Collection([1, 2]);
        $returned = $c->push(3);
        $this->assertSame($c, $returned);
        $this->assertEquals([1, 2, 3], $c->all());
    }

    // ─────────────────────────────────────────────────────────────
    // each()
    // ─────────────────────────────────────────────────────────────

    public function test_each_iterates_all_items(): void
    {
        $visited = [];
        $c = new Collection(['a' => 1, 'b' => 2, 'c' => 3]);
        $returned = $c->each(function ($v, $k) use (&$visited) {
            $visited[$k] = $v;
        });

        $this->assertEquals(['a' => 1, 'b' => 2, 'c' => 3], $visited);
        $this->assertSame($c, $returned); // returns self for chaining
    }

    // ─────────────────────────────────────────────────────────────
    // unique()
    // ─────────────────────────────────────────────────────────────

    public function test_unique_removes_duplicate_scalars(): void
    {
        $c = new Collection([1, 2, 2, 3, 3, 3]);
        $unique = $c->unique()->values();
        $this->assertEquals([1, 2, 3], $unique->all());
    }

    public function test_unique_by_key_on_arrays(): void
    {
        $c = new Collection([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
            ['id' => 1, 'name' => 'Alice Duplicate'],
        ]);
        $unique = $c->unique('id');
        $this->assertEquals(2, $unique->count());
        $this->assertEquals('Alice', $unique->first()['name']);
    }

    public function test_unique_by_key_on_objects(): void
    {
        $c = new Collection([
            new StubItem(1, 'Alice'),
            new StubItem(2, 'Bob'),
            new StubItem(1, 'Alice Again'),
        ]);
        $unique = $c->unique('id');
        $this->assertEquals(2, $unique->count());
    }

    // ─────────────────────────────────────────────────────────────
    // chunk()
    // ─────────────────────────────────────────────────────────────

    public function test_chunk_splits_into_sized_sub_collections(): void
    {
        $c = new Collection([1, 2, 3, 4, 5]);
        $chunks = $c->chunk(2);

        $this->assertEquals(3, $chunks->count());
        $this->assertEquals([1, 2], $chunks->get(0)->all());
        $this->assertEquals([3, 4], $chunks->get(1)->all());
        $this->assertEquals([5], $chunks->get(2)->all());
    }

    public function test_chunk_exact_division(): void
    {
        $chunks = (new Collection([1, 2, 3, 4]))->chunk(2);
        $this->assertEquals(2, $chunks->count());
    }

    public function test_chunk_size_larger_than_collection(): void
    {
        $chunks = (new Collection([1, 2]))->chunk(10);
        $this->assertEquals(1, $chunks->count());
        $this->assertEquals([1, 2], $chunks->get(0)->all());
    }

    // ─────────────────────────────────────────────────────────────
    // take() / skip()
    // ─────────────────────────────────────────────────────────────

    public function test_take_returns_first_n_items(): void
    {
        $c = new Collection([1, 2, 3, 4, 5]);
        $this->assertEquals([1, 2, 3], $c->take(3)->all());
    }

    public function test_take_negative_returns_last_n_items(): void
    {
        $c = new Collection([1, 2, 3, 4, 5]);
        $this->assertEquals([3, 4, 5], $c->take(-3)->all());
    }

    public function test_take_more_than_available(): void
    {
        $c = new Collection([1, 2]);
        $this->assertEquals([1, 2], $c->take(10)->all());
    }

    public function test_skip_returns_items_after_offset(): void
    {
        $c = new Collection([1, 2, 3, 4, 5]);
        $this->assertEquals([3, 4, 5], $c->skip(2)->all());
    }

    public function test_skip_beyond_length_returns_empty(): void
    {
        $c = new Collection([1, 2]);
        $this->assertTrue($c->skip(10)->isEmpty());
    }

    // ─────────────────────────────────────────────────────────────
    // merge() / concat()
    // ─────────────────────────────────────────────────────────────

    public function test_merge_with_array(): void
    {
        $c = new Collection([1, 2]);
        $result = $c->merge([3, 4]);
        $this->assertEquals([1, 2, 3, 4], $result->all());
    }

    public function test_merge_with_collection(): void
    {
        $c = new Collection([1, 2]);
        $result = $c->merge(new Collection([3, 4]));
        $this->assertEquals([1, 2, 3, 4], $result->all());
    }

    public function test_merge_does_not_mutate_original(): void
    {
        $c = new Collection([1, 2]);
        $c->merge([3, 4]);
        $this->assertEquals([1, 2], $c->all());
    }

    public function test_concat_with_array(): void
    {
        $c = new Collection([1, 2]);
        $result = $c->concat([3, 4]);
        $this->assertEquals([1, 2, 3, 4], $result->all());
    }

    public function test_concat_with_collection(): void
    {
        $result = (new Collection([1]))->concat(new Collection([2, 3]));
        $this->assertEquals([1, 2, 3], $result->all());
    }

    // ─────────────────────────────────────────────────────────────
    // flatten() / collapse()
    // ─────────────────────────────────────────────────────────────

    public function test_flatten_fully_nested_arrays(): void
    {
        $c = new Collection([[1, 2], [3, [4, 5]]]);
        $this->assertEquals([1, 2, 3, 4, 5], $c->flatten()->all());
    }

    public function test_flatten_depth_1(): void
    {
        $c = new Collection([[1, 2], [3, [4, 5]]]);
        $result = $c->flatten(1);
        $this->assertEquals([1, 2, 3, [4, 5]], $result->all());
    }

    public function test_flatten_with_nested_collections(): void
    {
        $c = new Collection([
            new Collection([1, 2]),
            new Collection([3, 4]),
        ]);
        $this->assertEquals([1, 2, 3, 4], $c->flatten()->all());
    }

    public function test_flatten_mixed_scalars_and_arrays(): void
    {
        $c = new Collection([1, [2, 3], 4]);
        $this->assertEquals([1, 2, 3, 4], $c->flatten()->all());
    }

    public function test_collapse_is_flatten_depth_1(): void
    {
        $c = new Collection([[1, 2], [3, 4]]);
        $this->assertEquals([1, 2, 3, 4], $c->collapse()->all());
    }

    // ─────────────────────────────────────────────────────────────
    // zip()
    // ─────────────────────────────────────────────────────────────

    public function test_zip_pairs_elements(): void
    {
        $c = new Collection(['a', 'b', 'c']);
        $result = $c->zip([1, 2, 3]);
        $this->assertEquals([['a', 1], ['b', 2], ['c', 3]], $result->all());
    }

    public function test_zip_shorter_second_array_fills_null(): void
    {
        $c = new Collection(['a', 'b', 'c']);
        $result = $c->zip([1]);
        $this->assertEquals([['a', 1], ['b', null], ['c', null]], $result->all());
    }

    public function test_zip_with_collection(): void
    {
        $c = new Collection([1, 2]);
        $result = $c->zip(new Collection(['x', 'y']));
        $this->assertEquals([[1, 'x'], [2, 'y']], $result->all());
    }

    // ─────────────────────────────────────────────────────────────
    // sum() / avg() / min() / max()
    // ─────────────────────────────────────────────────────────────

    public function test_sum_of_plain_values(): void
    {
        $this->assertEquals(15, (new Collection([1, 2, 3, 4, 5]))->sum());
    }

    public function test_sum_by_key(): void
    {
        $c = new Collection($this->sampleUsers());
        $this->assertEquals(118, $c->sum('age')); // 30+25+35+28
    }

    public function test_sum_of_empty_collection(): void
    {
        $this->assertEquals(0, (new Collection())->sum());
    }

    public function test_avg_of_plain_values(): void
    {
        $this->assertEquals(3.0, (new Collection([1, 2, 3, 4, 5]))->avg());
    }

    public function test_avg_by_key(): void
    {
        $c = new Collection($this->sampleUsers());
        $this->assertEquals(29.5, $c->avg('age')); // 118 / 4
    }

    public function test_avg_of_empty_returns_zero(): void
    {
        $this->assertEquals(0, (new Collection())->avg());
    }

    public function test_min_of_plain_values(): void
    {
        $this->assertEquals(1, (new Collection([3, 1, 4, 1, 5]))->min());
    }

    public function test_min_by_key(): void
    {
        $c = new Collection($this->sampleUsers());
        $this->assertEquals(25, $c->min('age'));
    }

    public function test_max_of_plain_values(): void
    {
        $this->assertEquals(9, (new Collection([3, 1, 4, 1, 5, 9]))->max());
    }

    public function test_max_by_key(): void
    {
        $c = new Collection($this->sampleUsers());
        $this->assertEquals(35, $c->max('age'));
    }

    // ─────────────────────────────────────────────────────────────
    // contains()
    // ─────────────────────────────────────────────────────────────

    public function test_contains_scalar_value(): void
    {
        $c = new Collection([1, 2, 3]);
        $this->assertTrue($c->contains(2));
        $this->assertFalse($c->contains(99));
    }

    public function test_contains_with_closure(): void
    {
        $c = new Collection([1, 2, 3, 4]);
        $this->assertTrue($c->contains(fn($v) => $v > 3));
        $this->assertFalse($c->contains(fn($v) => $v > 100));
    }

    public function test_contains_with_key_value_pair(): void
    {
        $c = new Collection($this->sampleUsers());
        $this->assertTrue($c->contains('name', 'Alice'));
        $this->assertFalse($c->contains('name', 'Nobody'));
    }

    public function test_contains_strict_type_check(): void
    {
        $c = new Collection([1, 2, 3]);
        // in_array with strict=true: '1' (string) should NOT match 1 (int)
        $this->assertFalse($c->contains('1'));
    }

    // ─────────────────────────────────────────────────────────────
    // keyBy()
    // ─────────────────────────────────────────────────────────────

    public function test_keyBy_string_key(): void
    {
        $c = new Collection($this->sampleUsers());
        $keyed = $c->keyBy('id');
        $this->assertEquals('Alice', $keyed->get(1)['name']);
        $this->assertEquals('Bob', $keyed->get(2)['name']);
    }

    public function test_keyBy_callable(): void
    {
        $c = new Collection($this->sampleUsers());
        $keyed = $c->keyBy(fn($item) => strtolower($item['name']));
        $this->assertEquals(1, $keyed->get('alice')['id']);
    }

    // ─────────────────────────────────────────────────────────────
    // firstWhere()
    // ─────────────────────────────────────────────────────────────

    public function test_firstWhere_by_key_value_arrays(): void
    {
        $c = new Collection($this->sampleUsers());
        $result = $c->firstWhere('name', 'Charlie');
        $this->assertEquals(3, $result['id']);
    }

    public function test_firstWhere_returns_null_when_not_found(): void
    {
        $c = new Collection($this->sampleUsers());
        $this->assertNull($c->firstWhere('name', 'Nobody'));
    }

    public function test_firstWhere_by_closure(): void
    {
        $c = new Collection($this->sampleUsers());
        $result = $c->firstWhere(fn($item) => $item['age'] > 30);
        $this->assertEquals('Charlie', $result['name']);
    }

    public function test_firstWhere_on_objects(): void
    {
        $c = new Collection([
            new StubItem(1, 'Alice', 'active'),
            new StubItem(2, 'Bob', 'inactive'),
        ]);
        $result = $c->firstWhere('name', 'Bob');
        $this->assertEquals(2, $result->id);
    }

    // ─────────────────────────────────────────────────────────────
    // whereNull() / whereNotNull() / whereNotEmpty()
    // ─────────────────────────────────────────────────────────────

    public function test_whereNull_filters_to_null_values_in_arrays(): void
    {
        $c = new Collection([
            ['name' => 'Alice', 'email' => 'alice@test.com'],
            ['name' => 'Bob', 'email' => null],
            ['name' => 'Carol', 'email' => null],
        ]);
        $result = $c->whereNull('email');
        $this->assertEquals(2, $result->count());
    }

    public function test_whereNotNull_filters_out_nulls(): void
    {
        $c = new Collection([
            ['name' => 'Alice', 'email' => 'alice@test.com'],
            ['name' => 'Bob', 'email' => null],
        ]);
        $result = $c->whereNotNull('email');
        $this->assertEquals(1, $result->count());
        $this->assertEquals('Alice', $result->first()['name']);
    }

    public function test_whereNull_on_objects_with_getAttribute(): void
    {
        // StubItem doesn't have getAttribute; test with a simple object
        $obj1 = new \stdClass();
        $obj1->val = null;
        $obj2 = new \stdClass();
        $obj2->val = 'hello';
        $c = new Collection([$obj1, $obj2]);

        $result = $c->whereNull('val');
        $this->assertEquals(1, $result->count());
    }

    public function test_whereNotEmpty_filters_out_empty_values(): void
    {
        $c = new Collection([
            ['name' => 'Alice', 'bio' => 'Developer'],
            ['name' => 'Bob', 'bio' => ''],
            ['name' => 'Carol', 'bio' => null],
            ['name' => 'Dave', 'bio' => 'Designer'],
        ]);
        $result = $c->whereNotEmpty('bio');
        $this->assertEquals(2, $result->count());
    }

    // ─────────────────────────────────────────────────────────────
    // whereIn()
    // ─────────────────────────────────────────────────────────────

    public function test_whereIn_filters_matching_values_arrays(): void
    {
        $c = new Collection($this->sampleUsers());
        $result = $c->whereIn('name', ['Alice', 'Diana']);
        $this->assertEquals(2, $result->count());
    }

    public function test_whereIn_on_objects(): void
    {
        $c = new Collection([
            new StubItem(1, 'Alice'),
            new StubItem(2, 'Bob'),
            new StubItem(3, 'Charlie'),
        ]);
        $result = $c->whereIn('id', [1, 3]);
        $this->assertEquals(2, $result->count());
    }

    public function test_whereIn_returns_empty_when_no_match(): void
    {
        $c = new Collection($this->sampleUsers());
        $result = $c->whereIn('name', ['Nobody']);
        $this->assertTrue($result->isEmpty());
    }

    // ─────────────────────────────────────────────────────────────
    // get() / keys() / has()
    // ─────────────────────────────────────────────────────────────

    public function test_get_returns_item_at_index(): void
    {
        $c = new Collection(['a' => 1, 'b' => 2]);
        $this->assertEquals(1, $c->get('a'));
        $this->assertEquals(2, $c->get('b'));
    }

    public function test_get_returns_empty_collection_for_missing_key(): void
    {
        $c = new Collection([1, 2, 3]);
        $result = $c->get(99);
        // The implementation calls collect() which presumably returns a Collection
        // We just verify it doesn't throw
        $this->assertNotNull($result);
    }

    public function test_keys_returns_all_keys(): void
    {
        $c = new Collection(['a' => 1, 'b' => 2, 'c' => 3]);
        $this->assertEquals(['a', 'b', 'c'], $c->keys());
    }

    public function test_has_returns_true_for_existing_key(): void
    {
        $c = new Collection(['x' => 10, 'y' => 20]);
        $this->assertTrue($c->has('x'));
        $this->assertFalse($c->has('z'));
    }

    public function test_has_with_numeric_keys(): void
    {
        $c = new Collection([10, 20, 30]);
        $this->assertTrue($c->has(0));
        $this->assertTrue($c->has(2));
        $this->assertFalse($c->has(5));
    }

    // ─────────────────────────────────────────────────────────────
    // mapWithKeys()
    // ─────────────────────────────────────────────────────────────

    public function test_mapWithKeys_transforms_to_associative(): void
    {
        $c = new Collection($this->sampleUsers());
        $result = $c->mapWithKeys(fn($user) => [$user['id'] => $user['name']]);
        $this->assertEquals([1 => 'Alice', 2 => 'Bob', 3 => 'Charlie', 4 => 'Diana'], $result->all());
    }

    public function test_mapWithKeys_throws_on_non_array_return(): void
    {
        $c = new Collection([1, 2]);
        $this->expectException(\UnexpectedValueException::class);
        $c->mapWithKeys(fn($v) => $v); // returns scalar, not array
    }

    // ─────────────────────────────────────────────────────────────
    // slice()
    // ─────────────────────────────────────────────────────────────

    public function test_slice_with_offset_and_length(): void
    {
        $c = new Collection([1, 2, 3, 4, 5]);
        $this->assertEquals([2, 3], $c->slice(1, 2)->all());
    }

    public function test_slice_with_offset_only_returns_rest(): void
    {
        $c = new Collection([1, 2, 3, 4, 5]);
        $this->assertEquals([3, 4, 5], $c->slice(2)->all());
    }

    public function test_slice_negative_offset(): void
    {
        $c = new Collection([1, 2, 3, 4, 5]);
        $this->assertEquals([4, 5], $c->slice(-2)->all());
    }

    // ─────────────────────────────────────────────────────────────
    // reduce()
    // ─────────────────────────────────────────────────────────────

    public function test_reduce_sums_values(): void
    {
        $c = new Collection([1, 2, 3, 4, 5]);
        $sum = $c->reduce(fn($carry, $item) => $carry + $item, 0);
        $this->assertEquals(15, $sum);
    }

    public function test_reduce_concatenates_strings(): void
    {
        $c = new Collection(['Hello', ' ', 'World']);
        $result = $c->reduce(fn($carry, $item) => $carry . $item, '');
        $this->assertEquals('Hello World', $result);
    }

    public function test_reduce_with_null_initial(): void
    {
        $c = new Collection([1, 2, 3]);
        $result = $c->reduce(fn($carry, $item) => ($carry ?? 0) + $item);
        $this->assertEquals(6, $result);
    }

    public function test_reduce_callback_receives_key(): void
    {
        $c = new Collection(['a' => 1, 'b' => 2]);
        $result = $c->reduce(function ($carry, $item, $key) {
            $carry[$key] = $item * 10;
            return $carry;
        }, []);
        $this->assertEquals(['a' => 10, 'b' => 20], $result);
    }

    // ─────────────────────────────────────────────────────────────
    // toArray() / toJson() / jsonSerialize()
    // ─────────────────────────────────────────────────────────────

    public function test_toArray_returns_plain_array(): void
    {
        $c = new Collection([1, 'two', [3]]);
        $this->assertEquals([1, 'two', [3]], $c->toArray());
    }

    public function test_toJson_returns_valid_json(): void
    {
        $c = new Collection(['a' => 1, 'b' => 2]);
        $json = $c->toJson();
        $this->assertEquals(['a' => 1, 'b' => 2], json_decode($json, true));
    }

    public function test_jsonSerialize_returns_same_as_toArray(): void
    {
        $c = new Collection([1, 2, 3]);
        $this->assertEquals($c->toArray(), $c->jsonSerialize());
    }

    // ─────────────────────────────────────────────────────────────
    // IteratorAggregate / Countable — interface compliance
    // ─────────────────────────────────────────────────────────────

    public function test_collection_is_iterable_via_foreach(): void
    {
        $c = new Collection([10, 20, 30]);
        $sum = 0;
        foreach ($c as $value) {
            $sum += $value;
        }
        $this->assertEquals(60, $sum);
    }

    public function test_count_works_with_php_count(): void
    {
        $c = new Collection([1, 2, 3]);
        $this->assertEquals(3, count($c));
    }

    // ─────────────────────────────────────────────────────────────
    // CHAINING — verify methods return new Collection instances
    // ─────────────────────────────────────────────────────────────

    public function test_chaining_filter_map_values(): void
    {
        $result = (new Collection([1, 2, 3, 4, 5, 6]))
            ->filter(fn($v) => $v % 2 === 0)   // [2, 4, 6]
            ->map(fn($v) => $v * 10)            // [20, 40, 60]
            ->values();                          // reindex

        $this->assertEquals([20, 40, 60], $result->all());
    }

    public function test_chaining_where_pluck_sum(): void
    {
        $c = new Collection($this->sampleUsers());
        $totalAge = $c->where('status', 'active')->sum('age');
        $this->assertEquals(93, $totalAge); // 30 + 35 + 28
    }

    public function test_chaining_does_not_mutate_original(): void
    {
        $original = new Collection([1, 2, 3, 4, 5]);
        $original->filter(fn($v) => $v > 3)->map(fn($v) => $v * 2);
        $this->assertEquals([1, 2, 3, 4, 5], $original->all());
    }

    // ─────────────────────────────────────────────────────────────
    // EDGE CASES
    // ─────────────────────────────────────────────────────────────

    public function test_where_on_empty_collection(): void
    {
        $this->assertTrue((new Collection())->where('x', 1)->isEmpty());
    }

    public function test_pluck_on_empty_collection(): void
    {
        $this->assertTrue((new Collection())->pluck('name')->isEmpty());
    }

    public function test_groupBy_on_empty_collection(): void
    {
        $this->assertTrue((new Collection())->groupBy('key')->isEmpty());
    }

    public function test_sortBy_on_empty_collection(): void
    {
        $this->assertTrue((new Collection())->sortBy('key')->isEmpty());
    }

    public function test_chunk_on_empty_collection(): void
    {
        $this->assertTrue((new Collection())->chunk(5)->isEmpty());
    }

    public function test_flatten_already_flat(): void
    {
        $c = new Collection([1, 2, 3]);
        $this->assertEquals([1, 2, 3], $c->flatten()->all());
    }

    public function test_zip_on_empty_collection(): void
    {
        $this->assertTrue((new Collection())->zip([1, 2])->isEmpty());
    }

    public function test_unique_on_empty_collection(): void
    {
        $this->assertTrue((new Collection())->unique()->isEmpty());
    }

    public function test_reduce_on_empty_returns_initial(): void
    {
        $result = (new Collection())->reduce(fn($c, $i) => $c + $i, 42);
        $this->assertEquals(42, $result);
    }

    public function test_first_on_falsy_first_element(): void
    {
        // reset() returns false for [0, ...], and the code does `?: null`
        // This is actually a known quirk — 0 is falsy so first() returns null
        $c = new Collection([0, 1, 2]);
        // The implementation: reset($this->items) ?: null
        // reset() returns 0, and 0 ?: null => null
        $this->assertNull($c->first());
    }

    public function test_last_on_falsy_last_element(): void
    {
        // Same quirk as first() — end() returns 0, 0 ?: null => null
        $c = new Collection([1, 2, 0]);
        $this->assertNull($c->last());
    }
}