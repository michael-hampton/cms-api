<?php
$title = ucfirst($type);
$description = 'Public ' . $type . ' directory';
?>

@include('header', ['menu' => $menu])

<main class="mt-20">
    <div class="container">
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
