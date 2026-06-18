<?php
$categoryBase = $directoryBase ?? ('/' . \App\Framework\Support\SiteContext::slug());
$categories = $page->categories ?? [];
?>

<?php if (count($categories) > 0): ?>
    <nav class="public-breadcrumbs" aria-label="Breadcrumb">
        <ol class="public-breadcrumbs__list">
            <?php foreach ($categories as $category): ?>
                <li class="public-breadcrumbs__item">
                    <a
                        class="public-breadcrumbs__link"
                        href="<?= htmlspecialchars($categoryBase . '/categories/' . rawurlencode((string) $category->slug), ENT_QUOTES, 'UTF-8') ?>"
                    >
                        <?= htmlspecialchars((string) $category->name, ENT_QUOTES, 'UTF-8') ?>
                    </a>
                </li>
            <?php endforeach; ?>

            <li class="public-breadcrumbs__item public-breadcrumbs__item--current" aria-current="page">
                <?= htmlspecialchars((string) $page->title, ENT_QUOTES, 'UTF-8') ?>
            </li>
        </ol>
    </nav>
<?php endif; ?>

<style>
    .public-breadcrumbs {
        margin: 0 0 1rem;
        color: #64748b;
        font-size: .875rem;
    }
    .public-breadcrumbs__list {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .4rem;
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .public-breadcrumbs__item {
        display: inline-flex;
        align-items: center;
        min-width: 0;
    }
    .public-breadcrumbs__item + .public-breadcrumbs__item::before {
        content: '/';
        margin-right: .4rem;
        color: #cbd5e1;
    }
    .public-breadcrumbs__link {
        color: #475569;
        font-weight: 600;
        text-decoration: none;
    }
    .public-breadcrumbs__link:hover,
    .public-breadcrumbs__link:focus-visible {
        color: #2563eb;
        text-decoration: underline;
        text-underline-offset: .2em;
    }
    .public-breadcrumbs__item--current {
        max-width: min(36rem, 100%);
        overflow: hidden;
        color: #64748b;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
</style>
