<?php

namespace App\Resources\OpenCollab;

use App\Framework\Resource\JsonResource;

class ReadabilityScoreResource extends JsonResource
{
    public function toArray(): array
    {
        $score = (float)($this->getAttribute('readability_score') ?? 0);

        return [
            'article_id' => $this->getAttribute('article_id'),
            'readability_score' => $score,
            'grade' => $this->grade($score),
            'label' => $this->label($score),
            'last_calculated_at' => $this->getAttribute('last_calculated_at'),
        ];
    }

    private function grade(float $score): string
    {
        return match (true) {
            $score >= 80 => 'A',
            $score >= 65 => 'B',
            $score >= 50 => 'C',
            $score >= 35 => 'D',
            default => 'F',
        };
    }

    private function label(float $score): string
    {
        return match (true) {
            $score >= 80 => 'Very easy to read',
            $score >= 65 => 'Easy to read',
            $score >= 50 => 'Fairly easy',
            $score >= 35 => 'Difficult',
            default => 'Very difficult',
        };
    }
}