<?php

namespace App\Actions\Brief;

use App\Models\Brief;
use App\Repositories\Cms\Briefs\BriefCollaboratorRepository;

class BulkAssignCollaborator
{
    public function __construct(
        private readonly BriefCollaboratorRepository $collaboratorRepository,
        private readonly LogBriefActivity            $activityService
    )
    {
    }

    public function handle(array $briefIds, int $userId, string $role, int $siteId): int
    {
        $count = 0;

        foreach ($briefIds as $briefId) {
            $existing = $this->collaboratorRepository->findByBriefAndUser($briefId, $userId);

            if ($existing) {
                $this->collaboratorRepository->update($existing->id, ['role' => $role]);
            } else {
                $this->collaboratorRepository->create([
                    'collaboratable_id' => $briefId,
                    'user_id' => $userId,
                    'role' => $role,
                    'assigned_at' => now(),
                    'collaboratable_type' => Brief::class,
                    'site_id' => $siteId
                ]);
            }

            $this->activityService->handle(
                $briefId,
                $userId,
                'collaborator_added',
                "Assigned as {$role}"
            );

            $count++;
        }

        return $count;
    }
}