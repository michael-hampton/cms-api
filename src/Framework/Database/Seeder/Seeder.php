<?php

namespace App\Framework\Database\Seeder;

use App\Framework\Database\Database;

abstract class Seeder
{
    protected $database;

    public function __construct(?Database $database = null)
    {
        $this->database = $database ?: Database::getInstance();
    }

    abstract public function run(): void;

    protected function call(string $seederClass): void
    {
        $seeder = new $seederClass($this->database);
        $seeder->run();
    }
}