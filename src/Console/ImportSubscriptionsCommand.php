<?php

declare(strict_types=1);

namespace App\Console;

use App\Framework\Console\Command;
use App\Services\Subscriptions\Import\BulkSubscriptionImportService;
use App\Services\Subscriptions\Import\SubscriptionCsvReader;
use Throwable;

final class ImportSubscriptionsCommand extends Command
{
    public const SUCCESS = 1;
    public const FAILURE = 0;

    public $description = 'Imports members and subscriptions from a CSV file.';

    protected $signature = 'subscriptions:import
                            {file : Absolute or project-relative path to the CSV file}
                            {--site_id= : Site ID the members and subscriptions belong to}
                            {--stop-on-error : Stop at the first failed row}';

    public function __construct(
        private readonly SubscriptionCsvReader $reader,
        private readonly BulkSubscriptionImportService $importer,
    ) {
    }

    public function handle(): int
    {
        $siteId = (int)$this->option('site_id');
        if ($siteId < 1) {
            $this->error('--site_id is required and must be a positive integer.');
            return self::FAILURE;
        }

        $path = (string)$this->argument('file');
        if (!str_starts_with($path, DIRECTORY_SEPARATOR)) {
            $path = getcwd() . DIRECTORY_SEPARATOR . $path;
        }

        try {
            $result = $this->importer->import(
                $this->reader->read($path),
                $siteId,
                !(bool)$this->option('stop-on-error'),
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        foreach ($result['errors'] as $error) {
            $this->error(sprintf(
                'Line %d%s: %s',
                $error['line'],
                $error['email'] ? " ({$error['email']})" : '',
                $error['message'],
            ));
        }

        $this->info(sprintf(
            'Processed: %d, succeeded: %d, failed: %d',
            $result['processed'],
            $result['succeeded'],
            $result['failed'],
        ));

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
