@section('logic')
<?php
$pageTitle = 'Brief Workspace';
$activeNav = 'briefs';
$breadcrumbs = [['label' => 'My Briefs', 'url' => "/{$site}/open-collab/briefs"], ['label' => 'Brief Workspace']];
$pageClass = 'oc-brief-workspace-page';
?>
@endsection

@extends('open-collab/layouts/opencollab')

@section('content')
<section class="oc-brief-workspace" data-brief-workspace data-site="<?= htmlspecialchars($site ?? '') ?>" data-brief-id="<?= (int)$briefId ?>">
    <div class="oc-brief-workspace__loading" data-state="loading">
        <div class="oc-spinner oc-spinner--dark"></div>
        <span>Loading workspace</span>
    </div>

    <div class="oc-brief-state oc-brief-state--error" data-state="error" hidden>
        <strong>Workspace could not be loaded</strong>
        <span data-error-message>Please try again.</span>
        <button type="button" class="oc-btn oc-btn--ghost oc-btn--sm" data-retry>Retry</button>
    </div>

    <div class="oc-brief-workspace__content" data-workspace-content hidden>
        <header class="oc-brief-workspace__header" data-overview-header></header>

        <div class="oc-brief-workspace__grid">
            <main class="oc-brief-workspace__main">
                <section class="oc-brief-panel" data-overview-panel></section>
                <section class="oc-brief-panel" data-task-panel></section>
                <section class="oc-brief-panel" data-attachment-panel></section>
                <section class="oc-brief-panel" data-comment-panel></section>
                <section class="oc-brief-panel" data-timeline-panel></section>
            </main>

            <aside class="oc-brief-workspace__sidebar">
                <section class="oc-brief-panel" data-summary-panel></section>
                <section class="oc-brief-panel" data-action-panel></section>
            </aside>
        </div>
    </div>

    <div class="oc-brief-modal" data-action-modal hidden>
        <div class="oc-brief-modal__dialog">
            <button type="button" class="oc-modal__close" data-modal-close aria-label="Close">&times;</button>
            <h2 data-modal-title></h2>
            <form data-modal-form>
                <div data-modal-fields></div>
                <div class="oc-brief-modal__errors" data-modal-errors hidden></div>
                <div class="oc-brief-modal__actions">
                    <button type="button" class="oc-btn oc-btn--ghost oc-btn--sm" data-modal-close>Cancel</button>
                    <button type="submit" class="oc-btn oc-btn--primary oc-btn--sm" data-modal-submit>Submit</button>
                </div>
            </form>
        </div>
    </div>
</section>
@endsection

@section('scripts')
@js('open-collab-brief-workspace.js')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.ContributorBriefWorkspacePage) {
            new window.ContributorBriefWorkspacePage().init();
        }
    });
</script>
@endsection
