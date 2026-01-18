<?php

namespace App\Actions\Brief;

use App\Models\Brief;
use App\Models\BriefVersion;
use App\Models\Model;

class CreateBriefVersion
{
    public static function execute(int $briefId, int $userId, ?string $changeSummary = null): Model
    {
        $brief = Brief::with(['attachments', 'comments'])->find($briefId);

        $latestVersion = BriefVersion::where('brief_id', $briefId)
            ->orderBy('version_number', 'desc')
            ->first();

        $versionNumber = $latestVersion ? $latestVersion->version_number + 1 : 1;

        return BriefVersion::create([
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