@section('logic')
<?php
/**
 * Template: open-collab/admin/violations/index.blade.php
 *
 * This page is a surface orchestrator. Section structure and surface context
 * (including the violations.resolve_action capability) come from
 * AdminViolationPageController::index(); data and rendering are handled by
 * open-collab-surface-widgets.js.
 */
$pageTitle = 'Violation Management';
$activeNav = 'violations';
$breadcrumbs = [['label' => 'Violations']];

$extraHead = ($extraHead ?? '') . "\n"
        . '<link rel="stylesheet" href="' . asset('open-collab-surface-widgets.css', 'css') . '">';
$extraScripts = ($extraScripts ?? '') . "\n"
        . '<script src="' . asset('open-collab/widgets/oc-shared.js', 'js') . '"></script>';
$extraScripts = ($extraScripts ?? '') . "\n"
        . '<script src="' . asset('open-collab/widgets/oc-api-client.js', 'js') . '"></script>';
$extraScripts = ($extraScripts ?? '') . "\n"
        . '<script src="' . asset('open-collab/widgets/oc-admin-violations-widget.js', 'js') . '"></script>';
$extraScripts = ($extraScripts ?? '') . "\n"
        . '<script src="' . asset('open-collab/widgets/oc-surface-controller.js', 'js') . '"></script>';

?>
@endsection

@extends('open-collab/layouts/admin')

@section('content')
<style>
    .filter-pill, .sev-pill {
        padding: 4px 12px;
        border-radius: 20px;
        border: 1.5px solid var(--border);
        background: #fff;
        font-size: .75rem;
        font-weight: 500;
        color: var(--slate);
        cursor: pointer;
        transition: background .15s, color .15s, border-color .15s;
    }

    .filter-pill:hover, .sev-pill:hover {
        border-color: var(--navy);
    }

    .filter-pill--active,
    .sev-pill--active {
        background: var(--navy);
        color: #fff !important;
        border-color: var(--navy);
    }
</style>

<div data-open-collab-surface="<?= htmlspecialchars($surface ?? 'admin.violations.index') ?>">
    <?php foreach (($sections ?? []) as $section): ?>
        <section data-surface-section="<?= htmlspecialchars($section['key'] ?? '') ?>" aria-label="<?= htmlspecialchars($section['title'] ?? '') ?>"></section>
    <?php endforeach; ?>
</div>
@endsection

@section('scripts')
<script>
    window.OPEN_COLLAB_SURFACE = <?= json_encode($surface ?? 'admin.violations.index') ?>;
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