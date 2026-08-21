<?php

namespace App\Repositories\Members;

use App\Framework\Database\Database;
use App\Framework\Support\Collection;
use App\Models\CommunicationLog;
use App\Models\Model;

/**
 * CommunicationLogRepository
 *
 * Provides the CRM-facing member communication history.
 *
 * This is intentionally separate from operational delivery tables such as
 * subscription_communication_deliveries. Delivery tables answer "how/why was
 * this sent?"; communication_logs answers "what has this member received?".
 */
class CommunicationLogRepository
{
    /**
     * Record a communication against a member for CRM/audit history.
     *
     * @param array{
     *     member_id:int,
     *     type?:string,
     *     channel?:string,
     *     subject?:string|null,
     *     preview?:string|null,
     *     status?:string,
     *     template_name?:string|null,
     *     campaign_name?:string|null,
     *     sent_at?:mixed,
     *     opened_at?:mixed
     * } $data
     */
    public function record(array $data): Model
    {
        return CommunicationLog::create([
            'member_id'     => $data['member_id'],
            'type'          => $data['type'] ?? 'transactional',
            'channel'       => $data['channel'] ?? 'email',
            'subject'       => $data['subject'] ?? null,
            'preview'       => $data['preview'] ?? null,
            'status'        => $data['status'] ?? 'sent',
            'template_name' => $data['template_name'] ?? null,
            'campaign_name' => $data['campaign_name'] ?? null,
            'sent_at'       => $data['sent_at'] ?? now_datetime(),
            'opened_at'     => $data['opened_at'] ?? null,
        ]);
    }

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
        ?string $createdFrom = null,
        ?string $createdTo = null,
        ?string $updatedFrom = null,
        ?string $updatedTo = null,
    ): array
    {
        $query = Database::table('communication_logs')
            ->where('member_id', $memberId)
            ->orderByDesc('sent_at');

        if ($type !== null) {
            $query->where('type', $type);
        }

        if (!empty($createdFrom)) {
            $query->where('created_at', '>=', $createdFrom . ' 00:00:00');
        }

        if (!empty($createdTo)) {
            $query->where('created_at', '<=', $createdTo . ' 23:59:59');
        }

        if (!empty($updatedFrom)) {
            $query->where('updated_at', '>=', $updatedFrom . ' 00:00:00');
        }

        if (!empty($updatedTo)) {
            $query->where('updated_at', '<=', $updatedTo . ' 23:59:59');
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
