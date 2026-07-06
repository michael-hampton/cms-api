<?php

namespace App\Framework\Support\Config\Publishing;

enum KeyDiffStatus: string
{
    /** Neither the current user nor anyone else changed this key since the base version. */
    case Unchanged = 'unchanged';

    /** Only the current user changed this key; safe to publish automatically. */
    case MineOnly = 'mine_only';

    /** Only someone else changed this key; their change already lives in "latest" and is left alone. */
    case TheirsOnly = 'theirs_only';

    /** Both changed it, but landed on the same result (e.g. both edited it to the same value, or both deleted it). */
    case BothSame = 'both_same';

    /** Both changed it to different results (including delete-vs-edit). Requires explicit resolution. */
    case Conflict = 'conflict';
}