<?php

namespace App\Services\MemberInsights\Segmentation;

use App\Framework\Support\Collection;
use App\Models\Segment;
use App\Models\Subscription;
use App\Repositories\MemberInsights\SegmentPreviewRepository;

/**
 * Dry-run evaluation of a segment against active subscriptions.
 *
 * Contract:
 *   - NO assignments are written.
 *   - Returns a count of matching subscriptions.
 *   - Returns a sample of matching subscription records (default: 10).
 *
 * The repository drives chunked iteration so large subscriber bases do not
 * blow memory. We stop collecting sample records once the cap is reached but
 * continue counting to give an accurate total.
 */
class SegmentPreviewService
{
    private const DEFAULT_SAMPLE_SIZE = 10;

    public function __construct(
        private readonly SegmentRuleEngine        $ruleEngine,
        private readonly SegmentPreviewRepository $previewRepository,
    ) {
    }

    /**
     * @return array{count: int, sample: Subscription[]}
     */
    public function preview(Segment $segment, int $sampleSize = self::DEFAULT_SAMPLE_SIZE): array
    {
        $count  = 0;
        $sample = [];

        $this->previewRepository->chunkActiveSubscriptionsForSegment(
            $segment,
            function (Collection $subscriptions) use ($segment, $sampleSize, &$count, &$sample) {
                foreach ($subscriptions as $subscription) {
                    if (!$this->ruleEngine->matches($subscription, $segment)) {
                        continue;
                    }

                    $count++;

                    if (count($sample) < $sampleSize) {
                        $sample[] = $subscription;
                    }
                }
            }
        );

        return [
            'count'  => $count,
            'sample' => $sample,
        ];
    }
}