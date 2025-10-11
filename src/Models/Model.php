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
use DateTime;

abstract class Model
{
    use Serializable, RelationshipBuilder;

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
    protected $relations = [];
    protected $with = [];
    protected $eagerLoaded = [];
    protected static $observers = [];
    protected static $globalEvents = [];
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

    protected array $internalAttributes = ['exists', 'original'];

    private ?EagerLoader $relationManager = null;

    public function __construct(array $attributes = [], $database = null)
    {
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
        if (str_ends_with($key, '_count')) {
            return true;
        }

        if (!empty($this->fillable)) {
            return in_array($key, $this->fillable);
        }

        return !in_array($key, $this->guarded);
    }

    public function getAttribute(string $key)
    {
        // Check if it's a relationship
        if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
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

    public function setAttribute(string $key, $value): void
    {
        // Check if there's an accessor
        $accessorMethod = 'set' . str_replace(' ', '', ucwords(str_replace('_', ' ', $key))) . 'Attribute';
        if (method_exists($this, $accessorMethod)) {
            $this->$accessorMethod($value);
            return;
        }

        // Handle array/json casting - convert arrays to JSON strings for storage
        if (isset($this->casts[$key]) && in_array($this->casts[$key], ['array', 'json'])) {
            if (is_array($value)) {
                $this->attributes[$key] = json_encode($value);
                return;
            }
        }

        $this->attributes[$key] = $value;
    }

    public function save(): bool
    {
        // Fire saving event
        if ($this->fireModelEvent('saving') === false) {
            return false;
        }

        if ($this->timestamps) {
            $now = date($this->dateFormat);
            if (!$this->exists) {
                $this->setAttribute('created_at', $now);
            }
            $this->setAttribute('updated_at', $now);
        }

        if ($this->exists) {
            if ($this->fireModelEvent('updating') === false) {
                return false;
            }

            $result = $this->performUpdate();

            if ($result) {
                $this->fireModelEvent('updated');
                $this->fireModelEvent('saved');
            }

            return $result;
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

    public function update(array $attributes): bool
    {
        // Update model's attributes
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }

        return $this->performUpdate();
    }

    public function fresh(): ?self
    {
        $id = $this->getAttribute($this->primaryKey);
        if (!$id) {
            return null;
        }

        return static::find($id);
    }

    protected function performUpdate(): bool
    {
        $primaryKeyValue = $this->getAttribute($this->primaryKey);

        if (!$primaryKeyValue) {
            return false;
        }

        $affectedRows = $this->database->update(
            $this->table,
            $this->attributes,
            [$this->primaryKey => $primaryKeyValue]
        );

        if ($affectedRows > 0) {
            $this->original = $this->attributes;
            return true;
        }

        return false;
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

    public function forceDelete(): bool
    {
        if (!$this->exists) {
            return false;
        }

        return $this->performHardDelete();
    }

    protected function usesSoftDeletes(): bool
    {
        return in_array('deleted_at', $this->fillable) ||
            method_exists($this, 'bootSoftDeletes');
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

// Add scope for soft deletes
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

    // Override where to exclude soft deleted by default
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

    public static function find(int $id): ?self
    {
        $instance = new static();
        $result = $instance->newQuery()->find($id);

        if (!$result) {
            return null;
        }

        if (!$result instanceof Model) {
            return $instance->hydrateFromArray($result);
        }

        return $result;
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

    public static function with(array $relations): QueryBuilder
    {
        $instance = new static();
        return $instance->newQuery()->with($relations);
    }

    public static function withRelations(array $relations): QueryBuilder
    {
        return static::with($relations);
    }

    public function load(array $relations): self
    {
        foreach ($relations as $relation) {
            if (method_exists($this, $relation)) {
                $this->eagerLoaded[$relation] = $this->$relation();
            }
        }
        return $this;
    }

    private function getClassName(?string $class = null): string
    {
        $class = $class ?: static::class;
        return basename(str_replace('\\', '/', $class));
    }

    // Automatic relation loading for serialization
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

    protected function getDatabaseAttributes(): array
    {
        return array_diff_key(
            $this->attributes,
            array_flip($this->internalAttributes)
        );
    }

    public function attributesToArray(): array
    {
        $attributes = $this->getDatabaseAttributes();

        // Automatically include loaded relations
        foreach ($this->eagerLoaded as $relation => $data) {
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


    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    protected function class_basename(string $class): string
    {
        $class = is_object($class) ? get_class($class) : $class;
        return basename(str_replace('\\', '/', $class));
    }

    public static function scope(string $name, callable $callback): void
    {
        static::$scopes[$name] = $callback;
    }

    public function scopeWhere(QueryBuilder $query, ...$args): QueryBuilder
    {
        return $query->where(...$args);
    }

    private function hydrateFromArray(array $data): self
    {
        $model = new static($data);
        $model->exists = true;
        $model->original = $model->attributes;
        $model->fireModelEvent('retrieved');
        return $model;
    }

    // Override serialization methods from trait to respect model configuration
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

    protected function shouldIncludeAttribute(string $key): bool
    {
        if (!empty($this->visible)) {
            return in_array($key, $this->visible);
        }

        return !in_array($key, $this->hidden);
    }

    /**
     * Check if a method is a relationship method
     */
    protected function isRelationshipMethod(string $method): bool
    {
        return method_exists($this, $method);
        //&& $this->methodReturnsRelationshipHandler($method);
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
        $query = $this->newQuery();
        return $this->$scopeMethod($query, ...$arguments);
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
     * Call a scope method statically
     */
    protected function callStaticScope(string $method, array $arguments)
    {
        $scopeMethod = 'scope' . ucfirst($method);
        $query = $this->newQuery();
        return $this->$scopeMethod($query, ...$arguments);
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

    public function hydrate(array $data): self
    {
        $model = new static($data);
        $model->exists = true;
        $model->original = $model->attributes;
        $model->fireModelEvent('retrieved');
        return $model;
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

    public static function created(callable $callback): void
    {
        static::registerModelEvent('created', $callback);
    }

    public static function updating(callable $callback): void
    {
        static::registerModelEvent('updating', $callback);
    }

    public static function updated(callable $callback): void
    {
        static::registerModelEvent('updated', $callback);
    }

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
        $rawValue = $this->attributes[$key] ?? null;
        return $this->castAttribute($key, $this->$mutatorMethod($rawValue));
    }

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
        // Check for eager loaded relation first for performance
        if ($this->relationLoaded($key)) {
            return $this->getRelation($key);
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

    // Direct model pagination
    public static function paginate(int $perPage = 15, int $page = 1): array
    {
        $instance = new static();
        $query = $instance->newQuery();
        return $query->paginate($perPage, $page);
    }

    // Upsert functionality
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

    // Update or Insert
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

    // Increment
    public function increment(string $column, int $amount = 1): bool
    {
        $currentValue = $this->getAttribute($column) ?: 0;
        $this->setAttribute($column, $currentValue + $amount);
        return $this->save();
    }

    // Decrement
    public function decrement(string $column, int $amount = 1): bool
    {
        return $this->increment($column, -$amount);
    }

    public static function query(): QueryBuilder
    {
        $instance = new static();
        return (new QueryBuilder($instance->table, $instance->relationManager, $instance->database));
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

    public function setRelation(string $relation, $value): self
    {
        $this->eagerLoaded[$relation] = $value;
        return $this;
    }

    public function getRelation(string $relation)
    {
        return $this->eagerLoaded[$relation] ?? null;
    }

    public function relationLoaded(string $relation): bool
    {
        return array_key_exists($relation, $this->eagerLoaded);
    }

    /**
     * @return mixed
     */
    public function getTable()
    {
        return $this->table;
    }

    public function setExists(bool $exists): self
    {
        $this->exists = $exists;

        return $this;
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

}