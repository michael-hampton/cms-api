<?php

namespace App\Services\PublicContent\Routing;

final readonly class RouteOverrideBranch
{
    /**
     * @param array<string, mixed> $values Shallow override payload layered onto the base route
     */
    public function __construct(
        public RouteOverrideAudience $audience,
        public array $values,
    ) {
    }
}
