@section('logic')
<?php
/**
 * Template: open-collab/contributor/earnings/index.php
 *
 * This page is a surface orchestrator. Section structure comes from the manifest;
 * data and rendering are handled by open-collab-surface-widgets.js.
 */
$extraHead = ($extraHead ?? '') . "\n"
    . '<link rel="stylesheet" href="' . asset('open-collab-surface-widgets.css', 'css') . '">';
$extraScripts = ($extraScripts ?? '') . "\n"
    . '<script src="' . asset('open-collab-surface-widgets.js', 'js') . '"></script>';
?>
@endsection

@extends('open-collab/layouts/opencollab')

@section('content')

<div id="status-toast"
     style="position:fixed;bottom:24px;left:50%;transform:translateX(-50%);
            background:var(--navy);color:#fff;padding:9px 20px;border-radius:20px;
            font-size:.8rem;font-weight:500;opacity:0;transition:opacity .3s;
            z-index:300;pointer-events:none;"></div>

<script>
    window.OPEN_COLLAB_SURFACE = <?= json_encode($surface ?? 'earnings.index') ?>;
    window.OPEN_COLLAB_SITE = <?= json_encode($site ?? '') ?>;
    window.OPEN_COLLAB_SURFACE_SECTIONS = {!! json_encode($sections ?? []) !!};
    window.OPEN_COLLAB_SURFACE_CONTEXT = {!! json_encode($surfaceContext ?? []) !!};
</script>

<div data-open-collab-surface="<?= htmlspecialchars($surface ?? 'earnings.index') ?>">
    <?php foreach (($sections ?? []) as $section): ?>
        <section data-surface-section="<?= htmlspecialchars($section['key'] ?? '') ?>" aria-label="<?= htmlspecialchars($section['title'] ?? '') ?>"></section>
    <?php endforeach; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        new OpenCollabSurfaceRenderer({
            surface: window.OPEN_COLLAB_SURFACE,
            site: window.OPEN_COLLAB_SITE,
            sections: window.OPEN_COLLAB_SURFACE_SECTIONS,
            context: window.OPEN_COLLAB_SURFACE_CONTEXT,
            token: () => localStorage.getItem('oc_token') || '',
        }).init();
    });
</script>

@endsection
