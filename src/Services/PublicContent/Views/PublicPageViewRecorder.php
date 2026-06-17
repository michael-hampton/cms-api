<?php

namespace App\Services\PublicContent\Views;

use App\Framework\Support\Cache\Cache;
use App\Repositories\Members\PageViewRepository;

final class PublicPageViewRecorder
{
    private const int VIEW_WINDOW_SECONDS = 1800;
    private const int REQUEST_WINDOW_SECONDS = 60;
    private const int REQUEST_LIMIT = 120;

    public function __construct(
        private readonly PageViewRepository $views,
    ) {
    }

    public function record(
        int $pageId,
        int $siteId,
        ?int $memberId,
        string $ipAddress,
        ?string $userAgent = null,
        ?string $referer = null,
    ): array {
        $identity = $memberId !== null
            ? 'member-' . $memberId
            : 'visitor-' . hash('sha256', $ipAddress);

        $requestState = $this->consumeRequest($siteId, $identity);

        if (!$requestState['allowed']) {
            return [
                'recorded' => false,
                'duplicate' => false,
                'limited' => true,
                'retry_after' => $requestState['retry_after'],
            ];
        }

        $viewKey = sprintf('page-view-%d-%d-%s', $siteId, $pageId, $identity);

        if (Cache::has($viewKey)) {
            return [
                'recorded' => false,
                'duplicate' => true,
                'limited' => false,
                'retry_after' => 0,
            ];
        }

        $this->views->recordView(
            $pageId,
            $memberId,
            $siteId,
            $ipAddress,
            $userAgent,
            $referer,
        );

        Cache::put($viewKey, true, self::VIEW_WINDOW_SECONDS);

        return [
            'recorded' => true,
            'duplicate' => false,
            'limited' => false,
            'retry_after' => 0,
        ];
    }

    private function consumeRequest(int $siteId, string $identity): array
    {
        $key = sprintf('page-view-requests-%d-%s', $siteId, $identity);
        $now = time();
        $state = Cache::get($key, [
            'count' => 0,
            'reset_at' => $now + self::REQUEST_WINDOW_SECONDS,
        ]);

        if (!is_array($state) || (int) ($state['reset_at'] ?? 0) <= $now) {
            $state = [
                'count' => 0,
                'reset_at' => $now + self::REQUEST_WINDOW_SECONDS,
            ];
        }

        $count = (int) ($state['count'] ?? 0);
        $resetAt = (int) ($state['reset_at'] ?? ($now + self::REQUEST_WINDOW_SECONDS));

        if ($count >= self::REQUEST_LIMIT) {
            return [
                'allowed' => false,
                'retry_after' => max(1, $resetAt - $now),
            ];
        }

        Cache::put(
            $key,
            ['count' => $count + 1, 'reset_at' => $resetAt],
            max(1, $resetAt - $now),
        );

        return [
            'allowed' => true,
            'retry_after' => 0,
        ];
    }
}
