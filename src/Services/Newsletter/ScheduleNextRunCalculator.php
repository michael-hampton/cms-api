<?php

namespace App\Services\Newsletter;

use App\Enums\Newsletters\ScheduleFrequency;
use InvalidArgumentException;

/**
 * Calculates the next DateTime a schedule should run.
 *
 * Pure value object — no infrastructure dependencies.
 * All inputs are plain values; output is a DateTimeImmutable.
 */
class ScheduleNextRunCalculator
{
    /**
     * @param ScheduleFrequency $frequency
     * @param int|null $dayOfWeek 0=Sun, 6=Sat (required when frequency=weekly)
     * @param int|null $dayOfMonth 1-28 (required when frequency=monthly)
     * @param string $time HH:MM in 24h format
     * @param \DateTimeImmutable $from The reference point (defaults to now)
     */
    public function calculate(
        ScheduleFrequency  $frequency,
        ?int               $dayOfWeek,
        ?int               $dayOfMonth,
        string             $time,
        \DateTimeImmutable $from = new \DateTimeImmutable()
    ): \DateTimeImmutable
    {
        $this->validateInputs($frequency, $dayOfWeek, $dayOfMonth, $time);

        [$hour, $minute] = $this->parseTime($time);

        return match ($frequency) {
            ScheduleFrequency::DAILY => $this->nextDaily($from, $hour, $minute),
            ScheduleFrequency::WEEKLY => $this->nextWeekly($from, $dayOfWeek, $hour, $minute),
            ScheduleFrequency::MONTHLY => $this->nextMonthly($from, $dayOfMonth, $hour, $minute),
        };
    }

    // -------------------------------------------------------------------------

    private function validateInputs(
        ScheduleFrequency $frequency,
        ?int              $dayOfWeek,
        ?int              $dayOfMonth,
        string            $time
    ): void
    {
        if ($frequency === ScheduleFrequency::WEEKLY) {
            if ($dayOfWeek === null || $dayOfWeek < 0 || $dayOfWeek > 6) {
                throw new InvalidArgumentException('day_of_week must be 0–6 for weekly schedules');
            }
        }

        if ($frequency === ScheduleFrequency::MONTHLY) {
            if ($dayOfMonth === null || $dayOfMonth < 1 || $dayOfMonth > 28) {
                throw new InvalidArgumentException('day_of_month must be 1–28 for monthly schedules');
            }
        }

        if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
            throw new InvalidArgumentException('time must be in HH:MM format');
        }

        [$hour, $minute] = $this->parseTime($time);

        if ($hour > 23 || $minute > 59) {
            throw new InvalidArgumentException('time contains an invalid hour or minute value');
        }
    }

    private function parseTime(string $time): array
    {
        [$hour, $minute] = explode(':', $time);
        return [(int)$hour, (int)$minute];
    }

    private function nextDaily(\DateTimeImmutable $from, int $hour, int $minute): \DateTimeImmutable
    {
        $candidate = $from->setTime($hour, $minute, 0);

        if ($candidate <= $from) {
            $candidate = $candidate->modify('+1 day');
        }

        return $candidate;
    }

    // -------------------------------------------------------------------------

    private function nextWeekly(\DateTimeImmutable $from, int $targetDow, int $hour, int $minute): \DateTimeImmutable
    {
        $candidate = $from->setTime($hour, $minute, 0);
        $currentDow = (int)$from->format('w'); // 0=Sun

        $daysUntil = ($targetDow - $currentDow + 7) % 7;

        if ($daysUntil === 0 && $candidate <= $from) {
            $daysUntil = 7;
        }

        return $candidate->modify("+{$daysUntil} days");
    }

    private function nextMonthly(\DateTimeImmutable $from, int $targetDay, int $hour, int $minute): \DateTimeImmutable
    {
        // Cap to 28 to avoid month-end edge cases (already validated)
        $candidate = $from->setDate((int)$from->format('Y'), (int)$from->format('n'), $targetDay)
            ->setTime($hour, $minute, 0);

        if ($candidate <= $from) {
            $candidate = $candidate->modify('+1 month');
        }

        return $candidate;
    }
}