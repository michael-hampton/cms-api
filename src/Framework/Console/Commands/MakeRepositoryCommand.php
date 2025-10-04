<?php

namespace App\Framework\Console\Commands;

use App\Framework\Console\Command;

class MakeRepositoryCommand extends Command
{
    protected $signature = 'make:repository {name}';
    protected $description = 'Create a new repository';

    public function handle(): int
    {
        $name = $this->ask('Enter repository name (e.g., UserRepository)');
        if (empty($name)) {
            $this->error('Repository name is required');
            return 1;
        }

        $modelName = $this->ask('Enter model name for this repository (e.g., User)');
        if (empty($modelName)) {
            $this->error('Model name is required');
            return 1;
        }

        $filename = "app/Repositories/{$name}.php";
        $this->ensureDirectory(dirname($filename));

        $stub = $this->getRepositoryStub($name, $modelName);

        if (file_put_contents($filename, $stub)) {
            $this->info("Created repository: {$filename}");
            return 0;
        } else {
            $this->error("Failed to create repository file");
            return 1;
        }
    }

    private function getRepositoryStub(string $name, string $modelName): string
    {
        return "<?php

class {$name} extends Repository
{
    protected function getModelClass(): string
    {
        return {$modelName}::class;
    }
    
    // Add custom repository methods here
}
";
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }
}