@yield('logic')

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'OpenCollab') ?> — OpenCollab</title>
    @css('open-collab.css')
    <?php if (!empty($extraHead)): echo $extraHead; endif; ?>
</head>
<body>

<div class="oc-shell">

    <!-- ── Sidebar ──────────────────────────────────────── -->
    <nav class="oc-sidebar" id="oc-sidebar" role="navigation" aria-label="Main navigation">

        <div class="oc-sidebar__brand">
            <a href="/contributor/dashboard" class="oc-sidebar__brand-mark">
                <div class="oc-sidebar__logo">O</div>
                <span class="oc-sidebar__brand-name">OpenCollab</span>
            </a>

            @include('open-collab.partials.brand-switcher', [
            'site' => $site,
            'currentSiteName' => $currentSiteName ?? $site,
            'availableSites' => $availableSites ?? [],
            ])
        </div>

        <div class="oc-sidebar__nav">

            <div class="oc-sidebar__section-label">Workspace</div>

            <a href="/<?= $site ?>/open-collab/dashboard"
               class="oc-sidebar__nav-link <?= ($activeNav ?? '') === 'dashboard' ? 'active' : '' ?>">
                <svg viewBox="0 0 20 20" fill="currentColor">
                    <path d="M2 10a8 8 0 1116 0A8 8 0 012 10zm8-4a1 1 0 00-1 1v3H6a1 1 0 000 2h3v3a1 1 0 002 0v-3h3a1 1 0 000-2h-3V7a1 1 0 00-1-1z"/>
                </svg>
                Dashboard
            </a>

            <a href="/<?= $site ?>/open-collab/briefs"
               class="oc-sidebar__nav-link <?= ($activeNav ?? '') === 'briefs' ? 'active' : '' ?>">
                <svg viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd"
                          d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V7.414A2 2 0 0017.414 6L15 3.586A2 2 0 0013.586 3H4zm8 1.5V7a1 1 0 001 1h2.5v7H4V5h8z"
                          clip-rule="evenodd"/>
                </svg>
                My Briefs
            </a>

            <a href="/<?= $site ?>/open-collab/articles"
               class="oc-sidebar__nav-link <?= ($activeNav ?? '') === 'articles' ? 'active' : '' ?>">
                <svg viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd"
                          d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z"
                          clip-rule="evenodd"/>
                </svg>
                My Articles
                <?php if (!empty($draftCount) && $draftCount > 0): ?>
                    <span class="oc-sidebar__badge"><?= $draftCount ?></span>
                <?php endif; ?>
            </a>

            <a href="/<?= $site ?>/open-collab/articles/create"
               class="oc-sidebar__nav-link <?= ($activeNav ?? '') === 'create' ? 'active' : '' ?>">
                <svg viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd"
                          d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z"
                          clip-rule="evenodd"/>
                </svg>
                New Article
            </a>

            <div class="oc-sidebar__section-label">Finance</div>

            <a href="/<?= $site ?>/open-collab/earnings"
               class="oc-sidebar__nav-link <?= ($activeNav ?? '') === 'earnings' ? 'active' : '' ?>">
                <svg viewBox="0 0 20 20" fill="currentColor">
                    <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/>
                    <path fill-rule="evenodd"
                          d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.077 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.077-2.354-1.253V5z"
                          clip-rule="evenodd"/>
                </svg>
                Earnings
            </a>

            <a href="/<?= $site ?>/open-collab/disputes"
               class="oc-sidebar__nav-link <?= ($activeNav ?? '') === 'disputes' ? 'active' : '' ?>">
                <svg viewBox="0 0 20 20" fill="currentColor">
                    <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/>
                    <path fill-rule="evenodd"
                          d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.077 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.077-2.354-1.253V5z"
                          clip-rule="evenodd"/>
                </svg>
                Disputes
            </a>

            <a href="/<?= $site ?>/open-collab/payouts"
               class="oc-sidebar__nav-link <?= ($activeNav ?? '') === 'payouts' ? 'active' : '' ?>">
                <svg viewBox="0 0 20 20" fill="currentColor">
                    <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/>
                    <path fill-rule="evenodd"
                          d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.077 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.077-2.354-1.253V5z"
                          clip-rule="evenodd"/>
                </svg>
                Payouts
            </a>

            <div class="oc-sidebar__section-label">Account</div>

            <a href="/<?= $site ?>/open-collab/settings"
               class="oc-sidebar__nav-link <?= ($activeNav ?? '') === 'settings' ? 'active' : '' ?>">
                <svg viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd"
                          d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z"
                          clip-rule="evenodd"/>
                </svg>
                Settings
            </a>

        </div>

        <div class="oc-sidebar__footer">
            <div class="oc-sidebar__user">
                <div class="oc-sidebar__avatar">
                    <?= strtoupper(substr($currentUser->name ?? 'C', 0, 1)) ?>
                </div>
                <div class="oc-sidebar__user-info">
                    <div class="oc-sidebar__user-name"><?= htmlspecialchars($currentUser->name ?? 'Contributor') ?></div>
                    <div class="oc-sidebar__user-role">Contributor</div>
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

    </nav><!-- /sidebar -->

    <!-- ── Main ─────────────────────────────────────────── -->
    <div class="oc-main">

        <!-- Top Header -->
        <header class="oc-header">
            <?php if (!empty($breadcrumbs)): ?>
                <nav class="oc-header__breadcrumb" aria-label="Breadcrumb">
                    <?php foreach ($breadcrumbs as $i => $crumb): ?>
                        <?php if ($i > 0): ?>
                            <svg width="12" height="12" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                      d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                                      clip-rule="evenodd"/>
                            </svg><?php endif; ?>
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
                <!-- Notification bell -->
                <div class="oc-notif-bell" id="notif-bell-wrap">
                    <button class="oc-notif-bell__btn" id="notif-bell"
                            aria-label="Notifications"
                            onclick="toggleNotifDropdown()">
                        <svg viewBox="0 0 20 20" fill="currentColor" width="20" height="20">
                            <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-2.83-2h5.66A3 3 0 0110 18z"/>
                        </svg>
                        <span class="oc-notif-bell__badge" id="notif-count-badge"></span>
                    </button>

                    <div class="oc-notif-dropdown" id="notif-dropdown">
                        <div class="oc-notif-dropdown__header">
                            <span class="oc-notif-dropdown__heading">Notifications</span>
                            <button class="oc-notif-dropdown__mark-all" onclick="window.markAllNotifRead()">
                                Mark all read
                            </button>
                        </div>
                        <div class="oc-notif-dropdown__list" id="notif-list">
                            <div class="oc-notif-dropdown__empty" id="notif-empty">No notifications</div>
                        </div>
                        <div class="oc-notif-dropdown__footer">
                            <button id="notif-load-more" class="oc-notif-dropdown__mark-all">
                                Load more
                            </button>
                        </div>
                    </div>
                </div>

                <?php if (!empty($headerActions)): echo $headerActions; endif; ?>
            </div>
        </header>

        <!-- Flash messages -->
        <?php if (!empty($flashSuccess)): ?>
            <div style="padding: 0 32px; margin-top: 16px;">
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
            <div style="padding: 0 32px; margin-top: 16px;">
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

<!-- Mobile sidebar toggle -->
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
    // Auto-dismiss flash messages
    // document.querySelectorAll('.oc-alert').forEach(el => {
    //     setTimeout(() => {
    //         el.style.transition = 'opacity .4s';
    //         el.style.opacity = '0';
    //         setTimeout(() => el.remove(), 400);
    //     }, 4500);
    // });

    // Mobile nav
    const mq = window.matchMedia('(max-width: 900px)');
    const toggleBtn = document.getElementById('sidebar-toggle');
    if (mq.matches) toggleBtn.style.display = 'grid';
    const wrapper = document.getElementById('oc-notifications');
    const btn = document.getElementById('notification-btn');
    const dropdown = document.getElementById('notification-dropdown');

    class NotificationBell {
        constructor({siteSlug, token}) {
            this.siteSlug = siteSlug;
            this.token = token;

            this.baseUrl = `/api/${siteSlug}/open-collab/notifications`;

            // state
            this.dropdownOpen = false;
            this.nextCursor = null;
            this.loading = false;
            this.initialLoaded = false;

            // DOM
            this.dropdown = null;
            this.list = null;
            this.empty = null;
            this.badge = null;
            this.loadMoreBtn = null;
        }

        init() {
            this.dropdown = document.getElementById('notif-dropdown');
            this.list = document.getElementById('notif-list');
            this.empty = document.getElementById('notif-empty');
            this.badge = document.getElementById('notif-count-badge');
            this.loadMoreBtn = document.getElementById('notif-load-more');

            this.bindEvents();
            this.startPolling();
        }

        bindEvents() {
            // scroll pagination
            this.list.addEventListener('scroll', () => {
                const nearBottom =
                    this.list.scrollTop + this.list.clientHeight >=
                    this.list.scrollHeight - 10;

                if (nearBottom && this.nextCursor && !this.loading) {
                    this.load(false);
                }
            });

            // button pagination
            if (this.loadMoreBtn) {
                this.loadMoreBtn.addEventListener('click', () => {
                    this.load(false);
                });
            }

            // click outside
            document.addEventListener('click', (e) => {
                const wrap = document.getElementById('notif-bell-wrap');
                if (this.dropdownOpen && !wrap.contains(e.target)) {
                    this.close();
                }
            });
        }

        toggle() {
            this.dropdownOpen ? this.close() : this.open();
        }

        open() {
            this.dropdownOpen = true;

            this.dropdown.style.display = 'block';
            this.dropdown.classList.add('oc-notif-dropdown--open');

            // only load once unless you decide to invalidate
            if (!this.initialLoaded) {
                this.nextCursor = null;
                this.load(true);
            }
        }

        close() {
            this.dropdownOpen = false;

            this.dropdown.classList.remove('oc-notif-dropdown--open');
            this.dropdown.style.display = 'none';
        }

        async load(reset = true) {
            if (!this.token || this.loading) return;

            this.loading = true;

            if (this.loadMoreBtn) {
                this.loadMoreBtn.disabled = true;
                this.loadMoreBtn.textContent = 'Loading...';
            }

            try {
                const url = new URL(this.baseUrl, window.location.origin);

                url.searchParams.set('per_page', 15);
                url.searchParams.set('unread_only', 0);

                if (!reset && this.nextCursor) {
                    url.searchParams.set('cursor', this.nextCursor);
                }

                const res = await fetch(url, {
                    headers: {Authorization: `Bearer ${this.token}`}
                });

                if (!res.ok) return;

                const data = await res.json();

                if (reset) {
                    this.list.innerHTML = '';
                }

                if (reset && (!data.notifications || data.notifications.length === 0)) {
                    this.empty.style.display = 'block';
                    this.initialLoaded = true;
                    return;
                }

                this.empty.style.display = 'none';

                this.appendNotifications(data.notifications);

                // cursor
                this.nextCursor = data.next_cursor || false;

                // button state
                if (this.loadMoreBtn) {
                    if (!this.nextCursor) {
                        this.loadMoreBtn.style.display = 'none';
                    } else {
                        this.loadMoreBtn.style.display = 'block';
                        this.loadMoreBtn.disabled = false;
                        this.loadMoreBtn.textContent = 'Load more';
                    }
                }

                this.initialLoaded = true;

            } finally {
                this.loading = false;
            }
        }

        appendNotifications(notifications) {
            const fragment = document.createDocumentFragment();

            notifications.forEach(n => {
                const item = document.createElement('div');
                item.className =
                    'oc-notif-item' + (n.is_read ? '' : ' oc-notif-item--unread');

                const message = n.body || n.message || '';

                item.innerHTML = `
                <div class="oc-notif-item__dot ${n.is_read ? 'oc-notif-item__dot--hidden' : ''}"></div>
                <div class="oc-notif-item__body">
                    <div class="oc-notif-item__title">${this.escape(n.title)}</div>
                    ${message ? `<div class="oc-notif-item__text">${this.escape(message)}</div>` : ''}
                    <div class="oc-notif-item__time">${this.formatRelative(n.created_at)}</div>
                </div>
            `;

                item.addEventListener('click', () => this.markOneRead(n.id, item));

                fragment.appendChild(item);
            });

            this.list.appendChild(fragment);
        }

        async markOneRead(id, el) {
            // optimistic UI
            el.classList.remove('oc-notif-item--unread');

            const dot = el.querySelector('.oc-notif-item__dot');
            if (dot) dot.classList.add('oc-notif-item__dot--hidden');

            try {
                await fetch(`${this.baseUrl}/read`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Authorization: `Bearer ${this.token}`
                    },
                    body: JSON.stringify({notification_id: id})
                });

                this.fetchCount();
            } catch {
            }
        }

        async markAllRead() {
            if (!this.token) return;

            try {
                await fetch(`${this.baseUrl}/read-all`, {
                    method: 'POST',
                    headers: {Authorization: `Bearer ${this.token}`}
                });

                this.badge.classList.remove('oc-notif-bell__badge--visible');
                this.badge.textContent = '';

                this.list.querySelectorAll('.oc-notif-item').forEach(el => {
                    el.classList.remove('oc-notif-item--unread');
                    const dot = el.querySelector('.oc-notif-item__dot');
                    if (dot) dot.classList.add('oc-notif-item__dot--hidden');
                });

            } catch {
            }
        }

        startPolling() {
            this.fetchCount();
            setInterval(() => this.fetchCount(), 30000);
        }

        async fetchCount() {
            if (!this.token) return;

            try {
                const res = await fetch(`${this.baseUrl}/unread-count`, {
                    headers: {Authorization: `Bearer ${this.token}`}
                });

                if (!res.ok) return;

                const data = await res.json();

                if (data.count > 0) {
                    this.badge.textContent = data.count > 99 ? '99+' : data.count;
                    this.badge.classList.add('oc-notif-bell__badge--visible');
                } else {
                    this.badge.classList.remove('oc-notif-bell__badge--visible');
                    this.badge.textContent = '';
                }

            } catch {
            }
        }

        formatRelative(iso) {
            const diff = Math.floor((Date.now() - new Date(iso)) / 1000);

            if (diff < 60) return 'just now';
            if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
            if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
            if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';

            return new Date(iso).toLocaleDateString();
        }

        escape(s) {
            return String(s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }
    }

    // init
    const bell = new NotificationBell({
        siteSlug: '<?= htmlspecialchars($site ?? '') ?>',
        token: localStorage.getItem('oc_token') || ''
    });

    bell.init();

    // expose to HTML
    window.toggleNotifDropdown = () => bell.toggle();
    window.markAllNotifRead = () => bell.markAllRead();
</script>

@yield('scripts')
</body>
</html>
