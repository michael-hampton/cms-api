<?php

namespace App\Tests\Unit\Framework\Support\Config;

use App\Framework\Support\Config\ConfigEntry;
use App\Framework\Support\Config\ConfigModel;
use InvalidArgumentException;
use OutOfRangeException;
use PHPUnit\Framework\TestCase;

final class ConfigModelTest extends TestCase
{
    // -----------------------------------------------------------------
    // Construction
    // -----------------------------------------------------------------

    public function test_constructs_empty_model_by_default(): void
    {
        $model = new ConfigModel();

        $this->assertSame(0, $model->size());
        $this->assertSame([], $model->all());
    }

    public function test_rejects_non_config_entry_items(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // @phpstan-ignore-next-line intentionally wrong type for the test
        new ConfigModel(['not-an-entry']);
    }

    public function test_from_array_preserves_key_order(): void
    {
        $model = ConfigModel::fromArray([
            'b' => 2,
            'a' => 1,
            'c' => 3,
        ]);

        $this->assertSame(['b', 'a', 'c'], array_map(fn (ConfigEntry $e) => $e->key, $model->all()));
    }

    public function test_from_array_never_produces_duplicates(): void
    {
        // PHP arrays can't have duplicate keys, so this is really just
        // confirming the round trip is duplicate-free by construction.
        $model = ConfigModel::fromArray(['a' => 1, 'b' => 2]);

        $this->assertFalse($model->hasDuplicateKeys());
    }

    public function test_from_pairs_preserves_order_and_duplicates(): void
    {
        $model = ConfigModel::fromPairs([
            ['a', 1],
            ['a', 2],
            ['b', 3],
        ]);

        $this->assertSame(3, $model->size());
        $this->assertSame(['a', 'a', 'b'], array_map(fn (ConfigEntry $e) => $e->key, $model->all()));
        $this->assertTrue($model->hasDuplicateKeys());
    }

    public function test_from_pairs_rejects_malformed_pairs(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ConfigModel::fromPairs([['only-a-key']]);
    }

    // -----------------------------------------------------------------
    // Conversion back out
    // -----------------------------------------------------------------

    public function test_to_array_round_trips_simple_config(): void
    {
        $data = ['site.name' => 'Acme', 'site.debug' => false];
        $model = ConfigModel::fromArray($data);

        $this->assertSame($data, $model->toArray());
    }

    public function test_to_array_last_write_wins_on_duplicates(): void
    {
        $model = ConfigModel::fromPairs([
            ['a', 'first'],
            ['a', 'second'],
        ]);

        $this->assertSame(['a' => 'second'], $model->toArray());
    }

    public function test_to_pairs_preserves_order_and_duplicates(): void
    {
        $pairs = [['a', 1], ['b', 2], ['a', 3]];
        $model = ConfigModel::fromPairs($pairs);

        $this->assertSame($pairs, $model->toPairs());
    }

    // -----------------------------------------------------------------
    // Lookup
    // -----------------------------------------------------------------

    public function test_get_by_id_and_get_by_key(): void
    {
        $model = ConfigModel::fromArray(['a' => 1, 'b' => 2]);
        $entryA = $model->getByKey('a');

        $this->assertNotNull($entryA);
        $this->assertSame($entryA, $model->getById($entryA->id));
        $this->assertNull($model->getById('missing-id'));
        $this->assertNull($model->getByKey('missing-key'));
    }

    public function test_get_all_by_key_returns_every_matching_entry_in_order(): void
    {
        $model = ConfigModel::fromPairs([['a', 1], ['b', 2], ['a', 3]]);
        $matches = $model->getAllByKey('a');

        $this->assertCount(2, $matches);
        $this->assertSame([1, 3], array_map(fn (ConfigEntry $e) => $e->value, $matches));
    }

    // -----------------------------------------------------------------
    // Mutation: add / remove
    // -----------------------------------------------------------------

    public function test_add_appends_a_new_entry_with_a_fresh_id(): void
    {
        $model = ConfigModel::fromArray(['a' => 1]);
        $updated = $model->add('b', 2);

        $this->assertSame(1, $model->size(), 'original model must be unchanged (immutable)');
        $this->assertSame(2, $updated->size());
        $this->assertSame(2, $updated->getByKey('b')?->value);
    }

    public function test_remove_by_id(): void
    {
        $model = ConfigModel::fromArray(['a' => 1, 'b' => 2]);
        $idToRemove = $model->getByKey('a')->id;

        $updated = $model->removeById($idToRemove);

        $this->assertSame(2, $model->size(), 'original model must be unchanged');
        $this->assertSame(1, $updated->size());
        $this->assertNull($updated->getByKey('a'));
    }

    public function test_remove_by_id_is_a_no_op_when_id_not_found(): void
    {
        $model = ConfigModel::fromArray(['a' => 1]);
        $updated = $model->removeById('does-not-exist');

        $this->assertSame(1, $updated->size());
    }

    public function test_remove_by_key_removes_every_matching_entry(): void
    {
        $model = ConfigModel::fromPairs([['a', 1], ['b', 2], ['a', 3]]);
        $updated = $model->removeByKey('a');

        $this->assertSame(3, $model->size(), 'original model must be unchanged');
        $this->assertSame(1, $updated->size());
        $this->assertSame('b', $updated->all()[0]->key);
    }

    // -----------------------------------------------------------------
    // Rename vs delete-and-re-add (the core identity guarantee)
    // -----------------------------------------------------------------

    public function test_rename_preserves_identity(): void
    {
        $model = ConfigModel::fromArray(['old.key' => 'value']);
        $originalEntry = $model->getByKey('old.key');

        $renamedModel = $model->rename($originalEntry->id, 'new.key');
        $renamedEntry = $renamedModel->getByKey('new.key');

        $this->assertSame($originalEntry->id, $renamedEntry->id);
        $this->assertSame('value', $renamedEntry->value);
        $this->assertNull($renamedModel->getByKey('old.key'));
        $this->assertNotNull($model->getByKey('old.key'), 'original model must be unchanged');
    }

    public function test_rename_throws_for_unknown_id(): void
    {
        $model = ConfigModel::fromArray(['a' => 1]);

        $this->expectException(OutOfRangeException::class);
        $model->rename('missing-id', 'b');
    }

    public function test_delete_and_readd_produces_a_different_identity_than_rename(): void
    {
        $model = ConfigModel::fromArray(['old.key' => 'value']);
        $originalEntry = $model->getByKey('old.key');

        $deletedAndReadded = $model
            ->removeById($originalEntry->id)
            ->add('old.key', 'value');

        $newEntry = $deletedAndReadded->getByKey('old.key');

        $this->assertNotSame(
            $originalEntry->id,
            $newEntry->id,
            'delete+re-add must NOT preserve identity, unlike rename()',
        );
    }

    public function test_set_value_preserves_identity(): void
    {
        $model = ConfigModel::fromArray(['a' => 1]);
        $entry = $model->getByKey('a');

        $updated = $model->setValue($entry->id, 99);

        $this->assertSame(99, $updated->getByKey('a')->value);
        $this->assertSame($entry->id, $updated->getByKey('a')->id);
        $this->assertSame(1, $model->getByKey('a')->value, 'original model must be unchanged');
    }

    public function test_set_value_throws_for_unknown_id(): void
    {
        $model = ConfigModel::fromArray(['a' => 1]);

        $this->expectException(OutOfRangeException::class);
        $model->setValue('missing-id', 99);
    }

    // -----------------------------------------------------------------
    // Duplicate detection
    // -----------------------------------------------------------------

    public function test_find_duplicate_keys_is_empty_for_unique_keys(): void
    {
        $model = ConfigModel::fromArray(['a' => 1, 'b' => 2]);

        $this->assertSame([], $model->findDuplicateKeys());
        $this->assertFalse($model->hasDuplicateKeys());
    }

    public function test_find_duplicate_keys_reports_deterministic_order(): void
    {
        $model = ConfigModel::fromPairs([
            ['a', 1],
            ['b', 2],
            ['a', 3],
            ['c', 4],
            ['b', 5],
        ]);

        $duplicates = $model->findDuplicateKeys();

        $this->assertSame(['a', 'b'], array_column($duplicates, 'key'));

        $aIds = $model->getAllByKey('a');
        $bIds = $model->getAllByKey('b');

        $this->assertSame(array_map(fn (ConfigEntry $e) => $e->id, $aIds), $duplicates[0]['entryIds']);
        $this->assertSame(array_map(fn (ConfigEntry $e) => $e->id, $bIds), $duplicates[1]['entryIds']);
    }

    public function test_find_duplicate_keys_is_stable_across_repeated_calls(): void
    {
        $model = ConfigModel::fromPairs([['a', 1], ['a', 2]]);

        $this->assertSame($model->findDuplicateKeys(), $model->findDuplicateKeys());
    }

    public function test_remove_duplicates_keep_first(): void
    {
        $model = ConfigModel::fromPairs([
            ['a', 'first'],
            ['b', 'only'],
            ['a', 'second'],
        ]);

        $deduped = $model->removeDuplicates('keep-first');

        $this->assertSame(2, $deduped->size());
        $this->assertSame('first', $deduped->getByKey('a')->value);
        $this->assertSame(['a', 'b'], array_map(fn (ConfigEntry $e) => $e->key, $deduped->all()));
        $this->assertFalse($deduped->hasDuplicateKeys());
    }

    public function test_remove_duplicates_keep_last(): void
    {
        $model = ConfigModel::fromPairs([
            ['a', 'first'],
            ['b', 'only'],
            ['a', 'second'],
        ]);

        $deduped = $model->removeDuplicates('keep-last');

        $this->assertSame(2, $deduped->size());
        $this->assertSame('second', $deduped->getByKey('a')->value);
        // Original relative order of surviving entries is preserved:
        // 'a' (the surviving occurrence) still comes before 'b' only if
        // its surviving index precedes b's index; here b sits between
        // the two 'a' occurrences, so after removing the first 'a' the
        // order becomes b, a.
        $this->assertSame(['b', 'a'], array_map(fn (ConfigEntry $e) => $e->key, $deduped->all()));
    }

    public function test_remove_duplicates_rejects_unknown_strategy(): void
    {
        $model = ConfigModel::fromArray(['a' => 1]);

        $this->expectException(InvalidArgumentException::class);
        $model->removeDuplicates('bogus');
    }

    public function test_remove_duplicates_does_not_mutate_original(): void
    {
        $model = ConfigModel::fromPairs([['a', 1], ['a', 2]]);
        $model->removeDuplicates('keep-first');

        $this->assertSame(2, $model->size(), 'original model must be unchanged');
    }

    // -----------------------------------------------------------------
    // Filtering (read-only)
    // -----------------------------------------------------------------

    public function test_filter_returns_matching_entries_without_mutating_model(): void
    {
        $model = ConfigModel::fromArray(['app.name' => 1, 'app.debug' => 2, 'db.host' => 3]);

        $matches = $model->filter(fn (ConfigEntry $e) => str_starts_with($e->key, 'app.'));

        $this->assertCount(2, $matches);
        $this->assertSame(3, $model->size(), 'filter must never change stored data');
        $this->assertSame(['app.name', 'app.debug', 'db.host'], array_map(fn (ConfigEntry $e) => $e->key, $model->all()));
    }

    public function test_filter_by_key_contains_is_case_insensitive(): void
    {
        $model = ConfigModel::fromArray(['App.Name' => 1, 'db.host' => 2]);

        $matches = $model->filterByKeyContains('app');

        $this->assertCount(1, $matches);
        $this->assertSame('App.Name', $matches[0]->key);
    }

    public function test_filter_by_key_contains_empty_term_returns_everything(): void
    {
        $model = ConfigModel::fromArray(['a' => 1, 'b' => 2]);

        $this->assertCount(2, $model->filterByKeyContains(''));
    }

    // -----------------------------------------------------------------
    // Serialization
    // -----------------------------------------------------------------

    public function test_to_serializable_array_shape(): void
    {
        $model = ConfigModel::fromArray(['a' => 1]);
        $entry = $model->getByKey('a');

        $this->assertSame([
            ['id' => $entry->id, 'key' => 'a', 'value' => 1],
        ], $model->toSerializableArray());
    }

    // -----------------------------------------------------------------
    // No storage/API side effects (sanity check for this ticket's scope)
    // -----------------------------------------------------------------

    public function test_model_is_pure_in_memory_with_no_side_effects(): void
    {
        // Building, mutating, and reading a model must never touch
        // globals, the filesystem, or anything outside the object graph.
        // This test is a smoke check: it simply exercises a full
        // lifecycle and asserts nothing external blew up, since a real
        // "no side effects" guarantee is really a code-review property,
        // not something a single assertion can prove.
        $model = ConfigModel::fromArray(['a' => 1, 'b' => 2]);
        $model = $model->add('c', 3);
        $model = $model->rename($model->getByKey('a')->id, 'a.renamed');
        $model = $model->removeByKey('b');

        $this->assertSame(['a.renamed' => 1, 'c' => 3], $model->toArray());
    }
}