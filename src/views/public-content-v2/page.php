<?php
$title = 'Content preview';
$description = 'Public content V2 preview';
?>

@include('header', ['menu' => $menu])

<main class="mt-20">
    <div class="container">
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
    </div>
</main>

<?php if (!empty($footerHtml)): ?>
    <?= $footerHtml ?>
<?php endif; ?>

@include('consent-banner', ['site' => $site])

@css('public-content-v2.css')
@js('base.js')
@js('public-content-v2-preview-links.js')
@js('public-content-v2-hydrators.js')
@js('public-content-v2.js')
