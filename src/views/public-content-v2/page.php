<?php
$preview = (bool)($preview ?? false);
$title = $preview ? 'Content preview' : ($pageTitle ?? '');
$description = $preview ? 'Public content V2 preview' : ($pageDescription ?? '');
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
            data-preview="<?= $preview ? 'true' : 'false' ?>"
        >
            <div class="public-content-v2-status" role="status" aria-live="polite">
                <div class="public-content-v2-spinner" aria-hidden="true"></div>
                <p>Loading content…</p>
            </div>
        </div>
    </div>
</main>

<?php if (isset($footerMenu) && $footerMenu): ?>
    <?php $footerRenderer = new \App\Services\FooterRenderer(); ?>
    <?= $footerRenderer->renderFooter($footerMenu) ?>
<?php endif; ?>

@include('consent-banner', ['site' => $site])

@css('member-hub.css')
@css('public-content-v2.css')
@js('base.js')
@js('public-content-v2-member-hub-loader.js')
<?php if ($preview): ?>
    @js('public-content-v2-preview-links.js')
<?php else: ?>
    @js('public-content-v2-production-links.js')
<?php endif; ?>
@js('public-content-v2-hydrators.js')
@js('public-content-v2-deals-carousel.js')
@js('public-content-v2.js')
