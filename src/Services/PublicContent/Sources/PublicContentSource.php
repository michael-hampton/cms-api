<?php

namespace App\Services\PublicContent\Sources;

use App\DTO\PublicContent\PublicContentContext;
use App\DTO\PublicContent\Sources\SourceResult;

/**
 * Shared contract for public content "sources": collaborators that fetch data for a
 * widget and must never let the page fail to render when the underlying data is
 * unavailable or malformed.
 *
 * Implementations always return a {@see SourceResult} rather than throwing, and
 * distinguish "genuinely empty" from "degraded" so templates can render an explicit
 * empty state instead of silently looking the same as a failure.
 *
 * A source whose natural inputs are narrower than a full {@see PublicContentContext}
 * (for example a page + site id + limit) may expose a more specific `resolve()`
 * signature instead of implementing this interface directly; the contract that matters
 * is the {@see SourceResult} return value, not the exact interface used to reach it.
 */
interface PublicContentSource
{
    public function resolve(PublicContentContext $context): SourceResult;
}
