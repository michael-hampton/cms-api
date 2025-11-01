<?php

namespace App\Framework\Migration;

class Blueprint
{
    private $table;
    private $columns = [];
    private $indexes = [];
    private $foreignKeys = [];
    private $mode = 'create';

    public function __construct(string $table, string $mode = 'create')
    {
        $this->table = $table;
        $this->mode = $mode;
    }

    // Primary key
    public function id(string $column = 'id'): Column
    {
        $col = $this->bigInteger($column)
            ->unsigned()        // <<< make it unsigned
            ->autoIncrement()
            ->primary();

        $this->indexes[] = ['type' => 'PRIMARY', 'columns' => [$column]];
        return $col;
    }

    // String types
    public function string(string $column, int $length = 255): Column
    {
        return $this->addColumn('string', $column, ['length' => $length]);
    }

    public function char(string $column, int $length = 255): Column
    {
        return $this->addColumn('char', $column, ['length' => $length]);
    }

    public function text(string $column): Column
    {
        return $this->addColumn('text', $column);
    }

    public function mediumText(string $column): Column
    {
        return $this->addColumn('mediumText', $column);
    }

    public function longText(string $column): Column
    {
        return $this->addColumn('longText', $column);
    }

    // Numeric types
    public function integer(string $column): Column
    {
        return $this->addColumn('integer', $column);
    }

    public function bigInteger(string $column): Column
    {
        return $this->addColumn('bigInteger', $column);
    }

    public function smallInteger(string $column): Column
    {
        return $this->addColumn('smallInteger', $column);
    }

    public function tinyInteger(string $column): Column
    {
        return $this->addColumn('tinyInteger', $column);
    }

    public function decimal(string $column, int $precision = 8, int $scale = 2): Column
    {
        return $this->addColumn('decimal', $column, ['precision' => $precision, 'scale' => $scale]);
    }

    public function float(string $column, int $precision = 53): Column
    {
        return $this->addColumn('float', $column, ['precision' => $precision]);
    }

    public function double(string $column): Column
    {
        return $this->addColumn('double', $column);
    }

    // Boolean
    public function boolean(string $column): Column
    {
        return $this->addColumn('boolean', $column);
    }

    // Date and time
    public function date(string $column): Column
    {
        return $this->addColumn('date', $column);
    }

    public function dateTime(string $column): Column
    {
        return $this->addColumn('dateTime', $column);
    }

    public function time(string $column): Column
    {
        return $this->addColumn('time', $column);
    }

    public function timestamp(string $column): Column
    {
        return $this->addColumn('timestamp', $column);
    }

    public function timestamps(): void
    {
        $this->timestamp('created_at')->nullable()->default('CURRENT_TIMESTAMP');
        $this->timestamp('updated_at')->nullable()->default('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
    }

    // JSON
    public function json(string $column): Column
    {
        return $this->addColumn('json', $column);
    }

    // Enum
    public function enum(string $column, array $values): Column
    {
        return $this->addColumn('enum', $column, ['values' => $values]);
    }

    // UUID
    public function uuid(string $column): Column
    {
        return $this->string($column, 36);
    }

    // Foreign key
    public function foreignId(string $column): Column
    {
        return $this->bigInteger($column)->unsigned();
    }

    public function unsignedBigInteger(string $column): Column
    {
        return $this->bigInteger($column)->unsigned();
    }

    public function foreign(string $column): ForeignKeyDefinition
    {
        $foreign = new ForeignKeyDefinition($column, $this->table);
        $this->foreignKeys[] = $foreign;
        return $foreign;
    }

    public function dropColumn($columns): void
    {
        $columns = is_array($columns) ? $columns : [$columns];

        foreach ($columns as $column) {
            $this->commands[] = [
                'type' => 'dropColumn',
                'column' => $column
            ];
        }
    }

    // Indexes
    public function index($columns, ?string $name = null): void
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $name = $name ?: 'idx_' . implode('_', $columns);
        $this->indexes[] = ['type' => 'INDEX', 'name' => $name, 'columns' => $columns];
    }

    public function unique($columns, ?string $name = null): void
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $name = $name ?: 'unique_' . implode('_', $columns);
        $this->indexes[] = ['type' => 'UNIQUE', 'name' => $name, 'columns' => $columns];
    }

    public function primary($columns): void
    {
        $columns = is_array($columns) ? $columns : [$columns];
        $this->indexes[] = ['type' => 'PRIMARY', 'name' => 'PRIMARY', 'columns' => $columns];
    }

    // Soft deletes
    public function softDeletes(): Column
    {
        return $this->timestamp('deleted_at')->nullable();
    }

    private function addColumn(string $type, string $name, array $parameters = []): Column
    {
        $column = new Column($type, $name, $parameters);
        $this->columns[] = $column;
        return $column;
    }

    public function toSql(): string
    {
        $sql = "CREATE TABLE `{$this->table}` (\n";

        $columnDefinitions = [];
        foreach ($this->columns as $column) {
            $columnDefinitions[] = "    " . $column->toSql();
        }

        $sql .= implode(",\n", $columnDefinitions);

        // Add indexes with proper syntax
        foreach ($this->indexes as $index) {
            if ($index['type'] === 'PRIMARY') {
                $quotedColumns = array_map(function($col) {
                    return "`{$col}`";
                }, $index['columns']);
                $sql .= ",\n    PRIMARY KEY (" . implode(', ', $quotedColumns) . ")";
            } elseif ($index['type'] === 'UNIQUE') {
                $quotedColumns = array_map(function($col) {
                    return "`{$col}`";
                }, $index['columns']);
                $sql .= ",\n    UNIQUE KEY `{$index['name']}` (" . implode(', ', $quotedColumns) . ")";
            } else {
                // Regular index - fix the "INDEX KEY" issue
                $quotedColumns = array_map(function($col) {
                    return "`{$col}`";
                }, $index['columns']);
                $sql .= ",\n    INDEX `{$index['name']}` (" . implode(', ', $quotedColumns) . ")";
            }
        }

        // Add foreign keys
        foreach ($this->foreignKeys as $foreign) {
            $sql .= ",\n    " . $foreign->toSql();
        }

        $sql .= "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        return $sql;
    }

    public function toAlterSql(): array
    {
        $statements = [];

        // Handle drop columns
        foreach ($this->commands ?? [] as $command) {
            if ($command['type'] === 'dropColumn') {
                $statements[] = "ALTER TABLE `{$this->table}` DROP COLUMN `{$command['column']}`";
            }
        }

        foreach ($this->columns as $column) {
            $statements[] = "ALTER TABLE `{$this->table}` ADD COLUMN " . $column->toSql();
        }

        foreach ($this->indexes as $index) {
            if ($index['type'] === 'PRIMARY') {
                $quotedColumns = array_map(function($col) {
                    return "`{$col}`";
                }, $index['columns']);
                $statements[] = "ALTER TABLE `{$this->table}` ADD PRIMARY KEY (" . implode(', ', $quotedColumns) . ")";
            } elseif ($index['type'] === 'UNIQUE') {
                $quotedColumns = array_map(function($col) {
                    return "`{$col}`";
                }, $index['columns']);
                $statements[] = "ALTER TABLE `{$this->table}` ADD UNIQUE KEY `{$index['name']}` (" . implode(', ', $quotedColumns) . ")";
            } else {
                $quotedColumns = array_map(function($col) {
                    return "`{$col}`";
                }, $index['columns']);
                $statements[] = "ALTER TABLE `{$this->table}` ADD KEY `{$index['name']}` (" . implode(', ', $quotedColumns) . ")";
            }
        }

        foreach ($this->foreignKeys as $foreign) {
            $statements[] = "ALTER TABLE `{$this->table}` ADD " . $foreign->toSql();
        }

        return $statements;
    }
}