<?php
$preview = (bool)($preview ?? false);
$title = $preview ? 'Content preview' : ($pageTitle ?? '');
$description = $preview ? 'Public content V2 preview' : ($pageDescription ?? '');
$regionSlug = isset($territory) && $territory ? (string)$territory->slug : '';
$resolvedLocale = (string)($locale ?? '');
$csrfToken = \App\Framework\Security\Csrf::getToken();
$initialHero = $initialHero ?? null;
$initialHeroBlockId = $initialHero ? (string) $initialHero->blockId : '';
?>

@include('header', [
    'menu' => $menu,
    'title' => $title,
    'description' => $description,
    'seo' => $seo ?? [],
    'heroPreloadUrl' => $heroPreloadUrl ?? null,
])

<main class="mt-20">
    <div class="container">
        <?php if ($initialHero): ?>
            <div
                id="public-content-v2-initial-hero"
                class="public-content-v2-app public-content-v2-initial-hero"
                data-initial-hero-block-id="<?= htmlspecialchars($initialHeroBlockId, ENT_QUOTES, 'UTF-8') ?>"
            >
                <article class="public-content-v2-document">
                    <div class="page-layout full-width">
                        <div class="main-content full-width">
                            <?= $initialHero->html ?>
                        </div>
                    </div>
                </article>
            </div>
        <?php endif; ?>

        <div
            id="public-content-v2-app"
            class="public-content-v2-app"
            data-api-url="<?= htmlspecialchars($apiUrl, ENT_QUOTES, 'UTF-8') ?>"
            data-site="<?= htmlspecialchars($siteSlug, ENT_QUOTES, 'UTF-8') ?>"
            data-slug="<?= htmlspecialchars($contentSlug, ENT_QUOTES, 'UTF-8') ?>"
            data-region="<?= htmlspecialchars($regionSlug, ENT_QUOTES, 'UTF-8') ?>"
            data-locale="<?= htmlspecialchars($resolvedLocale, ENT_QUOTES, 'UTF-8') ?>"
            data-preview="<?= $preview ? 'true' : 'false' ?>"
            data-csrf-token="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
            data-initial-hero-block-id="<?= htmlspecialchars($initialHeroBlockId, ENT_QUOTES, 'UTF-8') ?>"
        >
            <div class="public-content-v2-status" role="status" aria-live="polite">
                <div class="public-content-v2-spinner" aria-hidden="true"></div>
                <p>Loading content…</p>
            </div>
        </div>
    </div>
</main>

<?php if (isset($footerMenu) && $footerMenu): ?>
    <div id="public-content-v2-footer" hidden>
        <?php $footerRenderer = new \App\Services\FooterRenderer(); ?>
        <?= $footerRenderer->renderFooter($footerMenu) ?>
    </div>
<?php endif; ?>

@include('consent-banner', ['site' => $site])

@css('member-hub.css')
@css('public-content-v2.css')
@css('public-content-v2-brand.css')
@css('public-content-v2-header-brand.css')
@js('base.js')
<?php if ($preview): ?>
    @js('public-content-v2-preview-links.js')
    @js('public-content-v2-search.js')
<?php else: ?>
    @js('public-content-v2-production-links.js')
<?php endif; ?>
@js('public-content-v2-hydrators.js')
@js('public-content-v2-deals-carousel.js')
@js('public-content-v2-deals-cart.js')
@js('public-content-v2-accessibility.js')
@js('newsletter-scroll-trigger.js')
@js('public-content-v2-design-tokens.js')
@js('public-content-v2.js')