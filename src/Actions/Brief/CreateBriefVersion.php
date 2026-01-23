<?php

namespace App\Actions\Brief;

use App\Models\Model;
use App\Repositories\Cms\Briefs\BriefRepository;
use App\Repositories\Cms\Briefs\BriefVersionRepository;

class CreateBriefVersion
{
    public function __construct(
        private readonly BriefRepository        $briefRepository,
        private readonly BriefVersionRepository $versionRepository
    )
    {
    }

    public function handle(int $briefId, int $userId, ?string $changeSummary = null): Model
    {
        $brief = $this->briefRepository->getCompleteBriefData($briefId);

        if (!$brief) {
            throw new \Exception("Brief not found: {$briefId}");
        }

        $latestVersion = $this->versionRepository->getLatest($briefId);
        $versionNumber = $latestVersion ? $latestVersion->version_number + 1 : 1;

        return $this->versionRepository->create([
            'brief_id' => $briefId,
            'version_number' => $versionNumber,
            'title' => $brief->title,
            'description' => $brief->description,
            'data' => [
                'target_word_count' => $brief->target_word_count,
                'seo_keywords' => $brief->seo_keywords,
                'target_audience' => $brief->target_audience,
                'attachments_count' => $brief->attachments->count(),
                'comments_count' => $brief->comments->count()
            ],
            'created_by' => $userId,
            'change_summary' => $changeSummary
        ]);
    }
}