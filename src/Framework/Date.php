<?php

namespace App\Framework;

use DateInterval;
use DateTime;

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

    public function startOfMonth(): DateTime
    {
        return $this->setDate($this->format('Y'), $this->format('m'), 1)
            ->setTime(0, 0, 0);
    }

    public function endOfMonth(): DateTime
    {
        return $this->setDate($this->format('Y'), $this->format('m'), $this->format('t'))
            ->setTime(23, 59, 59);
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


    function diffForHumans()
    {

        $now = new DateTime();

        $diff = $now->diff($this);

        $isPast = $this < $now;

        $units = [
            'y' => 'year',
            'm' => 'month',
            'd' => 'day',
            'h' => 'hour',
            'i' => 'minute',
            's' => 'second'
        ];

        foreach ($units as $key => $name) {
            $value = $diff->$key;

            if ($value > 0) {
                $plural = $value > 1 ? 's' : '';
                $timeString = $value . ' ' . $name . $plural;

                return $isPast
                    ? $timeString . ' ago'
                    : 'in ' . $timeString;
            }
        }

        return 'just now';
    }
}