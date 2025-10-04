<?php

namespace App\Framework\Migration;

class ForeignKeyDefinition
{
    private $column;
    private $references;
    private $on;
    private $onDelete = 'RESTRICT';
    private $onUpdate = 'RESTRICT';
    private $name;

    public function __construct(string $column, string $table)
    {
        $this->column = $column;
        $this->name = 'fk_' . $table . '_' . $column;    }

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