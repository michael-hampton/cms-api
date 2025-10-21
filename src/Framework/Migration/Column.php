<?php

namespace App\Framework\Migration;

class Column
{
    private $type;
    private $name;
    private $parameters;
    private $nullable = false;
    private $default = null;
    private $autoIncrement = false;
    private $unsigned = false;
    private $unique = false;
    private $primary = false;
    private string $after;
    protected bool $useCurrent = false;
    protected bool $useCurrentOnUpdate = false;

    public function __construct(string $type, string $name, array $parameters = [])
    {
        $this->type = $type;
        $this->name = $name;
        $this->parameters = $parameters;
    }

    public function useCurrent(): self
    {
        $this->useCurrent = true;
        return $this;
    }

    public function useCurrentOnUpdate(): self
    {
        $this->useCurrentOnUpdate = true;
        return $this;
    }

    public function nullable(): self
    {
        $this->nullable = true;
        return $this;
    }

    public function default($value): self
    {
        $this->default = $value;
        return $this;
    }

    public function after(string $column): self
    {
        $this->after = $column;
        return $this;
    }

    public function autoIncrement(): self
    {
        $this->autoIncrement = true;
        return $this;
    }

    public function unsigned(): self
    {
        $this->unsigned = true;
        return $this;
    }

    public function unique(): self
    {
        $this->unique = true;
        return $this;
    }

    public function primary(): self
    {
        $this->primary = true;
        return $this;
    }

    public function toSql(): string
    {
        $sql = "`{$this->name}` " . $this->getTypeDefinition();

        if ($this->unsigned) {
            $sql .= ' UNSIGNED';
        }

        if (!$this->nullable) {
            $sql .= ' NOT NULL';
        } else {
            $sql .= ' NULL';
        }

        if ($this->autoIncrement) {
            $sql .= ' AUTO_INCREMENT';
            // MySQL forbids DEFAULT on AUTO_INCREMENT
            return $sql;
        }

        if ($this->default !== null) {
            if (is_bool($this->default)) {
                $sql .= ' DEFAULT ' . ($this->default ? '1' : '0');
            } elseif (is_numeric($this->default)) {
                $sql .= " DEFAULT {$this->default}";
            } elseif (is_string($this->default)) {
                $upper = strtoupper($this->default);
                if (in_array($upper, ['CURRENT_TIMESTAMP', 'CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'])) {
                    $sql .= " DEFAULT {$this->default}";
                } elseif ($upper === 'NULL' && $this->nullable) {
                    $sql .= " DEFAULT NULL";
                } else {
                    $escaped = str_replace("'", "''", $this->default);
                    $sql .= " DEFAULT '{$escaped}'";
                }
            }
        }

        if ($this->useCurrent) {
            $sql .= " DEFAULT CURRENT_TIMESTAMP";
        }

        if ($this->useCurrentOnUpdate) {
            $sql .= " ON UPDATE CURRENT_TIMESTAMP";
        }

        if (isset($this->after)) {
            $sql .= " AFTER `{$this->after}`";
        }

        return $sql;
    }

    private function getTypeDefinition(): string
    {
        switch ($this->type) {
            case 'string':
            case 'varchar':
                return "VARCHAR({$this->parameters['length']})";
            case 'char':
                return "CHAR({$this->parameters['length']})";
            case 'text':
                return 'TEXT';
            case 'mediumText':
                return 'MEDIUMTEXT';
            case 'longText':
                return 'LONGTEXT';
            case 'integer':
            case 'int':
                return 'INT';
            case 'bigInteger':
            case 'bigint':
                return 'BIGINT';
            case 'smallInteger':
            case 'smallint':
                return 'SMALLINT';
            case 'tinyInteger':
            case 'tinyint':
                return 'TINYINT';
            case 'decimal':
                return "DECIMAL({$this->parameters['precision']},{$this->parameters['scale']})";
            case 'float':
                return "FLOAT({$this->parameters['precision']})";
            case 'double':
                return 'DOUBLE';
            case 'boolean':
                return 'TINYINT(1)';
            case 'date':
                return 'DATE';
            case 'dateTime':
                return 'DATETIME';
            case 'time':
                return 'TIME';
            case 'timestamp':
                return 'TIMESTAMP';
            case 'json':
                return 'JSON';
            case 'enum':
                $values = implode("','", $this->parameters['values']);
                return "ENUM('{$values}')";
            default:
                return strtoupper($this->type);
        }
    }
}