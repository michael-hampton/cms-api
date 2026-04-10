<?php

namespace App\Resources\OpenCollab;

use App\Framework\Resource\JsonResource;

/**
 * Exposes the contributor's onboarding completion state.
 * Used by the frontend step indicator and gating middleware.
 */
class OnboardingStatusResource extends JsonResource
{
    public function toArray(): array
    {
        $pendingSteps = $this->getAttribute('pending_steps') ?? [];

        return [
            'is_complete' => empty($pendingSteps),
            'pending_steps' => $pendingSteps,
            'completed_steps' => $this->resolveCompleted($pendingSteps),
            'next_step' => $pendingSteps[0] ?? null,
        ];
    }

    private function resolveCompleted(array $pending): array
    {
        $all = ['profile', 'payment', 'contract', 'guidelines'];
        return array_values(array_diff($all, $pending));
    }
}