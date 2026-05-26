<?php

namespace App\Repositories\Billing;

use App\Framework\Support\Collection;
use App\Models\Attachment;
use App\Repositories\Repository;

class AttachmentRepository extends Repository
{
    public function findByMember(int $memberId, int $siteId): Collection
    {
        return Attachment::where('member_id', $memberId)
            ->where('site_id', $siteId)
            ->orderByDesc('created_at')
            ->get();
    }

    public function findByEntity(string $type, int $id): Collection
    {
        return Attachment::where('attachmentable_type', $type)
            ->where('attachmentable_id', $id)
            ->orderByDesc('created_at')
            ->get();
    }

    protected function getModelClass(): string
    {
        return Attachment::class;
    }
}