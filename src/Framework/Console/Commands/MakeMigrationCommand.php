<?php

namespace App\Framework\Console\Commands;

use App\Framework\Console\Command;

class MakeMigrationCommand extends Command
{
    protected $signature = 'make:migration {name}';
    public $description = 'Create a new migration file';

    public function handle(): int
    {
        $name = $this->argument('name');

        if (empty($name)) {
            $name = $this->ask('Enter migration name');
            if (empty($name)) {
                $this->error('Migration name is required');
                return 1;
            }
        }

        $timestamp = date('Y_m_d_His');
        $className = $this->getClassName($name);
        $filename = "database/migrations/{$timestamp}_{$name}.php";

        if (!is_dir('database/migrations')) {
            mkdir('database/migrations', 0755, true);
        }

        $stub = $this->getMigrationStub($className, $name);

        if (file_put_contents($filename, $stub)) {
            $this->info("Created migration: {$filename}");
            return 0;
        } else {
            $this->error("Failed to create migration file");
            return 1;
        }
    }

    private function getClassName(string $name): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
    }

    private function getMigrationStub(string $className, string $name): string
    {
        return "<?php
        use App\Framework\Database\Schema;
use App\Framework\Migration\Blueprint;
use App\Framework\Migration\Migration;

class {$className} extends Migration
{
    public function up(): void
    {
        Schema::create('table_name', function (Blueprint \$table) {
            \$table->id();
            \$table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
}
";
    }
}