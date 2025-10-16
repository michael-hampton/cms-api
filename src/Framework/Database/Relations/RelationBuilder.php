<?php

namespace App\Framework\Database\Relations;

use App\Framework\Database\QueryBuilder;
use App\Framework\Support\Collection;
use App\Models\Model;
use BadMethodCallException;

class RelationBuilder implements RelationBuilderInterface
{
    protected QueryBuilder $query;
    protected RelationshipHandler $handler;
    protected Model $parent;
    protected array $relationData;

    protected array $pivotWheres = [];
    protected array $pivotColumns = [];

    public function __construct(
        RelationshipHandler $handler,
        Model $parent,
        array $relationData
    ) {
        $this->handler = $handler;
        $this->parent = $parent;
        $this->relationData = $relationData;

        // Create the base query for the related model
        $relatedModel = $relationData['related'];
        $relatedInstance = new $relatedModel();
        $this->query = $relatedInstance->newQuery();

        // Apply base relationship constraints
        $this->addRelationshipConstraints();
    }

    /**
     * Apply the base constraints for the relationship
     */
    protected function addRelationshipConstraints(): void
    {
        switch ($this->relationData['type']) {
            case 'hasOne':
            case 'hasMany':
                $localKeyValue = $this->parent->getAttribute($this->relationData['local_key']);
                if ($localKeyValue) {
                    $this->query->where($this->relationData['foreign_key'], $localKeyValue);
                } else {
                    // If no local key value, make impossible query
                    $this->query->where('1', '=', '0');
                }
                break;

            case 'belongsTo':
                $foreignKeyValue = $this->parent->getAttribute($this->relationData['foreign_key']);
                if ($foreignKeyValue) {
                    $this->query->where($this->relationData['owner_key'], $foreignKeyValue);
                } else {
                    $this->query->where('1', '=', '0');
                }
                break;

            case 'belongsToMany':
                $parentId = $this->parent->getAttribute('id');
                if ($parentId) {
                    $this->addBelongsToManyConstraints($parentId);
                } else {
                    $this->query->where('1', '=', '0');
                }
                break;
        }
    }

    /**
     * Add constraints for belongsToMany relationships
     */
    protected function addBelongsToManyConstraints(int $parentId): void
    {
        $pivotTable = $this->relationData['pivot_table'];
        $foreignKey = $this->relationData['foreign_key'];
        $relatedKey = $this->relationData['related_key'];

        // Get the related model table name
        $relatedModel = $this->relationData['related'];
        $relatedInstance = new $relatedModel();
        $relatedTable = $relatedInstance->getTable();

        $this->query->join($pivotTable, "{$relatedTable}.id", '=', "{$pivotTable}.{$relatedKey}")
            ->where("{$pivotTable}.{$foreignKey}", $parentId);
    }

    /**
     * Forward method calls to the underlying QueryBuilder
     */
    public function __call(string $method, array $arguments)
    {
        // Methods that should return the result directly (not chainable)
        $terminalMethods = [
            'get', 'first', 'find', 'count', 'exists', 'sum', 'avg', 'min', 'max',
            'pluck', 'value', 'chunk', 'paginate'
        ];

        if (in_array($method, $terminalMethods)) {
            return $this->handleTerminalMethod($method, $arguments);
        }

        // Methods specific to belongsToMany relationships
        $pivotMethods = ['attach', 'detach', 'sync', 'toggle', 'updateExistingPivot'];
        if (in_array($method, $pivotMethods)) {
            return $this->handlePivotMethod($method, $arguments);
        }

        // Check if method exists on QueryBuilder
        if (method_exists($this->query, $method)) {
            $result = $this->query->$method(...$arguments);

            // If QueryBuilder returns itself, return this RelationBuilder for chaining
            if ($result === $this->query) {
                return $this;
            }

            return $result;
        }

        throw new BadMethodCallException(
            "Method {$method} does not exist on " . static::class . " or QueryBuilder"
        );
    }

    /**
     * Handle terminal methods that execute the query
     */
    protected function handleTerminalMethod(string $method, array $arguments)
    {
        switch ($method) {
            case 'get':
                return $this->get();

            case 'first':
                return $this->first();

            case 'find':
                return $this->query->find(...$arguments);

            case 'count':
            case 'exists':
            case 'sum':
            case 'avg':
            case 'min':
            case 'max':
                return $this->query->$method(...$arguments);

            case 'pluck':
                $results = $this->query->pluck(...$arguments);
                return $results;

            case 'chunk':
                return $this->query->chunk(...$arguments);

            case 'paginate':
                return $this->query->paginate(...$arguments);

            default:
                return $this->query->$method(...$arguments);
        }
    }

    /**
     * Handle pivot-specific methods for belongsToMany
     */
    protected function handlePivotMethod(string $method, array $arguments)
    {
        if ($this->relationData['type'] !== 'belongsToMany') {
            throw new BadMethodCallException(
                "Method {$method} is only available for belongsToMany relationships"
            );
        }

        // Ensure handler has context
        $handler = clone $this->handler;
        $handler->setContext($this->parent, $this->relationData);

        return $handler->$method(...$arguments);
    }

    /**
     * Execute the query and return results
     */
    public function get(): Collection
    {
        $results = $this->query->get();

        // For belongsToMany, we need to handle pivot data differently
        if ($this->relationData['type'] === 'belongsToMany') {
            return $this->handleBelongsToManyResults($results);
        }

        // For other relationship types, wrap in Collection
        if ($this->relationData['type'] === 'hasMany') {
            return $results;
        }

        return $results;
    }

    /**
     * Get the first result
     */
    public function first(): ?Model
    {
        $this->query->limit(1);
        $results = $this->get();
        return $results->first();
    }

    /**
     * Handle belongsToMany results with pivot data
     */
    protected function handleBelongsToManyResults(Collection $results): Collection
    {
        // If results are already model instances, return them as-is
        if ($results->isEmpty() && is_object($results->first())) {
            return $results;
        }

        // If results are arrays, filter out pivot columns
        $pivotTable = $this->relationData['pivot_table'];
        $cleanResults = [];

        foreach ($results as $result) {
            if (is_array($result)) {
                // Filter out pivot columns
                $modelData = [];
                foreach ($result as $key => $value) {
                    // Skip pivot table columns (they might be prefixed)
                    if (!str_starts_with($key, $pivotTable . '_') &&
                        !str_starts_with($key, $pivotTable . '.')) {
                        $modelData[$key] = $value;
                    }
                }
                $cleanResults[] = $modelData;
            } else {
                // If it's already an object, keep it
                $cleanResults[] = $result;
            }
        }

        return new Collection($cleanResults);
    }

    /**
     * Add eager loading constraints
     */
    public function with(array $relations): self
    {
        $this->query->with($relations);
        return $this;
    }

    /**
     * Add a subquery to count related models
     */
    public function withCount($relations, ?callable $callback = null): self
    {
        if (is_string($relations)) {
            $relations = [$relations => $callback];
        }

        if (is_array($relations) && !is_callable(reset($relations))) {
            // Convert simple array to callback array
            $formatted = [];
            foreach ($relations as $relation) {
                $formatted[$relation] = null;
            }
            $relations = $formatted;
        }

        foreach ($relations as $relation => $callback) {
            $this->addWithCountSubquery($relation, $callback);
        }

        return $this;
    }

    /**
     * Add constraints for existence of related models
     */
    public function whereHas(string $relation, ?callable $callback = null): self
    {
        return $this->has($relation, '>=', 1, 'and', $callback);
    }

    /**
     * Add constraints for non-existence of related models
     */
    public function whereDoesntHave(string $relation, ?callable $callback = null): self
    {
        return $this->has($relation, '<', 1, 'and', $callback);
    }

    /**
     * Add constraints based on related model count
     */
    public function has(string $relation, string $operator = '>=', int $count = 1, string $boolean = 'and', ?callable $callback = null): self
    {
        $relatedModel = $this->getRelatedModelClass();
        $tempModel = new $relatedModel();

        var_dump($relatedModel);
        die;

        if (!method_exists($tempModel, $relation)) {
            throw new BadMethodCallException("Relationship {$relation} does not exist on {$relatedModel}");
        }

        // Get relation data by analyzing the method
        $analyzer = new RelationshipAnalyzer();
        $relationData = $analyzer->analyzeRelationshipMethod($tempModel, $relation);

        $this->addHasConstraint($relationData, $operator, $count, $boolean, $callback);

        return $this;
    }

    /**
     * Add the actual withCount subquery
     */
    protected function addWithCountSubquery(string $relation, ?callable $callback): void
    {
        $relatedModel = $this->getRelatedModelClass();
        $tempModel = new $relatedModel();

        if (!method_exists($tempModel, $relation)) {
            throw new BadMethodCallException("Relationship {$relation} does not exist on {$relatedModel}");
        }

        $analyzer = new RelationshipAnalyzer();
        $relationData = $analyzer->analyzeRelationshipMethod($tempModel, $relation);

        $countColumn = "{$relation}_count";
        $subquery = $this->buildCountSubquery($relationData, $callback);

        // Add the subquery as a select
        $currentSelects = $this->query->selects;
        if ($currentSelects === ['*']) {
            $relatedModelInstance = new ($this->relationData['related'])();
            $table = $relatedModelInstance->getTable();
            $this->query->select([$table . '.*']);
        }

        $this->query->addSelect(["({$subquery}) as {$countColumn}"]);
    }

    /**
     * Build the count subquery SQL
     */
    protected function buildCountSubquery(array $relationData, ?callable $callback): string
    {
        $relatedModel = $relationData['related'];
        $relatedInstance = new $relatedModel();
        $relatedTable = $relatedInstance->getTable();

        switch ($relationData['type']) {
            case 'hasMany':
            case 'hasOne':
                return $this->buildHasManyCountSubquery($relationData, $relatedTable, $callback);

            case 'belongsTo':
                return $this->buildBelongsToCountSubquery($relationData, $relatedTable, $callback);

            case 'belongsToMany':
                return $this->buildBelongsToManyCountSubquery($relationData, $relatedTable, $callback);

            default:
                throw new BadMethodCallException("Unknown relation type: {$relationData['type']}");
        }
    }

    /**
     * Build hasMany/hasOne count subquery
     */
    protected function buildHasManyCountSubquery(array $relationData, string $relatedTable, ?callable $callback): string
    {
        $parentTable = $this->getParentTable();
        $foreignKey = $relationData['foreign_key'];
        $localKey = $relationData['local_key'];

        $subquery = "SELECT COUNT(*) FROM {$relatedTable} WHERE {$relatedTable}.{$foreignKey} = {$parentTable}.{$localKey}";

        if ($callback) {
            // Create a temporary query builder for the callback
            $tempQuery = new QueryBuilder($relatedTable, $this->handler->eagerLoader, $this->handler->database);
            $callback($tempQuery);

            [$sql, $bindings] = $tempQuery->toSql();
            // Extract WHERE conditions from the callback query
            if (strpos($sql, 'WHERE') !== false) {
                $whereClause = substr($sql, strpos($sql, 'WHERE') + 5);
                $whereClause = preg_replace('/ORDER BY.*$/i', '', $whereClause);
                $whereClause = preg_replace('/LIMIT.*$/i', '', $whereClause);
                $subquery .= " AND " . trim($whereClause);
            }
        }

        return $subquery;
    }

    /**
     * Build belongsTo count subquery
     */
    protected function buildBelongsToCountSubquery(array $relationData, string $relatedTable, ?callable $callback): string
    {
        $parentTable = $this->getParentTable();
        $foreignKey = $relationData['foreign_key'];
        $ownerKey = $relationData['owner_key'];

        $subquery = "SELECT COUNT(*) FROM {$relatedTable} WHERE {$relatedTable}.{$ownerKey} = {$parentTable}.{$foreignKey}";

        if ($callback) {
            $tempQuery = new QueryBuilder($relatedTable, $this->handler->eagerLoader, $this->handler->database);
            $callback($tempQuery);

            [$sql, $bindings] = $tempQuery->toSql();
            if (strpos($sql, 'WHERE') !== false) {
                $whereClause = substr($sql, strpos($sql, 'WHERE') + 5);
                $whereClause = preg_replace('/ORDER BY.*$/i', '', $whereClause);
                $whereClause = preg_replace('/LIMIT.*$/i', '', $whereClause);
                $subquery .= " AND " . trim($whereClause);
            }
        }

        return $subquery;
    }

    /**
     * Build belongsToMany count subquery
     */
    protected function buildBelongsToManyCountSubquery(array $relationData, string $relatedTable, ?callable $callback): string
    {
        $parentTable = $this->getParentTable();
        $pivotTable = $relationData['pivot_table'];
        $foreignKey = $relationData['foreign_key'];
        $relatedKey = $relationData['related_key'];

        $subquery = "SELECT COUNT(*) FROM {$relatedTable} 
                    INNER JOIN {$pivotTable} ON {$relatedTable}.id = {$pivotTable}.{$relatedKey} 
                    WHERE {$pivotTable}.{$foreignKey} = {$parentTable}.id";

        if ($callback) {
            $tempQuery = new QueryBuilder($relatedTable, $this->handler->eagerLoader, $this->handler->database);
            $callback($tempQuery);

            [$sql, $bindings] = $tempQuery->toSql();
            if (strpos($sql, 'WHERE') !== false) {
                $whereClause = substr($sql, strpos($sql, 'WHERE') + 5);
                $whereClause = preg_replace('/ORDER BY.*$/i', '', $whereClause);
                $whereClause = preg_replace('/LIMIT.*$/i', '', $whereClause);
                $subquery .= " AND " . trim($whereClause);
            }
        }

        return $subquery;
    }

    /**
     * Add has constraint for whereHas/whereDoesntHave
     */
    protected function addHasConstraint(array $relationData, string $operator, int $count, string $boolean, ?callable $callback): void
    {
        $relatedModel = $relationData['related'];
        $relatedInstance = new $relatedModel();
        $relatedTable = $relatedInstance->getTable();

        $existsQuery = $this->buildExistsSubquery($relationData, $relatedTable, $callback);

        if ($operator === '>=' && $count === 1) {
            $constraint = "EXISTS ({$existsQuery})";
        } elseif ($operator === '<' && $count === 1) {
            $constraint = "NOT EXISTS ({$existsQuery})";
        } else {
            $countQuery = $this->buildCountSubquery($relationData, $callback);
            $constraint = "({$countQuery}) {$operator} {$count}";
        }

        if ($boolean === 'and') {
            $this->query->whereRaw($constraint);
        } else {
            $this->query->orWhereRaw($constraint);
        }
    }

    /**
     * Build exists subquery for whereHas
     */
    protected function buildExistsSubquery(array $relationData, string $relatedTable, ?callable $callback): string
    {
        switch ($relationData['type']) {
            case 'hasMany':
            case 'hasOne':
                return $this->buildHasManyExistsSubquery($relationData, $relatedTable, $callback);

            case 'belongsTo':
                return $this->buildBelongsToExistsSubquery($relationData, $relatedTable, $callback);

            case 'belongsToMany':
                return $this->buildBelongsToManyExistsSubquery($relationData, $relatedTable, $callback);

            default:
                throw new BadMethodCallException("Unknown relation type: {$relationData['type']}");
        }
    }

    /**
     * Build hasMany exists subquery
     */
    protected function buildHasManyExistsSubquery(array $relationData, string $relatedTable, ?callable $callback): string
    {
        $parentTable = $this->getParentTable();
        $foreignKey = $relationData['foreign_key'];
        $localKey = $relationData['local_key'];

        $subquery = "SELECT 1 FROM {$relatedTable} WHERE {$relatedTable}.{$foreignKey} = {$parentTable}.{$localKey}";

        if ($callback) {
            $tempQuery = new QueryBuilder($relatedTable, $this->handler->eagerLoader, $this->handler->database);
            $callback($tempQuery);

            [$sql, $bindings] = $tempQuery->toSql();
            if (strpos($sql, 'WHERE') !== false) {
                $whereClause = substr($sql, strpos($sql, 'WHERE') + 5);
                $whereClause = preg_replace('/ORDER BY.*$/i', '', $whereClause);
                $whereClause = preg_replace('/LIMIT.*$/i', '', $whereClause);
                $subquery .= " AND " . trim($whereClause);
            }
        }

        return $subquery . " LIMIT 1";
    }

    /**
     * Build belongsTo exists subquery
     */
    protected function buildBelongsToExistsSubquery(array $relationData, string $relatedTable, ?callable $callback): string
    {
        $parentTable = $this->getParentTable();
        $foreignKey = $relationData['foreign_key'];
        $ownerKey = $relationData['owner_key'];

        $subquery = "SELECT 1 FROM {$relatedTable} WHERE {$relatedTable}.{$ownerKey} = {$parentTable}.{$foreignKey}";

        if ($callback) {
            $tempQuery = new QueryBuilder($relatedTable, $this->handler->eagerLoader, $this->handler->database);
            $callback($tempQuery);

            [$sql, $bindings] = $tempQuery->toSql();
            if (strpos($sql, 'WHERE') !== false) {
                $whereClause = substr($sql, strpos($sql, 'WHERE') + 5);
                $whereClause = preg_replace('/ORDER BY.*$/i', '', $whereClause);
                $whereClause = preg_replace('/LIMIT.*$/i', '', $whereClause);
                $subquery .= " AND " . trim($whereClause);
            }
        }

        return $subquery . " LIMIT 1";
    }

    /**
     * Build belongsToMany exists subquery
     */
    protected function buildBelongsToManyExistsSubquery(array $relationData, string $relatedTable, ?callable $callback): string
    {
        $parentTable = $this->getParentTable();
        $pivotTable = $relationData['pivot_table'];
        $foreignKey = $relationData['foreign_key'];
        $relatedKey = $relationData['related_key'];

        $subquery = "SELECT 1 FROM {$relatedTable} 
                    INNER JOIN {$pivotTable} ON {$relatedTable}.id = {$pivotTable}.{$relatedKey} 
                    WHERE {$pivotTable}.{$foreignKey} = {$parentTable}.id";

        if ($callback) {
            $tempQuery = new QueryBuilder($relatedTable, $this->handler->eagerLoader, $this->handler->database);
            $callback($tempQuery);

            [$sql, $bindings] = $tempQuery->toSql();
            if (strpos($sql, 'WHERE') !== false) {
                $whereClause = substr($sql, strpos($sql, 'WHERE') + 5);
                $whereClause = preg_replace('/ORDER BY.*$/i', '', $whereClause);
                $whereClause = preg_replace('/LIMIT.*$/i', '', $whereClause);
                $subquery .= " AND " . trim($whereClause);
            }
        }

        return $subquery . " LIMIT 1";
    }

    /**
     * Get the related model class
     */
    protected function getRelatedModelClass(): string
    {
        return $this->relationData['related'];
    }

    /**
     * Get the parent table name
     */
    protected function getParentTable(): string
    {
        return $this->parent->getTable();
    }

    /**
     * Get the underlying QueryBuilder
     */
    public function getQuery(): QueryBuilder
    {
        return $this->query;
    }

    /**
     * Get the table name for the query
     */
    public function getTable(): string
    {
        return $this->query->getTable();
    }

    /**
     * Get the relationship handler
     */
    public function getHandler(): RelationshipHandler
    {
        return $this->handler;
    }

    /**
     * Get the parent model
     */
    public function getParent(): Model
    {
        return $this->parent;
    }

    /**
     * Get relation data
     */
    public function getRelationData(): array
    {
        return $this->relationData;
    }

    /**
     * Clone the relation builder
     */
    public function __clone()
    {
        $this->query = clone $this->query;
        $this->handler = clone $this->handler;
    }

    public function toSql(): array
    {
        return $this->query->toSql();
    }

    public function dd(): void
    {
        [$sql, $bindings] = $this->toSql();
        var_dump([
            'sql' => $sql,
            'bindings' => $bindings,
            'relation_type' => $this->relationData['type'],
            'parent_id' => $this->parent->getAttribute('id')
        ]);
        exit;
    }

    public function dump(): self
    {
        [$sql, $bindings] = $this->toSql();
        var_dump([
            'sql' => $sql,
            'bindings' => $bindings,
            'relation_type' => $this->relationData['type']
        ]);
        return $this;
    }

    /**
     * Specify which pivot columns to include in the results
     */
    public function withPivot(...$columns): self
    {
        if ($this->relationData['type'] !== 'belongsToMany') {
            throw new BadMethodCallException(
                "withPivot() is only available for belongsToMany relationships"
            );
        }

        $columns = is_array($columns[0]) ? $columns[0] : $columns;
        $this->pivotColumns = array_merge($this->pivotColumns, $columns);

        // Pass to handler if it supports it
        if (method_exists($this->handler, 'withPivot')) {
            $this->handler->withPivot($columns);
        }

        return $this;
    }

    /**
     * Add a where clause on the pivot table
     */
    public function wherePivot(string $column, $operator = null, $value = null): self
    {
        if ($this->relationData['type'] !== 'belongsToMany') {
            throw new BadMethodCallException(
                "wherePivot() is only available for belongsToMany relationships"
            );
        }

        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $pivotTable = $this->relationData['pivot_table'];
        $this->query->where("{$pivotTable}.{$column}", $operator, $value);

        return $this;
    }

    /**
     * Add an OR where clause on the pivot table
     */
    public function orWherePivot(string $column, $operator = null, $value = null): self
    {
        if ($this->relationData['type'] !== 'belongsToMany') {
            throw new BadMethodCallException(
                "orWherePivot() is only available for belongsToMany relationships"
            );
        }

        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $pivotTable = $this->relationData['pivot_table'];
        $this->query->orWhere("{$pivotTable}.{$column}", $operator, $value);

        return $this;
    }

    /**
     * Add a whereIn clause on the pivot table
     */
    public function wherePivotIn(string $column, array $values): self
    {
        if ($this->relationData['type'] !== 'belongsToMany') {
            throw new BadMethodCallException(
                "wherePivotIn() is only available for belongsToMany relationships"
            );
        }

        $pivotTable = $this->relationData['pivot_table'];
        $this->query->whereIn("{$pivotTable}.{$column}", $values);

        return $this;
    }

    /**
     * Add a whereNotIn clause on the pivot table
     */
    public function wherePivotNotIn(string $column, array $values): self
    {
        if ($this->relationData['type'] !== 'belongsToMany') {
            throw new BadMethodCallException(
                "wherePivotNotIn() is only available for belongsToMany relationships"
            );
        }

        $pivotTable = $this->relationData['pivot_table'];
        $this->query->whereNotIn("{$pivotTable}.{$column}", $values);

        return $this;
    }

    /**
     * Add a whereNull clause on the pivot table
     */
    public function wherePivotNull(string $column): self
    {
        if ($this->relationData['type'] !== 'belongsToMany') {
            throw new BadMethodCallException(
                "wherePivotNull() is only available for belongsToMany relationships"
            );
        }

        $pivotTable = $this->relationData['pivot_table'];
        $this->query->whereNull("{$pivotTable}.{$column}");

        return $this;
    }

    /**
     * Add a whereNotNull clause on the pivot table
     */
    public function wherePivotNotNull(string $column): self
    {
        if ($this->relationData['type'] !== 'belongsToMany') {
            throw new BadMethodCallException(
                "wherePivotNotNull() is only available for belongsToMany relationships"
            );
        }

        $pivotTable = $this->relationData['pivot_table'];
        $this->query->whereNotNull("{$pivotTable}.{$column}");

        return $this;
    }
}