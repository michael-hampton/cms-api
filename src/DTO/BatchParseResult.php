<?php

namespace App\DTO;

class BatchParseResult
{
    private $results;
    private $errors;
    private $successCount;
    private $failedCount;

    public function __construct(array $results, array $errors, int $successCount, int $failedCount)
    {
        $this->results = $results;
        $this->errors = $errors;
        $this->successCount = $successCount;
        $this->failedCount = $failedCount;
    }

    public function getResults(): array
    {
        return $this->results;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    public function getFailedCount(): int
    {
        return $this->failedCount;
    }

    public function getTotalCount(): int
    {
        return $this->successCount + $this->failedCount;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function toArray(): array
    {
        return [
            'results' => $this->results,
            'errors' => $this->errors,
            'summary' => [
                'total' => $this->getTotalCount(),
                'success' => $this->successCount,
                'failed' => $this->failedCount,
                'success_rate' => $this->getTotalCount() > 0
                    ? round(($this->successCount / $this->getTotalCount()) * 100, 2)
                    : 0
            ]
        ];
    }
}