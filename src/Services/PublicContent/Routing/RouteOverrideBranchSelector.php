<?php

namespace App\Services\PublicContent\Routing;

use RuntimeException;

/**
 * First step of the Flexi-style route resolution pipeline.
 *
 * Exact language+territory match, then subscriber-status refinement.
 * Duplicate audiences are an explicit error (documented departure from
 * silent first-wins Flexi behaviour).
 */
final class RouteOverrideBranchSelector
{
    /**
     * @param list<RouteOverrideBranch> $branches
     */
    public function select(
        array $branches,
        ?string $language,
        ?string $territory,
        ?string $subscriberStatus,
    ): ?RouteOverrideBranch {
        if ($language === null || trim($language) === '' || $territory === null || trim($territory) === '') {
            return null;
        }

        $this->assertNoDuplicateAudiences($branches);

        $languageTerritoryMatches = array_values(array_filter(
            $branches,
            static fn(RouteOverrideBranch $branch): bool => strcasecmp($branch->audience->language, $language) === 0
                && strcasecmp($branch->audience->territory, $territory) === 0,
        ));

        if ($languageTerritoryMatches === []) {
            return null;
        }

        $requestStatus = RouteOverrideAudience::normaliseSubscriberStatus($subscriberStatus);

        $exactStatus = array_values(array_filter(
            $languageTerritoryMatches,
            static fn(RouteOverrideBranch $branch): bool => RouteOverrideAudience::normaliseSubscriberStatus(
                $branch->audience->subscriberStatus,
            ) === $requestStatus,
        ));

        if ($exactStatus !== []) {
            return $exactStatus[0];
        }

        // Subscriber refinement: fall back to the non-subscriber branch for this language+territory.
        $nonSubscriber = array_values(array_filter(
            $languageTerritoryMatches,
            static fn(RouteOverrideBranch $branch): bool => RouteOverrideAudience::normaliseSubscriberStatus(
                $branch->audience->subscriberStatus,
            ) === '',
        ));

        return $nonSubscriber[0] ?? null;
    }

    /**
     * @param list<RouteOverrideBranch> $branches
     */
    private function assertNoDuplicateAudiences(array $branches): void
    {
        $seen = [];

        foreach ($branches as $branch) {
            $key = $branch->audience->selectorKey();
            if (isset($seen[$key])) {
                throw new RuntimeException(
                    'Duplicate route override audiences are not allowed; refusing silent first-wins selection.',
                );
            }
            $seen[$key] = true;
        }
    }
}
