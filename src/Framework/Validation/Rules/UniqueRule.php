<?php

namespace App\Framework\Validation\Rules;

use App\Framework\Database\Database;
use App\Framework\Database\QueryBuilder;
use App\Framework\Database\Relations\EagerLoader;
use App\Framework\Database\Relations\RelationHandlerFactory;
use App\Framework\Database\Relations\RelationshipAnalyzer;
use Exception;

class UniqueRule extends DatabaseRule
{
    private $table;
    private $column;
    private $ignore;
    private $ignoreColumn;

    public function setDatabase($database): void
    {
        $this->database = $database;
    }

    public function setParameters(array $parameters): void
    {
        if (!empty($parameters)) {
            $this->table = $parameters[0] ?? '';
            $this->column = $parameters[1] ?? 'email';
            $this->ignore = $parameters[2] ?? null;
            $this->ignoreColumn = $parameters[3] ?? 'id';
        }
    }

    public function validate($value, array $data = []): bool
    {
        if ($value === null || $value === '') {
            return true; // Empty values are valid for unique rule
        }

        $query = new QueryBuilder(
            $this->table,
            new EagerLoader(
                new RelationshipAnalyzer(),
                new RelationHandlerFactory($this->database)
            ),
            $this->database
        );
        $query->where($this->column, $value);

        if ($this->ignore !== null) {
            $query->where($this->ignoreColumn, '!=', $this->ignore);
        }

        return !$query->exists();
    }

    protected function getDefaultMessage(): string
    {
        return 'This value is already taken';
    }
}