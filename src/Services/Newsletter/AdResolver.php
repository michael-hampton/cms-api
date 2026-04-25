<?php

namespace App\Services\Newsletter;

use App\Framework\Support\Logger;
use App\Models\Member;
use App\Services\Adverts\PromotionInjector;

/**
 * Resolves template ad slots using the existing adverts promotion injector.
 * The template block only decides whether a slot exists and where it sits;
 * the actual advert content is built by the shared adverts/newsletter flow.
 */
class AdResolver
{
    /** @var array<string, array|null> Runtime cache keyed by "{siteId}:{placement}:{memberId}" */
    private array $cache = [];

    public function __construct(
        private readonly PromotionInjector $promotionInjector,
        private readonly Logger            $logger,
    )
    {
    }

    public function resolveBlock(string $placement, int $siteId, ?Member $member = null): ?array
    {
        $cacheKey = $this->cacheKey($placement, $siteId, $member);

        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        try {
            $blocks = $this->promotionInjector->getBlocksForSurface(
                surfaceType: 'newsletter_issue',
                surfaceId: $siteId,
                member: $member,
                siteId: $siteId,
                channel: 'newsletter',
            );

            foreach (['top', 'mid', 'bottom'] as $slotPlacement) {
                $this->cache[$this->cacheKey($slotPlacement, $siteId, $member)] =
                    $blocks[$this->placementIndex($slotPlacement)] ?? null;
            }

            return $this->cache[$cacheKey] ?? null;
        } catch (\Throwable $e) {
            $this->logger->error('AdResolver failed to resolve block', [
                'placement' => $placement,
                'site_id' => $siteId,
                'error' => $e->getMessage(),
            ]);

            $this->cache[$cacheKey] = null;
            return null;
        }
    }

    private function cacheKey(string $placement, int $siteId, ?Member $member): string
    {
        return "{$siteId}:{$placement}:" . ($member?->id ?? 0);
    }

    private function placementIndex(string $placement): int
    {
        return match ($placement) {
            'top' => 0,
            'bottom' => 2,
            default => 1,
        };
    }

    public function warmCache(int $siteId, ?Member $member = null): void
    {
        try {
            $blocks = $this->promotionInjector->getBlocksForSurface(
                surfaceType: 'newsletter_issue',
                surfaceId: $siteId,
                member: $member,
                siteId: $siteId,
                channel: 'newsletter',
            );

            foreach (['top', 'mid', 'bottom'] as $placement) {
                $this->cache[$this->cacheKey($placement, $siteId, $member)] =
                    $blocks[$this->placementIndex($placement)] ?? null;
            }
        } catch (\Throwable $e) {
            $this->logger->error('AdResolver cache warm failed', [
                'site_id' => $siteId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function clearCache(): void
    {
        $this->cache = [];
    }
}
