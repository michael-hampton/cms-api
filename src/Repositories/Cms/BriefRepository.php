<?php

namespace App\Repositories\Cms;

use App\Models\Brief;
use App\Models\BriefAttachment;
use App\Models\BriefComment;
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

    public function updateComment(int $briefId, int $commentId, string $content): ?Model
    {
        $comment = BriefComment::where('brief_id', $briefId)
            ->where('id', $commentId)
            ->first();

        if (!$comment) {
            return null;
        }

        $comment->update(['content' => $content]);
        return $comment->fresh();
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

    protected function getModelClass(): string
    {
        return Brief::class;
    }
}