<?php

namespace App\Services\PublicContent\Observability;

use App\Framework\Support\Logger;
use App\Services\PublicContent\Rollout\PublicContentKillSwitch;
use Throwable;

/**
 * Watches the named runtime failure signal and trips the same kill path used
 * for parity regressions when the live failure-rate threshold is breached.
 */
final class PublicContentRuntimeFailureMonitor
{
    public function __construct(
        private readonly PublicContentRuntimeFailureSignal $signal,
        private readonly PublicContentKillSwitch $killSwitch,
        private readonly ?Logger $logger = null,
    ) {
    }

    public function signalName(): string
    {
        return $this->signal->name();
    }

    public function observeSuccess(?int $siteId = null): void
    {
        $this->signal->recordSuccess();
    }

    public function observeFailure(?int $siteId = null, ?Throwable $exception = null): void
    {
        $this->signal->recordFailure();
        $this->maybeKill($siteId, $exception);
    }

    /**
     * Prove the signal without real rollout traffic by injecting synthetic failures.
     *
     * @return array{signal: string, breached: bool, failure_rate: float, killed: bool}
     */
    public function proveWithSyntheticFailures(int $count, ?int $siteId = null): array
    {
        for ($i = 0; $i < $count; $i++) {
            $this->signal->injectSyntheticFailure();
        }

        $killed = $this->maybeKill($siteId);

        return [
            'signal' => $this->signal->name(),
            'breached' => $this->signal->isBreached(),
            'failure_rate' => $this->signal->failureRate(),
            'killed' => $killed,
        ];
    }

    private function maybeKill(?int $siteId, ?Throwable $exception = null): bool
    {
        if (!$this->signal->isBreached()) {
            return false;
        }

        $reason = sprintf(
            'runtime_failure_rate_breach:%.4f>=%.4f',
            $this->signal->failureRate(),
            $this->signal->threshold(),
        );

        if ($siteId !== null) {
            $this->killSwitch->excludeSite($siteId, $reason);
        } else {
            $this->killSwitch->disableGlobally($reason);
        }

        $this->logger?->warning('Public content runtime failure rate breached; kill-switch engaged.', [
            'signal' => $this->signal->name(),
            'failure_rate' => $this->signal->failureRate(),
            'threshold' => $this->signal->threshold(),
            'site_id' => $siteId,
            'exception' => $exception !== null ? $exception::class : null,
            'message' => $exception?->getMessage(),
        ]);

        return true;
    }
}
