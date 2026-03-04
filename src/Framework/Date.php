<?php

namespace App\Framework;

use DateInterval;

class Date extends \DateTime
{
    public function lt(\DateTime $other): bool
    {
        return $this < $other;
    }

    public function gt(\DateTime $other): bool
    {
        return $this > $other;
    }

    public function subDays(int $days): self
    {
        return $this->sub(new DateInterval("P{$days}D"));
    }

    public function subDay(): self
    {
        return $this->sub(new DateInterval("P1D"));
    }

    public function subMonths(int $months): self
    {
        return $this->sub(new DateInterval("P{$months}M"));
    }

    public function addMonths(int $months): self
    {
        return $this->add(new DateInterval("P{$months}M"));
    }

    public function subMinutes(int $minutes): self
    {
        return $this->sub(new \DateInterval("PT{$minutes}M"));
    }

    public function addMinutes(int $minutes): self
    {
        return $this->add(new \DateInterval("PT{$minutes}M"));
    }

    public function addDay(): self
    {
        return $this->add(new DateInterval("P1D"));
    }

    public function addDays(int $days): self
    {
        return $this->add(new DateInterval("P{$days}D"));
    }

    public function diffInDays(\DateTime $other): int
    {
        return (int)$this->diff($other)->days;
    }

    public function toDateString(): string
    {
        return $this->format('Y-m-d');
    }

    public function toDateTimeString(): string
    {
        return $this->format('Y-m-d H:i:s');
    }

    public function startOfDay(): self
    {
        return $this->setTime(0, 0, 0);
    }

    public function subHours(int $hours): self
    {
        return $this->sub(new DateInterval("PT{$hours}H"));
    }

    public function addHours(int $hours): self
    {
        return $this->add(new DateInterval("PT{$hours}H"));
    }

    public function subWeeks(int $weeks): self
    {
        return $this->sub(new DateInterval("P{$weeks}W"));
    }

    public function addWeeks(int $weeks): self
    {
        return $this->add(new DateInterval("P{$weeks}W"));
    }

    public static function parseDate(string $value): ?\DateTimeImmutable
    {
        $formats = [
            'Y-m-d H:i:s',
            'Y-m-d\TH:i',
        ];

        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);

            if ($date && $date->format($format) === $value) {
                return $date;
            }
        }

        return null;
    }
}