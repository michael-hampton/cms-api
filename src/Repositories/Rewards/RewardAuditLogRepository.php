<?php

namespace App\Repositories\Rewards;

use App\Framework\Support\Collection;
use App\Models\Model;
use App\Models\RewardAuditLog;
use App\Repositories\Repository;

class RewardAuditLogRepository extends Repository
{
    public function logAction(
        int     $memberRewardId,
        string  $action,
        ?int    $userId = null,
        ?string $oldStatus = null,
        ?string $newStatus = null,
        ?array  $oldData = null,
        ?array  $newData = null,
        ?string $notes = null,
        ?int    $rewardDefinitionId = null
    ): Model
    {
        return RewardAuditLog::create([
            'member_reward_id' => $memberRewardId,
            'reward_definition_id' => $rewardDefinitionId,
            'user_id' => $userId,
            'action' => $action,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'old_data' => $oldData,
            'new_data' => $newData,
            'notes' => $notes,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
    }

    public function getLogsForReward(int $memberRewardId): Collection
    {
        return RewardAuditLog::where('member_reward_id', $memberRewardId)
            ->with(['user'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getLogsByAction(string $action, int $limit = 100): Collection
    {
        return RewardAuditLog::where('action', $action)
            ->with(['memberReward', 'user'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getRecentLogs(int $limit = 50): Collection
    {
        return RewardAuditLog::with(['memberReward', 'user', 'rewardDefinition'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getLogsByDateRange(string $dateFrom, string $dateTo): Collection
    {
        return RewardAuditLog::whereBetween('created_at', [$dateFrom, $dateTo])
            ->with(['memberReward', 'user'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getLogsByUser(int $userId, int $limit = 100): Collection
    {
        return RewardAuditLog::where('user_id', $userId)
            ->with(['memberReward'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    protected function getModelClass(): string
    {
        return RewardAuditLog::class;
    }
}