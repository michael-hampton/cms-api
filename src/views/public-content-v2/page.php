<?php
$preview = (bool)($preview ?? false);
$title = $preview ? 'Content preview' : ($pageTitle ?? '');
$description = $preview ? 'Public content V2 preview' : ($pageDescription ?? '');
$regionSlug = isset($territory) && $territory ? (string)$territory->slug : '';
$resolvedLocale = (string)($locale ?? '');
$csrfToken = \App\Framework\Security\Csrf::getToken();
$initialHero = $initialHero ?? null;
$initialHeroBlockId = $initialHero ? (string) $initialHero->blockId : '';
$pageType = (string)($pageType ?? 'content');

// Load custom tenant enhancements from JSON settings
$customCss = isset($site) ? $site->getSetting('custom_css', '') : '';
$customJs = isset($site) ? $site->getSetting('custom_js', '') : '';
$gentleHtmlEnabled = isset($site) ? (bool)$site->getSetting('gentle_html_formatting', true) : true;

// Safeguard: Protect single-line comments from destroying runtime syntax when minified
if (!empty($customJs)) {
    $customJs = preg_replace_callback('/\/\/([^\n\r]*)/', function($matches) {
        return '/* ' . str_replace('*/', '* /', trim($matches[1])) . ' */';
    }, $customJs);
}

$designTokenVariables = is_array($designTokenVariables ?? null) ? $designTokenVariables : [];
$publicContentStyleParts = [];
foreach ($designTokenVariables as $name => $value) {
    if (!is_string($name) || !preg_match('/^--[a-z0-9-]+$/', $name)) {
        continue;
    }

    $cleanValue = str_replace([';', '"', "'", '<', '>'], '', (string) $value);
    if (trim($cleanValue) === '') {
        continue;
    }

    $publicContentStyleParts[] = $name . ': ' . htmlspecialchars($cleanValue, ENT_QUOTES, 'UTF-8');
}

$publicContentStyle = implode('; ', $publicContentStyleParts);
?>

    @include('header', [
    'menu' => $menu,
    'title' => $title,
    'description' => $description,
    'seo' => $seo ?? [],
    'heroPreloadUrl' => $heroPreloadUrl ?? null,
    ])

    <main class="mt-20 <?= $gentleHtmlEnabled ? 'gentle-layout-flow' : '' ?>">
        <div class="container">
            <?php if ($initialHero): ?>
                <div
                        id="public-content-v2-initial-hero"
                        class="public-content-v2-app public-content-v2-initial-hero"
                        data-content-type="<?= htmlspecialchars($pageType, ENT_QUOTES, 'UTF-8') ?>"
                        data-initial-hero-block-id="<?= htmlspecialchars($initialHeroBlockId, ENT_QUOTES, 'UTF-8') ?>"
                        <?= $publicContentStyle !== '' ? 'style="' . $publicContentStyle . '"' : '' ?>
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
                    data-content-type="<?= htmlspecialchars($pageType, ENT_QUOTES, 'UTF-8') ?>"
                    data-region="<?= htmlspecialchars($regionSlug, ENT_QUOTES, 'UTF-8') ?>"
                    data-locale="<?= htmlspecialchars($resolvedLocale, ENT_QUOTES, 'UTF-8') ?>"
                    data-preview="<?= $preview ? 'true' : 'false' ?>"
                    data-csrf-token="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
                    data-initial-hero-block-id="<?= htmlspecialchars($initialHeroBlockId, ENT_QUOTES, 'UTF-8') ?>"
                    <?= $publicContentStyle !== '' ? 'style="' . $publicContentStyle . '"' : '' ?>
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
    @js('public-islands.js')
    @js('public-content-v2-hydrators.js')
    @js('public-content-v2-deals-carousel.js')
    @js('public-content-v2-deals-cart.js')
    @js('public-content-v2-accessibility.js')
    @js('newsletter-scroll-trigger.js')
    @js('public-content-v2-design-tokens.js')
    @js('public-content-v2.js')

<?php if (!empty($customCss)): ?>
    <style id="tenant-custom-styles">
        <?= $customCss ?>
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const targetApp = document.getElementById('public-content-v2-app');
            const customStyles = document.getElementById('tenant-custom-styles');

            if (targetApp && customStyles) {
                // Monitor when the loading spinner disappears and API content populates
                const observer = new MutationObserver(() => {
                    // If the app uses a Shadow Root, inject styles inside the isolation boundary
                    if (targetApp.shadowRoot && !targetApp.shadowRoot.getElementById('tenant-custom-styles')) {
                        targetApp.shadowRoot.appendChild(customStyles.cloneNode(true));
                    }
                });

                observer.observe(targetApp, { childList: true, subtree: true });
            }
        });
    </script>
<?php endif; ?>

<?php if (!empty($customJs)): ?>
    <script id="tenant-runtime-scripts">
        (function() {
            try {
                <?= $customJs ?>
            } catch (error) {
                console.error("Custom Script Execution Exception:", error);
            }
        })();
    </script>
<?php endif; ?>