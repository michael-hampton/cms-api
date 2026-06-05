<?php

declare(strict_types=1);

namespace App\Repositories\Subscriptions;

use App\Models\Model;
use App\Repositories\Repository;

/**
 * Persistence for the subscription_changes audit table.
 * No business logic.
 */
class SubscriptionChangeRepository extends Repository
{
    public function recordEditionChange(
        int     $subscriptionId,
        int     $oldEditionId,
        int     $newEditionId,
        int     $createdBy,
        ?string $reason = null,
    ): Model {
        return $this->create([
            'subscription_id'  => $subscriptionId,
            'change_type'      => 'edition_change',
            'old_edition_id'   => $oldEditionId,
            'new_edition_id'   => $newEditionId,
            'reason'           => $reason,
            'created_by'       => $createdBy,
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);
    }

    public function recordPublicationChange(
        int     $subscriptionId,
        int     $oldPublicationId,
        int     $newPublicationId,
        int     $oldEditionId,
        int     $newEditionId,
        int     $remainingIssuesTransferred,
        int     $createdBy,
        ?string $reason = null,
    ): Model {
        return $this->create([
            'subscription_id'            => $subscriptionId,
            'change_type'                => 'publication_change',
            'old_publication_id'         => $oldPublicationId,
            'new_publication_id'         => $newPublicationId,
            'old_edition_id'             => $oldEditionId,
            'new_edition_id'             => $newEditionId,
            'remaining_issues_transferred' => $remainingIssuesTransferred,
            'reason'                     => $reason,
            'created_by'                 => $createdBy,
            'created_at'                 => date('Y-m-d H:i:s'),
            'updated_at'                 => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Find all changes for a subscription, newest first.
     */
    public function findBySubscription(int $subscriptionId): mixed
    {
        return $this->query()
            ->where('subscription_id', '=', $subscriptionId)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();
    }

    protected function getModelClass(): string
    {
        return \App\Models\SubscriptionChange::class;
    }
}