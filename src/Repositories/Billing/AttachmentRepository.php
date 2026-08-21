<?php

namespace App\Repositories\Billing;

use App\Framework\Support\Collection;
use App\Models\Attachment;
use App\Repositories\Repository;

class AttachmentRepository extends Repository
{
    public function findByMember(int $memberId, int $siteId, array $filters = []): Collection
    {
        $query = Attachment::where('member_id', $memberId)
            ->where('site_id', $siteId);

        self::applyDateFilters($query, $filters);

        return $query->orderByDesc('created_at')->get();
    }

    public function findByEntity(string $type, int $id, array $filters = []): Collection
    {
        $query = Attachment::where('attachmentable_type', $type)
            ->where('attachmentable_id', $id);

        self::applyDateFilters($query, $filters);

        return $query->orderByDesc('created_at')->get();
    }

    private static function applyDateFilters($query, array $filters): void
    {
        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from'] . ' 00:00:00');
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }

        if (!empty($filters['updated_from'])) {
            $query->where('updated_at', '>=', $filters['updated_from'] . ' 00:00:00');
        }

        if (!empty($filters['updated_to'])) {
            $query->where('updated_at', '<=', $filters['updated_to'] . ' 23:59:59');
        }
    }

    protected function getModelClass(): string
    {
        return Attachment::class;
    }
}