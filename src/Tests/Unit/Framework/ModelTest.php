<?php

namespace App\Tests\Unit\Framework;

use App\Framework\Database\Database;
use App\Framework\Database\QueryBuilder;
use App\Framework\Support\Collection;
use App\Models\Model;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

// ─────────────────────────────────────────────────────────────────────
// CONCRETE MODEL STUBS — each isolates a specific behaviour
// ─────────────────────────────────────────────────────────────────────

/** Minimal model with nothing special */
class BasicModel extends Model
{
    protected $table = 'basics';
    protected $fillable = ['name', 'email', 'age'];
}

/** Model with $guarded instead of $fillable */
class GuardedModel extends Model
{
    protected $table = 'guarded_items';
    protected $guarded = ['secret'];
}

/** Model with type casts */
class CastModel extends Model
{
    protected $table = 'cast_items';
    protected $fillable = ['int_col', 'float_col', 'str_col', 'bool_col', 'json_col', 'date_col'];
    protected $casts = [
        'int_col' => 'integer',
        'float_col' => 'float',
        'str_col' => 'string',
        'bool_col' => 'boolean',
        'json_col' => 'json',
        'date_col' => 'datetime',
    ];
}

/** Model with get/set mutators */
class MutatorModel extends Model
{
    protected $table = 'mutators';
    protected $fillable = ['first_name', 'last_name', 'slug'];

    public function getFirstNameAttribute($value = null)
    {
        // Mutator uppercases the stored value
        $raw = $value ?? ($this->attributes['first_name'] ?? '');
        return strtoupper($raw);
    }

    public function setSlugAttribute($value)
    {
        $this->attributes['slug'] = strtolower(str_replace(' ', '-', $value));
    }
}

/** Model with appended computed attributes */
class AppendModel extends Model
{
    protected $table = 'appends';
    protected $fillable = ['first_name', 'last_name'];
    protected $appends = ['full_name'];

    public function getFullNameAttribute()
    {
        return ($this->attributes['first_name'] ?? '') . ' ' . ($this->attributes['last_name'] ?? '');
    }
}

/** Model with hidden / visible configuration */
class VisibilityModel extends Model
{
    protected $table = 'visibility';
    protected $fillable = ['name', 'email', 'password', 'token'];
    protected $hidden = ['password', 'token'];
}

/** Model that uses soft deletes */
class SoftDeleteModel extends Model
{
    protected $table = 'soft_items';
    protected $fillable = ['name', 'deleted_at'];
}

/** Model with a scope */
class ScopedModel extends Model
{
    protected $table = 'scoped_items';
    protected $fillable = ['name', 'status'];

    public function scopeActive(QueryBuilder $query): QueryBuilder
    {
        return $query->where('status', 'active');
    }

    public function scopeOlderThan(QueryBuilder $query, int $age): QueryBuilder
    {
        return $query->where('age', '>', $age);
    }
}

/** Model that registers an observer in boot() */
class ObservableModel extends Model
{
    protected $table = 'observables';
    protected $fillable = ['name', 'status'];
}

/** Simple observer class */
class TestObserver
{
    public array $log = [];

    public function creating(Model $model): void
    {
        $this->log[] = 'creating';
    }

    public function created(Model $model): void
    {
        $this->log[] = 'created';
    }

    public function updating(Model $model): void
    {
        $this->log[] = 'updating';
    }

    public function updated(Model $model): void
    {
        $this->log[] = 'updated';
    }

    public function saving(Model $model): void
    {
        $this->log[] = 'saving';
    }

    public function saved(Model $model): void
    {
        $this->log[] = 'saved';
    }

    public function deleting(Model $model): void
    {
        $this->log[] = 'deleting';
    }

    public function deleted(Model $model): void
    {
        $this->log[] = 'deleted';
    }
}

/** Observer that vetoes creation */
class VetoObserver
{
    public function creating(Model $model): bool
    {
        return false; // veto
    }
}


class ModelTest extends TestCase
{
    /** @var MockObject&Database */
    private MockObject $db;

    public function test_constructor_populates_fillable_attributes(): void
    {
        $model = $this->basicModel(['name' => 'Alice', 'email' => 'a@test.com']);
        $this->assertEquals('Alice', $model->getAttribute('name'));
        $this->assertEquals('a@test.com', $model->getAttribute('email'));
    }

    /**
     * Helper: create a BasicModel with the mock DB injected
     */
    private function basicModel(array $attrs = []): BasicModel
    {
        return new BasicModel($attrs, $this->db);
    }

    // ─────────────────────────────────────────────────────────────
    // CONSTRUCTION & fill()
    // ─────────────────────────────────────────────────────────────

    public function test_constructor_ignores_non_fillable_attributes(): void
    {
        // BasicModel fillable = ['name', 'email', 'age'] — 'secret' is NOT fillable
        $model = $this->basicModel(['name' => 'Alice', 'secret' => 'hidden']);
        $this->assertEquals('Alice', $model->getAttribute('name'));
        $this->assertNull($model->getAttribute('secret'));
    }

    public function test_fill_returns_self_for_chaining(): void
    {
        $model = $this->basicModel();
        $returned = $model->fill(['name' => 'Bob']);
        $this->assertSame($model, $returned);
    }

    public function test_primary_key_is_always_fillable(): void
    {
        $model = $this->basicModel(['id' => 42, 'name' => 'Test']);
        $this->assertEquals(42, $model->getAttribute('id'));
    }

    public function test_guarded_model_blocks_guarded_keys(): void
    {
        $model = new GuardedModel(['name' => 'Test', 'secret' => 'nope'], $this->db);
        $this->assertEquals('Test', $model->getAttribute('name'));
        $this->assertNull($model->getAttribute('secret'));
    }

    // ─────────────────────────────────────────────────────────────
    // isFillable() — guarded vs fillable, keyword bypass
    // ─────────────────────────────────────────────────────────────

    public function test_keyword_attributes_bypass_fillable_check(): void
    {
        // 'total' contains keyword 'total' — should be fillable even if not in $fillable
        $model = $this->basicModel(['total' => 100]);
        $this->assertEquals(100, $model->getAttribute('total'));
    }

    public function test_count_keyword_bypasses_fillable(): void
    {
        $model = $this->basicModel(['item_count' => 5]);
        $this->assertEquals(5, $model->getAttribute('item_count'));
    }

    public function test_avg_keyword_bypasses_fillable(): void
    {
        $model = $this->basicModel(['avg_rating' => 4.5]);
        $this->assertEquals(4.5, $model->getAttribute('avg_rating'));
    }

    public function test_cast_integer(): void
    {
        $model = new CastModel(['int_col' => '42'], $this->db);
        $this->assertEquals(42, $model->getAttribute('int_col'));
    }

    // ─────────────────────────────────────────────────────────────
    // castAttribute() — all cast types
    // ─────────────────────────────────────────────────────────────

    public function test_cast_float(): void
    {
        $model = new CastModel(['float_col' => '3.14'], $this->db);

        // Ensure it's a float
        $this->assertIsFloat($model->getAttribute('float_col'));

        // Ensure the value is approximately 3.14
        $this->assertEqualsWithDelta(3.14, $model->getAttribute('float_col'), 0.00001);
    }

    public function test_cast_string(): void
    {
        $model = new CastModel(['str_col' => 123], $this->db);
        $this->assertEquals('123', $model->getAttribute('str_col'));
    }

    public function test_cast_boolean_true(): void
    {
        $model = new CastModel(['bool_col' => 1], $this->db);
        $this->assertEquals(true, $model->getAttribute('bool_col'));
    }

    public function test_cast_boolean_false(): void
    {
        $model = new CastModel(['bool_col' => 0], $this->db);
        $this->assertEquals(false, $model->getAttribute('bool_col'));
    }

    public function test_cast_json_from_string(): void
    {
        $json = '{"key":"value"}';
        $model = new CastModel([], $this->db);
        // Bypass fillable by setting attribute directly for test isolation
        $model->setAttribute('json_col', $json);
        $result = $model->getAttribute('json_col');
        $this->assertEquals(['key' => 'value'], $result);
    }

    public function test_cast_json_from_array_stored_as_string(): void
    {
        $model = new CastModel([], $this->db);
        $model->setAttribute('json_col', ['a' => 1, 'b' => 2]);
        // setAttribute converts array → JSON string for storage
        // getAttribute then decodes it back
        $result = $model->getAttribute('json_col');
        $this->assertEquals(['a' => 1, 'b' => 2], $result);
    }

    public function test_cast_json_invalid_string_returns_empty_array(): void
    {
        $model = new CastModel([], $this->db);
        // Force a bad JSON string into attributes directly
        $model->setAttribute('json_col', 'not-valid-json');
        $result = $model->getAttribute('json_col');
        $this->assertEquals([], $result);
    }

    public function test_cast_datetime_from_string(): void
    {
        $model = new CastModel(['date_col' => '2024-06-15 10:30:00'], $this->db);
        $result = $model->getAttribute('date_col');
        $this->assertInstanceOf(\DateTime::class, $result);
        $this->assertEquals('2024', $result->format('Y'));
        $this->assertEquals('06', $result->format('m'));
        $this->assertEquals('15', $result->format('d'));
    }

    public function test_cast_datetime_from_datetime_object(): void
    {
        $dt = new \DateTime('2024-01-01 00:00:00');
        $model = new CastModel(['date_col' => $dt], $this->db);
        $result = $model->getAttribute('date_col');
        $this->assertInstanceOf(\DateTime::class, $result);
    }

    public function test_cast_returns_null_unchanged(): void
    {
        $model = new CastModel(['int_col' => null], $this->db);
        $this->assertNull($model->getAttribute('int_col'));
    }

    public function test_setAttribute_stores_bool_as_int(): void //todo
    {
        $model = new CastModel([], $this->db);
        $model->setAttribute('bool_col', true);
        // Internally stored as 1
        $this->assertEquals(1, $model->attributes['bool_col']);
    }

    // ─────────────────────────────────────────────────────────────
    // setAttribute() — boolean storage, datetime formatting, json encoding
    // ─────────────────────────────────────────────────────────────

    public function test_setAttribute_stores_datetime_as_formatted_string(): void //todo
    {
        $model = new CastModel([], $this->db);
        $dt = new \DateTime('2024-03-15 12:00:00');
        $model->setAttribute('date_col', $dt);
        $this->assertEquals('2024-03-15 12:00:00', $model->attributes['date_col']);
    }

    public function test_setAttribute_stores_array_as_json_string(): void //todo
    {
        $model = new CastModel([], $this->db);
        $model->setAttribute('json_col', ['x' => 1]);
        $this->assertEquals('{"x":1}', $model->attributes['json_col']);
    }

    public function test_get_mutator_is_called_on_getAttribute(): void
    {
        $model = new MutatorModel(['first_name' => 'alice'], $this->db);
        // getFirstNameAttribute uppercases
        $this->assertEquals('ALICE', $model->getAttribute('first_name'));
    }

    // ─────────────────────────────────────────────────────────────
    // Mutators — get / set
    // ─────────────────────────────────────────────────────────────

    public function test_get_mutator_is_called_via_magic_get(): void
    {
        $model = new MutatorModel(['first_name' => 'bob'], $this->db);
        $this->assertEquals('BOB', $model->first_name);
    }

    public function test_set_mutator_is_called_on_setAttribute(): void //todo
    {
        $model = new MutatorModel([], $this->db);
        $model->setAttribute('slug', 'Hello World');
        $this->assertEquals('hello-world', $model->attributes['slug']);
    }

    public function test_set_mutator_is_called_via_magic_set(): void //todo
    {
        $model = new MutatorModel([], $this->db);
        $model->slug = 'My Page Title';
        $this->assertEquals('my-page-title', $model->attributes['slug']);
    }

    public function test_appended_attribute_appears_in_toArray(): void
    {
        $model = new AppendModel(['first_name' => 'Jane', 'last_name' => 'Doe'], $this->db);
        $arr = $model->toArray();
        $this->assertEquals('Jane Doe', $arr['full_name']);
    }

    // ─────────────────────────────────────────────────────────────
    // Appends
    // ─────────────────────────────────────────────────────────────

    public function test_appended_attribute_accessible_via_getAttribute(): void
    {
        $model = new AppendModel(['first_name' => 'John', 'last_name' => 'Smith'], $this->db);
        $this->assertEquals('John Smith', $model->getAttribute('full_name'));
    }

    public function test_hidden_attributes_excluded_from_toArray(): void
    {
        $model = new VisibilityModel([
            'name' => 'Alice',
            'email' => 'a@test.com',
            'password' => 'secret123',
            'token' => 'abc',
        ], $this->db);

        $arr = $model->toArray();
        $this->assertArrayHasKey('name', $arr);
        $this->assertArrayHasKey('email', $arr);
        $this->assertArrayNotHasKey('password', $arr);
        $this->assertArrayNotHasKey('token', $arr);
    }

    // ─────────────────────────────────────────────────────────────
    // Visibility — hidden / visible / makeVisible / makeHidden
    // ─────────────────────────────────────────────────────────────

    public function test_makeVisible_overrides_hidden(): void
    {
        $model = new VisibilityModel([
            'name' => 'Alice',
            'password' => 'secret123',
            'token' => 'abc',
        ], $this->db);

        $model->makeVisible('password');
        $arr = $model->toArray();
        $this->assertArrayHasKey('password', $arr);
        // token is still hidden
        $this->assertArrayNotHasKey('token', $arr);
    }

    public function test_makeHidden_adds_to_hidden_list(): void
    {
        $model = new VisibilityModel([
            'name' => 'Alice',
            'email' => 'a@test.com',
        ], $this->db);

        $model->makeHidden('email');
        $arr = $model->toArray();
        $this->assertArrayNotHasKey('email', $arr);
    }

    public function test_setVisible_restricts_output_to_listed_keys(): void
    {
        $model = new VisibilityModel([
            'name' => 'Alice',
            'email' => 'a@test.com',
            'password' => 'secret',
        ], $this->db);

        $model->setVisible(['name']);
        $arr = $model->toArray();
        $this->assertEquals(['name' => 'Alice'], $arr);
    }

    public function test_setHidden_replaces_hidden_list(): void
    {
        $model = new VisibilityModel([
            'name' => 'Alice',
            'email' => 'a@test.com',
            'password' => 'secret',
            'token' => 'abc',
        ], $this->db);

        // Replace hidden with only 'name'
        $model->setHidden(['name']);
        $arr = $model->toArray();
        $this->assertArrayNotHasKey('name', $arr);
        // password & token are no longer hidden since we replaced the list
        $this->assertArrayHasKey('password', $arr);
        $this->assertArrayHasKey('token', $arr);
    }

    public function test_save_calls_insert_on_new_model(): void //todo
    {
        $this->db->expects($this->once())
            ->method('insert')
            ->with('basics', $this->anything())
            ->willReturn(1); // new ID

        $model = $this->basicModel(['name' => 'Alice']);
        $result = $model->save();

        $this->assertTrue($result);
        $this->assertTrue($model->exists);
        $this->assertEquals(1, $model->getAttribute('id'));
    }

    // ─────────────────────────────────────────────────────────────
    // save() — insert path
    // ─────────────────────────────────────────────────────────────

    public function test_save_sets_timestamps_on_insert(): void
    {
        $this->db->expects($this->once())
            ->method('insert')
            ->willReturn(5);

        $model = $this->basicModel(['name' => 'Alice']);
        $model->save();

        $this->assertNotNull($model->getAttribute('created_at'));
        $this->assertNotNull($model->getAttribute('updated_at'));
    }

    public function test_save_returns_false_when_insert_fails(): void
    {
        $this->db->expects($this->once())
            ->method('insert')
            ->willReturn(0); // failure

        $model = $this->basicModel(['name' => 'Alice']);
        $result = $model->save();

        $this->assertFalse($result);
        $this->assertFalse($model->exists);
    }

    public function test_save_calls_update_on_existing_model(): void
    {
        $this->db->expects($this->once())
            ->method('update')
            ->willReturn(1);

        $model = $this->basicModel(['id' => 10, 'name' => 'Alice']);
        $model->exists = true;
        $model->original = $model->attributes;

        // Make a change so dirty detection picks it up
        $model->setAttribute('name', 'Alice Updated');
        $result = $model->save();

        $this->assertTrue($result);
    }

    // ─────────────────────────────────────────────────────────────
    // save() — update path
    // ─────────────────────────────────────────────────────────────

    public function test_save_update_does_not_call_db_when_no_changes(): void
    {
        // No DB update call expected when nothing is dirty
        $this->db->expects($this->never())
            ->method('update');

        $model = $this->basicModel(['id' => 10, 'name' => 'Alice']);
        $model->exists = true;
        $model->original = $model->attributes;

        $result = $model->save();
        $this->assertTrue($result);
    }

    public function test_save_update_sets_updated_at(): void
    {
        $this->db->expects($this->once())
            ->method('update')
            ->willReturn(1);

        $model = $this->basicModel(['id' => 10, 'name' => 'Original']);
        $model->exists = true;
        $model->original = $model->attributes;

        $model->setAttribute('name', 'Changed');
        $model->save();

        $this->assertNotNull($model->getAttribute('updated_at'));
    }

    public function test_update_fills_and_persists(): void
    {
        $this->db->expects($this->once())
            ->method('update')
            ->willReturn(1);

        $model = $this->basicModel(['id' => 5, 'name' => 'Before']);
        $model->exists = true;
        $model->original = $model->attributes;

        $result = $model->update(['name' => 'After']);
        $this->assertTrue($result);
        $this->assertEquals('After', $model->getAttribute('name'));
    }

    // ─────────────────────────────────────────────────────────────
    // update() method
    // ─────────────────────────────────────────────────────────────

    public function test_delete_performs_hard_delete(): void
    {
        $this->db->expects($this->once())
            ->method('delete')
            ->with('basics', ['id' => 7])
            ->willReturn(1);

        $model = $this->basicModel(['id' => 7, 'name' => 'ToDelete']);
        $model->exists = true;

        $result = $model->delete();
        $this->assertTrue($result);
        $this->assertFalse($model->exists);
    }

    // ─────────────────────────────────────────────────────────────
    // delete() — hard delete
    // ─────────────────────────────────────────────────────────────

    public function test_delete_returns_false_on_non_existing_model(): void
    {
        $model = $this->basicModel(['id' => 1]);
        $model->exists = false;

        $this->assertFalse($model->delete());
    }

    public function test_force_delete_bypasses_soft_delete(): void
    {
        $this->db->expects($this->once())
            ->method('delete')
            ->willReturn(1);

        $model = new SoftDeleteModel(['id' => 3, 'name' => 'Item'], $this->db);
        $model->exists = true;

        $result = $model->forceDelete();
        $this->assertTrue($result);
        $this->assertFalse($model->exists);
    }

    public function test_soft_delete_model_uses_soft_deletes(): void
    {
        $model = new SoftDeleteModel([], $this->db);
        // usesSoftDeletes() checks if 'deleted_at' is in fillable
        $this->assertTrue($model->usesSoftDeletes());
    }

    // ─────────────────────────────────────────────────────────────
    // Soft deletes
    // ─────────────────────────────────────────────────────────────

    public function test_basic_model_does_not_use_soft_deletes(): void
    {
        $model = $this->basicModel();
        $this->assertFalse($model->usesSoftDeletes());
    }

    public function test_soft_delete_sets_deleted_at(): void
    {
        // Soft delete calls save() internally, which calls insert (since exists may be tricky)
        // We mock insert to simulate the save succeeding
        $this->db->expects($this->atLeastOnce())
            ->method('update')
            ->willReturn(1);

        $model = new SoftDeleteModel(['id' => 1, 'name' => 'Item'], $this->db);
        $model->exists = true;
        $model->original = $model->attributes;

        $model->delete();
        $this->assertNotNull($model->getAttribute('deleted_at'));
    }

    public function test_trashed_returns_true_after_soft_delete(): void
    {
        $model = new SoftDeleteModel(['id' => 1, 'name' => 'Item'], $this->db);
        $model->exists = true;
        $model->setAttribute('deleted_at', '2024-01-01 00:00:00');

        $this->assertTrue($model->trashed());
    }

    public function test_trashed_returns_false_when_not_deleted(): void
    {
        $model = new SoftDeleteModel(['id' => 1, 'name' => 'Item'], $this->db);
        $model->exists = true;

        $this->assertFalse($model->trashed());
    }

    public function test_restore_clears_deleted_at(): void
    {
        $this->db->expects($this->atLeastOnce())
            ->method('update')
            ->willReturn(1);

        $model = new SoftDeleteModel(['id' => 1, 'name' => 'Item', 'deleted_at' => '2024-01-01 00:00:00'], $this->db);
        $model->exists = true;
        $model->original = $model->attributes;

        $result = $model->restore();
        $this->assertTrue($result);
        $this->assertNull($model->getAttribute('deleted_at'));
    }

    public function test_observer_receives_creating_and_created_events(): void
    {
        $observer = new TestObserver();
        ObservableModel::observe($observer);

        $this->db->expects($this->once())
            ->method('insert')
            ->willReturn(1);

        $model = new ObservableModel(['name' => 'Test'], $this->db);
        $model->save();

        $this->assertContains('creating', $observer->log);
        $this->assertContains('created', $observer->log);
        $this->assertContains('saving', $observer->log);
        $this->assertContains('saved', $observer->log);
    }

    // ─────────────────────────────────────────────────────────────
    // Observers & Events
    // ─────────────────────────────────────────────────────────────

    public function test_observer_can_veto_creation(): void
    {
        $vetoObserver = new VetoObserver();
        ObservableModel::observe($vetoObserver);

        // DB insert should NOT be called
        $this->db->expects($this->never())
            ->method('insert');

        $model = new ObservableModel(['name' => 'Vetoed'], $this->db);
        $result = $model->save();

        $this->assertFalse($result);
    }

    public function test_increment_increases_attribute_by_amount(): void
    {
        $this->db->expects($this->atLeastOnce())
            ->method('update')
            ->willReturn(1);

        $model = $this->basicModel(['id' => 1, 'age' => 10]);
        $model->exists = true;
        $model->original = $model->attributes;

        $model->increment('age', 5);
        $this->assertEquals(15, $model->getAttribute('age'));
    }

    // ─────────────────────────────────────────────────────────────
    // increment() / decrement()
    // ─────────────────────────────────────────────────────────────

    public function test_increment_defaults_to_1(): void
    {
        $this->db->expects($this->atLeastOnce())
            ->method('update')
            ->willReturn(1);

        $model = $this->basicModel(['id' => 1, 'age' => 10]);
        $model->exists = true;
        $model->original = $model->attributes;

        $model->increment('age');
        $this->assertEquals(11, $model->getAttribute('age'));
    }

    public function test_decrement_decreases_attribute(): void
    {
        $this->db->expects($this->atLeastOnce())
            ->method('update')
            ->willReturn(1);

        $model = $this->basicModel(['id' => 1, 'age' => 10]);
        $model->exists = true;
        $model->original = $model->attributes;

        $model->decrement('age', 3);
        $this->assertEquals(7, $model->getAttribute('age'));
    }

    public function test_increment_on_null_attribute_starts_from_zero(): void
    {
        $this->db->expects($this->atLeastOnce())
            ->method('update')
            ->willReturn(1);

        $model = $this->basicModel(['id' => 1]);
        $model->exists = true;
        $model->original = $model->attributes;

        $model->increment('age', 5);
        $this->assertEquals(5, $model->getAttribute('age'));
    }

    public function test_toArray_returns_attributes_as_array(): void
    {
        $model = $this->basicModel(['name' => 'Alice', 'email' => 'a@test.com']);
        $arr = $model->toArray();

        $this->assertEquals('Alice', $arr['name']);
        $this->assertEquals('a@test.com', $arr['email']);
    }

    // ─────────────────────────────────────────────────────────────
    // toArray() / toJson()
    // ─────────────────────────────────────────────────────────────

    public function test_toJson_returns_valid_json_string(): void
    {
        $model = $this->basicModel(['name' => 'Alice']);
        $json = $model->toJson();
        $decoded = json_decode($json, true);

        $this->assertEquals('Alice', $decoded['name']);
    }

    public function test_toArray_excludes_internal_attributes(): void
    {
        $model = $this->basicModel(['name' => 'Alice']);
        $arr = $model->toArray();

        $this->assertArrayNotHasKey('exists', $arr);
        $this->assertArrayNotHasKey('original', $arr);
    }

    public function test_magic_get_returns_attribute(): void
    {
        $model = $this->basicModel(['name' => 'Alice']);
        $this->assertEquals('Alice', $model->name);
    }

    // ─────────────────────────────────────────────────────────────
    // Magic methods: __get / __set
    // ─────────────────────────────────────────────────────────────

    public function test_magic_set_stores_attribute(): void
    {
        $model = $this->basicModel();
        $model->name = 'Bob';
        $this->assertEquals('Bob', $model->getAttribute('name'));
    }

    public function test_magic_get_returns_null_for_missing(): void
    {
        $model = $this->basicModel();
        $this->assertNull($model->nonexistent);
    }

    public function test_castAttributeForDb_integer(): void
    {
        $model = new CastModel([], $this->db);
        $result = $model->castAttributeForDb('int_col', '42');
        $this->assertSame(42, $result);
    }

    // ─────────────────────────────────────────────────────────────
    // castAttributeForDb()
    // ─────────────────────────────────────────────────────────────

    public function test_castAttributeForDb_float(): void
    {
        $model = new CastModel([], $this->db);
        $result = $model->castAttributeForDb('float_col', '3.14');
        $this->assertIsFloat($result);
    }

    public function test_castAttributeForDb_string(): void
    {
        $model = new CastModel([], $this->db);
        $result = $model->castAttributeForDb('str_col', 123);
        $this->assertSame('123', $result);
    }

    public function test_castAttributeForDb_json_array(): void
    {
        $model = new CastModel([], $this->db);
        $result = $model->castAttributeForDb('json_col', ['a' => 1]);
        $this->assertEquals('{"a":1}', $result);
    }

    public function test_castAttributeForDb_datetime_string(): void
    {
        $model = new CastModel([], $this->db);
        $result = $model->castAttributeForDb('date_col', '2024-06-15 10:00:00');
        $this->assertEquals('2024-06-15 10:00:00', $result);
    }

    public function test_castAttributeForDb_datetime_object(): void
    {
        $model = new CastModel([], $this->db);
        $dt = new \DateTime('2024-03-20 08:30:00');
        $result = $model->castAttributeForDb('date_col', $dt);
        $this->assertEquals('2024-03-20 08:30:00', $result);
    }

    public function test_castAttributeForDb_no_cast_returns_raw(): void
    {
        $model = new CastModel([], $this->db);
        // 'unknown_col' has no cast defined
        $result = $model->castAttributeForDb('unknown_col', 'raw_value');
        $this->assertEquals('raw_value', $result);
    }

    public function test_setRelation_and_getRelation(): void
    {
        $model = $this->basicModel();
        $fakeRelation = new Collection([['id' => 1]]);

        $model->setRelation('items', $fakeRelation);
        $this->assertSame($fakeRelation, $model->getRelation('items'));
    }

    // ─────────────────────────────────────────────────────────────
    // Relation loading: setRelation / getRelation / relationLoaded
    // ─────────────────────────────────────────────────────────────

    public function test_relationLoaded_returns_true_after_set(): void
    {
        $model = $this->basicModel();
        $this->assertFalse($model->relationLoaded('orders'));

        $model->setRelation('orders', new Collection());
        $this->assertTrue($model->relationLoaded('orders'));
    }

    public function test_getRelation_returns_null_for_unloaded(): void
    {
        $model = $this->basicModel();
        $this->assertNull($model->getRelation('missing'));
    }

    public function test_loaded_relation_included_in_toArray(): void
    {
        $model = $this->basicModel(['name' => 'Alice']);
        $model->setRelation('tags', new Collection([['name' => 'php'], ['name' => 'laravel']]));

        $arr = $model->toArray();
        $this->assertArrayHasKey('tags', $arr);
        $this->assertEquals(2, count($arr['tags']));
    }

    public function test_getTable_returns_table_name(): void
    {
        $model = $this->basicModel();
        $this->assertEquals('basics', $model->getTable());
    }

    // ─────────────────────────────────────────────────────────────
    // getTable() / setExists()
    // ─────────────────────────────────────────────────────────────

    public function test_setExists_updates_exists_flag(): void
    {
        $model = $this->basicModel();
        $model->setExists(true);
        $this->assertTrue($model->exists);

        $model->setExists(false);
        $this->assertFalse($model->exists);
    }

    public function test_hasScope_detects_scope_method(): void
    {
        $model = new ScopedModel([], $this->db);
        $this->assertTrue($model->hasScope('active'));
        $this->assertTrue($model->hasScope('olderThan'));
        $this->assertFalse($model->hasScope('nonExistentScope'));
    }

    // ─────────────────────────────────────────────────────────────
    // Scopes — instance and static
    // ─────────────────────────────────────────────────────────────

    public function test_isPlural_detects_plural_names(): void
    {
        $model = $this->basicModel();

        $this->assertTrue($model->isPlural('orders'));
        $this->assertTrue($model->isPlural('items'));
        $this->assertTrue($model->isPlural('children'));
        $this->assertTrue($model->isPlural('people'));

        $this->assertFalse($model->isPlural('order'));
        $this->assertFalse($model->isPlural('user'));
    }

    // ─────────────────────────────────────────────────────────────
    // isPlural() / getEmptyRelationValue()
    // ─────────────────────────────────────────────────────────────

    public function test_getEmptyRelationValue_returns_collection_for_plural(): void
    {
        $model = $this->basicModel();
        $result = $model->getEmptyRelationValue('orders');
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertTrue($result->isEmpty());
    }

    public function test_getEmptyRelationValue_returns_null_for_singular(): void
    {
        $model = $this->basicModel();
        $result = $model->getEmptyRelationValue('profile');
        $this->assertNull($result);
    }

    public function test_delete_hard_returns_false_when_db_affects_zero_rows(): void
    {
        $this->db->expects($this->once())
            ->method('delete')
            ->willReturn(0);

        $model = $this->basicModel(['id' => 99]);
        $model->exists = true;

        $result = $model->delete();
        $this->assertFalse($result);
    }

    // ─────────────────────────────────────────────────────────────
    // Edge cases
    // ─────────────────────────────────────────────────────────────

    public function test_force_delete_returns_false_when_not_existing(): void
    {
        $model = $this->basicModel(['id' => 1]);
        $model->exists = false;
        $this->assertFalse($model->forceDelete());
    }

    public function test_restore_returns_false_on_non_soft_delete_model(): void
    {
        $model = $this->basicModel(['id' => 1]);
        $model->exists = true;
        $this->assertFalse($model->restore());
    }

    public function test_performUpdate_returns_false_without_primary_key(): void
    {
        // Model with no ID set
        $this->db->expects($this->never())
            ->method('update');

        $model = $this->basicModel(['name' => 'NoId']);
        $model->exists = true;
        // performUpdate is protected; test via update()
        $result = $model->update(['name' => 'Still NoId']);
        $this->assertFalse($result);
    }

    public function test_makeVisible_with_multiple_args(): void
    {
        $model = new VisibilityModel([
            'name' => 'Alice',
            'password' => 'secret',
            'token' => 'abc',
        ], $this->db);

        $model->makeVisible(['password', 'token']);
        $arr = $model->toArray();
        $this->assertArrayHasKey('password', $arr);
        $this->assertArrayHasKey('token', $arr);
    }

    public function test_makeHidden_with_multiple_args(): void
    {
        $model = new VisibilityModel([
            'name' => 'Alice',
            'email' => 'a@test.com',
        ], $this->db);

        $model->makeHidden(['name', 'email']);
        $arr = $model->toArray();
        $this->assertArrayNotHasKey('name', $arr);
        $this->assertArrayNotHasKey('email', $arr);
    }

    public function test_shouldIncludeAttribute_respects_visible_whitelist(): void
    {
        $model = new VisibilityModel([], $this->db);
        $model->setVisible(['name']);

        $this->assertTrue($model->shouldIncludeAttribute('name'));
        $this->assertFalse($model->shouldIncludeAttribute('email'));
        $this->assertFalse($model->shouldIncludeAttribute('password'));
    }

    public function test_shouldIncludeAttribute_respects_hidden_blacklist(): void
    {
        $model = new VisibilityModel([], $this->db);
        // Default hidden = ['password', 'token']

        $this->assertTrue($model->shouldIncludeAttribute('name'));
        $this->assertFalse($model->shouldIncludeAttribute('password'));
        $this->assertFalse($model->shouldIncludeAttribute('token'));
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the Database singleton so no real DB calls happen
        $this->db = $this->createMock(Database::class);

        // Patch Database::getInstance() to return our mock
        // We use a simple approach: pass $db directly to model constructors
    }
}