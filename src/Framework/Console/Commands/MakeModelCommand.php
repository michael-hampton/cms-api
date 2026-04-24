<?php

namespace App\Framework\Console\Commands;

use App\Framework\Console\Command;

class MakeModelCommand extends Command
{
    protected $signature = 'make:model {name}';
    public $description = 'Create a new model';

    public function handle(): int
    {
        $name = $this->ask('Enter model name (e.g., User)');
        if (empty($name)) {
            $this->error('Model name is required');
            return 1;
        }

        $filename = "app/Models/{$name}.php";
        $this->ensureDirectory(dirname($filename));

        $stub = $this->getModelStub($name);

        if (file_put_contents($filename, $stub)) {
            $this->info("Created model: {$filename}");

            if ($this->confirm('Create migration for this model?')) {
                $this->createMigration($name);
            }

            return 0;
        } else {
            $this->error("Failed to create model file");
            return 1;
        }
    }

    private function getModelStub(string $name): string
    {
        $table = strtolower($name) . 's';

        return "<?php

class {$name} extends Model
{
    protected \$table = '{$table}';
    protected \$fillable = [];
    
    // Define relationships here
}
";
    }

    private function createMigration(string $name): void
    {
        $tableName = strtolower($name) . 's';
        $migrationName = "create_{$tableName}_table";
        $timestamp = date('Y_m_d_His');
        $className = 'Create' . ucfirst($tableName) . 'Table';
        $filename = "migrations/{$timestamp}_{$migrationName}.php";

        $stub = "<?php

class {$className} extends Migration
{
    public function up(): void
    {
        Schema::create('{$tableName}', function (Blueprint \$table) {
            \$table->id();
            \$table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$tableName}');
    }
}
";

        file_put_contents($filename, $stub);
        $this->info("Created migration: {$filename}");
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }
}