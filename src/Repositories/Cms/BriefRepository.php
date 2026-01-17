<?php

namespace App\Repositories\Cms;

use App\Models\Brief;
use App\Models\BriefAttachment;
use App\Models\BriefComment;
use App\Models\Model;
use App\Repositories\Repository;
use App\Search\PaginatedResult;
use App\Search\SearchConfigurationFactory;
use App\Search\SearchCriteria;
use App\Search\SearchEngine;

class BriefRepository extends Repository
{
    private SearchEngine $searchEngine;

    public function __construct()
    {
        parent::__construct();
        $config = SearchConfigurationFactory::create('brief');
        $this->searchEngine = new SearchEngine($config);
    }

    public function search(SearchCriteria $criteria): PaginatedResult
    {
        $query = Brief::with([
            'attachments',
            'attachments.image',
            'attachments.product',
            'comments',
            'comments.user',
            'comments.replies',
            'owner',
            'category'
        ]);

        return $this->searchEngine->search($query, $criteria);
    }

    public function getCompleteBriefData(int $briefId): ?Brief
    {
        return Brief::with([
            'attachments',
            'attachments.image',
            'attachments.product',
            'comments',
            'comments.user',
            'comments.replies',
            'comments.replies.user',
            'owner',
            'category',
            'convertedPage'
        ])->find($briefId);
    }

    public function addAttachment(int $briefId, array $attachmentData): Model
    {
        return BriefAttachment::create([
            'brief_id' => $briefId,
            ...$attachmentData
        ]);
    }

    public function deleteAttachment(int $briefId, int $attachmentId): bool
    {
        return BriefAttachment::where('brief_id', $briefId)
                ->where('id', $attachmentId)
                ->delete() > 0;
    }

    public function addComment(int $briefId, array $commentData): Model
    {
        return BriefComment::create([
            'brief_id' => $briefId,
            ...$commentData
        ]);
    }

    public function deleteComment(int $briefId, int $commentId): bool
    {
        return BriefComment::where('brief_id', $briefId)
                ->where('id', $commentId)
                ->delete() > 0;
    }

    public function markAsConverted(int $briefId, int $pageId): bool
    {
        $brief = $this->find($briefId);
        if (!$brief) {
            return false;
        }

        return $brief->update([
            'status' => 'converted',
            'converted_page_id' => $pageId,
            'converted_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function archive(int $briefId): bool
    {
        $brief = $this->find($briefId);
        if (!$brief) {
            return false;
        }

        return $brief->update(['status' => 'archived']);
    }

    public function getAttachment(int $attachmentId): ?Model
    {
        return BriefAttachment::find($attachmentId);
    }

    protected function getModelClass(): string
    {
        return Brief::class;
    }
}