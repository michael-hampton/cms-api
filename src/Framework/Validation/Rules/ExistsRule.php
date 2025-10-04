<?php

namespace App\Framework\Validation\Rules;

use App\Framework\Validation\ValidationRuleInterface;
use Exception;

class ExistsRule extends DatabaseRule
{
    private $table;
    private $column = 'id'; // default column

    public function setDatabase($database): void
    {
        $this->database = $database;
    }

    public function validate($value, array $data = []): bool
    {
        if (empty($value)) return true; // Let required rule handle empty values

        $result = $this->database->query(
            "SELECT COUNT(*) as count FROM {$this->table} WHERE {$this->column} = ?",
            [$value]
        );

        return $result->fetch()['count'] > 0;
    }

    public function getMessage(): string
    {
        return "The selected value is invalid.";
    }

    public function setParameters(array $parameters): void
    {
        if (count($parameters) >= 1) {
            $this->table = $parameters[0];
            $this->column = $parameters[1] ?? 'id'; // Default to 'id' if column not specified
        } else {
            throw new Exception("Exists rule requires at least a table parameter");
        }
    }

    protected function getDefaultMessage(): string
    {
       return 'Invalid value';
    }
}