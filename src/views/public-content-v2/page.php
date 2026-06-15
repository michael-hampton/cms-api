<?php
$title = 'Content preview';
$description = 'Public content V2 preview';
?>

@include('header', ['menu' => $menu])

<main class="public-content-v2-shell">
    <div
        id="public-content-v2-app"
        class="public-content-v2-app"
        data-api-url="<?= htmlspecialchars($apiUrl, ENT_QUOTES, 'UTF-8') ?>"
        data-site="<?= htmlspecialchars($siteSlug, ENT_QUOTES, 'UTF-8') ?>"
        data-slug="<?= htmlspecialchars($contentSlug, ENT_QUOTES, 'UTF-8') ?>"
    >
        <div class="public-content-v2-status" role="status" aria-live="polite">
            <div class="public-content-v2-spinner" aria-hidden="true"></div>
            <p>Loading content…</p>
        </div>
    </div>

    <div id="public-content-v2-supplementary" class="public-content-v2-supplementary"></div>
</main>

@include('consent-banner', ['site' => $site])
@include('components/newsletter-account-creation-modal')
@include('components/newsletter-modal')

@css('public-content-v2.css')
@css('public-content-v2-interactions.css')
@js('base.js')
@js('public-content-v2.js')
@js('public-content-v2-supplementary.js')
