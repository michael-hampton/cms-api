<?php

namespace App\Console;

use App\Services\Cms\BriefScheduleProcessor;

class ProcessBriefSchedules
{
    public function __construct(
        private readonly BriefScheduleProcessor $processor
    )
    {
    }

    public static function signature(): string
    {
        return 'brief:schedule:process';
    }

    public function handle(): void
    {
        $this->processor->process();
    }
}