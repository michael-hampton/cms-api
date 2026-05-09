<?php

namespace App\Repositories\Members;

use App\Framework\Database\Database;

/**
 * CommunicationLogRepository
 *
 * Reads from the communication_logs table (or equivalent — rename to match
 * your schema). This table should be populated by your email-sending layer
 * (Mailgun/SES/etc. webhook listeners or inline when dispatching mail).
 */
class CommunicationLogRepository
{
    /**
     * Return a paginated list of communication log entries for a member.
     *
     * @param int $memberId
     * @param string|null $type null = all, 'transactional', or 'marketing'
     * @param int $page
     * @param int $perPage
     *
     * @return array{ data: array, total: int, last_page: int }
     */
    public function getPaginatedForMember(
        int     $memberId,
        ?string $type = null,
        int     $page = 1,
        int     $perPage = 15,
    ): array
    {
        $query = Database::table('communication_logs')
            ->where('member_id', $memberId)
            ->orderByDesc('sent_at');

        if ($type !== null) {
            $query->where('type', $type);
        }

        $total = (clone $query)->count();
        $lastPage = max(1, (int)ceil($total / $perPage));

        $data = $query
            ->forPage($page, $perPage)
            ->get()
            ->all();

        return [
            'data' => $data,
            'total' => $total,
            'last_page' => $lastPage,
        ];
    }
}