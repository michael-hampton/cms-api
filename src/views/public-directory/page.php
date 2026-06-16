<?php
$title = ucfirst($type);
$description = 'Public ' . $type . ' directory';
$isListing = empty($slug);
$directoryLabel = match ($type) {
    'author' => 'authors',
    'category' => 'categories',
    'tag' => 'tags',
    default => 'items',
};
?>

@include('header', ['menu' => $menu])

<main class="mt-20">
    <div class="container">
        <?php if ($isListing): ?>
            <div class="directory-search-shell" data-directory-search-shell role="search">
                <svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="m21 21-4.35-4.35"></path>
                </svg>
                <input
                    type="search"
                    data-directory-search-shell-input
                    placeholder="Search <?= htmlspecialchars($directoryLabel, ENT_QUOTES, 'UTF-8') ?>…"
                    aria-label="Search <?= htmlspecialchars($directoryLabel, ENT_QUOTES, 'UTF-8') ?>"
                    autocomplete="off"
                >
                <button type="button" data-directory-search-shell-clear hidden>Clear</button>
            </div>
            <div class="directory-search-shell__summary" data-directory-search-shell-summary aria-live="polite"></div>
        <?php endif; ?>

        <div
            id="public-directory-app"
            class="public-directory-app"
            data-api-url="<?= htmlspecialchars($apiUrl, ENT_QUOTES, 'UTF-8') ?>"
            data-type="<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>"
            data-slug="<?= htmlspecialchars($slug ?? '', ENT_QUOTES, 'UTF-8') ?>"
            data-site="<?= htmlspecialchars($siteSlug, ENT_QUOTES, 'UTF-8') ?>"
            data-preview="<?= !empty($preview) ? 'true' : 'false' ?>"
        >
            <div class="public-directory-status" role="status" aria-live="polite">
                <div class="public-directory-spinner" aria-hidden="true"></div>
                <p>Loading…</p>
            </div>
        </div>
    </div>
</main>

<?php if (!empty($footerHtml)): ?>
    <?= $footerHtml ?>
<?php endif; ?>

@include('consent-banner', ['site' => $site])

@css('public-directory.css')
@js('base.js')
@js('public-directory.js')
@js('public-directory-search-shell.js')
