<?php

namespace App\Framework\Migration;

use App\Framework\Database\Database;
use App\Framework\Database\Schema;
use PDO;

class MigrationRunner
{
    private $database;
    private $migrationsPath;
    private $tableName = 'migrations';

    public function __construct(Database $database, string $migrationsPath = 'migrations')
    {
        $this->database = $database;
        $this->migrationsPath = $migrationsPath;
        Schema::setDatabase($database);
        $this->createMigrationsTable();
    }

    private function createMigrationsTable(): void
    {
        if (!Schema::hasTable($this->tableName)) {
            Schema::create($this->tableName, function (Blueprint $table) {
                $table->id();
                $table->string('migration');
                $table->integer('batch');
                $table->timestamps();
            });
        }
    }

    public function run(): void
    {
        $files = $this->getMigrationFiles();

        $ranMigrations = $this->getRanMigrations();

        $batch = $this->getNextBatchNumber();

        foreach ($files as $file) {
            $migrationName = pathinfo($file, PATHINFO_FILENAME);

            if (!in_array($migrationName, $ranMigrations)) {
                echo "Migrating: {$migrationName}\n";

                $migration = $this->resolve($file);

                $migration->up();

                $this->database->insert($this->tableName, [
                    'migration' => $migrationName,
                    'batch' => $batch
                ]);

                echo "Migrated: {$migrationName}\n";
            }
        }
    }

    public function rollback(int $steps = 1): void
    {
        $batches = $this->getBatches();
        $targetBatch = max(0, count($batches) - $steps);

        for ($i = count($batches) - 1; $i >= $targetBatch; $i--) {
            $batch = $batches[$i];
            $migrations = $this->getBatchMigrations($batch);

            foreach (array_reverse($migrations) as $migrationName) {
                echo "Rolling back: {$migrationName}\n";

                $file = $this->findMigrationFile($migrationName);
                if ($file) {
                    $migration = $this->resolve($file);
                    $migration->down();
                }

                $this->database->delete($this->tableName, ['migration' => $migrationName]);
                echo "Rolled back: {$migrationName}\n";
            }
        }
    }

    private function getMigrationFiles(): array
    {
        // Absolute path to the migrations folder
        $migrationsPath = __DIR__ . '/../../Database/Migrations';


        if ($migrationsPath === false || !is_dir($migrationsPath)) {
            return [];
        }

        $files = glob($migrationsPath . '/*.php');

        sort($files, SORT_STRING);

        return $files;
    }

    private function getRanMigrations(): array
    {
        $stmt = $this->database->query("SELECT migration FROM {$this->tableName} ORDER BY migration");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function getNextBatchNumber(): int
    {
        $stmt = $this->database->query("SELECT MAX(batch) as max_batch FROM {$this->tableName}");
        $result = $stmt->fetch();
        return ($result['max_batch'] ?? 0) + 1;
    }

    private function getBatches(): array
    {
        $stmt = $this->database->query("SELECT DISTINCT batch FROM {$this->tableName} ORDER BY batch");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function getBatchMigrations(int $batch): array
    {
        $stmt = $this->database->query("SELECT migration FROM {$this->tableName} WHERE batch = :batch ORDER BY migration", ['batch' => $batch]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function findMigrationFile(string $migrationName): ?string
    {
        $files = $this->getMigrationFiles();
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_FILENAME) === $migrationName) {
                return $file;
            }
        }
        return null;
    }

    private function resolve(string $file): Migration
    {
        require_once $file;

        $className = $this->getClassNameFromFile($file);
        return new $className();
    }

    private function getClassNameFromFile(string $file): string
    {
        $filename = pathinfo($file, PATHINFO_FILENAME);

        // Convert snake_case to PascalCase
        $parts = explode('_', $filename);

        // Remove timestamp (first 4 parts: YYYY_MM_DD_HHMMSS)
        if (count($parts) > 4 && is_numeric($parts[0])) {
            $parts = array_slice($parts, 4);
        }

        $className = '';
        foreach ($parts as $part) {
            $className .= ucfirst($part);
        }

        return $className;
    }
}