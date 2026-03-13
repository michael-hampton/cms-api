<?php

namespace App\Framework\Console\Commands;

use App\Framework\Console\Command;
use App\Framework\Database\Database;
use App\Framework\Migration\MigrationRunner;
use Exception;

class MigrateCommand extends Command
{
    protected $signature = 'migrate';
    public $description = 'Run database migrations';

    public function handle(): int
    {
        try {
            $database = Database::getInstance();
            $runner = new MigrationRunner($database, 'migrations');

            $this->info('Running migrations...');
            $runner->run();
            $this->info('Migrations completed successfully!');

            return 0;
        } catch (Exception $e) {
            $this->error('Migration failed: ' . $e->getMessage());
            return 1;
        }
    }
}