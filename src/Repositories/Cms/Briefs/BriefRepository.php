<?php

namespace App\Repositories\Cms\Briefs;

use App\Models\Brief;
use App\Models\BriefActivityLog;
use App\Models\BriefAttachment;
use App\Models\BriefCollaborator;
use App\Models\BriefComment;
use App\Models\BriefRelationship;
use App\Models\BriefTask;
use App\Models\BriefVersion;
use App\Models\Image;
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
        $comment = BriefComment::create([
            'brief_id' => $briefId,
            ...$commentData
        ]);

        // Load the user relationship before returning
        return BriefComment::with(['user'])
            ->where('id', $comment->id)
            ->first();
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

    // Updated to be backwards compatible
    public function updateComment(int $briefId, int $commentId, array $data): ?Model
    {
        $comment = BriefComment::where('brief_id', $briefId)
            ->where('id', $commentId)
            ->first();

        if (!$comment) {
            return null;
        }

        // Remove relationships from update
        $updateData = $data;
        unset($updateData['user'], $updateData['resolvedBy'], $updateData['task'], $updateData['replies']);
        $comment->update($updateData);

        return BriefComment::with(['user', 'resolvedBy'])
            ->where('id', $comment->id)
            ->first();
    }

    // In BriefRepository.php
    public function updateAttachment(int $briefId, int $attachmentId, array $data): ?Model
    {
        $attachment = BriefAttachment::where('brief_id', $briefId)
            ->where('id', $attachmentId)
            ->first();

        if (!$attachment) {
            return null;
        }

        // Remove relationship data that shouldn't be updated
        unset($data['image'], $data['product'], $data['brief']);

        // Remove timestamps if present
        unset($data['created_at'], $data['updated_at']);

        // If updating an image attachment and image_id changed
        if ($attachment->type === 'image' &&
            isset($data['image_id']) &&
            $data['image_id'] != $attachment->image_id) {

            // Verify the new image exists
            $image = Image::find($data['image_id']);
            if (!$image) {
                throw new \Exception('Image not found');
            }

            // Update file_url from the actual image
            $data['file_url'] = $image->file_path;
            $data['file_name'] = $image->name ?? basename($image->file_path);
        }

        $attachment->update($data);
        return $attachment->fresh(['image', 'product']);
    }

    public function bulkUpdateStatus(array $briefIds, string $status): bool
    {
        return Brief::whereIn('id', $briefIds)
                ->update(['status' => $status]) > 0;
    }

    public function bulkDelete(array $briefIds): bool
    {
        // Delete related data first
        BriefAttachment::whereIn('brief_id', $briefIds)->delete();
        BriefComment::whereIn('brief_id', $briefIds)->delete();
        BriefCollaborator::whereIn('brief_id', $briefIds)->delete();
        BriefTask::whereIn('brief_id', $briefIds)->delete();
        BriefVersion::whereIn('brief_id', $briefIds)->delete();
        BriefRelationship::whereIn('brief_id', $briefIds)->delete();
        BriefActivityLog::whereIn('brief_id', $briefIds)->delete();

        return Brief::whereIn('id', $briefIds)->delete() > 0;
    }

    public function getWithRelations(int $briefId): ?Brief
    {
        return Brief::with([
            'attachments',
            'attachments.image',
            'attachments.product',
            'comments',
            'comments.user',
            'comments.resolvedBy',
            'comments.task',
            'comments.replies',
            'comments.replies.user',
            'owner',
            'category',
            'template',
            'collaborators',
            'collaborators.user',
            'tasks',
            'tasks.assignee',
            'tasks.creator',
            'versions',
            'versions.creator',
            'relationships',
            'relationships.relatedBrief',
            'relationships.relatedPage',
            'activityLog',
            'activityLog.user',
            'lastActivityUser',
            'parentBrief',
            'childBriefs'
        ])->find($briefId);
    }

    protected function getModelClass(): string
    {
        return Brief::class;
    }
}