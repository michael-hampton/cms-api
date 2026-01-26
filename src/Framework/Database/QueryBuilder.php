<?php

namespace App\Framework\Database;


use App\Framework\Database\Relations\EagerLoader;
use App\Framework\Database\Relations\RelationshipAnalyzer;
use App\Framework\ModelRegistry;
use App\Framework\Support\Collection;
use App\Models\Model;
use BadMethodCallException;
use Closure;
use Exception;
use InvalidArgumentException;

class QueryBuilder
{
    protected $table;
    public $wheres = [];
    protected $orders = [];
    protected $groups = [];
    protected $havings = [];
    protected $limit;
    protected $offset;
    protected $joins = [];
    public $selects = ['*'];
    protected $database;
    protected $bindings = [];
    public $eagerLoad = [];

    private $reservedWords = [
        'order', 'group', 'index', 'key', 'primary', 'unique', 'foreign',
        'references', 'table', 'column', 'database', 'schema', 'select',
        'from', 'where', 'insert', 'update', 'delete', 'drop', 'create',
        'alter', 'limit', 'offset', 'distinct', 'count', 'sum', 'avg',
        'min', 'max', 'join', 'inner', 'left', 'right', 'on', 'as',
        'and', 'or', 'not', 'null', 'is', 'in', 'between', 'like',
        'exists', 'case', 'when', 'then', 'else', 'end', 'union',
        'all', 'any', 'some', 'having', 'desc', 'asc', 'value', 'type', 'interval'
    ];


    public function __construct(string $table, private readonly EagerLoader $relationManager, ?Database $database = null)
    {
        $this->table = $table;
        $this->database = $database ?: Database::getInstance();
    }

    // SELECT methods
    public function select($columns): self
    {
        $this->selects = is_array($columns) ? $columns : func_get_args();
        return $this;
    }

    public function addSelect($columns): self
    {
        $columns = is_array($columns) ? $columns : func_get_args();

        if ($this->selects === ['*']) {
            $this->selects = [];
        }

        $this->selects = array_merge($this->selects, $columns);
        return $this;
    }

    // Add to QueryBuilder.php

    public function countDistinct(string $column): int
    {
        $originalSelects = $this->selects;
        $originalOrders = $this->orders;

        // Use raw SQL for COUNT(DISTINCT column)
        $this->selects = ["COUNT(DISTINCT {$column}) as count"];
        $this->orders = [];

        [$sql, $params] = $this->toSql();
        $stmt = $this->database->query($sql, $params);
        $result = $stmt->fetch();

        $this->selects = $originalSelects;
        $this->orders = $originalOrders;

        return (int)($result['count'] ?? 0);
    }

    public function distinct(): self
    {
        if (!in_array('DISTINCT', $this->selects)) {
            array_unshift($this->selects, 'DISTINCT');
        }
        return $this;
    }

    // WHERE methods
    public function where($column, $operator = null, $value = null): self
    {
        if ($column instanceof Closure) {
            $subQuery = new static($this->table, $this->relationManager, $this->database); // new query builder
            $column($subQuery); // let closure add its wheres
            $this->wheres[] = [
                'type' => 'Nested',
                'query' => $subQuery,
                'boolean' => 'AND',
            ];
            return $this;
        }

        if (is_array($column)) {
            foreach ($column as $key => $val) {
                $this->where($key, '=', $val);
            }
            return $this;
        }

        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'Basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'AND'
        ];

        return $this;
    }

    public function orWhere($column, $operator = null, $value = null): self
    {
        if ($column instanceof Closure) {
            $subQuery = new static($this->table, $this->relationManager, $this->database);
            $column($subQuery); // let closure add its wheres
            $this->wheres[] = [
                'type' => 'Nested',
                'query' => $subQuery,
                'boolean' => 'OR',  // Note: OR instead of AND
            ];
            return $this;
        }

        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'Basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'OR'
        ];

        return $this;
    }

    public function whereLike(string $column, string $value): self
    {
        return $this->where($column, 'LIKE', $value);
    }

    public function whereDate(string $column, $operator, $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'Date',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'AND'
        ];

        return $this;
    }

    public function whereMonth(string $column, $operator, $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'Month',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'AND'
        ];

        return $this;
    }

    public function whereYear(string $column, $operator, $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'Year',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'AND'
        ];

        return $this;
    }

    public function orWhereLike(string $column, string $value): self
    {
        return $this->orWhere($column, 'LIKE', $value);
    }

    public function whereIn(string $column, array $values): self
    {
        if (empty($values)) {
            // If empty array, add impossible condition
            return $this->where('1', '=', '0');
        }

        $this->wheres[] = [
            'type' => 'In',
            'column' => $column,
            'values' => $values,
            'boolean' => 'AND'
        ];

        return $this;
    }

    public function orWhereIn(string $column, array $values): self
    {
        if (empty($values)) {
            return $this;
        }

        $this->wheres[] = [
            'type' => 'In',
            'column' => $column,
            'values' => $values,
            'boolean' => 'OR'
        ];

        return $this;
    }

    public function whereNotIn(string $column, array $values): self
    {
        $this->wheres[] = [
            'type' => 'NotIn',
            'column' => $column,
            'values' => $values,
            'boolean' => 'AND'
        ];

        return $this;
    }

    public function whereBetween(string $column, array $values): self
    {
        if (count($values) !== 2) {
            throw new InvalidArgumentException('Between method requires exactly 2 values');
        }

        $this->wheres[] = [
            'type' => 'Between',
            'column' => $column,
            'values' => $values,
            'boolean' => 'AND'
        ];

        return $this;
    }

    public function whereNotBetween(string $column, array $values): self
    {
        if (count($values) !== 2) {
            throw new InvalidArgumentException('Not between method requires exactly 2 values');
        }

        $this->wheres[] = [
            'type' => 'NotBetween',
            'column' => $column,
            'values' => $values,
            'boolean' => 'AND'
        ];

        return $this;
    }

    public function whereNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'Null',
            'column' => $column,
            'boolean' => 'AND'
        ];

        return $this;
    }

    public function whereNotNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'NotNull',
            'column' => $column,
            'boolean' => 'AND'
        ];

        return $this;
    }

    public function orWhereNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'Null',
            'column' => $column,
            'boolean' => 'OR'
        ];

        return $this;
    }

    public function orWhereNotNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'NotNull',
            'column' => $column,
            'boolean' => 'OR'
        ];

        return $this;
    }

    public function whereExists(QueryBuilder $query): self
    {
        $this->wheres[] = [
            'type' => 'Exists',
            'query' => $query,
            'boolean' => 'AND'
        ];

        return $this;
    }

    public function whereNotExists(QueryBuilder $query): self
    {
        $this->wheres[] = [
            'type' => 'NotExists',
            'query' => $query,
            'boolean' => 'AND'
        ];

        return $this;
    }

    // Subquery WHERE
    public function whereSub(string $column, string $operator, QueryBuilder $query): self
    {
        $this->wheres[] = [
            'type' => 'Sub',
            'column' => $column,
            'operator' => $operator,
            'query' => $query,
            'boolean' => 'AND'
        ];

        return $this;
    }

    // GROUP BY
    public function groupBy(...$columns): self
    {
        foreach ($columns as $column) {
            if (is_array($column)) {
                $this->groups = array_merge($this->groups, $column);
            } else {
                $this->groups[] = $column;
            }
        }
        return $this;
    }

    // HAVING
    public function having(string $column, string $operator, $value): self
    {
        $this->havings[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'AND'
        ];

        return $this;
    }

    public function orHaving(string $column, string $operator, $value): self
    {
        $this->havings[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'OR'
        ];

        return $this;
    }

    // ORDER BY
    public function orderBy($column, string $direction = 'asc'): self
    {
        if (is_array($column)) {
            foreach ($column as $col => $dir) {
                $this->orders[] = ['column' => $col, 'direction' => strtoupper($dir)];
            }
        } else {
            $this->orders[] = ['column' => $column, 'direction' => strtoupper($direction)];
        }
        return $this;
    }

    public function orderByDesc(string $column): self
    {
        return $this->orderBy($column, 'desc');
    }

    public function orderByRaw(string $sql): self
    {
        $this->orders[] = ['raw' => $sql];
        return $this;
    }

    public function latest(string $column = 'created_at'): self
    {
        return $this->orderBy($column, 'desc');
    }

    public function oldest(string $column = 'created_at'): self
    {
        return $this->orderBy($column, 'asc');
    }

    // JOINS
    public function join(string $table, $first, ?string $operator = null, ?string $second = null): self
    {
        return $this->addJoin('INNER', $table, $first, $operator, $second);
    }

    public function leftJoin(string $table, $first, ?string $operator = null, ?string $second = null): self
    {
        return $this->addJoin('LEFT', $table, $first, $operator, $second);
    }

    public function rightJoin(string $table, $first, ?string $operator = null, ?string $second = null): self
    {
        return $this->addJoin('RIGHT', $table, $first, $operator, $second);
    }

    public function crossJoin(string $table): self
    {
        $this->joins[] = [
            'type' => 'CROSS',
            'table' => $table
        ];
        return $this;
    }

    private function addJoin(string $type, string $table, $first, ?string $operator = null, ?string $second = null): self
    {
        if ($operator === null) {
            $operator = '=';
            if ($second === null) {
                $second = $first;
                $first = $this->table . '.id';
            }
        }

        $this->joins[] = [
            'type' => $type,
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second
        ];

        return $this;
    }

    // LIMIT and OFFSET
    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    public function forPage(int $page, int $perPage = 10)
    {
        $page = max($page, 1); // avoid page 0 or negatives

        return $this->skip(($page - 1) * $perPage)->take($perPage);
    }

    public function skip(int $offset): self
    {
        return $this->offset($offset);
    }

    public function take(int $limit): self
    {
        return $this->limit($limit);
    }

    // Pagination
    public function paginate(int $perPage = 15, int $page = 1): array
    {
        $offset = ($page - 1) * $perPage;

        // Get total count
        $totalQuery = clone $this;
        $totalQuery->selects = ['COUNT(*) as count'];
        $totalQuery->orders = [];
        $totalQuery->limit = null;
        $totalQuery->offset = null;

        [$countSql, $countParams] = $totalQuery->toSql();
        $stmt = $this->database->query($countSql, $countParams);
        $total = $stmt->fetch()['count'];

        // Get paginated results
        $this->limit($perPage)->offset($offset);
        $data = $this->get();

        return [
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => (int)$total,
                'last_page' => (int)ceil($total / $perPage),
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $total)
            ]
        ];
    }

    // Eager loading
    public function with(array $relations): self
    {
        $this->eagerLoad = array_merge($this->eagerLoad ?? [], $relations);
        return $this;
    }

    // Query execution methods
    public function get(): Collection
    {
        [$sql, $params] = $this->toSql();

        $stmt = $this->database->query($sql, $params);
        $results = $stmt->fetchAll();

        // Delegate eager loading to EagerLoader
        if (!empty($this->eagerLoad) && !empty($results)) {
            $modelClass = $this->getModelClassFromTable($this->table);
            if ($modelClass) {
                $results = $this->relationManager->loadRelationsForResults($results, $this->eagerLoad, $modelClass);
            }
        }

        // If we have a model class associated with this table, hydrate the results
        $modelClass = $this->getModelClassFromTable($this->table);

        if ($modelClass && !empty($results)) {
            return $this->hydrateModels($results, $modelClass);
        }

        return new Collection($results);
    }

    public function first()
    {
        $this->limit(1);
        $results = $this->get();

        return $results->first() ?? null;
    }

    public function count(): int
    {
        $originalSelects = $this->selects;
        $originalOrders = $this->orders;

        $this->selects = ['COUNT(*) as count'];
        $this->orders = [];

        [$sql, $params] = $this->toSql();
        $stmt = $this->database->query($sql, $params);
        $result = $stmt->fetch();

        $this->selects = $originalSelects;
        $this->orders = $originalOrders;

        return (int)$result['count'];
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    // Aggregate functions
    public function sum(string $column): float
    {
        return $this->aggregate('SUM', $column) ?? 0;
    }

    public function avg(string $column): float
    {
        return $this->aggregate('AVG', $column) ?? 0;
    }

    public function min(string $column)
    {
        return $this->aggregate('MIN', $column);
    }

    public function max(string $column)
    {
        return $this->aggregate('MAX', $column);
    }

    private function aggregate(string $function, string $column)
    {
        $originalSelects = $this->selects;
        $this->selects = ["{$function}({$column}) as aggregate"];

        [$sql, $params] = $this->toSql();
        $stmt = $this->database->query($sql, $params);
        $result = $stmt->fetch();

        $this->selects = $originalSelects;

        return $result['aggregate'];
    }

    public function increment(string $column, int $amount = 1, array $extra = []): int
    {
        $updates = array_merge($extra, [$column => $this->raw("$column + $amount")]);
        return $this->updateQuery($updates);
    }

    public function decrement(string $column, int $amount = 1, array $extra = []): int
    {
        return $this->increment($column, -$amount, $extra);
    }

    /**
     * Update records in the database with the given values.
     *
     * @param array $values
     * @return int The number of affected rows.
     */
    public function update(array $values): int
    {
        return $this->updateQuery($values);
    }

    private function updateQuery(array $values): int
    {
        $setParts = [];
        $bindings = [];
        $paramCounter = 0;

        foreach ($values as $col => $value) {
            if ($value instanceof RawExpression) {
                $setParts[] = $this->quoteColumn($col) . " = {$value->value}";
            } else {
                $paramKey = 'param_' . $paramCounter++;
                $setParts[] = $this->quoteColumn($col) . " = :{$paramKey}";
                $bindings[$paramKey] = $value;
            }
        }

        $sql = "UPDATE " . $this->quoteTable($this->table) . " SET " . implode(', ', $setParts);

        // Add WHERE conditions if any
        if (!empty($this->wheres)) {
            [$whereClause, $whereBindings] = $this->buildWhereClause($this->wheres, $paramCounter);
            $sql .= $whereClause;
            $bindings = array_merge($bindings, $whereBindings);
        }

        $stmt = $this->database->query($sql, $bindings);
        return $stmt->rowCount();
    }


    /**
     * Find by ID and return properly hydrated model
     */
    public function find($id, string $primaryKey = 'id')
    {
        return $this->where($primaryKey, $id)->first();
    }

    /**
     * Hydrate raw results into model instances
     */
    private function hydrateModels(array $results, string $modelClass): Collection
    {
        $models = [];

        foreach ($results as $data) {
            $model = $this->hydrateModel($data, $modelClass);

            $models[] = $model;
        }

        return new Collection($models);;
    }

    /**
     * Hydrate single result into model instance
     */
    private function hydrateModel(array $data, string $modelClass): Model
    {
        // Separate regular attributes from relation data
        $attributes = [];
        $relations = [];

        $tempModel = new $modelClass();

        foreach ($data as $key => $value) {
            // If this key matches a relation that was eager loaded, it's relation data
            if (in_array($key, $this->eagerLoad)) {
                $relations[$key] = $value;
            } else {
                $attributes[$key] = $value;
            }
        }

        // Create model with attributes
        $model = new $modelClass($attributes);
        $model->setExists(true);
        $model->original = $model->attributes;

        // Set relations
        foreach ($relations as $relationName => $relationData) {
            $model->setRelation($relationName, $relationData);
        }

        // Fire retrieved event
        //$model->fireModelEvent('retrieved');

        return $model;
    }

    // SQL Generation
    // Fixed SQL generation with proper quoting
    public function toSql(): array
    {
        $bindings = [];
        $paramCounter = 0;

        // SELECT with proper column quoting
        $selectClause = 'SELECT ';
        if (in_array('DISTINCT', $this->selects)) {
            $selectClause .= 'DISTINCT ';
            $selects = array_filter($this->selects, function ($select) {
                return $select !== 'DISTINCT';
            });
        } else {
            $selects = $this->selects;
        }

        // Quote column names but preserve wildcards and functions
        $quotedSelects = array_map([$this, 'quoteColumn'], $selects);
        $selectClause .= implode(', ', $quotedSelects);

        // FROM with table quoting
        $sql = $selectClause . ' FROM ' . $this->quoteTable($this->table);

        // JOINS with proper quoting
        foreach ($this->joins as $join) {
            if ($join['type'] === 'CROSS') {
                $sql .= " CROSS JOIN " . $this->quoteTable($join['table']);
            } else {
                $firstColumn = $this->quoteColumn($join['first']);
                $secondColumn = $this->quoteColumn($join['second']);
                $sql .= " {$join['type']} JOIN " . $this->quoteTable($join['table']) . " ON {$firstColumn} {$join['operator']} {$secondColumn}";
            }
        }

        // WHERE with proper column quoting
        if (!empty($this->wheres)) {
            $where = $this->buildWhereClause($this->wheres);
            [$whereClause, $bindings] = $where;
            $sql .= $whereClause;
        }

        // GROUP BY with column quoting
        if (!empty($this->groups)) {
            $quotedGroups = array_map([$this, 'quoteColumn'], $this->groups);
            $sql .= ' GROUP BY ' . implode(', ', $quotedGroups);
        }

        // HAVING with column quoting
        if (!empty($this->havings)) {
            $sql .= ' HAVING ';
            $havingStrings = [];

            foreach ($this->havings as $index => $having) {
                $paramKey = 'param_' . $paramCounter++;
                $quotedColumn = $this->quoteColumn($having['column']);
                $havingString = "{$quotedColumn} {$having['operator']} :{$paramKey}";

                if ($index > 0) {
                    $havingString = "{$having['boolean']} {$havingString}";
                }

                $havingStrings[] = $havingString;
                $bindings[$paramKey] = $having['value'];
            }

            $sql .= implode(' ', $havingStrings);
        }

        // ORDER BY with column quoting
        if (!empty($this->orders)) {
            $orderStrings = [];
            foreach ($this->orders as $order) {
                if (isset($order['raw'])) {
                    $orderStrings[] = $order['raw'];
                } else {
                    $quotedColumn = $this->quoteColumn($order['column']);
                    $orderStrings[] = "{$quotedColumn} {$order['direction']}";
                }
            }
            $sql .= ' ORDER BY ' . implode(', ', $orderStrings);
        }

        // LIMIT and OFFSET
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
            if ($this->offset !== null) {
                $sql .= ' OFFSET ' . $this->offset;
            }
        }

        return [$sql, $bindings];
    }

    private function quoteColumn(string $column): string
    {
        if ($column instanceof RawExpression) {
            return $column->value;
        }

        // Don't quote wildcards, functions, or already quoted columns
        if ($column === '*' ||
            strpos($column, '(') !== false ||
            strpos($column, '`') !== false ||
            strpos($column, '.') !== false && strpos(explode('.', $column)[1], '(') !== false) {
            return $column;
        }

        // Handle table.column format
        if (is_string($column) && strpos($column, '.') !== false) {
            [$table, $col] = explode('.', $column, 2);

            if (in_array(strtolower($col), $this->reservedWords)) {
                return "`{$table}`.`{$col}`";
            }

            return "{$table}.{$col}";
        }

        // Quote if it's a reserved word
        if (in_array(strtolower($column), $this->reservedWords)) {
            return "`{$column}`";
        }

        return $column;
    }

    // Helper method to quote table names if needed
    private function quoteTable(string $table): string
    {
        if (in_array(strtolower($table), $this->reservedWords)) {
            return "`{$table}`";
        }
        return $table;
    }

    private function buildWhereClause(array $wheres, int $paramCounter = 0): array
    {
        $sql = ' WHERE ';
        $whereStrings = [];
        $bindings = [];

        foreach ($this->wheres as $index => $where) {
            [$whereClause, $whereBindings] = $this->compileWhere($where, $paramCounter);
            $bindings = array_merge($bindings, $whereBindings);

            if ($index > 0) {
                $whereClause = "{$where['boolean']} {$whereClause}";
            }

            $whereStrings[] = $whereClause;
        }

        $sql .= implode(' ', $whereStrings);

        return [$sql, $bindings];
    }

    private function compileWheres(array $wheres, int &$paramCounter): array
    {
        $sqlParts = [];
        $bindings = [];

        foreach ($wheres as $where) {
            [$fragment, $fragBindings] = $this->compileWhere($where, $paramCounter);

            $prefix = empty($sqlParts) ? '' : ' ' . $where['boolean'] . ' ';
            $sqlParts[] = $prefix . $fragment;

            $bindings = array_merge($bindings, $fragBindings);
        }

        return [implode('', $sqlParts), $bindings];
    }

    private function compileWhere(array $where, int &$paramCounter): array
    {
        $bindings = [];

        switch ($where['type']) {
            case 'Date':
                $paramKey = 'param_' . $paramCounter++;
                $quotedColumn = $this->quoteColumn($where['column']);
                $bindings[$paramKey] = $where['value'];
                // Wrap the column in the DATE() function
                return ["DATE({$quotedColumn}) {$where['operator']} :{$paramKey}", $bindings];
            case 'Month':
                $paramKey = 'param_' . $paramCounter++;
                $quotedColumn = $this->quoteColumn($where['column']);
                $bindings[$paramKey] = $where['value'];
                return ["MONTH({$quotedColumn}) {$where['operator']} :{$paramKey}", $bindings];

            case 'Year':
                $paramKey = 'param_' . $paramCounter++;
                $quotedColumn = $this->quoteColumn($where['column']);
                $bindings[$paramKey] = $where['value'];
                return ["YEAR({$quotedColumn}) {$where['operator']} :{$paramKey}", $bindings];
            case 'Nested':
                [$subSql, $subBindings] = $this->compileWheres($where['query']->wheres, $paramCounter);
                return ['(' . $subSql . ')', $subBindings];
            case 'Raw':
                // For raw SQL, just return as-is with bindings
                return [$where['sql'], $where['bindings'] ?? []];
            case 'Basic':
                $paramKey = 'param_' . $paramCounter++;
                $quotedColumn = $this->quoteColumn($where['column']);
                $bindings[$paramKey] = $where['value'];
                return ["{$quotedColumn} {$where['operator']} :{$paramKey}", $bindings];
            case 'ColumnComparison':
                $firstColumn = $this->quoteColumn($where['first']);
                $secondColumn = $this->quoteColumn($where['second']);
                $operator = $where['operator'];

                return ["{$firstColumn} {$operator} {$secondColumn}", $bindings];


            case 'In':
                $quotedColumn = $this->quoteColumn($where['column']);
                $placeholders = [];
                foreach ($where['values'] as $value) {
                    $paramKey = 'param_' . $paramCounter++;
                    $placeholders[] = ':' . $paramKey;
                    $bindings[$paramKey] = $value;
                }
                return ["{$quotedColumn} IN (" . implode(', ', $placeholders) . ")", $bindings];

            case 'NotIn':
                $quotedColumn = $this->quoteColumn($where['column']);
                $placeholders = [];
                foreach ($where['values'] as $value) {
                    $paramKey = 'param_' . $paramCounter++;
                    $placeholders[] = ':' . $paramKey;
                    $bindings[$paramKey] = $value;
                }
                return ["{$quotedColumn} NOT IN (" . implode(', ', $placeholders) . ")", $bindings];

            case 'Between':
                $quotedColumn = $this->quoteColumn($where['column']);
                $paramKey1 = 'param_' . $paramCounter++;
                $paramKey2 = 'param_' . $paramCounter++;
                $bindings[$paramKey1] = $where['values'][0];
                $bindings[$paramKey2] = $where['values'][1];
                return ["{$quotedColumn} BETWEEN :{$paramKey1} AND :{$paramKey2}", $bindings];

            case 'NotBetween':
                $quotedColumn = $this->quoteColumn($where['column']);
                $paramKey1 = 'param_' . $paramCounter++;
                $paramKey2 = 'param_' . $paramCounter++;
                $bindings[$paramKey1] = $where['values'][0];
                $bindings[$paramKey2] = $where['values'][1];
                return ["{$quotedColumn} NOT BETWEEN :{$paramKey1} AND :{$paramKey2}", $bindings];

            case 'Null':
                $quotedColumn = $this->quoteColumn($where['column']);
                return ["{$quotedColumn} IS NULL", $bindings];

            case 'NotNull':
                $quotedColumn = $this->quoteColumn($where['column']);
                return ["{$quotedColumn} IS NOT NULL", $bindings];

            case 'Exists':
                [$subSql, $subBindings] = $where['query']->toSql();
                return ["EXISTS ({$subSql})", $subBindings];

            case 'NotExists':
                [$subSql, $subBindings] = $where['query']->toSql();
                return ["NOT EXISTS ({$subSql})", $subBindings];

            case 'Sub':
                $quotedColumn = $this->quoteColumn($where['column']);
                [$subSql, $subBindings] = $where['query']->toSql();
                return ["{$quotedColumn} {$where['operator']} ({$subSql})", $subBindings];
            case 'JsonContains':
                $paramKey = 'param_' . $paramCounter++;
                $quotedColumn = $this->quoteColumn($where['column']);
                $bindings[$paramKey] = $where['value'];
                return ["JSON_CONTAINS ({$quotedColumn}, :{$paramKey})", $bindings];
                break;

            default:
                throw new Exception("Unknown where type: {$where['type']}");
        }
    }

    private function getModelClassFromTable(string $table): ?string
    {
        return ModelRegistry::getModelForTable($table);
    }

    function raw(string $expression): RawExpression
    {
        return new RawExpression($expression);
    }

    public function chunk(int $count, callable $callback): bool
    {
        $page = 1;

        do {
            $results = $this->limit($count)->offset(($page - 1) * $count)->get();

            if (empty($results)) {
                break;
            }

            if ($callback($results, $page) === false) {
                return false;
            }

            $page++;
        } while (count($results) == $count);

        return true;
    }

    public function delete(): int
    {
        $bindings = [];
        $paramCounter = 0;

        $sql = "DELETE FROM " . $this->quoteTable($this->table);

        // Add WHERE conditions if any
        if (!empty($this->wheres)) {
            [$whereClause, $whereBindings] = $this->buildWhereClause($this->wheres);
            $sql .= $whereClause;
            $bindings = array_merge($bindings, $whereBindings);
        }

        $stmt = $this->database->query($sql, $bindings);
        return $stmt->rowCount();
    }

    public function insert(array $values): int
    {
        $columns = array_keys($values);
        $quotedColumns = array_map([$this, 'quoteColumn'], $columns);

        $placeholders = [];
        $bindings = [];
        $paramCounter = 0;

        foreach ($values as $value) {
            $paramKey = 'param_' . $paramCounter++;
            $placeholders[] = ":{$paramKey}";
            $bindings[$paramKey] = $value;
        }

        $sql = "INSERT INTO " . $this->quoteTable($this->table) .
            " (" . implode(', ', $quotedColumns) . ") VALUES (" . implode(', ', $placeholders) . ")";

        $stmt = $this->database->query($sql, $bindings);
        return $this->database->lastInsertId();
    }

    public function insertMany(array $records): int
    {
        if (empty($records)) {
            return 0;
        }

        $columns = array_keys($records[0]);
        $quotedColumns = array_map([$this, 'quoteColumn'], $columns);

        $sql = "INSERT INTO " . $this->quoteTable($this->table) . " (" . implode(', ', $quotedColumns) . ") VALUES ";

        $valueParts = [];
        $bindings = [];
        $paramCounter = 0;

        foreach ($records as $record) {
            $placeholders = [];
            foreach ($columns as $column) {
                $paramKey = 'param_' . $paramCounter++;
                $placeholders[] = ":{$paramKey}";
                $bindings[$paramKey] = $record[$column] ?? null;
            }
            $valueParts[] = '(' . implode(', ', $placeholders) . ')';
        }

        $sql .= implode(', ', $valueParts);

        $stmt = $this->database->query($sql, $bindings);
        return $stmt->rowCount();
    }

    public function when($condition, callable $callback, ?callable $default = null): self
    {
        if ($condition) {
            $callback($this, $condition);
        } elseif ($default) {
            $default($this, $condition);
        }

        return $this;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function pluck(string $column, ?string $key = null): array
    {
        // If column has a dot, alias it so PDO result keeps that name
        if (str_contains($column, '.')) {
            $alias = str_replace('.', '_', $column); // e.g. categories.id → categories_id
            $this->selects = ["$column AS $alias"];
            $column = $alias;
        } else {
            $this->selects = [$column];
        }

        if ($key) {
            if (str_contains($key, '.')) {
                $alias = str_replace('.', '_', $key);
                $this->selects[] = "$key AS $alias";
                $key = $alias;
            } else {
                $this->selects[] = $key;
            }
        }

        [$sql, $params] = $this->toSql();
        $stmt = $this->database->query($sql, $params);
        $results = $stmt->fetchAll();

        if ($key) {
            $plucked = [];
            foreach ($results as $result) {
                $plucked[$result[$key]] = $result[$column];
            }
            return $plucked;
        }

        return array_column($results, $column);
    }


    public function value(string $column)
    {
        $this->selects = [$column];
        $this->limit(1);

        [$sql, $params] = $this->toSql();
        $stmt = $this->database->query($sql, $params);
        $result = $stmt->fetch();

        return $result ? $result[$column] : null;
    }

    public function whereRaw(string $sql, array $bindings = []): self
    {
        $this->wheres[] = [
            'type' => 'Raw',
            'sql' => $sql,
            'bindings' => $bindings,
            'boolean' => 'AND'
        ];

        return $this;
    }

    public function orWhereRaw(string $sql, array $bindings = []): self
    {
        $this->wheres[] = [
            'type' => 'Raw',
            'sql' => $sql,
            'bindings' => $bindings,
            'boolean' => 'OR'
        ];

        return $this;
    }

    /**
     * Add subquery to count related models
     */
    public function withCount($relations, ?callable $callback = null): self
    {
        if (is_string($relations)) {
            $relations = [$relations => $callback];
        }

        if (is_array($relations) && !is_callable(reset($relations))) {
            // Convert simple array to callback array
            $formatted = [];
            foreach ($relations as $key => $value) {
                if (is_numeric($key)) {
                    // Handle ['blocks', 'categories'] format - use value as relation name
                    $formatted[$value] = null;
                } else {
                    // Handle ['blocks' => callback] format - use key as relation name
                    $formatted[$key] = $value;
                }
            }
            $relations = $formatted;
        }

        foreach ($relations as $relation => $callback) {
            if (empty($relation)) {
                throw new InvalidArgumentException('Relation name cannot be empty');
            }
            $this->addWithCountToMainQuery($relation, $callback);
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

    public function orWhereDoesntHave(string $relation, ?callable $callback = null): self
    {
        return $this->has($relation, '<', 1, 'or', $callback);
    }

    /**
     * Add constraints based on related model count
     */
    public function has(string $relation, string $operator = '>=', int $count = 1, string $boolean = 'and', ?callable $callback = null): self
    {
        $modelClass = $this->getModelClassFromTable($this->table);
        if (!$modelClass) {
            throw new \Exception("No model class found for table: {$this->table}");
        }

        $tempModel = new $modelClass();
        if (!method_exists($tempModel, $relation)) {
            throw new BadMethodCallException("Relationship {$relation} does not exist on {$modelClass}");
        }

        // Get relation data by analyzing the method
        $analyzer = new RelationshipAnalyzer();
        $relationData = $analyzer->analyzeRelationshipMethod($tempModel, $relation);

        $this->addHasConstraintToMainQuery($relationData, $operator, $count, $boolean, $callback);

        return $this;
    }

    /**
     * Build count subquery for main query
     */
    protected function buildCountSubqueryForMainQuery(array $relationData, ?callable $callback): string
    {
        $relatedModel = $relationData['related'];
        $relatedInstance = new $relatedModel();
        $relatedTable = $relatedInstance->getTable();

        switch ($relationData['type']) {
            case 'hasMany':
            case 'hasOne':
                $foreignKey = $relationData['foreign_key'];
                $localKey = $relationData['local_key'];

                $subquery = "SELECT COUNT(*) FROM {$relatedTable} WHERE {$relatedTable}.{$foreignKey} = {$this->table}.{$localKey}";

                if ($callback) {
                    $tempQuery = new QueryBuilder($relatedTable, $this->relationManager, $this->database);
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

            case 'belongsTo':
                $foreignKey = $relationData['foreign_key'];
                $ownerKey = $relationData['owner_key'];

                $subquery = "SELECT COUNT(*) FROM {$relatedTable} WHERE {$relatedTable}.{$ownerKey} = {$this->table}.{$foreignKey}";

                if ($callback) {
                    $tempQuery = new QueryBuilder($relatedTable, $this->relationManager, $this->database);
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

            case 'belongsToMany':
                $pivotTable = $relationData['pivot_table'];
                $foreignKey = $relationData['foreign_key'];
                $relatedKey = $relationData['related_key'];

                $subquery = "SELECT COUNT(*) FROM {$relatedTable} 
                        INNER JOIN {$pivotTable} ON {$relatedTable}.id = {$pivotTable}.{$relatedKey} 
                        WHERE {$pivotTable}.{$foreignKey} = {$this->table}.id";

                if ($callback) {
                    $tempQuery = new QueryBuilder($relatedTable, $this->relationManager, $this->database);
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

            default:
                throw new BadMethodCallException("Unknown relation type: {$relationData['type']}");
        }
    }

    /**
     * Add has constraint to main query
     */
    protected function addHasConstraintToMainQuery(array $relationData, string $operator, int $count, string $boolean, ?callable $callback): void
    {
        $useExists = $count === 1 && in_array($operator, ['>=', '>', '<', '<=']);

        $subqueryData = $this->buildSubqueryWithBindings($relationData, $callback, $useExists);

        if ($useExists) {
            $constraint = in_array($operator, ['>=', '>'])
                ? "EXISTS ({$subqueryData['sql']})"
                : "NOT EXISTS ({$subqueryData['sql']})";
        } else {
            $countSql = str_replace('SELECT 1', 'SELECT COUNT(*)', $subqueryData['sql']);
            $countSql = str_replace('LIMIT 1', '', $countSql);
            $constraint = "({$countSql}) {$operator} {$count}";
        }

        if ($boolean === 'and') {
            $this->whereRaw($constraint, $subqueryData['bindings']);
        } else {
            $this->orWhereRaw($constraint, $subqueryData['bindings']);
        }
    }


    /**
     * Add withCount subquery to main query
     */
    // In QueryBuilder, update the addWithCountToMainQuery method:

    protected function addWithCountToMainQuery(string $relation, ?callable $callback): void
    {
        $modelClass = $this->getModelClassFromTable($this->table);

        if (!$modelClass) {
            throw new \Exception("No model class found for table: {$this->table}");
        }

        $parentModel = new $modelClass(); // This should be Page

        if (!method_exists($parentModel, $relation)) {
            throw new BadMethodCallException("Relationship {$relation} does not exist on {$modelClass}");
        }

        // Analyze the relationship on the parent model (Page)
        $analyzer = new RelationshipAnalyzer();
        $relationData = $analyzer->analyzeRelationshipMethod($parentModel, $relation);

        $countColumn = "{$relation}_count";

        // Pass the parent model class to ensure we're using the right context
        $subqueryData = $this->buildSubqueryWithBindings($relationData, $callback, false, $modelClass);

        // Convert EXISTS query to COUNT query
        $countSql = str_replace('SELECT 1 FROM', 'SELECT COUNT(*) FROM', $subqueryData['sql']);
        $countSql = str_replace(' LIMIT 1', '', $countSql);

        // Add the subquery as a select
        if ($this->selects === ['*']) {
            $this->selects = ["{$this->table}.*"];
        }

        $this->addSelect(["({$countSql}) as {$countColumn}"]);
    }

    /**
     * Build subquery with proper parameter binding
     */
    protected function buildSubqueryWithBindings(array $relationData, ?callable $callback, bool $isExists = true): array
    {
        $relatedModel = $relationData['related'];
        $relatedInstance = new $relatedModel();
        $relatedTable = "`{$relatedInstance->getTable()}`";

        $subquery = '';
        $bindings = [];

        switch ($relationData['type']) {
            case 'hasMany':
            case 'hasOne':
                $foreignKey = $relationData['foreign_key'];
                $localKey = $relationData['local_key'];

                $selectClause = $isExists ? 'SELECT 1' : 'SELECT COUNT(*)';
                $subquery = "{$selectClause} FROM {$relatedTable} WHERE {$relatedTable}.{$foreignKey} = {$this->table}.{$localKey}";

                break;

            case 'belongsTo':
                $foreignKey = $relationData['foreign_key'];
                $ownerKey = $relationData['owner_key'];

                $selectClause = $isExists ? 'SELECT 1' : 'SELECT COUNT(*)';
                $subquery = "{$selectClause} FROM {$relatedTable} WHERE {$relatedTable}.{$ownerKey} = {$this->table}.{$foreignKey}";

                break;

            case 'belongsToMany':
                $pivotTable = $relationData['pivot_table'];
                $foreignKey = $relationData['foreign_key'];
                $relatedKey = $relationData['related_key'];

                $selectClause = $isExists ? 'SELECT 1' : 'SELECT COUNT(*)';
                $subquery = "{$selectClause} FROM {$relatedTable} 
                        INNER JOIN {$pivotTable} ON {$relatedTable}.id = {$pivotTable}.{$relatedKey} 
                        WHERE {$pivotTable}.{$foreignKey} = {$this->table}.id";


                break;

            default:
                throw new BadMethodCallException("Unknown relation type: {$relationData['type']}");
        }

        // Apply callback filters (if any)
        if ($callback) {
            $relatedModel = $relationData['related']; // This is Block
            $relatedInstance = new $relatedModel();   // Create Block instance
            $relatedTable = "`{$relatedInstance->getTable()}`";

            $tempQuery = new QueryBuilder($relatedTable, $this->relationManager, $this->database);
            $callback($tempQuery); // Apply callback constraints to Block query

            $conditions = $this->buildConditionsFromQuery($tempQuery, $bindings);
            if (!empty($conditions)) {
                $subquery .= " AND " . implode(' AND ', $conditions);
            }
        }

        if ($isExists) {
            $subquery .= " LIMIT 1";
        }

        return ['sql' => $subquery, 'bindings' => $bindings];
    }

    /**
     * Build conditions from query with proper binding
     */
    protected function buildConditionsFromQuery(QueryBuilder $query, array &$bindings): array
    {
        if (empty($query->wheres)) {
            return [];
        }

        $conditions = [];

        foreach ($query->wheres as $where) {
            switch ($where['type']) {
                case 'Basic':
                    // Get the table name from the query
                    $table = $query->getTable();
                    $column = $where['column'];

                    // Add table prefix if not already present
                    if (strpos($column, '.') === false) {
                        $column = "{$table}.{$column}";
                    }

                    $column = $this->quoteColumn($column);
                    $value = $where['value'];

                    // Use literal values to avoid binding conflicts for now
                    if (is_string($value)) {
                        $escapedValue = str_replace("'", "''", $value); // Basic SQL escaping
                        $conditions[] = "{$column} {$where['operator']} '{$escapedValue}'";
                    } elseif (is_numeric($value)) {
                        $conditions[] = "{$column} {$where['operator']} {$value}";
                    } elseif (is_null($value)) {
                        $conditions[] = "{$column} IS NULL";
                    } elseif (is_bool($value)) {
                        $conditions[] = "{$column} {$where['operator']} " . ($value ? '1' : '0');
                    } else {
                        $conditions[] = "{$column} {$where['operator']} '{$value}'";
                    }
                    break;

                case 'In':
                    $table = $query->getTable();
                    $column = $where['column'];

                    if (strpos($column, '.') === false) {
                        $column = "{$table}.{$column}";
                    }

                    $column = $this->quoteColumn($column);
                    $values = array_map(function ($v) {
                        if (is_string($v)) {
                            return "'" . str_replace("'", "''", $v) . "'";
                        }
                        return $v;
                    }, $where['values']);
                    $conditions[] = "{$column} IN (" . implode(', ', $values) . ")";
                    break;

                case 'NotIn':
                    $table = $query->getTable();
                    $column = $where['column'];

                    if (strpos($column, '.') === false) {
                        $column = "{$table}.{$column}";
                    }

                    $column = $this->quoteColumn($column);
                    $values = array_map(function ($v) {
                        if (is_string($v)) {
                            return "'" . str_replace("'", "''", $v) . "'";
                        }
                        return $v;
                    }, $where['values']);
                    $conditions[] = "{$column} NOT IN (" . implode(', ', $values) . ")";
                    break;

                case 'Null':
                    $table = $query->getTable();
                    $column = $where['column'];

                    if (strpos($column, '.') === false) {
                        $column = "{$table}.{$column}";
                    }

                    $column = $this->quoteColumn($column);
                    $conditions[] = "{$column} IS NULL";
                    break;

                case 'NotNull':
                    $table = $query->getTable();
                    $column = $where['column'];

                    if (strpos($column, '.') === false) {
                        $column = "{$table}.{$column}";
                    }

                    $column = $this->quoteColumn($column);
                    $conditions[] = "{$column} IS NOT NULL";
                    break;
            }
        }

        return $conditions;
    }

    public function whereColumn(string $first, string $operatorOrSecond, ?string $second = null): self
    {
        if ($second === null) {
            $second = $operatorOrSecond;
            $operatorOrSecond = '=';
        }

        $this->wheres[] = [
            'type' => 'ColumnComparison',
            'first' => $first,
            'operator' => $operatorOrSecond,
            'second' => $second,
        ];

        return $this;
    }

    public function whereJsonContains(string $column, $value): self
    {
        // Normalize value into JSON format
        // Arrays become JSON arrays, scalars become JSON strings
        $jsonValue = is_array($value)
            ? json_encode($value)
            : json_encode([$value]);

        $this->wheres[] = [
            'type' => 'JsonContains',
            'column' => $column,
            'value' => $jsonValue,
        ];

        return $this;
    }

    public function selectRaw(string $expression): self
    {
        $this->selects[] = new RawExpression($expression);
        return $this;
    }

}