<?php

namespace App\Services\PublicContent\Routing;

/**
 * Audience selector for a Flexi-style override branch.
 * subscriber_status is an open text value (not a fixed enum).
 */
final readonly class RouteOverrideAudience
{
    public function __construct(
        public string $language,
        public string $territory,
        public ?string $subscriberStatus = null,
    ) {
    }

    public function selectorKey(): string
    {
        $status = $this->normaliseSubscriberStatus($this->subscriberStatus);

        return strtolower($this->language) . '|' . strtolower($this->territory) . '|' . $status;
    }

    /**
     * not-connected, blank and absent are the same non-subscriber case.
     */
    public static function normaliseSubscriberStatus(?string $status): string
    {
        $trimmed = strtolower(trim((string) $status));

        if ($trimmed === '' || $trimmed === 'not-connected' || $trimmed === 'not_connected') {
            return '';
        }

        return $trimmed;
    }

    public function matchesRequest(string $language, string $territory, ?string $subscriberStatus): bool
    {
        if (strcasecmp($this->language, $language) !== 0 || strcasecmp($this->territory, $territory) !== 0) {
            return false;
        }

        return self::normaliseSubscriberStatus($this->subscriberStatus)
            === self::normaliseSubscriberStatus($subscriberStatus);
    }
}
