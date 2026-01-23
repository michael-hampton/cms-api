<?php

namespace App\Actions\Brief;

use App\Models\Model;
use App\Repositories\Cms\Briefs\BriefActivityLogRepository;
use App\Repositories\Cms\Briefs\BriefRepository;

class LogBriefActivity
{
    public function __construct(
        private readonly BriefActivityLogRepository $activityLogRepository,
        private readonly BriefRepository            $briefRepository
    )
    {
    }

    public function handle(int $briefId, int $userId, string $action, string $description, array $metadata = []): Model
    {
        $this->activityLogRepository->create([
            'brief_id' => $briefId,
            'user_id' => $userId,
            'action' => $action,
            'description' => $description,
            'metadata' => $metadata
        ]);

        return $this->briefRepository->updateLastActivity($briefId, $userId);
    }
}