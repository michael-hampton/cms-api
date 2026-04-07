<?php

namespace App\Services\Cms;

use App\Actions\Brief\DuplicateBrief;
use App\Models\BriefSchedule;
use App\Repositories\Cms\Briefs\BriefScheduleRepository;

class BriefScheduleProcessor
{
    public function __construct(
        private readonly BriefScheduleRepository $scheduleRepository,
        private readonly DuplicateBrief          $duplicateBrief
    )
    {
    }

    public function process(?\DateTime $now = null): void
    {
        $now = $now ?? new \DateTime();
        $schedules = $this->scheduleRepository->findDue($now);

        foreach ($schedules as $schedule) {
            // Idempotency: only proceed if we successfully flipped processing to true.
            // If two cron processes run simultaneously, only one wins this CAS update.
            if (!$this->scheduleRepository->markProcessing($schedule->id)) {
                continue;
            }

            $this->runSchedule($schedule, $now);
        }
    }

    private function runSchedule(BriefSchedule $schedule, \DateTime $now): void
    {
        $this->duplicateBrief->handle(
            $schedule->source_brief_id,
            $schedule->source_brief_id, // owner = source brief's owner; service resolves via getWithRelations
            null,
            null
        );

        $newCount = $schedule->occurrences_count + 1;
        $nextRunAt = $this->calculateNextRunAt(
            $schedule->frequency,
            $schedule->next_run_at,
            $schedule->week_days ?? [],
            $schedule->custom_interval
        );

        $shouldDeactivate = $this->shouldDeactivate($schedule, $newCount, $now);

        $this->scheduleRepository->update($schedule->id, [
            'occurrences_count' => $newCount,
            'next_run_at' => $nextRunAt->format('Y-m-d H:i:s'),
            'active' => !$shouldDeactivate,
            'processing' => false,
        ]);
    }

    public function calculateNextRunAt(
        string    $frequency,
        \DateTime $from,
        array     $weekDays,
        ?int      $customInterval
    ): \DateTime
    {
        $next = clone $from;

        return match ($frequency) {
            'daily' => $next->modify('+1 day'),
            'monthly' => $next->modify('+1 month'),
            'custom' => $next->modify("+{$customInterval} days"),
            'weekly' => $this->nextWeeklyRunAt($from, $weekDays),
            default => $next->modify('+1 day'),
        };
    }

    private function nextWeeklyRunAt(\DateTime $from, array $weekDays): \DateTime
    {
        if (empty($weekDays)) {
            return (clone $from)->modify('+7 days');
        }

        sort($weekDays);
        $candidate = clone $from;
        $candidate->modify('+1 day'); // always at least tomorrow

        // Walk forward up to 7 days to find the next matching weekday
        // PHP: 1 = Monday … 7 = Sunday
        for ($i = 0; $i < 7; $i++) {
            if (in_array((int)$candidate->format('N'), $weekDays, true)) {
                return $candidate;
            }
            $candidate->modify('+1 day');
        }

        return $candidate;
    }

    private function shouldDeactivate(
        BriefSchedule $schedule,
        int           $newCount,
        \DateTime     $now
    ): bool
    {
        return match ($schedule->end_type) {
            'after_occurrences' => $newCount >= $schedule->end_after_occurrences,
            'on_date' => $schedule->end_date && $now >= $schedule->end_date,
            default => false,
        };
    }
}