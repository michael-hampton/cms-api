<?php

namespace App\Framework\Database;


use App\Framework\Database\Relations\EagerLoader;
use App\Framework\Database\Relations\RelationshipAnalyzer;
use App\Framework\Macro\HasMacros;
use App\Framework\ModelRegistry;
use App\Framework\Support\Collection;
use App\Models\Model;
use BadMethodCallException;
use Closure;
use Exception;
use InvalidArgumentException;

class QueryBuilder
{
    use HasMacros;

    private string $paramPrefix;
    private int $paramCounter = 0;
    public $wheres = [];
    public $selects = ['*'];
    public $eagerLoad = [];
    protected $table;
    protected $orders = [];
    protected $groups = [];
    protected $havings = [];
    protected $limit;
    protected $offset;
    protected $joins = [];
    protected $database;
    protected $bindings = [];
    private $reservedWords = [
        'order', 'group', 'index', 'key', 'primary', 'unique', 'foreign',
        'references', 'table', 'column', 'database', 'schema', 'select',
        'from', 'where', 'insert', 'update', 'delete', 'drop', 'create',
        'alter', 'limit', 'offset', 'distinct', 'count', 'sum', 'avg',
        'min', 'max', 'join', 'inner', 'left', 'right', 'on', 'as',
        'and', 'or', 'not', 'null', 'is', 'in', 'between', 'like',
        'exists', 'case', 'when', 'then', 'else', 'end', 'union',
        'all', 'any', 'some', 'having', 'desc', 'asc', 'value', 'type', 'interval', 'rank'
    ];


    public function __construct(string $table, private readonly EagerLoader $relationManager, ?Database $database = null)
    {
        $this->table = $table;
        $this->database = $database ?: Database::getInstance();
        $this->paramPrefix = spl_object_id($this);
    }

    // SELECT methods
    public function select($columns): self
    {
        $columns = is_array($columns) ? $columns : func_get_args();

        // Preserve DISTINCT if already set
        if (in_array('DISTINCT', $this->selects)) {
            $this->selects = array_merge(['DISTINCT'], $columns);
        } else {
            $this->selects = $columns;
        }

        return $this;
    }

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

    // Add to QueryBuilder.php

    public function toSql(): array
    {
        $bindings = [];

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
            $groupParts = [];

            foreach ($this->groups as $group) {
                if (is_array($group) && isset($group['raw'])) {
                    $groupParts[] = $group['raw'];
                } else {
                    $groupParts[] = $this->quoteColumn($group);
                }
            }

            $sql .= ' GROUP BY ' . implode(', ', $groupParts);
        }

        // HAVING with column quoting
        if (!empty($this->havings)) {
            $sql .= ' HAVING ';
            $havingStrings = [];

            foreach ($this->havings as $index => $having) {
                $havingString = '';

                if (isset($having['type']) && $having['type'] === 'Raw') {
                    $havingString = $having['sql'];
                    // Note: Bindings were already merged in havingRaw()
                    // and will be handled by the database query call
                } else {
                    $paramKey = $this->paramPrefix . '_param_' . $this->paramCounter++;
                    $quotedColumn = $this->quoteColumn($having['column']);
                    $havingString = "{$quotedColumn} {$having['operator']} :{$paramKey}";
                    $bindings[$paramKey] = $having['value'];
                }

                if ($index > 0) {
                    $havingString = "{$having['boolean']} {$havingString}";
                }

                $havingStrings[] = $havingString;
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

    private function quoteTable(string $table): string
    {
        if (in_array(strtolower($table), $this->reservedWords)) {
            return "`{$table}`";
        }
        return $table;
    }

    // WHERE methods

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

    private function compileWhere(array $where, int &$paramCounter): array
    {
        $bindings = [];

        switch ($where['type']) {
            case 'Date':
                $paramKey = $this->paramPrefix . '_param_' . $this->paramCounter++;
                $quotedColumn = $this->quoteColumn($where['column']);
                $bindings[$paramKey] = $where['value'];
                // Wrap the column in the DATE() function
                return ["DATE({$quotedColumn}) {$where['operator']} :{$paramKey}", $bindings];
            case 'Month':
                $paramKey = $this->paramPrefix . '_param_' . $this->paramCounter++;
                $quotedColumn = $this->quoteColumn($where['column']);
                $bindings[$paramKey] = $where['value'];
                return ["MONTH({$quotedColumn}) {$where['operator']} :{$paramKey}", $bindings];

            case 'Year':
                $paramKey = $this->paramPrefix . '_param_' . $this->paramCounter++;
                $quotedColumn = $this->quoteColumn($where['column']);
                $bindings[$paramKey] = $where['value'];
                return ["YEAR({$quotedColumn}) {$where['operator']} :{$paramKey}", $bindings];
            case 'Nested':
                [$subSql, $subBindings] = $this->compileWheres($where['query']->wheres, $paramCounter);
                return ['(' . $subSql . ')', $subBindings];
            case 'NotBasic':

                $quotedColumn = $this->quoteColumn($where['column']);
                $bindings = [];

                if (is_null($where['value'])) {
                    return ["{$quotedColumn} IS NOT NULL", $bindings];
                }

                $uniqueId = bin2hex(random_bytes(2));
                $paramKey = "p_{$uniqueId}";

                $bindings[$paramKey] = is_bool($where['value'])
                    ? ($where['value'] ? 1 : 0)
                    : $where['value'];

                return [
                    "NOT ({$quotedColumn} {$where['operator']} :{$paramKey})",
                    $bindings
                ];
            case 'Raw':
                // For raw SQL, just return as-is with bindings
                return [$where['sql'], $where['bindings'] ?? []];
            case 'Basic':
                $paramKey = $this->paramPrefix . '_param_' . $this->paramCounter++;
                $quotedColumn = $this->quoteColumn($where['column']);
                $bindings[$paramKey] = $where['value'];
                return ["{$quotedColumn} {$where['operator']} :{$paramKey}", $bindings];
            case 'ColumnComparison':
                $firstColumn = $this->quoteColumn($where['first']);
                $secondColumn = $this->quoteColumn($where['second']);
                $operator = $where['operator'];

                // Match Basic case: return array with SQL fragment and bindings
                return ["{$firstColumn} {$operator} {$secondColumn}", $bindings];


            case 'In':
                $quotedColumn = $this->quoteColumn($where['column']);

                // 🔑 Fix: Use the pre-compiled fragment and bindings from whereIn()
                if (isset($where['sql_fragment'])) {
                    foreach ($where['bindings'] as $key => $val) {
                        $bindings[$key] = $val;
                    }
                    return ["{$quotedColumn} IN ({$where['sql_fragment']})", $bindings];
                }

                // Fallback for older code using 'values'
                $placeholders = [];
                foreach (($where['values'] ?? []) as $value) {
                    $paramKey = $this->paramPrefix . '_param_' . $this->paramCounter++;
                    $placeholders[] = ':' . $paramKey;
                    $bindings[$paramKey] = $value;
                }
                return ["{$quotedColumn} IN (" . implode(', ', $placeholders) . ")", $bindings];

            case 'NotIn':
                $quotedColumn = $this->quoteColumn($where['column']);

                // 🔑 Fix: Use the pre-compiled fragment and bindings from whereNotIn()
                if (isset($where['sql_fragment'])) {
                    foreach ($where['bindings'] as $key => $val) {
                        $bindings[$key] = $val;
                    }
                    return ["{$quotedColumn} NOT IN ({$where['sql_fragment']})", $bindings];
                }

                $placeholders = [];
                foreach (($where['values'] ?? []) as $value) {
                    $paramKey = $this->paramPrefix . '_param_' . $this->paramCounter++;
                    $placeholders[] = ':' . $paramKey;
                    $bindings[$paramKey] = $value;
                }
                return ["{$quotedColumn} NOT IN (" . implode(', ', $placeholders) . ")", $bindings];

            case 'Between':
                $quotedColumn = $this->quoteColumn($where['column']);
                $paramKey1 = $this->paramPrefix . '_param_' . $this->paramCounter++;
                $paramKey2 = $this->paramPrefix . '_param_' . $this->paramCounter++;
                $bindings[$paramKey1] = $where['values'][0];
                $bindings[$paramKey2] = $where['values'][1];
                return ["{$quotedColumn} BETWEEN :{$paramKey1} AND :{$paramKey2}", $bindings];

            case 'NotBetween':
                $quotedColumn = $this->quoteColumn($where['column']);
                $paramKey1 = $this->paramPrefix . '_param_' . $this->paramCounter++;
                $paramKey2 = $this->paramPrefix . '_param_' . $this->paramCounter++;
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
                $paramKey = $this->paramPrefix . '_param_' . $this->paramCounter++;
                $quotedColumn = $this->quoteColumn($where['column']);
                $bindings[$paramKey] = $where['value'];
                return ["JSON_CONTAINS ({$quotedColumn}, :{$paramKey})", $bindings];
                break;

            default:
                throw new Exception("Unknown where type: {$where['type']}");
        }
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

    public function distinct(): self
    {
        if (!in_array('DISTINCT', $this->selects)) {
            array_unshift($this->selects, 'DISTINCT');
        }
        return $this;
    }

    public function whereLike(string $column, string $value): self
    {
        return $this->where($column, 'LIKE', $value);
    }

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

    public function orWhereIn(string $column, array $values): self
    {
        return $this->whereIn($column, $values, 'or');
    }

    public function whereIn(string $column, array|Collection|callable $values, string $boolean = 'and'): self
    {
        if (is_callable($values)) {
            $subQuery = new self($this->table, $this->relationManager);

            $values($subQuery);

            [$sql, $bindings] = $subQuery->toSql();

            $this->wheres[] = [
                'type' => 'In',
                'column' => $column,
                'sql_fragment' => "({$sql})",
                'bindings' => $bindings, // <- important change
                'boolean' => $boolean,
            ];

            return $this;
        }


        if ($values instanceof Collection) {
            $values = $values->toArray();
        }

        // 🔑 Fix: Handle empty arrays to prevent "IN ()" syntax error
        if (empty($values)) {
            // If we want to match nothing, we use a condition that is always false
            return $this->whereRaw('1=0', [], $boolean);
        }

        $placeholders = [];
        $localBindings = [];
        foreach ($values as $value) {
            $uniqueId = bin2hex(random_bytes(2));
            // Ensure index is unique even within this specific In call
            $paramName = "in_" . $uniqueId . "_" . count($this->bindings) . count($localBindings);

            $placeholders[] = ":{$paramName}";
            $localBindings[$paramName] = $value;
        }

        $this->wheres[] = [
            'type' => 'In',
            'column' => $column,
            'sql_fragment' => implode(', ', $placeholders),
            'bindings' => $localBindings,
            'boolean' => $boolean
        ];

        // Merge into master bindings
        $this->bindings = array_merge($this->bindings, $localBindings);

        return $this;
    }

    public function table(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    public function bindings(): array
    {
        return $this->bindings;
    }

    public function whereRaw(string $sql, array $bindings = [], string $boolean = 'and'): self
    {
        $this->wheres[] = [
            'type' => 'Raw',
            'sql' => $sql,
            'bindings' => $bindings,
            'boolean' => $boolean
        ];

        foreach ($bindings as $key => $value) {
            if (is_string($key)) {
                // Preserve named parameters exactly as they appear in SQL
                $this->bindings[$key] = $value;
            } else {
                $this->bindings[] = $value;
            }
        }

        return $this;
    }

    public function whereNotIn(string $column, array $values, string $boolean = 'and'): self
    {
        // 🔑 Fix: If we exclude "nothing", the condition is always true.
        // We can just skip adding the where clause entirely.
        if (empty($values)) {
            return $this;
        }

        $placeholders = [];
        $localBindings = [];
        foreach ($values as $value) {
            $uniqueId = bin2hex(random_bytes(2));
            $paramName = "notin_" . $uniqueId . "_" . count($this->bindings) . count($localBindings);

            $placeholders[] = ":{$paramName}";
            $localBindings[$paramName] = $value;
        }

        $this->wheres[] = [
            'type' => 'NotIn',
            'column' => $column,
            'sql_fragment' => implode(', ', $placeholders),
            'bindings' => $localBindings,
            'boolean' => $boolean
        ];

        $this->bindings = array_merge($this->bindings, $localBindings);

        return $this;
    }

    public function whereNot(string $column, $operator = null, $value = null, string $boolean = 'AND'): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'type' => 'NotBasic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => $boolean,
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

    // Subquery WHERE

    public function whereNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'Null',
            'column' => $column,
            'boolean' => 'AND'
        ];

        return $this;
    }

    // GROUP BY

    public function whereNotNull(string $column): self
    {
        $this->wheres[] = [
            'type' => 'NotNull',
            'column' => $column,
            'boolean' => 'AND'
        ];

        return $this;
    }

    // HAVING

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

    // ORDER BY

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

    // JOINS

    public function orderByDesc(string $column): self
    {
        return $this->orderBy($column, 'desc');
    }

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

    // LIMIT and OFFSET

    public function from(?string $alias = null)
    {
        $this->table = $alias
            ? "{$this->table} as {$alias}"
            : $this->table;

        return $this;
    }

    public function join(string $table, $first, ?string $operator = null, ?string $second = null): self
    {
        return $this->addJoin('INNER', $table, $first, $operator, $second);
    }

    private function addJoin(string $type, string $table, $first, ?string $operator = null, ?string $second = null): self
    {
        // Handle two-argument form: join('posts', 'posts.user_id')
        if ($operator === null && $second === null) {
            $operator = '=';
            $second = $first;
            $first = $this->table . '.id';
        } // Handle three-argument form: join('posts', 'users.id', 'posts.user_id')
        elseif ($second === null) {
            $second = $operator;
            $operator = '=';
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

    public function leftJoin(string $table, $first, ?string $operator = null, ?string $second = null): self
    {
        return $this->addJoin('LEFT', $table, $first, $operator, $second);
    }

    public function rightJoin(string $table, $first, ?string $operator = null, ?string $second = null): self
    {
        return $this->addJoin('RIGHT', $table, $first, $operator, $second);
    }

    // Pagination

    public function crossJoin(string $table): self
    {
        $this->joins[] = [
            'type' => 'CROSS',
            'table' => $table
        ];
        return $this;
    }

    // Eager loading

    public function forPage(int $page, int $perPage = 10)
    {
        $page = max($page, 1); // avoid page 0 or negatives

        return $this->skip(($page - 1) * $perPage)->take($perPage);
    }

    // Query execution methods

    public function take(int $limit): self
    {
        return $this->limit($limit);
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function skip(int $offset): self
    {
        return $this->offset($offset);
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    // Aggregate functions

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
                'to' => min($offset + $perPage, $total),
                'total_pages' => (int)ceil($total / $perPage),
                'has_more' => $page < (int)ceil($total / $perPage)
            ]
        ];
    }

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

    private function getModelClassFromTable(string $table): ?string
    {
        return ModelRegistry::getModelForTable($table);
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

        return new Collection($models);
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

    public function with(array|string $relations): self
    {
        if (is_string($relations)) {
            $relations = [$relations];
        }

        $this->eagerLoad = array_merge($this->eagerLoad ?? [], $relations);
        return $this;
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function count(): int
    {
        $originalSelects = $this->selects;
        $originalOrders = $this->orders;

        // default
        $countSql = 'COUNT(*) as count';

        // if DISTINCT + specific column
        if (
            in_array('DISTINCT', $this->selects, true) &&
            count($this->selects) === 2 &&
            $this->selects[1] !== '*'
        ) {
            $column = $this->selects[1];
            $countSql = "COUNT(DISTINCT {$column}) as count";
        }

        $this->selects = [$countSql];
        $this->orders = [];

        [$sql, $params] = $this->toSql();

        $stmt = $this->database->query($sql, $params);
        $result = $stmt->fetch();

        $this->selects = $originalSelects;
        $this->orders = $originalOrders;

        return (int)$result['count'];
    }

    public function sum(string $column): float
    {
        return $this->aggregate('SUM', $column) ?? 0;
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

    public function avg(string $column): float
    {
        return $this->aggregate('AVG', $column) ?? 0;
    }

    public function min(string $column)
    {
        return $this->aggregate('MIN', $column);
    }

    // SQL Generation
    // Fixed SQL generation with proper quoting

    public function max(string $column)
    {
        return $this->aggregate('MAX', $column);
    }

    public function decrement(string $column, int $amount = 1, array $extra = []): int
    {
        return $this->increment($column, -$amount, $extra);
    }

    public function increment(string $column, int $amount = 1, array $extra = []): int
    {
        $updates = array_merge($extra, [$column => $this->raw("$column + $amount")]);
        return $this->updateQuery($updates);
    }

    function raw(string $expression): RawExpression
    {
        return new RawExpression($expression);
    }

    // Helper method to quote table names if needed

    private function updateQuery(array $values): int
    {
        $setParts = [];
        $bindings = [];
        $paramCounter = 0;

        foreach ($values as $col => $value) {
            if ($value instanceof RawExpression) {
                $setParts[] = $this->quoteColumn($col) . " = {$value->value}";
            } else {

                if (is_bool($value)) $value = (int)($value == true);

                $paramKey = $this->paramPrefix . '_param_' . $this->paramCounter++;
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
     * Update records in the database with the given values.
     *
     * @param array $values
     * @return int The number of affected rows.
     */
    public function update(array $values): int
    {
        return $this->updateQuery($values);
    }

    /**
     * Find by ID and return properly hydrated model
     */
    public function find($id, string $primaryKey = 'id')
    {
        return $this->where($primaryKey, $id)->first();
    }

    public function first()
    {
        $this->limit(1);
        $results = $this->get();

        return $results->first() ?? null;
    }

    public function orHavingRaw(string $sql, array $bindings = []): self
    {
        return $this->havingRaw($sql, $bindings, 'OR');
    }

    public function havingRaw(string $sql, array $bindings = [], string $boolean = 'AND'): self
    {
        $this->havings[] = [
            'type' => 'Raw',
            'sql' => $sql,
            'bindings' => $bindings,
            'boolean' => $boolean
        ];

        // Merge bindings into the main pool
        foreach ($bindings as $key => $value) {
            if (is_string($key)) {
                $this->bindings[$key] = $value;
            } else {
                $this->bindings[] = $value;
            }
        }

        return $this;
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

    public function chunkById(int $count, callable $callback, string $column = 'id'): bool
    {
        $lastId = 0;

        do {
            $results = $this->where($column, '>', $lastId)
                ->orderBy($column)
                ->limit($count)
                ->get();

            if ($results->isEmpty()) {
                break;
            }

            $lastId = $results->last()->{$column};

            if ($callback($results) === false) {
                return false;
            }

        } while ($results->count() === $count);

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
        // Detect bulk insert
        $isBulk = isset($values[0]) && is_array($values[0]);

        if (!$isBulk) {
            $values = [$values];
        }

        $columns = array_keys($values[0]);
        $quotedColumns = array_map([$this, 'quoteColumn'], $columns);

        $placeholders = [];
        $bindings = [];
        $paramCounter = 0;

        foreach ($values as $row) {
            $rowPlaceholders = [];

            foreach ($columns as $column) {
                $paramKey = $this->paramPrefix . '_param_' . $this->paramCounter++;
                $rowPlaceholders[] = ":{$paramKey}";
                $bindings[$paramKey] = $row[$column];
            }

            $placeholders[] = "(" . implode(', ', $rowPlaceholders) . ")";
        }

        $sql = "INSERT INTO " . $this->quoteTable($this->table) .
            " (" . implode(', ', $quotedColumns) . ") VALUES " .
            implode(', ', $placeholders);

        $this->database->query($sql, $bindings);

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
                $paramKey = $this->paramPrefix . '_param_' . $this->paramCounter++;
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

        $bindings = [];
        $selectClause = $isExists ? 'SELECT 1' : 'SELECT COUNT(*)';

        // 1. Build the base relationship join
        switch ($relationData['type']) {
            case 'hasMany':
            case 'hasOne':
            $subquery = "{$selectClause} FROM {$relatedTable} WHERE {$relatedTable}.{$relationData['foreign_key']} = {$this->table}.{$relationData['local_key']}";
                break;
            case 'belongsTo':
                $subquery = "{$selectClause} FROM {$relatedTable} WHERE {$relatedTable}.{$relationData['owner_key']} = {$this->table}.{$relationData['foreign_key']}";
                break;
            case 'belongsToMany':
                $subquery = "{$selectClause} FROM {$relatedTable} 
                    INNER JOIN {$relationData['pivot_table']} ON {$relatedTable}.id = {$relationData['pivot_table']}.{$relationData['related_key']} 
                    WHERE {$relationData['pivot_table']}.{$relationData['foreign_key']} = {$this->table}.id";
                break;
            case 'morphMany':
            case 'morphOne':
                $parentClass = $this->getModelClassFromTable($this->table);
                $subquery = "{$selectClause} FROM {$relatedTable} WHERE {$relatedTable}.{$relationData['morph_type']} = '{$parentClass}' AND {$relatedTable}.{$relationData['morph_id']} = {$this->table}.{$relationData['local_key']}";
                break;

            case 'morphTo':
                // MorphTo is more complex - skip for now in whereHas
                throw new BadMethodCallException("whereHas is not supported for morphTo relationships");
            default:
                throw new BadMethodCallException("Unknown relation: {$relationData['type']}");
        }

        // 2. Apply callback filters safely
        if ($callback) {
            $tempQuery = new QueryBuilder($relatedInstance->getTable(), $this->relationManager, $this->database);
            $callback($tempQuery);

            // 1. Get the conditions and local bindings from the closure
            $conditions = $this->buildConditionsFromQuery($tempQuery, $bindings);
            if (!empty($conditions)) {
                $subquery .= " AND (" . implode('', $conditions) . ")";
            }

            if (!empty($tempQuery->havings)) {
                $havingParts = [];
                foreach ($tempQuery->havings as $index => $having) {
                    $fragment = '';
                    if (isset($having['type']) && $having['type'] === 'Raw') {
                        $fragment = $having['sql'];
                        // Merge the specific bindings for this havingRaw
                        foreach ($having['bindings'] as $key => $bind) {
                            $bindings[] = $bind;
                        }
                    } else {
                        // Handle basic having if you use it
                        $paramName = "h_" . bin2hex(random_bytes(2));
                        $fragment = "{$this->quoteColumn($having['column'])} {$having['operator']} :{$paramName}";
                        $bindings[$paramName] = $having['value'];
                    }

                    $prefix = ($index === 0) ? ' HAVING ' : " {$having['boolean']} ";
                    $havingParts[] = $prefix . $fragment;
                }
                $subquery .= implode('', $havingParts);
            }
        }

        // 3. Append LIMIT at the very end
        if ($isExists) {
            $subquery .= " LIMIT 1";
        }

        return ['sql' => $subquery, 'bindings' => $bindings];
    }

    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Build conditions from query while preserving boolean (AND/OR) logic and bindings
     */
    protected function buildConditionsFromQuery(QueryBuilder $query, array &$bindings): array
    {
        if (empty($query->wheres)) {
            return [];
        }

        $conditions = [];

        foreach ($query->wheres as $index => $where) {
            $boolean = strtoupper($where['boolean']);
            // First condition in the array doesn't need a prefix because
            // the subquery caller handles the initial 'AND' join.
            $prefix = ($index === 0) ? '' : " {$boolean} ";
            $fragment = '';

            switch ($where['type']) {
                case 'Raw':
                    $fragment = $where['sql'];
                    if (!empty($where['bindings'])) {
                        foreach ($where['bindings'] as $key => $bind) {
                            if (is_string($key)) {
                                $bindings[$key] = $bind;
                            } else {
                                $bindings[] = $bind;
                            }
                        }
                    }
                    break;

                case 'NotBasic':
                    $table = $query->getTable();
                    $column = (strpos($where['column'], '.') === false)
                        ? "{$table}.{$where['column']}"
                        : $where['column'];

                    $column = $this->quoteColumn($column);
                    $value = $where['value'];

                    if (is_null($value)) {
                        $fragment = "{$column} IS NOT NULL";
                    } else {
                        $uniqueId = bin2hex(random_bytes(2));
                        $paramName = "sub_" . $uniqueId . "_" . count($bindings);

                        $fragment = "NOT ({$column} {$where['operator']} :{$paramName})";
                        $bindings[$paramName] = is_bool($value) ? ($value ? 1 : 0) : $value;
                    }
                    break;

                case 'Basic':
                    $table = $query->getTable();
                    $column = (strpos($where['column'], '.') === false) ? "{$table}.{$where['column']}" : $where['column'];
                    $column = $this->quoteColumn($column);
                    $value = $where['value'];

                    if (is_null($value)) {
                        $fragment = "{$column} IS NULL";
                    } else {
                        // Create a unique key that will be preserved by whereRaw
                        $uniqueId = bin2hex(random_bytes(2));
                        $paramName = "sub_" . $uniqueId . "_" . count($bindings);

                        $fragment = "{$column} {$where['operator']} :{$paramName}";
                        $bindings[$paramName] = is_bool($value) ? ($value ? 1 : 0) : $value;
                    }
                    break;

                case 'Nested':
                    $subConditions = $this->buildConditionsFromQuery($where['query'], $bindings);
                    if (!empty($subConditions)) {
                        $fragment = '(' . implode('', $subConditions) . ')';
                    }
                    break;

                case 'In':
                case 'NotIn':
                    $column = $this->quoteColumn($where['column']);
                    $operator = ($where['type'] === 'In') ? 'IN' : 'NOT IN';
                    $fragment = "{$column} {$operator} ({$where['sql_fragment']})";

                    // 🔑 Collect the bindings from the 'In' clause and merge into the main pool
                    if (!empty($where['bindings'])) {
                        foreach ($where['bindings'] as $key => $bind) {
                            $bindings[$key] = $bind;
                        }
                    }
                    break;

                case 'Null':
                    $fragment = $this->quoteColumn($where['column']) . " IS NULL";
                    break;

                case 'NotNull':
                    $fragment = $this->quoteColumn($where['column']) . " IS NOT NULL";
                    break;
            }

            if (!empty($fragment)) {
                $conditions[] = $prefix . $fragment;
            }
        }

        return $conditions;
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

    /**
     * Add constraints for existence of related models
     */
    public function whereHas(string $relation, ?callable $callback = null): self
    {
        return $this->has($relation, '>=', 1, 'and', $callback);
    }

    /**
     * Add constraints based on related model count
     */
    public function has(string $relation, string $operator = '>=', int $count = 1, string $boolean = 'and', ?callable $callback = null): self
    {
        // 1. Handle Nested Relationships (e.g., 'productOffer.product')
        if (str_contains($relation, '.')) {
            return $this->hasNested($relation, $operator, $count, $boolean, $callback);
        }

        $modelClass = $this->getModelClassFromTable($this->table);
        if (!$modelClass) {
            throw new \Exception("No model class found for table: {$this->table}");
        }

        $tempModel = new $modelClass();
        if (!method_exists($tempModel, $relation)) {
            throw new BadMethodCallException("Relationship {$relation} does not exist on {$modelClass}");
        }

        $analyzer = new RelationshipAnalyzer();
        $relationData = $analyzer->analyzeRelationshipMethod($tempModel, $relation);

        $this->addHasConstraintToMainQuery($relationData, $operator, $count, $boolean, $callback);

        return $this;
    }

    /**
     * Resolve nested relationship existence checks
     */
    protected function hasNested(string $relations, string $operator, int $count, string $boolean, ?callable $callback): self
    {
        $relations = explode('.', $relations);
        $firstRelation = array_shift($relations);

        // We wrap the remaining relations in a closure to pass down the chain
        return $this->has($firstRelation, '>=', 1, $boolean, function ($query) use ($relations, $operator, $count, $callback) {
            $remainingPath = implode('.', $relations);

            if (empty($remainingPath)) {
                // We reached the end of the chain, apply the final user callback
                if ($callback) $callback($query);
            } else {
                // Keep drilling down
                $query->has($remainingPath, $operator, $count, 'and', $callback);
            }
        });
    }

    /**
     * Add has constraint to main query
     */
    protected function addHasConstraintToMainQuery(array $relationData, string $operator, int $count, string $boolean, ?callable $callback): void
    {
        $subqueryData = $this->buildSubqueryWithBindings($relationData, $callback);

        $sql = $subqueryData['sql'];
        $subBindings = $subqueryData['bindings'];

        // 🔑 If operator is '<' and count is 1, it's a "DoesntHave", use NOT EXISTS
        $existsVerb = ($operator === '<' && $count === 1) ? 'NOT EXISTS' : 'EXISTS';
        $constraint = "{$existsVerb} ({$sql})";

        $this->whereRaw($constraint, $subBindings, $boolean);
    }

    /**
     * Add constraints for non-existence of related models
     */
    public function whereDoesntHave(string $relation, ?callable $callback = null): self
    {
        return $this->has($relation, '<', 1, 'and', $callback);
    }


    /**
     * Add withCount subquery to main query
     */
    // In QueryBuilder, update the addWithCountToMainQuery method:
    public function orWhereDoesntHave(string $relation, ?callable $callback = null): self
    {
        return $this->has($relation, '<', 1, 'or', $callback);
    }

    /**
     * Add an "or" constraint for existence of related models.
     *
     * @param string $relation
     * @param callable|null $callback
     * @return $this
     */
    public function orWhereHas(string $relation, ?callable $callback = null): self
    {
        return $this->has($relation, '>=', 1, 'or', $callback);
    }

    public function whereColumn(string $first, string $operatorOrSecond, ?string $second = null): self
    {
        if ($second === null) {
            // Called with 2 params: assume operator '='
            $second = $operatorOrSecond;
            $operatorOrSecond = '=';
        }

        // Add a proper ColumnComparison where clause
        $this->wheres[] = [
            'type' => 'ColumnComparison',
            'first' => $first,
            'operator' => $operatorOrSecond,
            'second' => $second,
            'boolean' => 'AND',
        ];

        return $this;
    }

    public function whereJsonContains(string $column, $tags, string $boolean = 'AND'): self
    {
        if (!is_array($tags)) {
            $tags = [$tags];
        }

        if (count($tags) > 1) {
            $nested = new self($this->table, $this->relationManager, $this->database);
            foreach ($tags as $tag) {
                $nested->whereJsonContains($column, $tag, 'OR');
            }
            $this->wheres[] = [
                'type' => 'Nested',
                'query' => $nested,
                'boolean' => $boolean,
            ];
        } else {
            $this->wheres[] = [
                'type' => 'JsonContains',
                'column' => $column,
                'value' => json_encode($tags[0]),
                'boolean' => $boolean,
            ];
        }

        return $this;
    }

    public function selectRaw(string $expression): self
    {
        // 🔑 If default select is still '*', wipe it
        if ($this->selects === ['*']) {
            $this->selects = [];
        }

        $this->selects[] = new RawExpression($expression);
        return $this;
    }

    public function __call(string $method, array $arguments)
    {
        $modelClass = $this->getModelClassFromTable($this->table);

        if ($modelClass) {
            $model = new $modelClass();
            $scopeMethod = 'scope' . ucfirst($method);

            if (method_exists($model, $scopeMethod)) {
                return $model->$scopeMethod($this, ...$arguments);
            }
        }

        // macros
        if ($this->hasMacro($method)) {
            return $this->callMacro($method, $arguments);
        }

        throw new BadMethodCallException("Method {$method} does not exist.");
    }

    public function groupByRaw(string $sql): self
    {
        $this->groups[] = ['raw' => $sql];

        return $this;
    }

    public function insertOrIgnore(array $values): int
    {
        if (empty($values)) {
            return 0;
        }

        // Support both single row and bulk insert
        $isMulti = is_array(reset($values));

        if (!$isMulti) {
            $values = [$values];
        }

        $columns = array_keys($values[0]);

        $placeholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';

        $sql = sprintf(
            'INSERT IGNORE INTO %s (%s) VALUES %s',
            $this->table,
            implode(', ', $columns),
            implode(', ', array_fill(0, count($values), $placeholders))
        );

        $bindings = [];

        foreach ($values as $row) {
            foreach ($columns as $column) {
                $bindings[] = $row[$column] ?? null;
            }
        }

        $statement = $this->database->prepare($sql);
        $statement->execute($bindings);

        return $statement->rowCount(); // returns inserted rows (ignored rows not counted)
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

}