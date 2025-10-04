<?php

namespace App\Framework\Database\Seeder;

use App\Framework\Database\Database;

class SeederRunner
{
    private $database;
    private $seedersPath;

    public function __construct(Database $database, string $seedersPath = 'seeders')
    {
        $this->database = $database;
        $this->seedersPath = $seedersPath;
    }

    public function run(array $seeders = []): void
    {
        if (empty($seeders)) {
            $seeders = $this->getAllSeeders();
        }

        foreach ($seeders as $seederClass) {
            echo "Seeding: {$seederClass}\n";

            $seeder = new $seederClass($this->database);
            $seeder->run();

            echo "Seeded: {$seederClass}\n";
        }
    }

    private function getAllSeeders(): array
    {
        $seeders = [];
        $namespace = 'App\\Database\\Seeders';

        if (is_dir($this->seedersPath)) {
            $files = glob($this->seedersPath . '/*.php');

            foreach ($files as $file) {
                require_once $file;

                $className = pathinfo($file, PATHINFO_FILENAME);
                $fullyQualified = $namespace . '\\' . $className;

                if (class_exists($fullyQualified)) {
                    $seeders[] = $fullyQualified;
                }
            }
        }

        return $seeders;
    }

}