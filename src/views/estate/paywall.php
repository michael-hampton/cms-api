<?php
$title = 'Subscribe to Read - ' . (string)$page->title;
$description = $page->meta_description ?? '';
?>

@include('header', [
    'menu' => $menu,
    'title' => 'Subscribe to Read',
    'page' => $page,
    'menuRenderer' => $menuRenderer,
])

<main class="mt-20">
    <div class="container">
        <article class="premium-content-preview">
            <?php if (!empty($page->listing_synopsis ?: $page->meta_description ?: $page->description)): ?>
                <p><?= htmlspecialchars(
                    (string)($page->listing_synopsis ?: $page->meta_description ?: $page->description),
                    ENT_QUOTES,
                    'UTF-8',
                ) ?></p>
            <?php endif; ?>
        </article>
    </div>
</main>

@include('components/paywall-overlay', [
    'page' => $page,
    'reason' => $reason,
    'member' => $member,
])

@css('paywall-overlay.css')
@js('paywall-overlay.js')

<script>
document.dispatchEvent(new CustomEvent('public-content:component-mounted', {
    detail: {
        element: document.body,
        component: {type: 'paywall-overlay'}
    }
}));
</script>
