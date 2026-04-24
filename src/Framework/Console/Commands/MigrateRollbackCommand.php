<?php

namespace App\Framework\Console\Commands;

use App\Framework\Console\Command;
use App\Framework\Database\Database;
use App\Framework\Migration\MigrationRunner;
use Exception;

class MigrateRollbackCommand extends Command
{
    protected $signature = 'migrate:rollback {--steps=1}';
    public $description = 'Rollback database migrations';

    public function handle(): int
    {
        try {
            $database = Database::getInstance();
            $runner = new MigrationRunner($database, 'migrations');

            $steps = 1; // You could parse this from command line args

            $this->warn("Rolling back {$steps} migration batch(es)...");
            $runner->rollback($steps);
            $this->info('Rollback completed successfully!');

            return 0;
        } catch (Exception $e) {
            $this->error('Rollback failed: ' . $e->getMessage());
            return 1;
        }
    }
}