<?php

namespace App\DTO\Consents;

class ConsentStatisticsDTO
{
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly string $category,
        public readonly int    $totalRecords,
        public readonly int    $granted,
        public readonly int    $active,
        public readonly float  $grantRate
    )
    {
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'category' => $this->category,
            'total_records' => $this->totalRecords,
            'granted' => $this->granted,
            'active' => $this->active,
            'grant_rate' => $this->grantRate
        ];
    }
}