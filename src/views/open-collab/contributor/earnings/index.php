@section('logic')
<?php
/**
 * Template: open-collab/contributor/earnings/index.php
 *
 * The page is now an orchestrator for configurable surface sections.
 * Default surface: earnings.index
 */
?>
@endsection

@extends('open-collab/layouts/opencollab')

@section('content')

<div id="status-toast"
     style="position:fixed;bottom:24px;left:50%;transform:translateX(-50%);
            background:var(--navy);color:#fff;padding:9px 20px;border-radius:20px;
            font-size:.8rem;font-weight:500;opacity:0;transition:opacity .3s;
            z-index:300;pointer-events:none;"></div>

<div data-open-collab-surface="<?= htmlspecialchars($surface ?? 'earnings.index') ?>">
    <?php foreach (($sections ?? []) as $section): ?>
        <section data-section-key="<?= htmlspecialchars($section->key()) ?>">
            <?php switch ($section->key()):
                case 'earnings.stats': ?>
                    @include('open-collab.sections.earnings.stats')
                    <?php break;
                case 'earnings.transactions_table': ?>
                    @include('open-collab.sections.earnings.transactions-table')
                    <?php break;
            endswitch; ?>
        </section>
    <?php endforeach; ?>
</div>

@endsection
