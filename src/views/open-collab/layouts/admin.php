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
                    <path fill-rule="evenodd"
                          d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                          clip-rule="evenodd"/>
                </svg>
                Violations
            </a>

            <a href="/<?= $site ?>/open-collab/admin/contributor-requests"
               class="oc-sidebar__nav-link <?= ($activeNav ?? '') === 'contributor-requests' ? 'active' : '' ?>">
                <svg viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd"
                          d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                          clip-rule="evenodd"/>
                </svg>
                Requests
            </a>

            <div class="oc-sidebar__section-label">Finance</div>

            <a href="/<?= $site ?>/open-collab/admin/payouts"
               class="oc-sidebar__nav-link <?= ($activeNav ?? '') === 'payouts' ? 'active' : '' ?>">
                <svg viewBox="0 0 20 20" fill="currentColor">
                    <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/>
                    <path fill-rule="evenodd"
                          d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.077 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.077-2.354-1.253V5z"
                          clip-rule="evenodd"/>
                </svg>
                Payouts
            </a>

            <a href="/<?= $site ?>/open-collab/admin/disputes"
               class="oc-sidebar__nav-link <?= ($activeNav ?? '') === 'disputes' ? 'active' : '' ?>">
                <svg viewBox="0 0 20 20" fill="currentColor">
                    <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/>
                    <path fill-rule="evenodd"
                          d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.077 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.077-2.354-1.253V5z"
                          clip-rule="evenodd"/>
                </svg>
                Disputes
            </a>

            <div class="oc-sidebar__section-label">Site Config</div>

            <a href="/<?= $site ?>/open-collab/admin/sites"
               class="oc-sidebar__nav-link <?= ($activeNav ?? '') === 'sites' ? 'active' : '' ?>">
                <svg viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd"
                          d="M4.083 9h1.946c.089-1.546.383-2.97.837-4.118A6.004 6.004 0 004.083
                     9zM10 2a8 8 0 100 16A8 8 0 0010 2zm0 2c-.076 0-.232.032-.465.262
                     -.238.234-.497.623-.737 1.182-.389.907-.673 2.142-.766 3.556h3.936
                     c-.093-1.414-.377-2.649-.766-3.556-.24-.559-.499-.948-.737-1.182
                     C10.232 4.032 10.076 4 10 4zm3.971 5c-.089-1.546-.383-2.97-.837-4.118
                     A6.004 6.004 0 0115.917 9h-1.946zm-2.003 2H8.032c.093 1.414.377
                     2.649.766 3.556.24.559.499.948.737 1.182.233.23.389.262.465.262
                     .076 0 .232-.032.465-.262.238-.234.498-.623.737-1.182.389-.907
                     .673-2.142.766-3.556zm1.166 4.118c.454-1.147.748-2.572.837-4.118
                     h1.946a6.004 6.004 0 01-2.783 4.118zm-6.268 0C6.412 13.97 6.118
                     12.546 6.03 11H4.083a6.004 6.004 0 002.783 4.118z"
                          clip-rule="evenodd"/>
                </svg>
                Sites
            </a>

            <a href="/<?= $site ?>/open-collab/admin/sites/settings"
               class="oc-sidebar__nav-link <?= ($activeNav ?? '') === 'site_settings' ? 'active' : '' ?>">
                <svg viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd"
                          d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z"
                          clip-rule="evenodd"/>
                </svg>
                Site Settings
            </a>

            <a href="/<?= $site ?>/open-collab/admin/contracts"
               class="oc-sidebar__nav-link <?= ($activeNav ?? '') === 'contracts' ? 'active' : '' ?>">
                <svg viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd"
                          d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"
                          clip-rule="evenodd"/>
                </svg>
                Contracts
            </a>

            <a href="/<?= $site ?>/open-collab/admin/guidelines"
               class="oc-sidebar__nav-link <?= ($activeNav ?? '') === 'guidelines' ? 'active' : '' ?>">
                <svg viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd"
                          d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                          clip-rule="evenodd"/>
                </svg>
                Guidelines
            </a>

            <a href="/<?= $site ?>/open-collab/admin/terms"
               class="oc-sidebar__nav-link <?= ($activeNav ?? '') === 'terms' ? 'active' : '' ?>">
                <svg viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd"
                          d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                          clip-rule="evenodd"/>
                </svg>
                Terms
            </a>

            <a href="/<?= $site ?>/open-collab/admin/payment-terms"
               class="oc-sidebar__nav-link <?= ($activeNav ?? '') === 'payment_terms' ? 'active' : '' ?>">
                <svg viewBox="0 0 20 20" fill="currentColor">
                    <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/>
                    <path fill-rule="evenodd"
                          d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.077 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.077-2.354-1.253V5z"
                          clip-rule="evenodd"/>
                </svg>
                Payment Terms
            </a>

            <div class="oc-sidebar__section-label">Activity</div>

            <a href="/<?= $site ?>/open-collab/admin/activity"
               class="oc-sidebar__nav-link <?= ($activeNav ?? '') === 'activity' ? 'active' : '' ?>">
                <svg viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd"
                          d="M3 3a1 1 0 000 2v8a2 2 0 002 2h2.586l-1.293 1.293a1 1 0 101.414 1.414L10 15.414l2.293 2.293a1 1 0 001.414-1.414L12.414 15H15a2 2 0 002-2V5a1 1 0 100-2H3zm11.707 4.707a1 1 0 00-1.414-1.414L10 9.586 8.707 8.293a1 1 0 00-1.414 0l-2 2a1 1 0 101.414 1.414L8 10.414l1.293 1.293a1 1 0 001.414 0l4-4z"
                          clip-rule="evenodd"/>
                </svg>
                Activity Feed
            </a>

        </div>

        <div class="oc-sidebar__footer">
            <div class="oc-sidebar__user">
                <div class="oc-sidebar__avatar">
                    <?= strtoupper(substr($currentUser->name ?? 'A', 0, 1)) ?>
                </div>
                <div class="oc-sidebar__user-info">
                    <div class="oc-sidebar__user-name"><?= htmlspecialchars($currentUser->name ?? 'Admin') ?></div>
                    <div class="oc-sidebar__user-role">Administrator</div>
                </div>
                <a href="/logout" class="oc-sidebar__sign-out" title="Sign out" aria-label="Sign out">
                    <svg viewBox="0 0 20 20" fill="currentColor" width="16" height="16">
                        <path fill-rule="evenodd"
                              d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z"
                              clip-rule="evenodd"/>
                    </svg>
                </a>
            </div>
        </div>

    </nav>

    <!-- ── Main ─────────────────────────────────────────── -->
    <div class="oc-main">

        <header class="oc-header">
            <?php if (!empty($breadcrumbs)): ?>
                <nav class="oc-header__breadcrumb" aria-label="Breadcrumb">
                    <?php foreach ($breadcrumbs as $i => $crumb): ?>
                        <?php if ($i > 0): ?>
                            <svg width="12" height="12" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                      d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                                      clip-rule="evenodd"/>
                            </svg>
                        <?php endif; ?>
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

            <div class="oc-header__spacer"></div>

            <div class="oc-header__actions">
                <?php if (!empty($headerActions)): echo $headerActions; endif; ?>
            </div>
        </header>

        <?php if (!empty($flashSuccess)): ?>
            <div style="padding:0 32px;margin-top:16px;">
                <div class="oc-alert oc-alert--success">
                    <svg viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                              d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                              clip-rule="evenodd"/>
                    </svg>
                    <?= htmlspecialchars($flashSuccess) ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($flashError)): ?>
            <div style="padding:0 32px;margin-top:16px;">
                <div class="oc-alert oc-alert--danger">
                    <svg viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                              d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                              clip-rule="evenodd"/>
                    </svg>
                    <?= htmlspecialchars($flashError) ?>
                </div>
            </div>
        <?php endif; ?>

        <main class="oc-page <?= $pageClass ?? '' ?>" id="main-content" role="main">
            @yield('content')
        </main>

    </div>

</div>

<button
        id="sidebar-toggle"
        aria-label="Toggle navigation"
        style="display:none;position:fixed;bottom:20px;right:20px;width:48px;height:48px;background:var(--navy);border:none;border-radius:50%;cursor:pointer;z-index:300;box-shadow:0 4px 16px rgba(0,0,0,.3);"
        onclick="document.getElementById('oc-sidebar').classList.toggle('open')">
    <svg viewBox="0 0 20 20" fill="white" width="20" height="20">
        <path fill-rule="evenodd"
              d="M3 5a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM3 15a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z"
              clip-rule="evenodd"/>
    </svg>
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
</html>
