@section('logic')
<?php
/**
 * Template: open-collab/admin/disputes/index.blade.php
 *
 * This page is a surface orchestrator. Section structure comes from the manifest;
 * data and rendering are handled by open-collab-surface-widgets.js.
 */
$pageTitle = 'Earnings Disputes';
$activeNav = 'disputes';
$breadcrumbs = [['label' => 'Earnings Disputes']];

$extraHead = ($extraHead ?? '') . "\n"
        . '<link rel="stylesheet" href="' . asset('open-collab-surface-widgets.css', 'css') . '">';
$extraScripts = ($extraScripts ?? '') . "\n"
        . '<script src="' . asset('open-collab/widgets/oc-shared.js', 'js') . '"></script>';
$extraScripts = ($extraScripts ?? '') . "\n"
        . '<script src="' . asset('open-collab/widgets/oc-api-client.js', 'js') . '"></script>';
$extraScripts = ($extraScripts ?? '') . "\n"
        . '<script src="' . asset('open-collab/widgets/oc-admin-disputes-widget.js', 'js') . '"></script>';
$extraScripts = ($extraScripts ?? '') . "\n"
        . '<script src="' . asset('open-collab/widgets/oc-surface-controller.js', 'js') . '"></script>';

?>
@endsection

@extends('open-collab/layouts/admin')

@section('content')
<style>
    .filter-pill {
        padding: 5px 14px;
        border-radius: 20px;
        border: 1.5px solid var(--border);
        background: #fff;
        font-size: .78rem;
        font-weight: 500;
        color: var(--slate);
        cursor: pointer;
        transition: background .15s, color .15s, border-color .15s;
        white-space: nowrap;
    }
    .filter-pill:hover {
        border-color: var(--navy);
        color: var(--navy);
    }
    .filter-pill--active {
        background: var(--navy);
        color: #fff;
        border-color: var(--navy);
    }
</style>

<div data-open-collab-surface="<?= htmlspecialchars($surface ?? 'admin.disputes.index') ?>">
    <?php foreach (($sections ?? []) as $section): ?>
        <section data-surface-section="<?= htmlspecialchars($section['key'] ?? '') ?>" aria-label="<?= htmlspecialchars($section['title'] ?? '') ?>"></section>
    <?php endforeach; ?>
</div>
@endsection

@section('scripts')
<script>
    window.OPEN_COLLAB_SURFACE = <?= json_encode($surface ?? 'admin.disputes.index') ?>;
    window.OPEN_COLLAB_SITE = <?= json_encode($site ?? '') ?>;
    window.OPEN_COLLAB_SURFACE_SECTIONS = {!! json_encode($sections ?? []) !!};
    window.OPEN_COLLAB_SURFACE_CONTEXT = {!! json_encode($surfaceContext ?? []) !!};

    document.addEventListener('DOMContentLoaded', () => {
        const readToken = () => localStorage.getItem('oc_token') || '';
        new OpenCollabSurfaceRenderer({
            surface: window.OPEN_COLLAB_SURFACE,
            site: window.OPEN_COLLAB_SITE,
            sections: window.OPEN_COLLAB_SURFACE_SECTIONS,
            context: window.OPEN_COLLAB_SURFACE_CONTEXT,
            token: readToken,
        }).init();
    });
</script>
@endsection