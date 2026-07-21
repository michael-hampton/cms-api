<?php

namespace App\Services\PublicContent\Parity;

use App\Services\PublicContent\Observability\PublicContentRuntimeFailureSignal;
use App\Services\PublicContent\Rollout\PublicContentKillSwitch;

/**
 * Parity regression kill path — shared with the runtime failure monitor.
 *
 * A sustained mismatch rate against the same threshold trips brand or global kill.
 */
final class PublicContentParityKillPath
{
    public function __construct(
        private readonly PublicContentRuntimeFailureSignal $signal,
        private readonly PublicContentKillSwitch $killSwitch,
    ) {
    }

    public function recordMismatch(?int $siteId = null): void
    {
        $this->signal->recordFailure();
        $this->tripIfBreached($siteId, 'parity_regression');
    }

    public function recordMatch(): void
    {
        $this->signal->recordSuccess();
    }

    private function tripIfBreached(?int $siteId, string $reasonPrefix): void
    {
        if (!$this->signal->isBreached()) {
            return;
        }

        $reason = sprintf(
            '%s:%.4f>=%.4f',
            $reasonPrefix,
            $this->signal->failureRate(),
            $this->signal->threshold(),
        );

        if ($siteId !== null) {
            $this->killSwitch->excludeSite($siteId, $reason);
        } else {
            $this->killSwitch->disableGlobally($reason);
        }
    }
}
