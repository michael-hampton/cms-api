<?php

namespace App\Services\Newsletter;

use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\NewsletterSendSchedule;
use App\Repositories\Newsletters\NewsletterRepository;
use App\Repositories\Newsletters\NewsletterSendScheduleRepository;

/**
 * Finds all NewsletterSendSchedules that are due and executes the send.
 *
 * This is the bridge between NewsletterScheduleService (schedule management)
 * and NewsletterSendService (actual delivery). It is called by a cron command.
 *
 * After a successful or partially-successful send, next_run_at is advanced.
 * If the send fails entirely, next_run_at is NOT advanced so it retries on
 * the next cron tick, up to the caller's tolerance.
 */
class NewsletterSendScheduleRunner
{
    public function __construct(
        private readonly NewsletterSendScheduleRepository $scheduleRepository,
        private readonly NewsletterRepository             $newsletterRepository,
        private readonly NewsletterSendService            $sendService,
        private readonly ScheduleNextRunCalculator        $calculator,
        private readonly Database                         $database,
        private readonly Logger                           $logger,
    )
    {
    }

    /**
     * @return array{processed: int, failed: int, skipped: int}
     */
    public function run(?int $siteId = null): array
    {
        $schedules = $this->scheduleRepository->getDueSchedules($siteId);

        $processed = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($schedules as $schedule) {
            $result = $this->processSchedule($schedule);

            match ($result) {
                'processed' => $processed++,
                'failed' => $failed++,
                'skipped' => $skipped++,
            };
        }

        $this->logger->info('Newsletter send schedules run complete', [
            'processed' => $processed,
            'failed' => $failed,
            'skipped' => $skipped,
        ]);

        return compact('processed', 'failed', 'skipped');
    }

    private function processSchedule(NewsletterSendSchedule $schedule): string
    {
        $newsletter = $this->newsletterRepository->find($schedule->newsletter_id);

        if (!$newsletter) {
            $this->logger->error('NewsletterSendSchedule references missing newsletter', [
                'schedule_id' => $schedule->id,
                'newsletter_id' => $schedule->newsletter_id,
            ]);

            // Non-retryable config problem — advance anyway to avoid hammering
            $this->advanceNextRunAt($schedule);
            return 'skipped';
        }

        if ($newsletter->paused) {
            $this->logger->info('Skipping paused newsletter', [
                'schedule_id' => $schedule->id,
                'newsletter_id' => $newsletter->id,
            ]);
            $this->advanceNextRunAt($schedule);
            return 'skipped';
        }

        $sendResult = $this->sendService->sendNewsletter($newsletter, $schedule->site_id);

        if ($sendResult['success'] || ($sendResult['partial_failure'] ?? false)) {
            $this->advanceNextRunAt($schedule);

            $this->logger->info('NewsletterSendSchedule processed', [
                'schedule_id' => $schedule->id,
                'newsletter_id' => $newsletter->id,
                'send_id' => $sendResult['send_id'] ?? null,
                'recipients' => $sendResult['recipients'] ?? 0,
            ]);

            return 'processed';
        }

        // Total failure — do NOT advance next_run_at
        $this->logger->error('NewsletterSendSchedule send failed', [
            'schedule_id' => $schedule->id,
            'newsletter_id' => $newsletter->id,
            'error' => $sendResult['error'] ?? 'Unknown',
        ]);

        return 'failed';
    }

    private function advanceNextRunAt(NewsletterSendSchedule $schedule): void
    {
        $frequency = \App\Enums\Newsletters\ScheduleFrequency::from($schedule->frequency);
        $nextRunAt = $this->calculator->calculate(
            $frequency,
            $schedule->day_of_week,
            $schedule->day_of_month,
            $schedule->time,
        );

        $this->database->transaction(function () use ($schedule, $nextRunAt) {
            $this->scheduleRepository->update($schedule->id, [
                'last_run_at' => now_datetime()->format('Y-m-d H:i:s'),
                'next_run_at' => $nextRunAt->format('Y-m-d H:i:s'),
            ]);
        });
    }
}