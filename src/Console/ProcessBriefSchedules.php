<?php

namespace App\Console;

use App\Framework\Console\Command;
use App\Framework\Console\ReportsCommandResult;
use App\Services\Cms\BriefScheduleProcessor;

class ProcessBriefSchedules extends Command
{
    use ReportsCommandResult;

    const SUCCESS = 1;
    const FAILURE = 0;

    protected $signature = 'brief:schedule:process';
    public $description = 'Processes scheduled CMS briefs.';

    public function __construct(
        private readonly BriefScheduleProcessor $processor
    )
    {
    }

    public function handle(): int
    {
        $result = $this->createResult('brief:schedule:process');

        try {
            $this->processor->process();
            $result->incrementSucceeded();
            $result->addMessage("Brief schedules processed successfully.");
        } catch (\Throwable $e) {
            $this->reportFailure(
                result: $result,
                message: "Failed to process brief schedules: {$e->getMessage()}",
                throwable: $e
            );
        }

        $this->reportResult($result);
        return $result->hasFailures() ? self::FAILURE : self::SUCCESS;
    }
}