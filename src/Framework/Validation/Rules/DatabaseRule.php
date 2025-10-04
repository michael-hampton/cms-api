<?php

namespace App\Framework\Validation\Rules;

use App\Framework\Validation\ValidationRuleInterface;

abstract class DatabaseRule extends BaseValidationRule
{
    protected $database;

    public function setDatabase($database): void
    {
        $this->database = $database;
    }
}