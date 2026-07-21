<?php
/**
 * Recirculation ("read this next") island.
 *
 * Expected: $recirculation (SourceResult|null), $siteSlug (string)
 *
 * Eligible only for article / review / buying-guide (see public_content.widgets.recirculation).
 */

use App\DTO\PublicContent\Sources\SourceResult;

$recirculation = $recirculation ?? null;
$siteSlug = (string) ($siteSlug ?? '');

if (!$recirculation instanceof SourceResult) {
    return;
}

$status = $recirculation->status->value;
$items = $recirculation->items();
$isDegraded = $recirculation->isDegraded();
$hasItems = $items !== [];
?>
<aside class="recirculation"
       data-status="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"
       data-degraded="<?= $isDegraded ? 'true' : 'false' ?>"
       <?php if (!$hasItems): ?>hidden<?php endif; ?>
       aria-label="Read this next">
    <?php if ($hasItems): ?>
        <div class="recirculation__header">
            <h2 class="recirculation__title">Read this next</h2>
        </div>
        <div class="recirculation__grid">
            <?php foreach ($items as $item): ?>
                <?php
                $title = htmlspecialchars((string) ($item->listing_title ?: $item->title ?? ''), ENT_QUOTES, 'UTF-8');
                $slug = htmlspecialchars((string) ($item->slug ?? ''), ENT_QUOTES, 'UTF-8');
                $url = '/' . htmlspecialchars($siteSlug, ENT_QUOTES, 'UTF-8') . '/' . $slug;
                $synopsis = htmlspecialchars((string) ($item->listing_synopsis ?: $item->meta_description ?? ''), ENT_QUOTES, 'UTF-8');
                $image = (string) ($item->listing_image_url ?? $item->metadata->featured_image ?? '');
                ?>
                <article class="recirculation__card">
                    <a href="<?= $url ?>" class="recirculation__card-link">
                        <div class="recirculation__card-media">
                            <?php if ($image !== ''): ?>
                                <img src="<?= htmlspecialchars($image, ENT_QUOTES, 'UTF-8') ?>"
                                     alt=""
                                     loading="lazy"
                                     class="recirculation__card-image">
                            <?php else: ?>
                                <div class="recirculation__card-image recirculation__card-image--placeholder" aria-hidden="true"></div>
                            <?php endif; ?>
                        </div>
                        <div class="recirculation__card-body">
                            <h3 class="recirculation__card-title"><?= $title ?></h3>
                            <?php if ($synopsis !== ''): ?>
                                <p class="recirculation__card-synopsis"><?= $synopsis ?></p>
                            <?php endif; ?>
                        </div>
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</aside>

<style>
    .recirculation {
        margin: 3rem 0;
        padding: 0;
    }

    .recirculation[hidden] {
        display: none !important;
    }

    .recirculation__header {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        margin-bottom: 1.25rem;
    }

    .recirculation__title {
        margin: 0;
        font-size: 1.375rem;
        font-weight: 700;
        letter-spacing: -0.02em;
        color: var(--text-primary, #111827);
    }

    .recirculation__grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 1.25rem;
    }

    .recirculation__card {
        background: var(--surface-color, #fff);
        border: 1px solid var(--border-color, #e5e7eb);
        border-radius: 0.875rem;
        overflow: hidden;
        transition: box-shadow 0.2s ease, transform 0.2s ease, border-color 0.2s ease;
    }

    .recirculation__card:hover {
        transform: translateY(-3px);
        border-color: color-mix(in srgb, var(--primary-color, #2563eb) 28%, var(--border-color, #e5e7eb));
        box-shadow: 0 12px 28px -12px rgba(15, 23, 42, 0.18);
    }

    .recirculation__card-link {
        display: grid;
        grid-template-rows: auto 1fr;
        height: 100%;
        color: inherit;
        text-decoration: none;
    }

    .recirculation__card-media {
        aspect-ratio: 16 / 9;
        overflow: hidden;
        background: color-mix(in srgb, var(--primary-color, #2563eb) 8%, #f8fafc);
    }

    .recirculation__card-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .recirculation__card-image--placeholder {
        min-height: 100%;
        background:
            linear-gradient(135deg,
                color-mix(in srgb, var(--primary-color, #2563eb) 16%, #f1f5f9),
                #e2e8f0);
    }

    .recirculation__card-body {
        padding: 1rem 1.1rem 1.15rem;
    }

    .recirculation__card-title {
        margin: 0;
        font-size: 1.05rem;
        font-weight: 700;
        line-height: 1.3;
        letter-spacing: -0.015em;
        color: var(--text-primary, #111827);
    }

    .recirculation__card-synopsis {
        margin: 0.55rem 0 0;
        font-size: 0.875rem;
        line-height: 1.5;
        color: var(--text-secondary, #64748b);
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    @media (max-width: 640px) {
        .recirculation__grid {
            grid-template-columns: 1fr;
        }
    }
</style>
