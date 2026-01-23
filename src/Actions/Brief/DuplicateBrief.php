<?php

namespace App\Actions\Brief;

use App\Framework\Database\Database;
use App\Models\Brief;
use App\Repositories\Cms\Briefs\BriefRepository;

class DuplicateBrief
{
    public function __construct(
        private readonly BriefRepository  $briefRepository,
        private readonly LogBriefActivity $logBriefActivity,
        private readonly Database         $database
    )
    {
    }

    public function handle(int $briefId, int $userId): Brief
    {
        return $this->database->transaction(function () use ($briefId, $userId) {
            $original = $this->briefRepository->getWithRelations($briefId);

            if (!$original) {
                throw new \Exception("Brief not found: {$briefId}");
            }

            $newBriefData = [
                'site_id' => $original->site_id,
                'title' => $original->title . ' (Copy)',
                'description' => $original->description,
                'owner_id' => $userId,
                'category_id' => $original->category_id,
                'status' => 'draft',
                'target_word_count' => $original->target_word_count,
                'seo_keywords' => $original->seo_keywords,
                'target_audience' => $original->target_audience,
                'template_id' => $original->template_id
            ];

            $newBrief = $this->briefRepository->create($newBriefData);

            // Copy attachments
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

            $this->logBriefActivity->handle(
                $newBrief->id,
                $userId,
                'duplicated',
                "Duplicated from brief #{$briefId}"
            );

            return $this->briefRepository->getWithRelations($newBrief->id);
        });
    }
}