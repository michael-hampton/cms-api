<?php

namespace App\Console\Commands\Subscriptions;

use App\Framework\Support\Logger;
use App\Jobs\Subscriptions\GenerateIssueDeliveriesJob;
use App\Models\IssueDelivery;

class ProcessIssueSchedulesCommand
{
    public function __construct(
        private readonly GenerateIssueDeliveriesJob $generateJob
    )
    {
    }

    public function handle(): int
    {
        $now = new \DateTime();

        $schedules = IssueDelivery::where('status', 'active')
            ->where(function ($query) use ($now) {
                $query->where('on_sale_date', '<=', $now->format('Y-m-d H:i:s'))
                    ->orWhere('estimated_delivery_date', '<=', $now->format('Y-m-d H:i:s'));
            })
            ->get();

        $processedCount = 0;

        foreach ($schedules as $schedule) {
            try {
                dispatch($this->generateJob->handle($schedule));
                $processedCount++;
            } catch (\Exception $e) {
                Logger::error('Failed to process issue schedule', [
                    'schedule_id' => $schedule->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Logger::info('Issue schedules processed', [
            'total_schedules' => $schedules->count(),
            'processed' => $processedCount,
        ]);

        return 0;
    }
}