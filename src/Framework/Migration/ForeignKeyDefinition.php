<?php

namespace App\Framework\Migration;

class ForeignKeyDefinition
{
    private string $column;
    private ?string $references = null;
    private string $on;
    private string $onDelete = 'RESTRICT';
    private string $onUpdate = 'RESTRICT';
    private string $name;

    public function __construct(string $column, string $table)
    {
        $this->column = $column;
        $this->on = $table;

        $this->name = $this->makeIdentifier('fk', [$table, $column]);
    }

    private function makeIdentifier(string $prefix, array $parts, int $max = 64): string
    {
        $base = $prefix . '_' . implode('_', $parts);

        if (strlen($base) <= $max) {
            return $base;
        }

        $hash = substr(md5($base), 0, 8);

        return substr($base, 0, $max - 9) . '_' . $hash;
    }

    public function references(string $column): self
    {
        $this->references = $column;
        return $this;
    }

    public function on(string $table): self
    {
        $this->on = $table;
        return $this;
    }

    public function onDelete(string $action): self
    {
        $this->onDelete = $action;
        return $this;
    }

    public function onUpdate(string $action): self
    {
        $this->onUpdate = $action;
        return $this;
    }

    public function cascadeOnDelete(): self
    {
        return $this->onDelete('CASCADE');
    }

    public function nullOnDelete(): self
    {
        return $this->onDelete('SET NULL');
    }

    public function restrictOnDelete(): self
    {
        return $this->onDelete('RESTRICT');
    }

    public function toSql(): string
    {
        return "CONSTRAINT `{$this->name}` FOREIGN KEY (`{$this->column}`) REFERENCES `{$this->on}`(`{$this->references}`) ON DELETE {$this->onDelete} ON UPDATE {$this->onUpdate}";
    }
}