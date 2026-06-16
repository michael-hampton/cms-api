<?php
$pageTypeLabel = match ((string) $page->page_type) {
    'article' => 'Article',
    'content' => 'Story',
    'landing-page' => 'Explore',
    default => null,
};
$subtitle = trim((string) ($page->subtitle ?? ''));
?>

<header class="public-page-heading">
    <div class="public-page-heading__content">
        <?php if ($pageTypeLabel): ?>
            <span class="public-page-heading__eyebrow"><?= htmlspecialchars($pageTypeLabel, ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif; ?>

        <h1 class="public-page-heading__title"><?= htmlspecialchars((string) $page->title, ENT_QUOTES, 'UTF-8') ?></h1>

        <?php if ($subtitle !== ''): ?>
            <p class="public-page-heading__subtitle"><?= htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
    </div>

    <div class="public-page-heading__member">
        @include('components/member-badge')
    </div>
</header>

<style>
    .public-page-heading {
        display:flex;
        align-items:flex-start;
        justify-content:space-between;
        gap:2rem;
        margin:0 0 1.5rem;
        padding:clamp(1.5rem,4vw,2.5rem);
        border:1px solid #e5e7eb;
        border-radius:18px;
        background:linear-gradient(135deg,#ffffff 0%,#f8fafc 100%);
        box-shadow:0 16px 40px rgba(15,23,42,.06);
        overflow:hidden;
        position:relative;
    }
    .public-page-heading::before {
        content:'';
        position:absolute;
        top:0;
        left:0;
        width:100%;
        height:4px;
        background:linear-gradient(90deg,#2563eb,#7c3aed);
    }
    .public-page-heading__content { min-width:0; }
    .public-page-heading__eyebrow {
        display:inline-flex;
        margin-bottom:.75rem;
        color:#2563eb;
        font-size:.75rem;
        font-weight:800;
        letter-spacing:.14em;
        text-transform:uppercase;
    }
    .public-page-heading__title {
        margin:0;
        color:#0f172a;
        font-size:clamp(2rem,5vw,4rem);
        font-weight:800;
        letter-spacing:-.035em;
        line-height:1.05;
        text-wrap:balance;
    }
    .public-page-heading__subtitle {
        max-width:52rem;
        margin:1rem 0 0;
        color:#64748b;
        font-size:clamp(1rem,2vw,1.2rem);
        line-height:1.65;
    }
    .public-page-heading__member { flex:0 0 auto; }
    @media (max-width:720px) {
        .public-page-heading { flex-direction:column; gap:1rem; }
        .public-page-heading__member { align-self:flex-start; }
    }
</style>
