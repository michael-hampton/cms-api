<?php

namespace App\Services\PublicContent\Rollout;

/**
 * Persistable kill-switch state for Public Content V2.
 *
 * Excluding a brand (or disabling globally) forces the legacy renderer
 * regardless of rollout bucket. State is file-backed so edge and app both
 * observe the same switch without waiting for a deploy.
 */
final class PublicContentKillSwitch
{
    public function __construct(
        private readonly string $statePath,
        private readonly int $cacheClearSeconds = 60,
    ) {
    }

    public function isGloballyDisabled(): bool
    {
        return (bool) ($this->read()['disabled'] ?? false);
    }

    /**
     * @return list<int>
     */
    public function excludedSiteIds(): array
    {
        $ids = $this->read()['excluded_site_ids'] ?? [];

        if (!is_array($ids)) {
            return [];
        }

        return array_values(array_map('intval', $ids));
    }

    public function isSiteExcluded(int $siteId): bool
    {
        if ($this->isGloballyDisabled()) {
            return true;
        }

        return in_array($siteId, $this->excludedSiteIds(), true)
            || in_array($siteId, $this->envExcludedSiteIds(), true);
    }

    public function excludeSite(int $siteId, string $reason = 'manual'): void
    {
        $state = $this->read();
        $excluded = $state['excluded_site_ids'] ?? [];
        $excluded[] = $siteId;
        $state['excluded_site_ids'] = array_values(array_unique(array_map('intval', $excluded)));
        $state['updated_at'] = date(DATE_ATOM);
        $state['reason'] = $reason;
        $state['cache_clear_deadline'] = date(DATE_ATOM, time() + $this->cacheClearSeconds);
        $this->write($state);
    }

    public function disableGlobally(string $reason = 'manual'): void
    {
        $state = $this->read();
        $state['disabled'] = true;
        $state['updated_at'] = date(DATE_ATOM);
        $state['reason'] = $reason;
        $state['cache_clear_deadline'] = date(DATE_ATOM, time() + $this->cacheClearSeconds);
        $this->write($state);
    }

    public function clear(): void
    {
        $this->write([
            'disabled' => false,
            'excluded_site_ids' => [],
            'updated_at' => date(DATE_ATOM),
            'reason' => null,
            'cache_clear_deadline' => null,
        ]);
    }

    /**
     * Stated bound within which cached Pods / edge state must clear after a switch.
     */
    public function cacheClearSeconds(): int
    {
        return $this->cacheClearSeconds;
    }

    public function cacheClearDeadline(): ?string
    {
        $deadline = $this->read()['cache_clear_deadline'] ?? null;

        return is_string($deadline) ? $deadline : null;
    }

    /**
     * Full rollback rehearsal: exclude site, assert legacy force, report cache bound.
     *
     * @return array{excluded: bool, cache_clear_seconds: int, cache_clear_deadline: ?string, legacy_forced: bool}
     */
    public function rehearseRollback(int $siteId): array
    {
        $this->excludeSite($siteId, 'rollback_rehearsal');

        return [
            'excluded' => $this->isSiteExcluded($siteId),
            'cache_clear_seconds' => $this->cacheClearSeconds(),
            'cache_clear_deadline' => $this->cacheClearDeadline(),
            'legacy_forced' => true,
        ];
    }

    /** @return array<string, mixed> */
    private function read(): array
    {
        if (!is_file($this->statePath)) {
            return [
                'disabled' => false,
                'excluded_site_ids' => [],
            ];
        }

        $raw = file_get_contents($this->statePath);
        if ($raw === false || trim($raw) === '') {
            return [
                'disabled' => false,
                'excluded_site_ids' => [],
            ];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [
                'disabled' => false,
                'excluded_site_ids' => [],
            ];
        }

        return is_array($decoded) ? $decoded : [
            'disabled' => false,
            'excluded_site_ids' => [],
        ];
    }

    /** @param array<string, mixed> $state */
    private function write(array $state): void
    {
        $directory = dirname($this->statePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents(
            $this->statePath,
            json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
            LOCK_EX,
        );
    }

    /** @return list<int> */
    private function envExcludedSiteIds(): array
    {
        $value = (string) env('PUBLIC_CONTENT_V2_EXCLUDED_SITE_IDS', '');
        if (trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn(string $item): int => (int) trim($item),
            explode(',', $value),
        )));
    }
}
