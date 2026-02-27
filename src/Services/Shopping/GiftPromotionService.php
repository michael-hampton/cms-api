<?php

namespace App\Services\Shopping;

use App\Framework\Database\Database;
use App\Models\GiftPromotion;
use App\Models\GiftPromotionTrigger;
use App\Models\Model;
use App\Repositories\Shopping\GiftPromotionRepository;
use Exception;

final class GiftPromotionService
{
    public function __construct(
        private readonly GiftPromotionRepository $repository,
        private readonly Database                $database,
    )
    {
    }

    public function paginate(int $siteId, array $filters): array
    {
        return $this->repository->paginate($siteId, $filters);
    }

    public function findById(int $id): ?Model
    {
        return $this->repository->findById($id);
    }

    public function create(int $siteId, array $data): GiftPromotion
    {
        return $this->database->transaction(function () use ($siteId, $data) {

            $promotion = $this->repository->create([
                ...$data,
                'site_id' => $siteId,
            ]);

            if (!empty($data['triggers'])) {
                $this->syncTriggers($promotion, $data['triggers']);
            }

            if (
                $promotion->supportsIssueExclusions() &&
                array_key_exists('excluded_issue_ids', $data)
            ) {
                $this->repository->syncExclusions(
                    $promotion,
                    $data['excluded_issue_ids']
                );
            }

            return $promotion;
        });
    }

    public function update(int $id, array $data): GiftPromotion
    {
        $promotion = $this->repository->find($id);

        if (!$promotion) {
            throw  new \Exception('Gift promotion not found');
        }

        return $this->database->transaction(function () use ($promotion, $data, $id) {

            $this->repository->update($id, $data);

            if (array_key_exists('triggers', $data)) {
                $this->syncTriggers($promotion, $data['triggers']);
            }

            if (
                $promotion->supportsIssueExclusions() &&
                array_key_exists('excluded_issue_ids', $data)
            ) {
                $this->repository->syncExclusions(
                    $promotion,
                    $data['excluded_issue_ids']
                );
            }

            return $promotion->refresh();
        });
    }

    public function toggleActive(int $id): GiftPromotion
    {
        $promotion = $this->repository->find($id);

        if (!$promotion) {
            throw new Exception('Gift promotion not found');
        }

        $this->repository->update($promotion->id, ['active' => !$promotion->active]);

        return $promotion->refresh();
    }

    public function isEligibleForIssue(GiftPromotion $promotion, int $issueDeliveryId): bool
    {
        if (!$promotion->active) {
            return false;
        }

        if (
            $promotion->supportsIssueExclusions() &&
            $promotion->hasExcludedIssue($issueDeliveryId)
        ) {
            return false;
        }

        return true;
    }

    private function syncTriggers(GiftPromotion $promotion, array $triggers): void
    {
        $relation = $promotion->triggers(true);

        $relation->delete();

        foreach ($triggers as $triggerData) {
            GiftPromotionTrigger::create([
                'type' => $triggerData['type'],
                'operator' => $triggerData['operator'],
                'reference_id' => $triggerData['reference_id'] ?? null,
                'value' => $triggerData['value'],
                'promotion_id' => $promotion->id,
            ]);
        }
    }
}