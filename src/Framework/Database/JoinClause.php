<?php

namespace App\Framework\Database;

class JoinClause
{
    private array $clauses = [];
    private array $bindings = [];

    public function __construct(
        private readonly string $type,
        private readonly string $table,
    ) {}

    public function on(string $first, string $operator, string $second, string $boolean = 'AND'): self
    {
        $this->clauses[] = [
            'type' => 'column',
            'boolean' => strtoupper($boolean),
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
        ];

        return $this;
    }

    public function orOn(string $first, string $operator, string $second): self
    {
        return $this->on($first, $operator, $second, 'OR');
    }

    public function where(string $column, string $operator, mixed $value, string $boolean = 'AND'): self
    {
        $this->clauses[] = [
            'type' => 'value',
            'boolean' => strtoupper($boolean),
            'column' => $column,
            'operator' => $operator,
        ];

        $this->bindings[] = $value;

        return $this;
    }

    public function toSql(): string
    {
        if ($this->clauses === []) {
            throw new \RuntimeException('Join clause must contain at least one condition.');
        }

        $sql = 'ON ';

        foreach ($this->clauses as $index => $clause) {
            if ($index > 0) {
                $sql .= ' ' . $clause['boolean'] . ' ';
            }

            if ($clause['type'] === 'column') {
                $sql .= "{$clause['first']} {$clause['operator']} {$clause['second']}";
                continue;
            }

            $sql .= "{$clause['column']} {$clause['operator']} ?";
        }

        return $sql;
    }

    public function bindings(): array
    {
        return $this->bindings;
    }
}