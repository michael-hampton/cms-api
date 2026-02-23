<?php

namespace App\Services\Newsletter;

use App\Enums\Newsletters\NewsletterScheduleStatus;
use App\Enums\Newsletters\ScheduleFrequency;
use App\Events\Newsletters\NewsletterCreationScheduleCreated;
use App\Events\Newsletters\NewsletterCreationScheduleUpdated;
use App\Events\Newsletters\NewsletterSendScheduleCreated;
use App\Events\Newsletters\NewsletterSendScheduleUpdated;
use App\Framework\Database\Database;
use App\Models\NewsletterCreationSchedule;
use App\Models\NewsletterSendSchedule;
use App\Repositories\Newsletters\NewsletterCreationScheduleRepository;
use App\Repositories\Newsletters\NewsletterSendScheduleRepository;
use DomainException;

class NewsletterScheduleService
{
    public function __construct(
        private readonly NewsletterCreationScheduleRepository $creationRepo,
        private readonly NewsletterSendScheduleRepository     $sendRepo,
        private readonly ScheduleNextRunCalculator            $calculator,
        private readonly Database                             $database,
    )
    {
    }

    // =========================================================================
    // Creation Schedule
    // =========================================================================

    public function createCreationSchedule(int $newsletterId, int $siteId, array $data): NewsletterCreationSchedule
    {
        if ($this->creationRepo->hasActiveScheduleForNewsletter($newsletterId)) {
            throw new DomainException('An active creation schedule already exists for this newsletter. Update or pause it first.');
        }

        $frequency = ScheduleFrequency::from($data['frequency']);
        $dayOfWeek = $data['day_of_week'] ?? null;
        $dayOfMonth = $data['day_of_month'] ?? null;
        $time = $data['time'];
        $nextRunAt = $this->calculator->calculate($frequency, $dayOfWeek !== null ? (int)$dayOfWeek : null, $dayOfMonth !== null ? (int)$dayOfMonth : null, $time);

        $schedule = $this->database->transaction(function () use ($newsletterId, $siteId, $frequency, $dayOfWeek, $dayOfMonth, $time, $nextRunAt) {
            return $this->creationRepo->create([
                'newsletter_id' => $newsletterId,
                'site_id' => $siteId,
                'frequency' => $frequency->value,
                'day_of_week' => $dayOfWeek !== null ? (int)$dayOfWeek : null,
                'day_of_month' => $dayOfMonth !== null ? (int)$dayOfMonth : null,
                'time' => $time,
                'status' => NewsletterScheduleStatus::ACTIVE->value,
                'next_run_at' => $nextRunAt->format('Y-m-d H:i:s'),
            ]);
        });

        event(new NewsletterCreationScheduleCreated($schedule));

        return $schedule;
    }

    public function updateCreationSchedule(int $scheduleId, array $data): NewsletterCreationSchedule
    {
        $schedule = $this->creationRepo->find($scheduleId);

        if (!$schedule) {
            throw new DomainException('Creation schedule not found.');
        }

        if ($schedule->isCancelled()) {
            throw new DomainException('Cannot update a cancelled schedule.');
        }

        $updates = $this->buildScheduleUpdates($schedule, $data);

        $updated = $this->database->transaction(function () use ($scheduleId, $updates) {
            return $this->creationRepo->update($scheduleId, $updates);
        });

        event(new NewsletterCreationScheduleUpdated($updated));

        return $updated;
    }

    /**
     * Build update payload, recalculating next_run_at only when schedule params change.
     * A status-only update (pause/resume) will not recalculate next_run_at.
     */
    private function buildScheduleUpdates(object $existing, array $data): array
    {
        $updates = [];

        // Status update — pause / resume
        if (array_key_exists('status', $data)) {
            $updates['status'] = NewsletterScheduleStatus::from($data['status'])->value;

            // Resuming: recalculate next_run_at from now
            if ($updates['status'] === NewsletterScheduleStatus::ACTIVE->value) {
                $frequency = ScheduleFrequency::from($data['frequency'] ?? $existing->frequency);
                $dayOfWeek = array_key_exists('day_of_week', $data) ? $data['day_of_week'] : $existing->day_of_week;
                $dayOfMonth = array_key_exists('day_of_month', $data) ? $data['day_of_month'] : $existing->day_of_month;
                $time = $data['time'] ?? $existing->time;

                $nextRunAt = $this->calculator->calculate($frequency, $dayOfWeek !== null ? (int)$dayOfWeek : null, $dayOfMonth !== null ? (int)$dayOfMonth : null, $time);
                $updates['next_run_at'] = $nextRunAt->format('Y-m-d H:i:s');
            }
        }

        // Schedule param updates — always recalculate next_run_at
        $scheduleParamKeys = ['frequency', 'day_of_week', 'day_of_month', 'time'];
        $scheduleParamsChanged = count(array_intersect_key($data, array_flip($scheduleParamKeys))) > 0;

        if ($scheduleParamsChanged) {
            $frequency = ScheduleFrequency::from($data['frequency'] ?? $existing->frequency);
            $dayOfWeek = array_key_exists('day_of_week', $data) ? $data['day_of_week'] : $existing->day_of_week;
            $dayOfMonth = array_key_exists('day_of_month', $data) ? $data['day_of_month'] : $existing->day_of_month;
            $time = $data['time'] ?? $existing->time;

            $nextRunAt = $this->calculator->calculate($frequency, $dayOfWeek !== null ? (int)$dayOfWeek : null, $dayOfMonth !== null ? (int)$dayOfMonth : null, $time);
            $updates['next_run_at'] = $nextRunAt->format('Y-m-d H:i:s');

            foreach ($scheduleParamKeys as $key) {
                if (array_key_exists($key, $data)) {
                    $updates[$key] = $data[$key];
                }
            }
        }

        return $updates;
    }

    // =========================================================================
    // Send Schedule
    // =========================================================================

    public function cancelCreationSchedule(int $scheduleId): NewsletterCreationSchedule
    {
        $schedule = $this->creationRepo->find($scheduleId);

        if (!$schedule) {
            throw new DomainException('Creation schedule not found.');
        }

        return $this->database->transaction(function () use ($scheduleId) {
            return $this->creationRepo->update($scheduleId, [
                'status' => NewsletterScheduleStatus::CANCELLED->value,
                'next_run_at' => null,
            ]);
        });
    }

    public function createSendSchedule(int $newsletterId, int $siteId, array $data): NewsletterSendSchedule
    {
        if ($this->sendRepo->hasActiveScheduleForNewsletter($newsletterId)) {
            throw new DomainException('An active send schedule already exists for this newsletter. Update or pause it first.');
        }

        $frequency = ScheduleFrequency::from($data['frequency']);
        $dayOfWeek = $data['day_of_week'] ?? null;
        $dayOfMonth = $data['day_of_month'] ?? null;
        $time = $data['time'];
        $nextRunAt = $this->calculator->calculate($frequency, $dayOfWeek !== null ? (int)$dayOfWeek : null, $dayOfMonth !== null ? (int)$dayOfMonth : null, $time);

        $schedule = $this->database->transaction(function () use ($newsletterId, $siteId, $data, $frequency, $dayOfWeek, $dayOfMonth, $time, $nextRunAt) {
            return $this->sendRepo->create([
                'newsletter_id' => $newsletterId,
                'site_id' => $siteId,
                'creation_schedule_id' => $data['creation_schedule_id'] ?? null,
                'frequency' => $frequency->value,
                'day_of_week' => $dayOfWeek !== null ? (int)$dayOfWeek : null,
                'day_of_month' => $dayOfMonth !== null ? (int)$dayOfMonth : null,
                'time' => $time,
                'status' => NewsletterScheduleStatus::ACTIVE->value,
                'next_run_at' => $nextRunAt->format('Y-m-d H:i:s'),
            ]);
        });

        event(new NewsletterSendScheduleCreated($schedule));

        return $schedule;
    }

    public function updateSendSchedule(int $scheduleId, array $data): NewsletterSendSchedule
    {
        $schedule = $this->sendRepo->find($scheduleId);

        if (!$schedule) {
            throw new DomainException('Send schedule not found.');
        }

        if ($schedule->isCancelled()) {
            throw new DomainException('Cannot update a cancelled schedule.');
        }

        $updates = $this->buildScheduleUpdates($schedule, $data);

        $updated = $this->database->transaction(function () use ($scheduleId, $updates) {
            return $this->sendRepo->update($scheduleId, $updates);
        });

        event(new NewsletterSendScheduleUpdated($updated));

        return $updated;
    }

    // =========================================================================
    // Internals
    // =========================================================================

    public function cancelSendSchedule(int $scheduleId): NewsletterSendSchedule
    {
        $schedule = $this->sendRepo->find($scheduleId);

        if (!$schedule) {
            throw new DomainException('Send schedule not found.');
        }

        return $this->database->transaction(function () use ($scheduleId) {
            return $this->sendRepo->update($scheduleId, [
                'status' => NewsletterScheduleStatus::CANCELLED->value,
                'next_run_at' => null,
            ]);
        });
    }
}