@yield('logic')

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Admin') ?> — OpenCollab Admin</title>
    @css('open-collab.css')
    <?php if (!empty($extraHead)): echo $extraHead; endif; ?>
</head>
<body>

<div class="oc-shell">

    <!-- ── Admin Sidebar ────────────────────────────────── -->
    <nav class="oc-sidebar" id="oc-sidebar" role="navigation" aria-label="Admin navigation">

        <div class="oc-sidebar__brand">
            <a href="/<?= htmlspecialchars($site ?? '') ?>/open-collab/admin/queue" class="oc-sidebar__brand-mark">
                <div class="oc-sidebar__logo">O</div>
                <span class="oc-sidebar__brand-name">OC Admin</span>
            </a>

            @include('open-collab.partials.brand-switcher', [
            'site' => $site,
            'currentSiteName' => $currentSiteName ?? $site,
            'availableSites' => $availableSites ?? [],
            ])
        </div>

        <div class="oc-sidebar__nav">

            <div class="oc-sidebar__section-label">Content</div>

            <a href="/<?= $site ?>/open-collab/admin/queue"
               class="oc-sidebar__nav-link <?= ($activeNav ?? '') === 'articles' ? 'active' : '' ?>">
                <svg viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd"
                          d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z"
                          clip-rule="evenodd"/>
                </svg>
                Approval Queue
                <?php if (!empty($pendingCount) && $pendingCount > 0): ?>
                    <span class="oc-sidebar__badge"><?= (int)$pendingCount ?></span>
                <?php endif; ?>
            </a>

            <a href="/<?= $site ?>/open-collab/admin/escalations"
               class="oc-sidebar__nav-link <?= ($activeNav ?? '') === 'articles' ? 'active' : '' ?>">
                <svg viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd"
                          d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z"
                          clip-rule="evenodd"/>
                </svg>
                Escalations
            </a>

            <div class="oc-sidebar__section-label">Contributors</div>

            <a href="/<?= $site ?>/open-collab/admin/contributors"
               class="oc-sidebar__nav-link <?= ($activeNav ?? '') === 'contributors' ? 'active' : '' ?>">
                <svg viewBox="0 0 20 20" fill="currentColor">
                    <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
                </svg>
                Contributors
            </a>

            <a href="/<?= $site ?>/open-collab/admin/invitations"
               class="oc-sidebar__nav-link <?= ($activeNav ?? '') === 'invitations' ? 'active' : '' ?>">
                <svg viewBox="0 0 20 20" fill="currentColor">
                    <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                    <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
                </svg>
                Invitations
            </a>

            <a href="/<?= $site ?>/open-collab/admin/violations"
               class="oc-sidebar__nav-link <?= ($activeNav ?? '') === 'violations' ? 'active' : '' ?>">
                <svg viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.72-1.36 3.486 0l6.518 11.589c.75 1.333-.213 2.987-1.743 2.987H3.482c-1.53 0-2.493-1.654-1.743-2.987L8.257 3.1zM11 14a1 1 0 10-2 0 1 1 0 002 0zm-1-2a1 1 0 01-1-1V7a1 1 0 112 0v4a1 1 0 01-1 1z" clip-rule="evenodd"/>
                </svg>
                Violations
            </a>

            <div class="oc-sidebar__section-label">Finance</div>

            <a href="/<?= $site ?>/open-collab/admin/payouts"
               class="oc-sidebar__nav-link <?= ($activeNav ?? '') === 'payouts' ? 'active' : '' ?>">
                <svg viewBox="0 0 20 20" fill="currentColor">
                    <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/>
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.077 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.077-2.354-1.253V5z" clip-rule="evenodd"/>
                </svg>
                Payouts
            </a>

            <a href="/<?= $site ?>/open-collab/admin/disputes"
               class="oc-sidebar__nav-link <?= ($activeNav ?? '') === 'disputes' ? 'active' : '' ?>">
                <svg viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v6a1 1 0 102 0V5zm-1 10a1.25 1.25 0 100-2.5 1.25 1.25 0 000 2.5z" clip-rule="evenodd"/>
                </svg>
                Disputes
            </a>

            <a href="/<?= $site ?>/open-collab/admin/payment-terms"
               class="oc-sidebar__nav-link <?= ($activeNav ?? '') === 'payment_terms' ? 'active' : '' ?>">
                <svg viewBox="0 0 20 20" fill="currentColor"><path d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"/></svg>
                Payment Terms
            </a>
        </div>
    </nav>

    <div class="oc-main">
        <header class="oc-header">
            <?php if (!empty($breadcrumbs)): ?>
                <nav class="oc-header__breadcrumb" aria-label="Breadcrumb">
                    <?php foreach ($breadcrumbs as $i => $crumb): ?>
                        <?php if ($i > 0): ?><span>/</span><?php endif; ?>
                        <?php if (!empty($crumb['url'])): ?>
                            <a href="<?= htmlspecialchars($crumb['url']) ?>"><?= htmlspecialchars($crumb['label']) ?></a>
                        <?php else: ?>
                            <span><?= htmlspecialchars($crumb['label']) ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </nav>
            <?php else: ?>
                <span class="oc-header__title"><?= htmlspecialchars($pageTitle ?? '') ?></span>
            <?php endif; ?>
        </header>

        <main class="oc-page <?= $pageClass ?? '' ?>" id="main-content" role="main">
            @yield('content')
        </main>
    </div>
</div>

<button id="sidebar-toggle" aria-label="Toggle navigation" style="display:none;position:fixed;bottom:20px;right:20px;width:48px;height:48px;background:var(--navy);border:none;border-radius:50%;cursor:pointer;z-index:300;box-shadow:0 4px 16px rgba(0,0,0,.3);" onclick="document.getElementById('oc-sidebar').classList.toggle('open')">
    <svg viewBox="0 0 20 20" fill="white" width="20" height="20"><path fill-rule="evenodd" d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/></svg>
</button>

<script>
    document.querySelectorAll('.oc-alert').forEach(el => {
        setTimeout(() => {
            el.style.transition = 'opacity .4s';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 400);
        }, 4500);
    });

    const mq = window.matchMedia('(max-width: 900px)');
    const toggleBtn = document.getElementById('sidebar-toggle');
    if (mq.matches) toggleBtn.style.display = 'grid';
    mq.addEventListener('change', e => toggleBtn.style.display = e.matches ? 'grid' : 'none');
</script>

@yield('scripts')
<?php if (!empty($extraScripts)): echo $extraScripts; endif; ?>
</html>
