<?php

namespace App\Framework\Console\Commands;

use App\Framework\Console\Command;

class MakeControllerCommand extends Command
{
    protected $signature = 'make:controller {name}';
    protected $description = 'Create a new controller';

    public function handle(): int
    {
        $name = $this->argument('name');

        if (empty($name)) {
            $name = $this->ask('Enter controller name (e.g., UserController)');
            if (empty($name)) {
                $this->error('Controller name is required');
                return 1;
            }
        }

        $filename = "app/Controllers/{$name}.php";
        $this->ensureDirectory(dirname($filename));

        $stub = $this->getControllerStub($name);

        if (file_put_contents($filename, $stub)) {
            $this->info("Created controller: {$filename}");
            return 0;
        } else {
            $this->error("Failed to create controller file");
            return 1;
        }
    }

    private function getControllerStub(string $name): string
    {
        return "<?php

namespace App\\Controllers;

class {$name} extends Controller
{
    public function index(): array
    {
        return \$this->jsonResponse([]);
    }
    
    public function show(int \$id): array
    {
        return \$this->jsonResponse([]);
    }
    
    public function store(Request \$request): array
    {
        return \$this->jsonResponse([], 201);
    }
    
    public function update(int \$id, Request \$request): array
    {
        return \$this->jsonResponse([]);
    }
    
    public function destroy(int \$id): array
    {
        return \$this->jsonResponse([], 204);
    }
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
