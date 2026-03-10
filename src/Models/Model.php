<?php

namespace App\Models;

use App\Events\ModelCreated;
use App\Events\ModelCreating;
use App\Events\ModelDeleted;
use App\Events\ModelDeleting;
use App\Events\ModelRetrieved;
use App\Events\ModelSaved;
use App\Events\ModelSaving;
use App\Events\ModelUpdated;
use App\Events\ModelUpdating;
use App\Framework\Database\Database;
use App\Framework\Database\QueryBuilder;
use App\Framework\Database\Relations\EagerLoader;
use App\Framework\Database\Relations\RelationBuilder;
use App\Framework\Database\Relations\RelationHandlerFactory;
use App\Framework\Database\Relations\RelationshipAnalyzer;
use App\Framework\Database\Relations\RelationshipBuilder;
use App\Framework\Database\Relations\RelationshipHandler;
use App\Framework\Support\Collection;
use App\Framework\Support\Event;
use App\Framework\Support\Serializable;
use BadMethodCallException;

abstract class Model
{
    use Serializable, RelationshipBuilder;

    protected static $observers = [];
    protected static $globalEvents = [];
    /**
     * @var mixed|null
     */
    private static array $scopes;
    private static array $eagerLoad = [];
    protected $table;
    protected $fillable = [];
    protected $guarded = [];
    protected $attributes = [];
    protected $original = [];
    protected $exists = false;
    protected $primaryKey = 'id';
    protected $timestamps = true;
    protected $dateFormat = 'Y-m-d H:i:s';
    protected $database;
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];
    protected $appends = [];
    protected $relations = [];
    protected $with = [];
    protected $eagerLoaded = [];
    protected $dispatchesEvents = [
        'creating' => ModelCreating::class,
        'created' => ModelCreated::class,
        'updating' => ModelUpdating::class,
        'updated' => ModelUpdated::class,
        'saving' => ModelSaving::class,
        'saved' => ModelSaved::class,
        'deleting' => ModelDeleting::class,
        'deleted' => ModelDeleted::class,
        'retrieved' => ModelRetrieved::class,
    ];

    protected $casts = [];

    // Define which relations should always be included in serialization
    protected $alwaysInclude = [];

    // Define which attributes should be hidden from serialization
    protected $hidden = [];

    // Define which attributes should be visible in serialization (overrides hidden)
    protected $visible = [];

    protected array $internalAttributes = ['exists', 'original', 'attributes'];

    private ?EagerLoader $relationManager = null;

    public function __construct(array $attributes = [], $database = null)
    {
        // Boot traits on first instantiation
        static $booted = [];
        $class = static::class;

        if (!isset($booted[$class])) {
            $booted[$class] = true; // Set this IMMEDIATELY to prevent recursion

            static::bootTraits();

            // This connects your Subscription.php boot() method
            if (method_exists($class, 'boot')) {
                static::boot();
            }
        }

        $this->database = $database ?? Database::getInstance();
        $this->relationManager = new EagerLoader(
            new RelationshipAnalyzer(),
            new RelationHandlerFactory($this->database),
            $this->database
        );

        if (!empty($attributes)) {
            $this->fill($attributes);
        }
    }

    /**
     * Boot all of the bootable traits on the model
     */
    protected static function bootTraits(): void
    {
        $class = static::class;

        $booted = [];

        foreach (class_uses_recursive($class) as $trait) {
            $method = 'boot' . class_basename($trait);

            if (method_exists($class, $method) && !in_array($method, $booted)) {
                forward_static_call([$class, $method]);
                $booted[] = $method;
            }
        }
    }

    protected static function boot()
    {
        // Empty base method so child classes can safely call parent::boot()
    }

    public function fill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            if ($key === $this->primaryKey || $this->isFillable($key)) {
                $this->setAttribute($key, $value);
            }
        }
        return $this;
    }

    protected function isFillable(string $key): bool
    {
        $keywords = ['count', 'total', 'avg', 'units_sold', 'day', 'revenue'];

        if (collect($keywords)->contains(fn($keyword) => str_contains($key, $keyword))) {
            return true;
        }

        if (!empty($this->fillable)) {
            return in_array($key, $this->fillable);
        }

        return !in_array($key, $this->guarded);
    }

    public function setAttribute(string $key, $value): void
    {
        // Handle boolean casting FIRST - convert bools to int for storage
        if (isset($this->casts[$key]) && in_array($this->casts[$key], ['bool', 'boolean'])) {
            $this->attributes[$key] = (int)$value;
            return;
        }

        // Handle datetime casting FIRST - convert to formatted string
        if (isset($this->casts[$key]) && in_array($this->casts[$key], ['date', 'datetime', 'timestamp'])) {
            if ($value instanceof \DateTimeInterface) {
                $this->attributes[$key] = $value->format($this->dateFormat);
                return;
            }

            if (is_string($value)) {
                $this->attributes[$key] = (new \DateTime($value))->format($this->dateFormat);
                return;
            }
        }

        // Handle array/json casting FIRST - convert arrays to JSON strings for storage
        if (isset($this->casts[$key]) && in_array($this->casts[$key], ['array', 'json'])) {
            if (is_array($value)) {
                $this->attributes[$key] = json_encode($value);
                return;
            }
        }

        // Check if there's a set mutator
        $mutatorMethod = 'set' . str_replace(' ', '', ucwords(str_replace('_', ' ', $key))) . 'Attribute';
        if (method_exists($this, $mutatorMethod)) {
            $this->$mutatorMethod($value);
            return;
        }

        $this->attributes[$key] = $value;
    }

    public static function withTrashed(): QueryBuilder
    {
        $instance = new static();
        return new QueryBuilder($instance->table, $instance->relationManager, $instance->database);
    }

    public static function onlyTrashed(): QueryBuilder
    {
        $instance = new static();
        $query = new QueryBuilder($instance->table, $instance->relationManager, $instance->database);
        return $query->whereNotNull('deleted_at');
    }

    public static function findOrFail($id)
    {
        // Create a new instance of the query builder for this table
        $instance = new static();

        return $instance->findOrFailCall($id);
    }

    public function findOrFailCall($id)
    {
        $result = $this->find($id);

        if (!$result) {
            throw new \Exception("No record found in {$this->table} with ID {$id}");
        }

        return $result;
    }

    public static function createMany(array $records): array
    {
        if (empty($records)) {
            return [];
        }

        return Database::runTransaction(function () use ($records) {

            $models = [];

            foreach ($records as $record) {
                $models[] = static::create($record);
            }

            return $models;
        });
    }

    public static function create(array $attributes): self
    {
        $model = new static($attributes);
        $model->save();
        $model->exists = true;
        return $model;
    }

    public static function all(): Collection
    {
        $instance = new static();
        return $instance->newQuery()->get();
    }

    public static function withRelations(array $relations): QueryBuilder
    {
        return static::with($relations);
    }

    public static function scope(string $name, callable $callback): void
    {
        static::$scopes[$name] = $callback;
    }

    /**
     * Handle dynamic static method calls
     *
     * Priority:
     * 1. Static scope methods
     * 2. Query builder methods
     */
    public static function __callStatic(string $method, array $arguments)
    {
        $instance = new static();

        // Check for static scope methods
        if ($instance->hasScope($method)) {
            return $instance->callStaticScope($method, $arguments);
        }

        // Proxy to fresh query builder
        return $instance->newQuery()->$method(...$arguments);
    }

    /**
     * Call a scope method statically
     */
    protected function callStaticScope(string $method, array $arguments)
    {
        $scopeMethod = 'scope' . ucfirst($method);
        $query = $this->newQuery();
        return $this->$scopeMethod($query, ...$arguments);
    }

    public static function hydrateStatic(array $data): self
    {
        $model = new static($data);
        $model->exists = true;
        $model->original = $model->attributes;
        $model->fireModelEvent('retrieved');
        return $model;
    }

    public static function observe($observer): void
    {
        $modelClass = static::class;

        if (!isset(static::$observers[$modelClass])) {
            static::$observers[$modelClass] = [];
        }

        static::$observers[$modelClass][] = $observer;
    }

    public static function creating(callable $callback): void
    {
        static::registerModelEvent('creating', $callback);
    }

    protected static function registerModelEvent(string $event, callable $callback): void
    {
        $modelClass = static::class;

        if (!isset(static::$globalEvents[$modelClass])) {
            static::$globalEvents[$modelClass] = [];
        }

        if (!isset(static::$globalEvents[$modelClass][$event])) {
            static::$globalEvents[$modelClass][$event] = [];
        }

        static::$globalEvents[$modelClass][$event][] = $callback;
    }

    public static function created(callable $callback): void
    {
        static::registerModelEvent('created', $callback);
    }

// Add scope for soft deletes

    public static function updating(callable $callback): void
    {
        static::registerModelEvent('updating', $callback);
    }

    public static function updated(callable $callback): void
    {
        static::registerModelEvent('updated', $callback);
    }

    // Override where to exclude soft deleted by default

    public static function saving(callable $callback): void
    {
        static::registerModelEvent('saving', $callback);
    }

    public static function saved(callable $callback): void
    {
        static::registerModelEvent('saved', $callback);
    }

    public static function deleting(callable $callback): void
    {
        static::registerModelEvent('deleting', $callback);
    }

    public static function deleted(callable $callback): void
    {
        static::registerModelEvent('deleted', $callback);
    }

    public static function paginate(int $perPage = 15, int $page = 1): array
    {
        $instance = new static();
        $query = $instance->newQuery();
        return $query->paginate($perPage, $page);
    }

    public static function upsert(array $values, array $uniqueBy, ?array $update = null): int
    {
        $instance = new static();
        $database = $instance->database;

        if (empty($values)) {
            return 0;
        }

        $update = $update ?: array_keys($values[0]);
        $table = $instance->table;

        // Build INSERT ... ON DUPLICATE KEY UPDATE query
        $columns = array_keys($values[0]);
        $quotedColumns = array_map(function ($col) {
            return "`{$col}`";
        }, $columns);

        $sql = "INSERT INTO `{$table}` (" . implode(', ', $quotedColumns) . ") VALUES ";

        $placeholders = [];
        $bindings = [];
        $paramCounter = 0;

        foreach ($values as $row) {
            $rowPlaceholders = [];
            foreach ($columns as $column) {
                $paramKey = 'param_' . $paramCounter++;
                $rowPlaceholders[] = ":{$paramKey}";
                $bindings[$paramKey] = $row[$column];
            }
            $placeholders[] = '(' . implode(', ', $rowPlaceholders) . ')';
        }

        $sql .= implode(', ', $placeholders);

        // Add ON DUPLICATE KEY UPDATE
        $updateParts = [];
        foreach ($update as $column) {
            $updateParts[] = "`{$column}` = VALUES(`{$column}`)";
        }

        $sql .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updateParts);

        $stmt = $database->query($sql, $bindings);
        return $stmt->rowCount();
    }

    public static function query(): QueryBuilder
    {
        $instance = new static();
        return $instance->newQuery();
    }

    public static function updateOrInsert(array $attributes, array $values = []): bool
    {
        $instance = new static();

        // Try to find existing record
        $query = $instance->newQuery();
        foreach ($attributes as $key => $value) {
            $query->where($key, $value);
        }

        $existing = $query->first();

        if ($existing) {
            // Update existing
            $model = new static($existing);
            $model->exists = true;
            $model->fill($values);
            return $model->save();
        } else {
            // Insert new
            $model = new static(array_merge($attributes, $values));
            return $model->save();
        }
    }

    /**
     * Update an existing record matching the attributes, or create a new one.
     *
     * @param array $attributes Key-value pairs to match existing record
     * @param array $values Key-value pairs to update or set
     * @return static            The model instance
     */
    public static function updateOrCreate(array $attributes, array $values)
    {
        // Try to find existing record
        $instance = static::where($attributes)->first();

        if ($instance) {
            // Update existing record
            $instance->fill($values);
            $instance->save();
        } else {
            // Create new record with combined attributes and values
            $instance = new static(array_merge($attributes, $values));
            $instance->save();
        }

        return $instance;
    }

    public static function firstOrCreate(array $attributes, array $values = [])
    {
        // Step 1: Try to find an existing record matching the attributes
        $query = static::query();
        foreach ($attributes as $key => $value) {
            $query->where($key, $value);
        }

        $existing = $query->first();

        if ($existing) {
            return $existing;
        }

        // Step 2: Not found — create new record
        $data = array_merge($attributes, $values);

        return static::create($data);
    }

    /**
     * firstOrNew: returns the first record matching $conditions,
     * or a new instance if none found.
     */
    public static function firstOrNew(array $conditions): static
    {
        // Make a temporary instance to access $table
        $temp = new static();

        $record = Database::table($temp->table)
            ->where($conditions)
            ->first(); // null if not found

        if ($record) {
            $obj = new static($record->toArray());
            $obj->exists = true;
            return $obj;
        }

        return new static($conditions);
    }

    // Automatic relation loading for serialization

    public function forceFill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }

        return $this;
    }

    public function update(array $attributes): bool
    {
        // Update model's attributes
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }

        return $this->performUpdate();
    }

    protected function performUpdate(): bool
    {
        $primaryKeyValue = $this->getAttribute($this->primaryKey);

        if (!$primaryKeyValue) {
            return false;
        }

        // Determine dirty attributes (only changed values)
        $dirty = array_diff_assoc(
            $this->attributes,
            $this->original ?? []
        );

        if (empty($dirty)) {
            // No changes, return success without DB call
            return true;
        }

        // Cast dirty attributes for DB storage
        foreach ($dirty as $key => $value) {
            if ($value === null || (is_string($value) && trim($value) === '')) {
                continue;
            }

            $dirty[$key] = $this->castAttributeForDb($key, $value);
        }

        // Perform database update
        $affectedRows = $this->database->update(
            $this->table,
            $dirty,
            [$this->primaryKey => $primaryKeyValue]
        );

        if ($affectedRows >= 0) {
            // Update original snapshot
            $this->original = $this->attributes;
            return true;
        }

        return false;
    }

    public function getAttribute(string $key)
    {
        // Check if it's a relationship
        if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }

        // Check if it's an appended attribute with a mutator
        if (in_array($key, $this->appends) && $this->hasGetMutator($key)) {
            return $this->callGetMutator($key);
        }

        // Check if there's a mutator
        $mutatorMethod = 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $key))) . 'Attribute';

        if (method_exists($this, $mutatorMethod)) {
            $value = $this->$mutatorMethod();
            return $this->castAttribute($key, $value);
        }

        $value = $this->attributes[$key] ?? null;
        return $this->castAttribute($key, $value);
    }

    /**
     * Check if a get mutator exists for an attribute
     */
    protected function hasGetMutator(string $key): bool
    {
        $mutatorMethod = $this->getGetMutatorMethod($key);
        return method_exists($this, $mutatorMethod);
    }

    /**
     * Get the get mutator method name
     */
    protected function getGetMutatorMethod(string $key): string
    {
        return 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $key))) . 'Attribute';
    }

    /**
     * Call a get mutator
     */
    protected function callGetMutator(string $key)
    {
        $mutatorMethod = $this->getGetMutatorMethod($key);
        // Don't pass raw value, let the mutator access what it needs from $this
        return $this->$mutatorMethod();
    }

    protected function castAttribute(string $key, $value)
    {
        if (!isset($this->casts[$key]) || $value === null) {
            return $value;
        }

        switch ($this->casts[$key]) {
            case 'int':
            case 'integer':
                return (int)$value;
            case 'float':
            case 'double':
                return (float)$value;
            case 'string':
                return (string)$value;
            case 'bool':
            case 'boolean':
                return (bool)$value;
            case 'array':
            case 'json':
                // IMPORTANT: Handle both string and array inputs
                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    return $decoded === null ? [] : $decoded;
                }
                return is_array($value) ? $value : [];
            case 'date':
            case 'datetime':
            case 'timestamp':
                return $value instanceof \DateTime ? $value : new \DateTime($value);
            default:
                return $value;
        }
    }

    /**
     * Cast an attribute before saving to database
     */
    protected function castAttributeForDb(string $key, $value)
    {
        if (isset($this->casts[$key])) {
            switch ($this->casts[$key]) {
                case 'date':
                case 'datetime':
                case 'timestamp':
                    if ($value instanceof \DateTimeInterface) {
                        return $value->format($this->dateFormat);
                    } elseif (is_string($value)) {
                        return (new \DateTime($value))->format($this->dateFormat);
                    }
                    break;

                case 'array':
                case 'json':
                    if (is_array($value)) {
                        return json_encode($value);
                    }
                    break;

                case 'int':
                case 'boolean':
                case 'bool':
                case 'integer':
                    return (int)$value;
                case 'float':
                case 'double':
                    return (float)$value;
                case 'string':
                    if ($value instanceof \UnitEnum) {
                        return $value instanceof \BackedEnum ? (string)$value->value : $value->name;
                    }
                    return (string)$value;
                default:
                    if ($value instanceof \UnitEnum) {
                        return $value instanceof \BackedEnum ? $value->value : $value->name;
                    }
            }
        }

        if ($value instanceof \UnitEnum) {
            return $value instanceof \BackedEnum ? $value->value : $value->name;
        }

        // No casting, return raw value
        return $value;
    }

    public function fresh(array $relations = []): ?self
    {
        $id = $this->getAttribute($this->primaryKey);
        if (!$id) {
            return null;
        }

        return static::find($id, $relations);
    }

    public static function find(int $id, array $relations = []): ?self
    {
        $instance = new static();
        $result = !empty($relations) ?
            $instance->newQuery()->with($relations)->find($id) :
            $instance->newQuery()->find($id);

        if (!$result) {
            return null;
        }

        if (!$result instanceof Model) {
            return $instance->hydrateFromArray($result);
        }

        return $result;
    }

    public static function with(array $relations): QueryBuilder
    {
        $instance = new static();
        return $instance->newQuery()->with($relations);
    }

    public function newQuery(): QueryBuilder
    {
        $query = new QueryBuilder($this->table, $this->relationManager, $this->database);

        // Apply global scopes (like soft deletes)
        if ($this->usesSoftDeletes()) {
            $query->whereNull('deleted_at');
        }

        // Apply default eager loading
        if (!empty($this->with)) {
            $query->eagerLoad = $this->with;
        }

        return $query;
    }

    protected function usesSoftDeletes(): bool
    {
        return in_array('deleted_at', $this->fillable) ||
            method_exists($this, 'bootSoftDeletes');
    }

    private function hydrateFromArray(array $data): self
    {
        $model = new static($data);
        $model->exists = true;
        $model->original = $model->attributes;
        $model->fireModelEvent('retrieved');
        return $model;
    }

    protected function fireModelEvent(string $event): bool
    {
        $modelClass = static::class;

        // Fire observers first
        if (isset(static::$observers[$modelClass])) {
            foreach (static::$observers[$modelClass] as $observer) {
                if (method_exists($observer, $event)) {
                    $result = $observer->$event($this);
                    if ($result === false) {
                        return false;
                    }
                }
            }
        }

        // Fire global events
        if (isset(static::$globalEvents[$modelClass][$event])) {
            foreach (static::$globalEvents[$modelClass][$event] as $callback) {
                $result = $callback($this);
                if ($result === false) {
                    return false;
                }
            }
        }

        // Fire application events
        if (isset($this->dispatchesEvents[$event])) {
            Event::fire($this->dispatchesEvents[$event], [$this]);
        }

        return true;
    }

    public function refresh(): static
    {
        if (!$this->exists) {
            return $this;
        }

        $primaryKeyValue = $this->getAttribute($this->primaryKey);

        $fresh = $this->database
            ->table($this->getTable())
            ->where($this->primaryKey, $primaryKeyValue)
            ->first();

        if (!$fresh) {
            throw new \RuntimeException('Model not found during refresh.');
        }

        // Replace attributes
        $this->attributes = (array)$fresh->getAttributes();
        // Keep the "original" snapshot in sync so subsequent update() calls
        // correctly detect dirtiness against the freshly loaded state.
        $this->original = $this->attributes;

        return $this;
    }

    public static function where($column, $operator = null, $value = null): QueryBuilder
    {
        $instance = new static();
        $query = new QueryBuilder($instance->table, $instance->relationManager, $instance->database);

        // Automatically exclude soft deleted records
        if ($instance->usesSoftDeletes()) {
            $query->whereNull('deleted_at');
        }

        return $query->where($column, $operator, $value);
    }

    /**
     * @return mixed
     */
    public function getTable()
    {
        return $this->table;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        // Fire deleting event
        if ($this->fireModelEvent('deleting') === false) {
            return false;
        }

        // Check for soft deletes
        if ($this->usesSoftDeletes()) {
            return $this->performSoftDelete();
        }

        return $this->performHardDelete();
    }

    protected function performSoftDelete(): bool
    {
        $this->setAttribute('deleted_at', date($this->dateFormat));
        $result = $this->save();

        if ($result) {
            $this->fireModelEvent('deleted');
        }

        return $result;
    }

    public function save(): bool
    {
        // Fire saving event
        if ($this->fireModelEvent('saving') === false) {
            return false;
        }

        if ($this->exists) {
            if ($this->fireModelEvent('updating') === false) {
                return false;
            }

            // Check for dirty attributes BEFORE adding timestamp
            $dirty = array_diff_assoc(
                $this->attributes,
                $this->original ?? []
            );

            // Only set updated_at if there are actual changes
            if ($this->timestamps && !empty($dirty)) {
                $this->setAttribute('updated_at', date($this->dateFormat));
            }

            $result = $this->performUpdate();

            if ($result) {
                $this->fireModelEvent('updated');
                $this->fireModelEvent('saved');
            }

            return $result;
        }

        // For new models, set both timestamps
        if ($this->timestamps) {
            $now = date($this->dateFormat);
            $this->setAttribute('created_at', $now);
            $this->setAttribute('updated_at', $now);
        }

        // Fire creating event
        if ($this->fireModelEvent('creating') === false) {
            return false;
        }

        $result = $this->performInsert();

        if ($result) {
            $this->fireModelEvent('created');
            $this->fireModelEvent('saved');
        }

        return $result;
    }

    protected function performInsert(): bool
    {
        $id = $this->database->insert($this->table, $this->attributes);

        if ($id > 0) {
            $this->setAttribute($this->primaryKey, $id);
            $this->exists = true;
            $this->original = $this->attributes;
            return true;
        }

        return false;
    }

    protected function performHardDelete(): bool
    {
        $primaryKeyValue = $this->getAttribute($this->primaryKey);
        if (!$primaryKeyValue) {
            return false;
        }

        $affectedRows = $this->database->delete($this->table, [$this->primaryKey => $primaryKeyValue]);

        if ($affectedRows > 0) {
            $this->exists = false;
            $this->fireModelEvent('deleted');
            return true;
        }

        return false;
    }

    public function forceDelete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        return $this->performHardDelete();
    }

    public function restore(): bool
    {
        if (!$this->usesSoftDeletes()) {
            return false;
        }

        $this->setAttribute('deleted_at', null);
        return $this->save();
    }

    public function trashed(): bool
    {
        return $this->usesSoftDeletes() && !empty($this->getAttribute('deleted_at'));
    }

    public function load(array|string $relations): self
    {
        if (is_string($relations)) {
            $relations = [$relations];
        }
        foreach ($relations as $relation) {
            if (method_exists($this, $relation)) {
                $this->eagerLoaded[$relation] = $this->$relation();
            }
        }
        return $this;
    }

    /**
     * Make an attribute visible (override hidden)
     */
    public function makeVisible($attributes): self
    {
        $attributes = is_array($attributes) ? $attributes : func_get_args();

        $this->hidden = array_diff($this->hidden, $attributes);

        if (!empty($this->visible)) {
            $this->visible = array_unique(array_merge($this->visible, $attributes));
        }

        return $this;
    }

    /**
     * Make an attribute hidden
     */
    public function makeHidden($attributes): self
    {
        $this->hidden = array_merge(
            $this->hidden,
            is_array($attributes) ? $attributes : func_get_args()
        );

        return $this;
    }

    /**
     * Set the visible attributes for the model
     */
    public function setVisible(array $visible): self
    {
        $this->visible = $visible;
        return $this;
    }

    /**
     * Set the hidden attributes for the model
     */
    public function setHidden(array $hidden): self
    {
        $this->hidden = $hidden;
        return $this;
    }

    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    public function toArray(): array
    {
        // Auto-load configured relations if they haven't been loaded yet
//        foreach ($this->alwaysInclude as $relation) {
//            if (!$this->relationLoaded($relation) && method_exists($this, $relation)) {
//                try {
//                    $this->setRelation($relation, $relationData);
//                } catch (Exception $e) {
//                    // Set empty value if relation loading fails
//                    $this->setRelation($relation, $this->getEmptyRelationValue($relation));
//                }
//            }
//        }

        return $this->attributesToArray();
    }

    public function attributesToArray(): array
    {
        $attributes = $this->getDatabaseAttributes();

        // Apply visibility rules
        $attributes = $this->getVisibleAttributes($attributes);

        // Cast all attributes
        foreach ($attributes as $key => $value) {
            $attributes[$key] = $this->castAttribute($key, $value);
        }

        // Append custom attributes - THIS IS THE KEY FIX
        if (!empty($this->appends)) {
            foreach ($this->appends as $key) {
                // Check if the attribute is visible before appending it
                if ($this->shouldIncludeAttribute($key)) {
                    // Call the accessor directly - DON'T use getAttribute
                    $mutatorMethod = 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $key))) . 'Attribute';
                    if (method_exists($this, $mutatorMethod)) {
                        $attributes[$key] = $this->$mutatorMethod();
                    }
                }
            }
        }

        // Automatically include loaded relations
        foreach ($this->eagerLoaded as $relation => $data) {
            if (!$this->shouldIncludeRelation($relation)) {
                continue;
            }

            if ($data instanceof Collection) {
                $attributes[$relation] = $data->toArray();
            } elseif ($data instanceof Model) {
                $attributes[$relation] = $data->toArray();
            } elseif (is_array($data)) {
                $attributes[$relation] = array_map(function ($item) {
                    return $item instanceof Model ? $item->toArray() : $item;
                }, $data);
            } else {
                $attributes[$relation] = $data;
            }
        }

        return $attributes;
    }

    protected function getDatabaseAttributes(): array
    {
        return array_diff_key(
            $this->attributes,
            array_flip($this->internalAttributes)
        );
    }

    /**
     * Get only the visible attributes based on hidden/visible configuration
     */
    protected function getVisibleAttributes(array $attributes): array
    {
        // If visible is set, only return those attributes
        if (!empty($this->visible)) {
            return array_intersect_key(
                $attributes,
                array_flip($this->visible)
            );
        }

        // Otherwise, exclude hidden attributes
        if (!empty($this->hidden)) {
            return array_diff_key(
                $attributes,
                array_flip($this->hidden)
            );
        }

        return $attributes;
    }

    protected function shouldIncludeAttribute(string $key): bool
    {
        if (!empty($this->visible)) {
            return in_array($key, $this->visible);
        }

        return !in_array($key, $this->hidden);
    }

    protected function shouldIncludeRelation(string $key): bool
    {
        // Always include if it's in the alwaysInclude array
        if (in_array($key, $this->alwaysInclude)) {
            return true;
        }

        // Don't include if it's hidden and not in visible
        if (!empty($this->visible)) {
            return in_array($key, $this->visible);
        }

        if (in_array($key, $this->hidden)) {
            return false;
        }

        return true;
    }

    public function scopeWhere(QueryBuilder $query, ...$args): QueryBuilder
    {
        return $query->where(...$args);
    }

    public function __call(string $method, array $arguments)
    {
        // First check if it's a relationship method
        if ($this->isRelationshipMethod($method)) {
            return $this->handleRelationshipCall($method, $arguments);
        }

        // Check for scope methods on the model instance
        if ($this->hasScope($method)) {
            return $this->callScope($method, $arguments);
        }

        // Handle special model methods
        if ($this->isSpecialModelMethod($method)) {
            return $this->callSpecialMethod($method, $arguments);
        }

        // Proxy to query builder for other methods
        return $this->proxyToQueryBuilder($method, $arguments);
    }

    /**
     * Check if a method is a relationship method
     */
    protected function isRelationshipMethod(string $method): bool
    {
        return method_exists($this, $method);
        //&& $this->methodReturnsRelationshipHandler($method);
    }

    /**
     * Handle relationship method calls
     */
    protected function handleRelationshipCall(string $method, array $arguments)
    {
        $result = $this->$method(...$arguments);

        // If it's a RelationBuilder, return it for query chaining
        if ($result instanceof RelationBuilder) {
            return $result;
        }

        // If it's a handler and should return handler (for attach/detach operations)
        if ($result instanceof RelationshipHandler && $result->shouldReturnHandler()) {
            return $result;
        }

        // For other cases (loaded relation data), cache and return
        $this->setRelation($method, $result);
        return $result;
    }

    public function setRelation(string $relation, $value): self
    {
        $this->eagerLoaded[$relation] = $value;
        return $this;
    }

    protected function hasScope(string $method): bool
    {
        $scopeMethod = 'scope' . ucfirst($method);
        return method_exists($this, $scopeMethod);
    }

    /**
     * Call a scope method on instance
     */
    protected function callScope(string $method, array $arguments)
    {
        $scopeMethod = 'scope' . ucfirst($method);
        return $this->$scopeMethod($this->newQuery(), ...$arguments);
    }

    /**
     * Check if method is a special model method
     */
    protected function isSpecialModelMethod(string $method): bool
    {
        return in_array($method, ['increment', 'decrement']);
    }

    /**
     * Call special model methods
     */
    protected function callSpecialMethod(string $method, array $arguments)
    {
        switch ($method) {
            case 'increment':
                $column = $arguments[0] ?? null;
                $amount = $arguments[1] ?? 1;
                return $this->increment($column, $amount);

            case 'decrement':
                $column = $arguments[0] ?? null;
                $amount = $arguments[1] ?? 1;
                return $this->decrement($column, $amount);

            default:
                throw new BadMethodCallException("Method {$method} not supported");
        }
    }

    public function increment(string $column, int $amount = 1): bool
    {
        $currentValue = $this->getAttribute($column) ?: 0;
        $this->setAttribute($column, $currentValue + $amount);
        return $this->save();
    }

    public function decrement(string $column, int $amount = 1): bool
    {
        return $this->increment($column, -$amount);
    }

    /**
     * Proxy method calls to query builder
     */
    protected function proxyToQueryBuilder(string $method, array $arguments)
    {
        $query = $this->newQuery();

        if (!method_exists($query, $method)) {
            throw new BadMethodCallException(
                "Method {$method} does not exist on " . static::class . " or QueryBuilder"
            );
        }

        return $query->$method(...$arguments);
    }

    public function hydrate(array $data): self
    {
        $model = new static($data);
        $model->exists = true;
        $model->original = $model->attributes;
        $model->fireModelEvent('retrieved');
        return $model;
    }

    // Direct model pagination

    /**
     * Handle dynamic property access
     *
     * Priority:
     * 1. Eager loaded relations
     * 2. Attribute mutators
     * 3. Relationship methods (lazy loading)
     * 4. Regular attributes
     */
    public function __get(string $key)
    {
        // Allow access to internal properties for testing/debugging
        if (in_array($key, ['attributes', 'exists', 'original'], true)) {
            return $this->$key;
        }

        // Check for eager loaded relation first for performance
        if ($this->relationLoaded($key)) {
            return $this->getRelation($key);
        }

        if (array_key_exists($key, $this->attributes)) {
            return $this->getAttribute($key);
        }

        // Check for attribute mutator
        if ($this->hasGetMutator($key)) {
            return $this->callGetMutator($key);
        }

        // Check if it's a relationship method and lazy load it
        if (method_exists($this, $key)) {
            return $this->loadAndCacheRelation($key);
        }

        // Return regular attribute
        return $this->getAttribute($key);
    }

    // Upsert functionality

    /**
     * Handle dynamic property assignment
     */
    public function __set(string $key, $value)
    {
        // Handle internal attributes
        if (in_array($key, $this->internalAttributes, true)) {
            $this->$key = $value;
            return;
        }

        // Check for attribute accessor
        if ($this->hasSetMutator($key)) {
            $this->callSetMutator($key, $value);
            return;
        }

        $this->setAttribute($key, $value);
    }

    // Update or Insert

    public function relationLoaded(string $relation): bool
    {
        return array_key_exists($relation, $this->eagerLoaded);
    }

    // Increment

    public function getRelation(string $relation)
    {
        return $this->eagerLoaded[$relation] ?? null;
    }

    // Decrement

    /**
     * Load and cache a relation using lazy loading
     */
    protected function loadAndCacheRelation(string $relationName)
    {
        try {
            $relation = $this->$relationName();
            $this->setRelation($relationName, $relation);
            return $relation;
        } catch (\Exception $e) {
            // Return appropriate empty value if relation loading fails
            return $this->getEmptyRelationValue($relationName);
        }
    }

    /**
     * Get empty value for relation based on naming conventions
     */
    protected function getEmptyRelationValue(string $relation)
    {
        // Plural relations typically return collections
        if ($this->isPlural($relation)) {
            return new Collection([]);
        }

        // Singular relations typically return null
        return null;
    }

    /**
     * Check if a relation name suggests a plural relationship
     */
    protected function isPlural(string $name): bool
    {
        return str_ends_with($name, 's') ||
            in_array($name, ['children', 'people', 'data']);
    }

    /**
     * Check if a set mutator exists for an attribute
     */
    protected function hasSetMutator(string $key): bool
    {
        $mutatorMethod = $this->getSetMutatorMethod($key);
        return method_exists($this, $mutatorMethod);
    }

    /**
     * Get the set mutator method name
     */
    protected function getSetMutatorMethod(string $key): string
    {
        return 'set' . str_replace(' ', '', ucwords(str_replace('_', ' ', $key))) . 'Attribute';
    }

    /**
     * Call a set mutator
     */
    protected function callSetMutator(string $key, $value): void
    {
        $mutatorMethod = $this->getSetMutatorMethod($key);
        $this->$mutatorMethod($value);
    }

    public function setExists(bool $exists): self
    {
        $this->exists = $exists;

        return $this;
    }

    public function lockForUpdate()
    {
        return $this;
    }

    protected function getAttributesForSerialization(): array
    {
        $attributes = [];

        foreach ($this->attributes as $key => $value) {
            if ($this->shouldIncludeAttribute($key)) {
                $attributes[$key] = $this->getAttributeForSerialization($key, $value);
            }
        }

        return $attributes;
    }

    protected function methodReturnsRelationshipHandler(string $method): bool
    {
        try {
            $reflection = new \ReflectionMethod($this, $method);
            $returnType = $reflection->getReturnType();

            if ($returnType) {
                $typeName = $returnType->getName();
                return is_subclass_of($typeName, RelationshipHandler::class) ||
                    $typeName === Collection::class ||
                    $typeName === 'mixed' ||
                    strpos($typeName, 'Handler') !== false;
            }

            // Fallback to analyzing method content for relationship calls
            return $this->methodContainsRelationshipCalls($method);
        } catch (\ReflectionException $e) {
            return false;
        }
    }

    protected function methodContainsRelationshipCalls(string $method): bool
    {
        try {
            $reflection = new \ReflectionMethod($this, $method);
            $filename = $reflection->getFileName();
            $startLine = $reflection->getStartLine();
            $endLine = $reflection->getEndLine();

            if ($filename && $startLine && $endLine) {
                $source = implode("", array_slice(file($filename), $startLine - 1, $endLine - $startLine + 1));
                return preg_match('/\$this->(hasOne|hasMany|belongsTo|belongsToMany)\s*\(/', $source);
            }
        } catch (\Exception $e) {
            // Continue with fallback
        }

        return false;
    }

    private function getClassName(?string $class = null): string
    {
        $class = $class ?: static::class;
        return basename(str_replace('\\', '/', $class));
    }

    private function camelToSnake(string $input): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }

    private function hasColumn(string $column): bool
    {
        // This would ideally check the actual table schema
        // For now, return true and let the database handle invalid columns
        return true;
    }

}