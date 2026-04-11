<?php

namespace App\Models;

/**
 * Represents a versioned set of brand/editorial guidelines for a site.
 *
 * Mirrors exactly how Contract works:
 *   - site_id      — the owning site
 *   - version      — integer, incremented each time guidelines are updated
 *   - content      — HTML/markdown body shown to contributors
 *   - created_at
 *
 * The Site model retains guidelines_version as a convenience pointer to the
 * current active version. ContributorOnboardingService reads that column and
 * compares it against UserGuidelinesAcknowledgement records.
 */
class Guideline extends Model
{
    protected $table = 'oc_guidelines';

    protected $fillable = [
        'site_id',
        'version',
        'content',
        'created_at',
    ];

    protected $casts = [
        'site_id' => 'int',
        'version' => 'int',
    ];
}