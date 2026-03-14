<?php

namespace App\Actions\Brief;

use App\Enums\BriefStatus;
use App\Framework\Database\Database;
use App\Models\Brief;
use App\Repositories\Cms\Briefs\BriefRepository;
use App\Repositories\Cms\Briefs\BriefTaskRepository;

class DuplicateBrief
{
    public function __construct(
        private readonly BriefRepository     $briefRepository,
        private readonly BriefTaskRepository $subtaskRepository,
        private readonly LogBriefActivity    $logBriefActivity,
        private readonly Database            $database
    )
    {
    }

    /**
     * @param int $briefId Source brief ID
     * @param int $userId Actor performing the clone
     * @param string|null $titleOverride Optional title; falls back to "{original} (Copy)"
     * @param bool $includeSubtasks Whether to clone subtasks (default true)
     */
    public function handle(
        int     $briefId,
        int     $userId,
        ?string $titleOverride = null,
        bool    $includeSubtasks = true
    ): Brief
    {
        return $this->database->transaction(function () use (
            $briefId, $userId, $titleOverride, $includeSubtasks
        ) {
            $original = $this->briefRepository->getWithRelations($briefId);

            if (!$original) {
                throw new \Exception("Brief not found: {$briefId}");
            }

            $newBrief = $this->briefRepository->create([
                'site_id' => $original->site_id,
                'title' => $titleOverride ?? $original->title . ' (Copy)',
                'description' => $original->description,
                'owner_id' => $userId,
                'category_id' => $original->category_id,
                'status' => BriefStatus::DRAFT->value,
                // converted_page_id intentionally omitted — reset to null
                'target_word_count' => $original->target_word_count,
                'seo_keywords' => $original->seo_keywords,
                'target_audience' => $original->target_audience,
                'template_id' => $original->template_id,
            ]);

            // Clone attachments (pre-existing behaviour)
            foreach ($original->attachments as $attachment) {
                $this->briefRepository->addAttachment($newBrief->id, [
                    'type' => $attachment->type,
                    'file_url' => $attachment->file_url,
                    'file_name' => $attachment->file_name,
                    'image_id' => $attachment->image_id,
                    'product_id' => $attachment->product_id,
                    'url' => $attachment->url,
                    'metadata' => $attachment->metadata,
                    'sort_order' => $attachment->sort_order,
                ]);
            }

            if ($includeSubtasks) {
                $this->cloneSubtasks($briefId, $newBrief->id);
            }

            $this->logBriefActivity->handle(
                $newBrief->id,
                $userId,
                'cloned',
                "Cloned from brief #{$briefId}"
            );

            return $this->briefRepository->getWithRelations($newBrief->id);
        });
    }

    private function cloneSubtasks(int $sourceBriefId, int $newBriefId): void
    {
        $subtasks = $this->subtaskRepository->getForBrief($sourceBriefId);

        foreach ($subtasks as $subtask) {
            $this->subtaskRepository->create([
                'brief_id' => $newBriefId,
                'title' => $subtask->title,
                'description' => $subtask->description,
                'status' => $subtask->status,
                'assigned_to' => $subtask->assigned_to,
                'due_date' => $subtask->due_date,
                'sort_order' => $subtask->sort_order,
            ]);
        }
    }
}