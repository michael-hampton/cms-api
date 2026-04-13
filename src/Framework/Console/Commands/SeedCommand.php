<?php

namespace App\Framework\Console\Commands;

use App\Framework\Console\Command;
use App\Framework\Database\Database;
use App\Framework\Database\Seeder\SeederRunner;
use Exception;

class SeedCommand extends Command
{
    protected $signature = 'db:seed {--class= : The class name of the root seeder}';
    public $description = 'Run the database seeders';

    public function handle(): int
    {
        try {
            $database = Database::getInstance();
            $runner = new SeederRunner($database, 'database/seeders');

            $className = $this->option('class') ?: $this->argument('name');

            if ($className) {
                $seederClass = $this->resolveSeederClass($className);

                if (!$seederClass) {
                    $this->error("Seeder class not found for: {$className}");
                    return 1;
                }

                $this->info("Seeding: {$seederClass}");
                $seeder = new $seederClass($database);
                $seeder->run();
                $this->info("Seeded: {$seederClass}");
            } else {
                $this->info('Running all seeders...');
                $runner->run();
                $this->info('Database seeding completed!');
            }

            return 0;

        } catch (Exception $e) {
            $this->error('Seeding failed: ' . $e->getMessage());
            return 1;
        }
    }

    private function resolveSeederClass(string $input): ?string
    {
        // Try different variations
        $variations = [
            "App\\Database\\Seeders\\{$input}",
            "App\\Database\\Seeders\\{$input}Seeder",
            "App\\Database\\Seeders\\" . ucfirst($input),
            "App\\Database\\Seeders\\" . ucfirst($input) . "Seeder",
            "App\\Database\\Seeders\\" . ucfirst(strtolower($input)) . "Seeder",
        ];

        foreach ($variations as $class) {
            if (class_exists($class)) {
                return $class;
            }
        }

        return null;
    }
}