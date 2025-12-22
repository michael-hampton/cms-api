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

    public function subMonths(int $months): self
    {
        return $this->sub(new DateInterval("P{$months}M"));
    }

    public function addMonths(int $months): self
    {
        return $this->add(new DateInterval("P{$months}M"));
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
}