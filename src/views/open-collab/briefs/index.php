@section('logic')
<?php
$pageTitle = 'My Briefs';
$activeNav = 'briefs';
$breadcrumbs = [['label' => 'Dashboard', 'url' => "/{$site}/open-collab/dashboard"], ['label' => 'My Briefs']];
$pageClass = 'oc-brief-inbox-page';
?>
@endsection

@extends('open-collab/layouts/opencollab')

@section('content')
<section class="oc-brief-inbox" data-brief-inbox data-site="<?= htmlspecialchars($site ?? '') ?>">
    <div class="oc-brief-inbox__titlebar">
        <div>
            <h1>My Briefs</h1>
        </div>
    </div>

    <div class="oc-brief-summary" aria-label="Brief summary">
        <div class="oc-brief-summary__card" data-summary-card="awaiting_response">
            <span>Awaiting Response</span>
            <strong data-summary="awaiting_response">0</strong>
        </div>
        <div class="oc-brief-summary__card" data-summary-card="in_progress">
            <span>In Progress</span>
            <strong data-summary="in_progress">0</strong>
        </div>
        <div class="oc-brief-summary__card" data-summary-card="submitted">
            <span>Submitted</span>
            <strong data-summary="submitted">0</strong>
        </div>
        <div class="oc-brief-summary__card" data-summary-card="returned_for_changes">
            <span>Returned for Changes</span>
            <strong data-summary="returned_for_changes">0</strong>
        </div>
        <div class="oc-brief-summary__card oc-brief-summary__card--danger" data-summary-card="overdue">
            <span>Overdue</span>
            <strong data-summary="overdue">0</strong>
        </div>
    </div>

    <div class="oc-brief-toolbar">
        <div class="oc-brief-filters" role="tablist" aria-label="Brief filters">
            <button type="button" class="oc-brief-filter is-active" data-filter="all">All</button>
            <button type="button" class="oc-brief-filter" data-filter="awaiting_response">Awaiting Response</button>
            <button type="button" class="oc-brief-filter" data-filter="accepted">Accepted</button>
            <button type="button" class="oc-brief-filter" data-filter="in_progress">In Progress</button>
            <button type="button" class="oc-brief-filter" data-filter="submitted">Submitted</button>
            <button type="button" class="oc-brief-filter" data-filter="returned_for_changes">Returned for Changes</button>
            <button type="button" class="oc-brief-filter" data-filter="completed">Completed</button>
            <button type="button" class="oc-brief-filter" data-filter="overdue">Overdue</button>
        </div>

        <label class="oc-brief-search">
            <span class="sr-only">Search briefs</span>
            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M8 4a4 4 0 102.472 7.144l3.192 3.192a1 1 0 001.414-1.414l-3.192-3.192A4 4 0 008 4zm-2 4a2 2 0 114 0 2 2 0 01-4 0z" clip-rule="evenodd"/>
            </svg>
            <input type="search" data-search placeholder="Search by title or brand" autocomplete="off">
        </label>
    </div>

    <div class="oc-brief-state oc-brief-state--loading" data-state="loading">
        <div class="oc-spinner oc-spinner--dark"></div>
        <span>Loading briefs</span>
    </div>

    <div class="oc-brief-state" data-state="empty" hidden>
        <strong>No briefs assigned</strong>
        <span>Assigned briefs will appear here when CMS sends work your way.</span>
    </div>

    <div class="oc-brief-state oc-brief-state--error" data-state="error" hidden>
        <strong>Briefs could not be loaded</strong>
        <span data-error-message>Please try again.</span>
        <button type="button" class="oc-btn oc-btn--ghost oc-btn--sm" data-retry>Retry</button>
    </div>

    <div class="oc-brief-list" data-brief-list hidden></div>
</section>
@endsection

@section('scripts')
@js('open-collab-brief-inbox.js')
<script>
    new ContributorBriefInboxPage().init();
</script>
@endsection
