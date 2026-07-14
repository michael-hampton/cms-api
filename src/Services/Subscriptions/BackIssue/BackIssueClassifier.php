<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\BackIssue;

use App\Enums\Subscriptions\FulfilmentTypeEnum;
use App\Models\IssueDelivery;
use App\Repositories\Subscriptions\LabelRunRepository;

/**
 * Decides whether a fulfilment being created for a given IssueDelivery is a
 * STANDARD fulfilment (the normal subscription pipeline will pick it up via
 * the Label Run workflow) or a BACK_ISSUE fulfilment (the issue has already
 * been through its Label Run, so it must be dispatched separately by
 * BackIssueReplacementCopyDispatchService).
 *
 * This class only decides — it does not query for candidates, write
 * anything, or dispatch anything. It has one reason to change: the rules
 * for what counts as "already printed".
 */
class BackIssueClassifier
{
    public function __construct(
        private readonly LabelRunRepository $labelRunRepository,
    ) {
    }

    /**
     * An issue is classified as already printed — and therefore a fulfilment
     * for it must be a BACK_ISSUE — when either:
     *   - a LabelRun for one of its fulfilments has already completed, or
     *   - it already went on sale (on_sale_date is in the past).
     *
     * The second check covers issues whose Label Run batch hasn't run yet
     * (or was skipped) but which are nonetheless already released and
     * purchasable as back issues per IssueAvailabilityPolicy — i.e. the
     * issue's normal fulfilment window has passed even though no LabelRun
     * row exists yet.
     */
    public function classify(IssueDelivery $issueDelivery): FulfilmentTypeEnum
    {
        return $this->isAlreadyPrinted($issueDelivery)
            ? FulfilmentTypeEnum::BACK_ISSUE
            : FulfilmentTypeEnum::STANDARD;
    }

    private function isAlreadyPrinted(IssueDelivery $issueDelivery): bool
    {
        if ($this->labelRunRepository->hasCompletedRunForIssueDelivery((int) $issueDelivery->id)) {
            return true;
        }

        return $issueDelivery->on_sale_date instanceof \DateTimeInterface
            && $issueDelivery->on_sale_date < new \DateTime();
    }
}
