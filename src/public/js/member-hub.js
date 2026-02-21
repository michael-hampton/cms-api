/**
 * Member Hub — member-hub.js  v2
 *
 * Depends on window.MH being written by member-hub.php before this loads.
 * No framework dependencies. Vanilla ES2017+.
 *
 * Tabs (logged-in):
 *   feed         – activity notifications
 *   saved        – saved articles
 *   notifications – system notifications (rewards, gifts, badges, verification)
 *   activity     – badges + points + stats
 *   community    – polls, predictors, leaderboard
 *   deals        – product deals
 *
 * Tabs (guest):
 *   community, deals
 */

(function () {
    'use strict';

    if (!window.MH) {
        console.warn('[MemberHub] window.MH not found — check member-hub.php is included before this script.');
        return;
    }

    alert('here')

    const CFG = window.MH;

    /* =========================================================================
       DOM helpers
       ========================================================================= */
    const $ = id => document.getElementById(id);
    const $$ = sel => document.querySelectorAll(sel);

    /* =========================================================================
       State
       ========================================================================= */
    const state = {
        panelOpen: false,
        activeTab: CFG.isLoggedIn ? 'feed' : 'community',
        isSaved: CFG.isSaved,
        isLiked: CFG.isLiked,
        likeCount: CFG.likeCount,
        unread: CFG.unread,

        // Cached API responses — null means not yet fetched
        feed: null,
        saved: null,
        notifications: null,
        activity: null,
        community: null,
        deals: null,
        subscriptions: null,
        badges: null,
    };

    /* =========================================================================
       Helpers
       ========================================================================= */
    function esc(str) {
        if (str == null) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function route(tpl, params = {}) {
        return Object.entries(params).reduce(
            (url, [k, v]) => url.replace(`{${k}}`, encodeURIComponent(v)),
            tpl
        );
    }

    async function api(url, options = {}) {
        alert(url)
        const res = await fetch(url, {
            credentials: 'same-origin',
            ...options,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CFG.csrf,
                'X-Requested-With': 'XMLHttpRequest',
                ...(options.headers ?? {}),
            },
        });
        if (!res.ok) throw new Error(`HTTP ${res.status} — ${url}`);
        if (res.status === 204) return null;
        return res.json();
    }

    /* =========================================================================
       Panel — open / close
       ========================================================================= */
    const overlay = $('mh-overlay');
    const panel = $('mh-panel');
    const closeBtn = $('mh-close');
    const hubTrigger = $('mh-hub-trigger');

    function openPanel(tab) {
        if (tab) state.activeTab = tab;
        panel.classList.add('is-open');
        panel.setAttribute('aria-hidden', 'false');
        overlay.classList.add('is-visible');
        overlay.removeAttribute('aria-hidden');
        if (hubTrigger) hubTrigger.setAttribute('aria-expanded', 'true');
        state.panelOpen = true;
        document.body.style.overflow = 'hidden';
        activateTab(state.activeTab);
    }

    function closePanel() {
        panel.classList.remove('is-open');
        panel.setAttribute('aria-hidden', 'true');
        overlay.classList.remove('is-visible');
        overlay.setAttribute('aria-hidden', 'true');
        if (hubTrigger) hubTrigger.setAttribute('aria-expanded', 'false');
        state.panelOpen = false;
        document.body.style.overflow = '';
    }

    if (hubTrigger) hubTrigger.addEventListener('click', () => {
        state.panelOpen ? closePanel() : openPanel();
    });

    if (closeBtn) closeBtn.addEventListener('click', closePanel);
    if (overlay) overlay.addEventListener('click', closePanel);

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && state.panelOpen) closePanel();
    });

    // Listen for the custom event fired by utility-bar.js
    document.addEventListener('memberHub:open', e => {
        openPanel(e.detail?.tab ?? null);
    });

    /* =========================================================================
       Tabs
       ========================================================================= */
    function activateTab(tabId) {
        state.activeTab = tabId;

        $$('#mh-tabs .mh-tab').forEach(btn => {
            const active = btn.dataset.tab === tabId;
            btn.classList.toggle('mh-tab--active', active);
            btn.setAttribute('aria-selected', String(active));
        });

        $$('.mh-pane').forEach(pane => {
            pane.classList.toggle('mh-pane--active', pane.id === `mh-pane-${tabId}`);
        });

        switch (tabId) {
            case 'feed':
                loadFeed();
                break;
            case 'saved':
                loadSaved();
                break;
            case 'notifications':
                loadNotifications();
                break;
            case 'activity':
                loadActivity();
                break;
            case 'community':
                loadCommunity();
                break;
            case 'deals':
                loadDeals();
                break;
            case 'subscriptions':
                loadSubscriptions();
                break;
            case 'badges':
                loadBadges();
                break;
        }
    }

    $$('#mh-tabs .mh-tab').forEach(btn => {
        btn.addEventListener('click', () => activateTab(btn.dataset.tab));
    });

    /* =========================================================================
       Hub badge (unread count)
       ========================================================================= */
    const hubBadge = $('mh-hub-badge');

    function setHubBadge(n) {
        if (!hubBadge) return;
        hubBadge.textContent = n > 0 ? String(n) : '';
        hubBadge.style.display = n > 0 ? '' : 'none';
        hubBadge.setAttribute('aria-label', `${n} unread notifications`);
    }

    setHubBadge(state.unread);

    /* =========================================================================
       UTILITY BAR — only runs on article pages
       ========================================================================= */
    if (CFG.isArticlePage) {
        initUtilityBar();
    }

    function initUtilityBar() {
        /* ── Reading progress ── */
        const progressFill = $('mh-progress-fill');
        const progressTrack = progressFill?.closest('[role="progressbar"]');

        function updateProgress() {
            if (!progressFill) return;
            const scrollable = document.documentElement.scrollHeight - window.innerHeight;
            if (scrollable <= 0) return;
            const pct = Math.min(100, Math.round((window.scrollY / scrollable) * 100));
            progressFill.style.width = `${pct}%`;
            if (progressTrack) progressTrack.setAttribute('aria-valuenow', pct);
        }

        window.addEventListener('scroll', updateProgress, {passive: true});
        updateProgress();

        /* ── Save button (article page utility bar) ── */
        const btnSave = $('mh-btn-save');
        const saveIcon = $('mh-save-icon');
        const saveLabel = $('mh-save-label');

        function setSaveState(saved, animate = false) {
            state.isSaved = saved;
            if (!btnSave) return;
            btnSave.dataset.saved = saved ? '1' : '0';
            btnSave.classList.toggle('mh-ub-btn--saved', saved);
            btnSave.setAttribute('aria-label', saved ? 'Unsave article' : 'Save article');
            if (saveIcon) saveIcon.setAttribute('fill', saved ? 'currentColor' : 'none');
            if (saveLabel) saveLabel.textContent = saved ? 'Saved' : 'Save';
            if (animate) {
                btnSave.classList.add('mh-btn-pop');
                setTimeout(() => btnSave.classList.remove('mh-btn-pop'), 300);
            }
            syncCardButtons(CFG.pageId, saved);
        }

        setSaveState(state.isSaved);

        if (btnSave) {
            btnSave.addEventListener('click', async () => {
                if (!CFG.isLoggedIn) {
                    showGuestPrompt(CFG.pageId, btnSave);
                    return;
                }
                const newSaved = !state.isSaved;
                setSaveState(newSaved, true);
                state.saved = null;
                try {
                    const res = await api(CFG.routes.saveToggle, {
                        method: 'POST',
                        body: JSON.stringify({page_id: CFG.pageId}),
                    });
                    setSaveState(res.saved, false);
                } catch (err) {
                    console.error('[MemberHub] save toggle:', err);
                    setSaveState(!newSaved);
                }
            });
        }

        /* ── Like button ── */
        const btnLike = $('mh-btn-like');
        const likeIcon = $('mh-like-icon');
        const likeLabel = $('mh-like-label');

        function setLikeState(liked, animate = false) {
            state.isLiked = liked;
            if (!btnLike) return;
            btnLike.dataset.liked = liked ? '1' : '0';
            btnLike.classList.toggle('mh-ub-btn--liked', liked);
            btnLike.setAttribute('aria-label', liked ? 'Unlike article' : 'Like article');
            if (likeIcon) likeIcon.setAttribute('fill', liked ? 'currentColor' : 'none');
            if (likeLabel) likeLabel.textContent = state.likeCount > 0 ? String(state.likeCount) : 'Like';
            if (animate) {
                btnLike.classList.add('mh-btn-pop');
                setTimeout(() => btnLike.classList.remove('mh-btn-pop'), 300);
            }
        }

        document.addEventListener('click', e => {
            if (guestPrompt &&
                guestPrompt.style.display !== 'none' &&
                !guestPrompt.contains(e.target) &&
                !e.target.closest('#mh-btn-save') &&
                !e.target.closest('.mh-card-save')) {
                hideGuestPrompt();
            }
        }, true);

        setLikeState(state.isLiked);

        if (btnLike) {
            btnLike.addEventListener('click', async () => {
                const newLiked = !state.isLiked;
                state.likeCount = Math.max(0, state.likeCount + (newLiked ? 1 : -1));
                setLikeState(newLiked, true);
                try {
                    await api(route(CFG.routes.likePage, {id: CFG.pageId}), {
                        method: newLiked ? 'POST' : 'DELETE',
                    });
                } catch (err) {
                    console.error('[MemberHub] like toggle:', err);
                    state.likeCount = Math.max(0, state.likeCount + (newLiked ? -1 : 1));
                    setLikeState(!newLiked);
                }
            });
        }

        /* ── Share button ── */
        const btnShare = $('mh-btn-share');
        if (btnShare) {
            btnShare.addEventListener('click', async () => {
                const shareData = {title: document.title, url: location.href};
                if (navigator.share) {
                    try {
                        await navigator.share(shareData);
                        return;
                    } catch (_) {
                    }
                }
                try {
                    await navigator.clipboard.writeText(location.href);
                    const lbl = btnShare.querySelector('.mh-ub-label');
                    if (lbl) {
                        const orig = lbl.textContent;
                        lbl.textContent = 'Copied!';
                        setTimeout(() => {
                            lbl.textContent = orig;
                        }, 1800);
                    }
                } catch (_) {
                }
            });
        }
    }

    /* =========================================================================
       CARD bookmark buttons
       ========================================================================= */
    function syncCardButtons(pageId, saved) {
        if (!pageId) return;
        $$(`[data-page-id="${pageId}"].mh-card-save`).forEach(btn => applyCardSaveState(btn, saved));
    }

    function applyCardSaveState(btn, saved) {
        btn.dataset.saved = saved ? '1' : '0';
        btn.setAttribute('aria-pressed', String(saved));
        btn.setAttribute('title', saved ? 'Remove from saved' : 'Save article');
        btn.classList.toggle('is-saved', saved);
        const empty = btn.querySelector('.mh-card-save-icon--empty');
        const filled = btn.querySelector('.mh-card-save-icon--filled');
        if (empty) empty.style.display = saved ? 'none' : '';
        if (filled) filled.style.display = saved ? '' : 'none';
    }

    async function handleCardSaveClick(btn) {
        const pageId = btn.dataset.pageId;
        if (!pageId) return;
        if (!CFG.isLoggedIn) {
            showGuestPrompt(pageId, btn);
            return;
        }

        const wasSaved = btn.dataset.saved === '1';
        const nowSaved = !wasSaved;
        applyCardSaveState(btn, nowSaved);
        state.saved = null;

        try {
            const method = nowSaved ? 'POST' : 'DELETE';
            await api(nowSaved
                    ? CFG.routes.saveToggle
                    : route(CFG.routes.saveDelete, {id: pageId}),
                {method, body: nowSaved ? JSON.stringify({page_id: pageId}) : undefined}
            );
        } catch (err) {
            console.error('[MemberHub] card save:', err);
            applyCardSaveState(btn, wasSaved);
        }
    }

    function wireCardSaveButtons() {
        $$('.mh-card-save:not([data-mh-wired])').forEach(btn => {
            btn.dataset.mhWired = '1';
            btn.addEventListener('click', e => {
                e.stopPropagation();
                e.preventDefault();
                handleCardSaveClick(btn);
            });
        });
    }

    wireCardSaveButtons();
    new MutationObserver(wireCardSaveButtons)
        .observe(document.body, {childList: true, subtree: true});

    /* =========================================================================
       GUEST SAVE PROMPT
       ========================================================================= */
    const guestPrompt = $('mh-guest-prompt');
    const mgpClose = $('mgp-close');
    const mgpPageId = $('mgp-page-id');
    const mgpForm = $('mgp-form');
    const mgpSuccess = $('mgp-success');

    function showGuestPrompt(pageId, triggerEl = null) {
        if (!guestPrompt) return;
        if (mgpPageId && pageId) mgpPageId.value = String(pageId);

        // Position near the trigger button, not fixed to bottom
        if (triggerEl) {
            const rect = triggerEl.getBoundingClientRect();
            const promptWidth = 320;
            let left = rect.left + rect.width / 2 - promptWidth / 2;
            // Clamp to viewport
            left = Math.max(8, Math.min(left, window.innerWidth - promptWidth - 8));
            const top = rect.top - 10; // appear above the button

            guestPrompt.style.position = 'fixed';
            guestPrompt.style.bottom = '';
            guestPrompt.style.top = '';
            guestPrompt.style.left = `${left}px`;
            guestPrompt.style.width = `${promptWidth}px`;
            guestPrompt.style.transform = 'none';

            // Position above or below depending on available space
            if (rect.top > 280) {
                guestPrompt.style.top = '';
                guestPrompt.style.bottom = `${window.innerHeight - rect.top + 8}px`;
            } else {
                guestPrompt.style.top = `${rect.bottom + 8}px`;
                guestPrompt.style.bottom = '';
            }
        }

        guestPrompt.style.display = '';
        guestPrompt.setAttribute('aria-hidden', 'false');
        guestPrompt.querySelector('#mgp-email')?.focus();
    }

    function hideGuestPrompt() {
        if (!guestPrompt) return;
        guestPrompt.style.display = 'none';
        guestPrompt.setAttribute('aria-hidden', 'true');
        if (mgpSuccess) mgpSuccess.style.display = 'none';
        if (mgpForm) {
            mgpForm.reset();
            mgpForm.style.display = '';
        }
    }

    if (mgpClose) mgpClose.addEventListener('click', hideGuestPrompt);

    if (mgpForm) {
        mgpForm.addEventListener('submit', async e => {
            e.preventDefault();
            const pageId = mgpPageId?.value;
            const email = mgpForm.querySelector('#mgp-email')?.value;
            const consent = mgpForm.querySelector('#mgp-consent')?.checked ?? false;

            try {
                await api(CFG.routes.guestSave, {
                    method: 'POST',
                    body: JSON.stringify({page_id: pageId, email, newsletter_consent: consent}),
                });
                if (mgpForm) mgpForm.style.display = 'none';
                if (mgpSuccess) mgpSuccess.style.display = '';
                setTimeout(hideGuestPrompt, 2600);
            } catch (err) {
                console.error('[MemberHub] guestSave:', err);
            }
        });
    }

    /* =========================================================================
       FEED TAB
       ========================================================================= */
    const FEED_ICONS = {
        comment: '💬', like: '❤️', badge: '🏆', article: '📰', mention: '📣',
    };

    async function loadFeed() {
        if (state.feed !== null || !CFG.isLoggedIn) return;

        const loading = $('mh-feed-loading');

        try {
            const res = await api(CFG.routes.feedIndex);
            state.feed = {
                notifications: res.notifications ?? [],
                activity: res.activity ?? [],
            };
            renderFeed();
        } catch (err) {
            console.error('[MemberHub] loadFeed:', err);
            if (loading) loading.innerHTML =
                '<p style="color:#334155;padding:15px;font-size:12px">Failed to load feed.</p>';
        }
    }

    function renderFeed() {
        const loading = $('mh-feed-loading');
        const content = $('mh-feed-content');
        const feedList = $('mh-feed-list');
        const feedBadge = $('mh-feed-badge');
        const markRead = $('mh-mark-read');

        if (!loading || !content || !feedList) return;
        loading.style.display = 'none';
        content.style.display = 'block';

        const notifications = state.feed?.notifications ?? [];
        const activity = state.feed?.activity ?? [];

        const unread = notifications.length;
        if (feedBadge) {
            feedBadge.textContent = unread > 0 ? String(unread) : '';
            feedBadge.style.display = unread > 0 ? '' : 'none';
        }
        if (markRead) markRead.style.display = unread > 0 ? '' : 'none';
        state.unread = unread;
        setHubBadge(unread);

        const COLOR = {success: 'var(--mh-green)', warning: 'var(--mh-amber)', info: 'var(--mh-blue-pale)'};

        const notifHtml = notifications.length ? `
        <div class="mh-pane-header"><span class="mh-section-label">Notifications</span></div>
        ${notifications.map(n => `
            <a href="${esc(n.url ?? '#')}"
               style="display:flex;gap:11px;padding:10px 15px;border-bottom:1px solid var(--mh-bg-3);text-decoration:none"
               onmouseover="this.style.background='rgba(255,255,255,0.02)'"
               onmouseout="this.style.background=''">
                <div class="mh-feed-icon">${esc(n.icon ?? '🔔')}</div>
                <div class="mh-feed-body">
                    <div style="color:var(--mh-text-primary);font-size:12px;font-weight:600;margin-bottom:2px">${esc(n.title)}</div>
                    <div style="color:var(--mh-text-dimmer);font-size:11px;line-height:1.4">${esc(n.message)}</div>
                </div>
                ${n.count > 1 ? `<span style="color:${COLOR[n.color] ?? 'var(--mh-text-mid)'};font-size:10px;font-weight:700;align-self:center;white-space:nowrap">${n.count}</span>` : ''}
            </a>
        `).join('')}
    ` : '';

        const ACTIVITY_ICONS = {
            comment: '💬', page_view: '👁', like: '❤️', badge: '🏆',
            purchase: '💳', login: '🔑', share: '📤',
        };

        const activityHtml = activity.length ? `
        <div class="mh-pane-header" style="margin-top:6px">
            <span class="mh-section-label">Recent Activity</span>
        </div>
        ${activity.map(a => `
            <div class="mh-feed-item">
                <div class="mh-feed-icon">${ACTIVITY_ICONS[a.type] ?? '📌'}</div>
                <div class="mh-feed-body">
                    <div class="mh-feed-text" style="text-transform:capitalize">
                        ${esc(a.type.replace(/_/g, ' '))}
                        ${a.entity_type ? `<span class="fn-action"> · ${esc(a.entity_type)}</span>` : ''}
                    </div>
                    <div class="mh-feed-time">${esc(a.date)}</div>
                </div>
                ${a.points > 0 ? `<span style="color:var(--mh-amber);font-size:10px;font-weight:700;align-self:center">+${a.points}pts</span>` : ''}
            </div>
        `).join('')}
    ` : '';

        feedList.innerHTML = notifHtml + activityHtml;

        if (!notifHtml && !activityHtml) {
            feedList.innerHTML = `
            <div class="mh-empty-state">
                <div class="mh-empty-icon">📭</div>
                <div class="mh-empty-title">Nothing yet</div>
                <div class="mh-empty-sub">Your activity and alerts will appear here</div>
            </div>`;
        }

        renderLivePreview();
    }

    const markReadBtn = $('mh-mark-read');
    const seeCommunity = $('mh-see-community');

    if (markReadBtn) {
        markReadBtn.addEventListener('click', async () => {
            if (!state.feed) return;
            state.feed = state.feed.map(f => ({...f, read: true}));
            renderFeed();
            try {
                await api(CFG.routes.feedReadAll, {method: 'POST'});
            } catch (err) {
                console.error('[MemberHub] markAllRead:', err);
            }
        });
    }

    if (seeCommunity) seeCommunity.addEventListener('click', () => activateTab('community'));

    async function renderLivePreview() {
        const liveList = $('mh-live-list');
        if (!liveList) return;
        try {
            const data = state.community ?? await api(CFG.routes.communityIndex);
            liveList.innerHTML = (data ?? []).slice(0, 2).map(item => `
                <div class="mh-live-item">
                    <div class="mh-live-info">
                        <div class="mh-live-title">${esc(item.title)}</div>
                        <div class="mh-live-count">
                            ${item.votes
                ? `${Number(item.votes).toLocaleString()} votes`
                : `${Number(item.entries ?? 0).toLocaleString()} entries`}
                        </div>
                    </div>
                    <button class="mh-live-btn" onclick="activateTab && activateTab('community')">
                        ${item.type === 'poll' ? 'Vote' : 'Join'}
                    </button>
                </div>
            `).join('');
        } catch (err) {
            console.error('[MemberHub] livePreview:', err);
        }
    }

    /* =========================================================================
       SAVED TAB
       ========================================================================= */
    async function loadSaved() {
        if (state.saved !== null || !CFG.isLoggedIn) return;
        try {
            const res = await api(CFG.routes.savesIndex);
            state.saved = res.data ?? [];
            renderSaved();
        } catch (err) {
            console.error('[MemberHub] loadSaved:', err);
        }
    }

    function renderSaved() {
        const loading = $('mh-saved-loading');
        const content = $('mh-saved-content');
        const savedList = $('mh-saved-list');
        const savedCount = $('mh-saved-count');
        const savedBadge = $('mh-saved-badge');
        const savedEmpty = $('mh-saved-empty');

        if (!loading || !content || !savedList) return;
        loading.style.display = 'none';
        content.style.display = 'block';

        const items = state.saved ?? [];

        if (savedCount) savedCount.textContent = `${items.length} saved`;

        if (savedBadge) {
            savedBadge.textContent = items.length > 0 ? String(items.length) : '';
            savedBadge.style.display = items.length > 0 ? '' : 'none';
        }

        if (savedEmpty) savedEmpty.style.display = items.length === 0 ? '' : 'none';

        savedList.innerHTML = items.map(item => `
            <div class="mh-saved-item" data-id="${esc(String(item.id))}">
                <div class="mh-saved-thumb">
                    ${item.image_url
            ? `<img src="${esc(item.image_url)}" alt="${esc(item.title)}" loading="lazy">`
            : '📄'}
                </div>
                <div class="mh-saved-body">
                    <div class="mh-saved-title">${esc(item.title)}</div>
                    <div class="mh-saved-meta">
                        ${item.category ? `<span class="mh-saved-cat">${esc(item.category)}</span>` : ''}
                        ${item.read_time
            ? `<span class="mh-saved-meta-text">
                                <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                                </svg>
                                ${esc(item.read_time)}</span>`
            : ''}
                        ${item.saved_at_label
            ? `<span class="mh-saved-meta-text">${esc(item.saved_at_label)}</span>`
            : ''}
                    </div>
                </div>
                <button class="mh-saved-remove" data-id="${esc(String(item.id))}" aria-label="Remove saved article">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <polyline points="3 6 5 6 21 6"/>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
                    </svg>
                </button>
            </div>
        `).join('');

        savedList.querySelectorAll('.mh-saved-item').forEach(row => {
            const item = items.find(i => String(i.id) === row.dataset.id);
            if (item?.url) {
                row.addEventListener('click', e => {
                    if (e.target.closest('.mh-saved-remove')) return;
                    location.href = item.url;
                });
                row.style.cursor = 'pointer';
            }
        });

        savedList.querySelectorAll('.mh-saved-remove').forEach(btn => {
            btn.addEventListener('click', e => {
                e.stopPropagation();
                removeSaved(btn.dataset.id, btn.closest('.mh-saved-item'));
            });
        });

        // Upsell block (once)
        if (!content.querySelector('.mh-upsell')) {
            content.insertAdjacentHTML('beforeend', `
                <div class="mh-upsell">
                    <div class="mh-upsell-inner">
                        <span class="mh-upsell-icon">⚡</span>
                        <div>
                            <div class="mh-upsell-title">Unlimited saves &amp; offline access</div>
                            <div class="mh-upsell-body">
                                Premium members sync their reading list across every device — even offline.
                            </div>
                            <a href="/${esc(CFG.siteSlug)}/member/upgrade" class="mh-btn-primary">
                                Explore Premium →
                            </a>
                        </div>
                    </div>
                </div>
            `);
        }
    }

    async function removeSaved(pageId, rowEl) {
        rowEl?.classList.add('is-removing');
        try {
            await api(CFG.routes.saveToggle, {
                method: 'POST',
                body: JSON.stringify({page_id: pageId}),
            });
            setTimeout(() => {
                state.saved = (state.saved ?? []).filter(i => String(i.page_id) !== String(pageId));
                syncCardButtons(pageId, false);
                renderSaved();
            }, 290);
        } catch (err) {
            console.error('[MemberHub] removeSaved:', err);
            rowEl?.classList.remove('is-removing');
        }
    }

    /* =========================================================================
       NOTIFICATIONS TAB
       Calls GET CFG.routes.notificationsIndex → [{type, icon, title, message, count, url, priority, color}]
       ========================================================================= */
    async function loadNotifications() {
        if (state.notifications !== null || !CFG.isLoggedIn) return;
        const loading = $('mh-notifications-loading');
        const content = $('mh-notifications-content');

        try {
            const notificationData = await api(CFG.routes.notificationsIndex);
            state.notifications = notificationData.data;

            console.log(state.notifications)
            renderNotifications();
        } catch (err) {
            console.error('[MemberHub] loadNotifications:', err);
            if (loading) loading.innerHTML =
                '<p style="color:#6b7280;padding:15px;font-size:12px">Failed to load notifications.</p>';
        }
    }

    function renderNotifications() {
        const loading = $('mh-notifications-loading');
        const content = $('mh-notifications-content');
        const list = $('mh-notifications-list');
        const badge = $('mh-notifications-tab-badge');

        if (!loading || !content) return;
        loading.style.display = 'none';
        content.style.display = 'block';

        const items = state.notifications ?? [];
        const total = items.reduce((sum, n) => sum + (n.count ?? 0), 0);

        if (badge) {
            badge.textContent = total > 0 ? String(total) : '';
            badge.style.display = total > 0 ? '' : 'none';
        }

        if (!list) return;

        if (items.length === 0) {
            list.innerHTML = `
                <div class="mh-empty-state">
                    <div class="mh-empty-icon">🔔</div>
                    <div class="mh-empty-title">All caught up</div>
                    <div class="mh-empty-sub">No new notifications right now</div>
                </div>`;
            return;
        }

        const COLOR_MAP = {
            success: 'mh-notif-count--success',
            warning: 'mh-notif-count--warning',
            info: '',
        };

        list.innerHTML = items.map(n => `
            <a class="mh-notif-item" href="${esc(n.url ?? '#')}" role="listitem">
                <div class="mh-notif-icon mh-notif-icon--${esc(n.type)}"
                     aria-hidden="true">${esc(n.icon)}</div>
                <div class="mh-notif-body">
                    <div class="mh-notif-title">${esc(n.title)}</div>
                    <div class="mh-notif-msg">${esc(n.message)}</div>
                </div>
                ${n.count > 0
            ? `<div class="mh-notif-count ${COLOR_MAP[n.color] ?? ''}">${n.count}</div>`
            : ''}
            </a>
        `).join('');
    }

    /* =========================================================================
       ACTIVITY TAB
       Calls:
         GET CFG.routes.activityProgress  → {total_points, badges_earned, badges_available, stats, next_badges}
         GET CFG.routes.activityBadges    → [{id, name, icon/emoji, earned}]
       ========================================================================= */
    async function loadActivity() {
        if (state.activity !== null || !CFG.isLoggedIn) return;
        const loading = $('mh-activity-loading');
        const content = $('mh-activity-content');

        try {
            state.activity = await api(CFG.routes.activityProgress);
            renderActivity();
        } catch (err) {
            console.error('[MemberHub] loadActivity:', err);
            if (loading) loading.innerHTML =
                '<p style="color:#6b7280;padding:15px;font-size:12px">Failed to load activity.</p>';
        }
    }

    function renderActivity() {
        const loading = $('mh-activity-loading');
        const content = $('mh-activity-content');

        if (!loading || !content) return;
        loading.style.display = 'none';
        content.style.display = 'block';

        const d = state.activity ?? {};
        const stats = d.stats ?? {};

        // Points banner
        const pointsBanner = $('mh-activity-points');
        if (pointsBanner) {
            pointsBanner.innerHTML = `
                <div class="mh-points-banner">
                    <div>
                        <div class="mh-points-total">${Number(d.total_points ?? 0).toLocaleString()}</div>
                        <div class="mh-points-label">Total points</div>
                    </div>
                    <div style="margin-left:auto;text-align:right">
                        <div style="color:#374151;font-size:12px;font-weight:700">
                            ${d.badges_earned ?? 0} / ${d.badges_available ?? 0}
                        </div>
                        <div class="mh-points-sub">badges earned</div>
                    </div>
                </div>`;
        }

        // Stats grid
        const statsGrid = $('mh-activity-stats');
        if (statsGrid) {
            const statItems = [
                {label: 'Articles read', value: stats.pages_read ?? 0},
                {label: 'Comments', value: stats.comments ?? 0},
                {label: 'Likes given', value: stats.likes ?? 0},
            ];
            statsGrid.innerHTML = `
                <div class="mh-stat-grid">
                    ${statItems.map(s => `
                        <div class="mh-stat-card">
                            <div class="mh-stat-value">${Number(s.value).toLocaleString()}</div>
                            <div class="mh-stat-label">${esc(s.label)}</div>
                        </div>`).join('')}
                </div>`;
        }

        // Badges
        const badgesEl = $('mh-activity-badges');
        if (badgesEl) {
            const earned = (d.badges ?? []).filter(b => b.earned);
            const unearned = (d.badges ?? []).filter(b => !b.earned).slice(0, 8);
            const combined = [...earned, ...unearned].slice(0, 12);

            if (combined.length === 0) {
                badgesEl.innerHTML = `
                    <div class="mh-empty-state" style="padding:20px">
                        <div class="mh-empty-icon">🏅</div>
                        <div class="mh-empty-title">No badges yet</div>
                        <div class="mh-empty-sub">Keep reading and engaging to earn badges</div>
                    </div>`;
            } else {
                badgesEl.innerHTML = `
                    <div class="mh-pane-header">
                        <span class="mh-section-label">Badges</span>
                        <a href="/${esc(CFG.siteSlug)}/member/activity/badges" class="mh-text-btn">See all</a>
                    </div>
                    <div class="mh-badge-grid">
                        ${combined.map(b => `
                            <div class="mh-badge-item" title="${esc(b.name)}">
                                <div class="mh-badge-icon ${b.earned ? 'mh-badge-icon--earned' : ''}">
                                    ${b.emoji ?? b.icon ?? '🏅'}
                                </div>
                                <div class="mh-badge-name">${esc(b.name)}</div>
                            </div>`).join('')}
                    </div>`;
            }
        }

        // Next badge progress
        const progressEl = $('mh-activity-next-badge');
        if (progressEl && (d.next_badges ?? []).length > 0) {
            const next = d.next_badges[0];
            const pct = Math.round(next.progress?.percentage ?? 0);
            progressEl.innerHTML = `
                <div class="mh-pane-header" style="padding-top:4px">
                    <span class="mh-section-label">Next badge</span>
                </div>
                <div style="padding:0 15px 14px">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
                        <div class="mh-badge-icon" style="width:36px;height:36px;font-size:16px">
                            ${next.badge?.emoji ?? '🏅'}
                        </div>
                        <div style="flex:1">
                            <div style="font-size:12px;font-weight:700;color:#111827">
                                ${esc(next.badge?.name)}
                            </div>
                            <div style="font-size:10.5px;color:#6b7280">${pct}% complete</div>
                        </div>
                    </div>
                    <div class="mh-progress-bar">
                        <div class="mh-progress-bar__fill" style="width:${pct}%"></div>
                    </div>
                </div>`;
        }
    }

    /* =========================================================================
       COMMUNITY TAB
       ========================================================================= */
    async function loadCommunity() {
        if (state.community !== null) return;
        try {
            if (CFG.routes.communityIndex) {
                const res = await api(CFG.routes.communityIndex);
                state.community = res;
            } else {
                state.community = mockCommunity();
            }
            renderCommunity();
        } catch (err) {
            console.error('[MemberHub] loadCommunity:', err);
            state.community = mockCommunity();
            renderCommunity();
        }
    }


    function renderCommunity() {
        const loading = $('mh-community-loading');
        const content = $('mh-community-content');
        if (!loading || !content) return;
        loading.style.display = 'none';
        content.style.display = 'block';

        const polls = state.community?.polls ?? [];
        const lb = state.community?.leaderboard ?? {points: [], activity: []};
        const rewards = state.community?.rewards ?? [];
        const memberRanks = state.community?.member_ranks ?? null;
        const isMock = !CFG.routes.communityIndex || Array.isArray(state.community);

        let html = '';

        // ── Rewards ───────────────────────────────────────────────────────────
        if (rewards.length) {
            html += `
            <div style="margin:0 15px 12px;padding:12px;background:linear-gradient(135deg,rgba(16,185,129,.08),rgba(52,211,153,.05));border-radius:10px;border:1px solid rgba(52,211,153,.15)">
                <div class="mh-section-label" style="margin-bottom:8px">🎁 Unclaimed Rewards</div>
                ${rewards.map(r => `
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(52,211,153,.1)">
                        <div>
                            <div style="color:var(--mh-text-primary);font-size:12px;font-weight:600">${esc(r.title)}</div>
                            ${r.expires_at ? `<div style="color:var(--mh-text-dimmest);font-size:10px">Expires ${esc(r.expires_at)}</div>` : ''}
                        </div>
                        <a href="/${esc(CFG.siteSlug)}/member/rewards"
                           style="background:rgba(16,185,129,.15);color:var(--mh-green-light);font-size:10.5px;font-weight:700;padding:4px 10px;border-radius:6px;text-decoration:none;border:1px solid rgba(52,211,153,.2)">
                            Claim
                        </a>
                    </div>
                `).join('')}
            </div>`;
        }

        // ── Polls ─────────────────────────────────────────────────────────────
        if (polls.length) {
            html += `<div class="mh-section-label" style="padding:0 15px 8px">📊 Active Polls</div>`;
            html += polls.map(poll => renderPoll(poll)).join('');
        } else if (isMock) {
            html += `
            <div class="mh-section-label" style="padding:0 15px 8px">📊 Active Polls</div>
            <div style="margin:0 15px 12px;padding:12px;background:var(--mh-bg-2);border-radius:10px;border:1px dashed var(--mh-border-2);text-align:center">
                <div style="color:var(--mh-text-dimmer);font-size:11px">No active polls — create one in the admin panel</div>
            </div>`;
        }

        // ── Leaderboard ───────────────────────────────────────────────────────
        html += renderLeaderboard(lb, memberRanks);

        content.innerHTML = html;

        // Wire vote buttons after rendering
        content.querySelectorAll('.mh-poll-option-btn:not([data-wired])').forEach(btn => {
            btn.dataset.wired = '1';
            btn.addEventListener('click', () => handlePollVote(
                btn.dataset.pollId,
                btn.dataset.optionId,
                btn.closest('.mh-poll')
            ));
        });
    }

    function renderPoll(poll) {
        if (poll.has_voted) {
            // Show results
            return `
            <div class="mh-poll" data-poll-id="${poll.id}" style="margin:0 15px 10px;padding:13px;background:var(--mh-bg-2);border-radius:10px;border:1px solid var(--mh-border-1)">
                <div style="color:var(--mh-text-primary);font-size:12.5px;font-weight:600;margin-bottom:10px">${esc(poll.question)}</div>
                ${poll.results.map(r => `
                    <div style="margin-bottom:7px">
                        <div style="display:flex;justify-content:space-between;margin-bottom:3px">
                            <span style="color:${r.id === poll.voted_option_id ? 'var(--mh-blue-pale)' : 'var(--mh-text-mid)'};font-size:11px;font-weight:${r.id === poll.voted_option_id ? '700' : '400'}">
                                ${r.id === poll.voted_option_id ? '✓ ' : ''}${esc(r.label)}
                            </span>
                            <span style="color:var(--mh-text-dimmer);font-size:10.5px">${r.percentage}%</span>
                        </div>
                        <div style="height:4px;background:var(--mh-bg-3);border-radius:4px">
                            <div style="height:100%;width:${r.percentage}%;background:${r.id === poll.voted_option_id ? 'var(--mh-blue)' : 'var(--mh-border-2)'};border-radius:4px;transition:width .4s ease"></div>
                        </div>
                    </div>
                `).join('')}
                <div style="color:var(--mh-text-dimmest);font-size:10px;margin-top:6px">${Number(poll.total_votes).toLocaleString()} votes${poll.closes_at ? ` · Closes ${esc(poll.closes_at)}` : ''}</div>
            </div>`;
        }

        // Show vote options
        return `
        <div class="mh-poll" data-poll-id="${poll.id}" style="margin:0 15px 10px;padding:13px;background:var(--mh-bg-2);border-radius:10px;border:1px solid var(--mh-border-1)">
            <div style="color:var(--mh-text-primary);font-size:12.5px;font-weight:600;margin-bottom:10px">${esc(poll.question)}</div>
            <div style="display:flex;flex-direction:column;gap:6px">
                ${poll.options.map(o => `
                    <button class="mh-poll-option-btn"
                            data-poll-id="${poll.id}"
                            data-option-id="${o.id}"
                            ${!CFG.isLoggedIn ? 'disabled' : ''}
                            style="text-align:left;padding:8px 12px;border-radius:7px;border:1px solid var(--mh-border-2);background:var(--mh-bg-3);color:var(--mh-text-mid);font-size:11.5px;cursor:${CFG.isLoggedIn ? 'pointer' : 'not-allowed'};transition:border-color .15s,background .15s"
                            onmouseover="${CFG.isLoggedIn ? "this.style.borderColor='var(--mh-blue-light)';this.style.background='rgba(37,99,235,0.08)'" : ''}"
                            onmouseout="${CFG.isLoggedIn ? "this.style.borderColor='var(--mh-border-2)';this.style.background='var(--mh-bg-3)'" : ''}">
                        ${esc(o.label)}
                    </button>
                `).join('')}
            </div>
            ${!CFG.isLoggedIn ? `<div style="color:var(--mh-text-dimmest);font-size:10px;margin-top:8px;text-align:center">Sign in to vote</div>` : ''}
            <div style="color:var(--mh-text-dimmest);font-size:10px;margin-top:6px">${Number(poll.total_votes).toLocaleString()} votes</div>
        </div>`;
    }

    function renderLeaderboard(lb, memberRanks) {
        const points = lb.points ?? [];
        const activity = lb.activity ?? [];

        if (!points.length && !activity.length) return '';

        // Active tab state — default to points
        const tabId = 'lb-tab-' + Math.random().toString(36).slice(2, 6);

        function rows(entries, type) {
            if (!entries.length) {
                return `<div style="color:var(--mh-text-dimmest);font-size:11px;text-align:center;padding:12px 0">No data yet this week</div>`;
            }
            return entries.map((e, i) => `
            <div style="display:flex;align-items:center;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--mh-border-1)">
                <div style="display:flex;align-items:center;gap:8px">
                    <span style="font-size:11.5px;font-weight:700;width:16px;color:${i === 0 ? 'var(--mh-amber)' : 'var(--mh-text-dimmest)'}">${e.rank ?? i + 1}</span>
                    <span style="color:var(--mh-text-mid);font-size:11.5px">${esc(e.display_name)}</span>
                </div>
                <span style="color:${type === 'points' ? 'var(--mh-amber)' : 'var(--mh-blue-pale)'};font-size:10.5px;font-weight:700">
                    ${Number(e.score).toLocaleString()} ${type === 'points' ? 'pts' : 'acts'}
                </span>
            </div>
        `).join('');
        }

        const myPointsRank = memberRanks?.points ? `<span style="color:var(--mh-text-dimmest);font-size:10px"> · Your rank: #${memberRanks.points}</span>` : '';
        const myActivityRank = memberRanks?.activity ? `<span style="color:var(--mh-text-dimmest);font-size:10px"> · Your rank: #${memberRanks.activity}</span>` : '';

        return `
        <div style="margin:10px 15px 0;padding:13px;background:var(--mh-bg-2);border-radius:10px;border:1px solid var(--mh-border-1)">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
                <span class="mh-section-label">🏅 This Week</span>
                <div style="display:flex;gap:4px">
                    <button onclick="mhLbTab('${tabId}','points',this)"
                            id="${tabId}-points-btn"
                            style="font-size:10px;font-weight:700;padding:3px 9px;border-radius:5px;border:1px solid var(--mh-blue);background:var(--mh-blue);color:#fff;cursor:pointer">
                        Points
                    </button>
                    <button onclick="mhLbTab('${tabId}','activity',this)"
                            id="${tabId}-activity-btn"
                            style="font-size:10px;font-weight:700;padding:3px 9px;border-radius:5px;border:1px solid var(--mh-border-2);background:transparent;color:var(--mh-text-dimmer);cursor:pointer">
                        Activity
                    </button>
                </div>
            </div>
            <div id="${tabId}-points">
                ${rows(points, 'points')}
                ${myPointsRank ? `<div style="margin-top:6px">${myPointsRank}</div>` : ''}
            </div>
            <div id="${tabId}-activity" style="display:none">
                ${rows(activity, 'activity')}
                ${myActivityRank ? `<div style="margin-top:6px">${myActivityRank}</div>` : ''}
            </div>
        </div>`;
    }

    // Leaderboard tab switcher — defined on window so inline onclick can find it
    window.mhLbTab = function (tabId, type, btn) {
        const other = type === 'points' ? 'activity' : 'points';
        const show = document.getElementById(`${tabId}-${type}`);
        const hide = document.getElementById(`${tabId}-${other}`);
        const otherBtn = document.getElementById(`${tabId}-${other}-btn`);
        if (show) show.style.display = '';
        if (hide) hide.style.display = 'none';
        if (btn) {
            btn.style.background = 'var(--mh-blue)';
            btn.style.color = '#fff';
            btn.style.borderColor = 'var(--mh-blue)';
        }
        if (otherBtn) {
            otherBtn.style.background = 'transparent';
            otherBtn.style.color = 'var(--mh-text-dimmer)';
            otherBtn.style.borderColor = 'var(--mh-border-2)';
        }
    };


    /* =========================================================================
       DEALS TAB
       ========================================================================= */
    async function loadDeals() {
        if (state.deals !== null) return;

        try {
            // guests see mock; logged-in members hit the real endpoint
            if (!CFG.isLoggedIn || !CFG.routes.dealsIndex) {
                state.deals = {featured: [], browse: mockDeals(), total: 0, isMock: true};
            } else {
                const res = await api(CFG.routes.dealsIndex);
                state.deals = {
                    featured: res.featured ?? [],
                    browse: res.browse ?? [],
                    total: res.total ?? 0,
                    isMock: false,
                };
            }
            renderDeals();
        } catch (err) {
            console.error('[MemberHub] loadDeals:', err);
            // fallback to mock on error
            state.deals = {featured: [], browse: mockDeals(), total: 0, isMock: true};
            renderDeals();
        }
    }

    async function handlePollVote(pollId, optionId, pollEl) {
        if (!CFG.isLoggedIn || !pollEl) return;

        // Disable all buttons in this poll immediately
        pollEl.querySelectorAll('.mh-poll-option-btn').forEach(b => {
            b.disabled = true;
            b.style.opacity = '0.5';
        });

        try {
            const res = await api(CFG.routes.pollVote, {
                method: 'POST',
                body: JSON.stringify({poll_id: pollId, option_id: optionId}),
            });

            if (res.success) {
                // Update state and re-render just this poll
                const pollIndex = (state.community?.polls ?? []).findIndex(p => String(p.id) === String(pollId));
                if (pollIndex > -1) {
                    state.community.polls[pollIndex].has_voted = true;
                    state.community.polls[pollIndex].voted_option_id = parseInt(optionId);
                    state.community.polls[pollIndex].results = res.results;
                    state.community.polls[pollIndex].total_votes = res.total_votes;
                    pollEl.outerHTML = renderPoll(state.community.polls[pollIndex]);

                    // Re-wire any remaining polls
                    const content = $('mh-community-content');
                    content?.querySelectorAll('.mh-poll-option-btn:not([data-wired])').forEach(btn => {
                        btn.dataset.wired = '1';
                        btn.addEventListener('click', () => handlePollVote(
                            btn.dataset.pollId, btn.dataset.optionId, btn.closest('.mh-poll')
                        ));
                    });
                }
            }
        } catch (err) {
            console.error('[MemberHub] pollVote:', err);
            pollEl.querySelectorAll('.mh-poll-option-btn').forEach(b => {
                b.disabled = false;
                b.style.opacity = '';
            });
        }
    }

    function renderDeals() {
        const loading = $('mh-deals-loading');
        const content = $('mh-deals-content');
        const list = $('mh-deals-list');

        if (!loading || !content || !list) return;
        loading.style.display = 'none';
        content.style.display = 'block';

        const featured = state.deals?.featured ?? [];
        const browse = state.deals?.browse ?? [];

        function dealCard(deal, isFeatured = false) {
            // Normalise field names — DealsService uses product_id/title/final_price
            // mock uses title/sale_price
            const title = esc(deal.title ?? '');
            const salePrc = Number(deal.final_price ?? deal.sale_price ?? 0).toFixed(2);
            const origPrc = Number(deal.original_price ?? 0).toFixed(2);
            const disc = deal.discount_percentage ?? deal.discount ?? 0;
            const merchant = esc(deal.merchant_name ?? deal.merchant ?? '');
            const img = deal.image ?? deal.image_url ?? null;
            const slug = deal.slug ?? null;
            const href = slug ? `/${esc(CFG.siteSlug)}/deals/${slug}` : '#';

            if (isFeatured) {
                return `
                <a href="${href}" style="display:flex;gap:11px;padding:11px 15px;border-bottom:1px solid var(--mh-bg-3);text-decoration:none"
                   onmouseover="this.style.background='rgba(255,255,255,0.015)'"
                   onmouseout="this.style.background=''">
                    <div class="mh-deal-thumb" style="width:56px;height:56px">
                        ${img ? `<img src="${esc(img)}" alt="${title}" loading="lazy">` : '🏷️'}
                    </div>
                    <div class="mh-deal-body">
                        <div class="mh-deal-title" style="margin-bottom:5px">${title}</div>
                        <div class="mh-deal-prices">
                            <span class="mh-deal-sale">£${salePrc}</span>
                            <span class="mh-deal-orig">£${origPrc}</span>
                            <span class="mh-deal-discount">-${disc}%</span>
                        </div>
                        ${merchant ? `<div class="mh-deal-merchant">via ${merchant}</div>` : ''}
                    </div>
                </a>`;
            }

            return `
            <a href="${href}" style="display:flex;gap:9px;padding:8px 15px;text-decoration:none;align-items:center"
               onmouseover="this.style.background='rgba(255,255,255,0.015)'"
               onmouseout="this.style.background=''">
                <div class="mh-deal-thumb" style="width:38px;height:38px;flex-shrink:0">
                    ${img ? `<img src="${esc(img)}" alt="${title}" loading="lazy">` : '🏷️'}
                </div>
                <div style="flex:1;min-width:0">
                    <div style="color:var(--mh-text-primary);font-size:11.5px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${title}</div>
                    <div style="display:flex;align-items:center;gap:6px;margin-top:2px">
                        <span class="mh-deal-sale" style="font-size:12px">£${salePrc}</span>
                        <span class="mh-deal-orig" style="font-size:10px">£${origPrc}</span>
                    </div>
                </div>
                <span class="mh-deal-discount">-${disc}%</span>
            </a>`;
        }

        let html = '';

        if (featured.length) {
            html += `<div class="mh-pane-header"><span class="mh-section-label">⚡ Featured today</span></div>`;
            html += featured.map(d => dealCard(d, true)).join('');
        }

        if (browse.length) {
            html += `
            <div class="mh-pane-header" style="margin-top:8px">
                <span class="mh-section-label">Browse deals</span>
                <a href="/${esc(CFG.siteSlug)}/deals" class="mh-text-btn">All deals →</a>
            </div>`;
            html += `<div style="display:grid;grid-template-columns:1fr;gap:1px;background:var(--mh-bg-3)">`;
            html += browse.slice(0, 6).map(d => `<div style="background:var(--mh-bg-1)">${dealCard(d, false)}</div>`).join('');
            html += `</div>`;
        }

        if (!html) {
            html = `
            <div class="mh-empty-state">
                <div class="mh-empty-icon">🏷️</div>
                <div class="mh-empty-title">No deals today</div>
                <div class="mh-empty-sub">Check back tomorrow for new offers</div>
            </div>`;
        }

        list.innerHTML = html;
    }

    /* =========================================================================
   SUBSCRIPTIONS TAB
   ========================================================================= */
    async function loadSubscriptions() {
        if (state.subscriptions !== null || !CFG.isLoggedIn) return;

        try {
            const res = await api(CFG.routes.subscriptionsIndex);
            state.subscriptions = res.data;
            renderSubscriptions();
        } catch (err) {
            console.error('[MemberHub] loadSubscriptions:', err);
        }
    }

    function renderSubscriptions() {
        const loading = $('mh-subscriptions-loading');
        const content = $('mh-subscriptions-content');
        const list = $('mh-subscriptions-list');
        const empty = $('mh-subscriptions-empty');

        if (!loading || !content) return;
        loading.style.display = 'none';
        content.style.display = 'block';

        const data = state.subscriptions;

        if (!data?.active) {
            if (empty) empty.style.display = '';
            return;
        }

        if (empty) empty.style.display = 'none';

        const s = data.active;
        const autoRenewLabel = s.auto_renew ? '✓ Auto-renews' : '⚠ Cancels';

        list.innerHTML = `
        <div style="margin:0 15px;padding:14px;background:var(--mh-bg-2);border-radius:10px;border:1px solid var(--mh-border-1)">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
                <span style="color:var(--mh-text-primary);font-size:13px;font-weight:700">${esc(s.plan_name)}</span>
                <span style="background:rgba(16,185,129,.12);color:var(--mh-green);font-size:9.5px;font-weight:700;padding:2px 8px;border-radius:20px;border:1px solid rgba(52,211,153,.2)">Active</span>
            </div>
            <div style="color:var(--mh-text-dimmer);font-size:11px;margin-bottom:4px">
                ${s.currency?.toUpperCase() ?? ''} ${Number(s.price ?? 0).toFixed(2)} / ${esc(s.billing_period ?? '')}
            </div>
            ${s.end_date ? `<div style="color:var(--mh-text-dimmest);font-size:10.5px">${autoRenewLabel} ${esc(s.end_date)}</div>` : ''}
            <a href="/${esc(CFG.siteSlug)}/member/subscriptions"
               style="display:inline-block;margin-top:10px;font-size:11px;font-weight:700;color:var(--mh-blue-light);text-decoration:none">
                Manage subscription →
            </a>
        </div>
        ${data.history_count > 1 ? `<div style="padding:8px 15px"><span style="color:var(--mh-text-dimmest);font-size:10.5px">${data.history_count - 1} previous subscription(s)</span></div>` : ''}
    `;
    }

    /* =========================================================================
       BADGES TAB
       ========================================================================= */
    async function loadBadges() {
        alert('here')
        if (state.badges !== null || !CFG.isLoggedIn) return;

        try {
            alert(CFG.routes.badgesIndex)
            const res = await api(CFG.routes.badgesIndex);
            state.badges = res.data;
            renderBadges();
        } catch (err) {
            console.error('[MemberHub] loadBadges:', err);
        }
    }

    function renderBadges() {
        const loading = $('mh-badges-loading');
        const content = $('mh-badges-content');
        const earned = $('mh-badges-earned');
        const activity = $('mh-badges-activity');
        const empty = $('mh-badges-empty');

        if (!loading || !content) return;
        loading.style.display = 'none';
        content.style.display = 'block';

        const data = state.badges;

        if (!data?.earned?.length && !data?.recent_activity?.length) {
            if (empty) empty.style.display = '';
            return;
        }

        if (empty) empty.style.display = 'none';

        // Points summary
        const summary = content.querySelector('#mh-badges-summary');
        if (!summary && data.total_points !== undefined) {
            const div = document.createElement('div');
            div.id = 'mh-badges-summary';
            div.style.cssText = 'padding:10px 15px;display:flex;gap:14px';
            div.innerHTML = `
            <div style="background:var(--mh-bg-2);border-radius:8px;padding:10px 14px;flex:1;border:1px solid var(--mh-border-1);text-align:center">
                <div style="color:var(--mh-amber);font-size:18px;font-weight:800">${Number(data.total_points).toLocaleString()}</div>
                <div style="color:var(--mh-text-dimmer);font-size:9.5px;font-weight:700;margin-top:2px">POINTS</div>
            </div>
            <div style="background:var(--mh-bg-2);border-radius:8px;padding:10px 14px;flex:1;border:1px solid var(--mh-border-1);text-align:center">
                <div style="color:var(--mh-blue-pale);font-size:18px;font-weight:800">${data.badges_earned}</div>
                <div style="color:var(--mh-text-dimmer);font-size:9.5px;font-weight:700;margin-top:2px">BADGES</div>
            </div>
        `;
            content.insertBefore(div, content.querySelector('.mh-pane-header'));
        }

        if (earned) {
            earned.innerHTML = (data.earned ?? []).slice(0, 6).map(b => `
            <div title="${esc(b.name)}: ${esc(b.description)}"
                 style="width:42px;height:42px;border-radius:50%;background:var(--mh-bg-2);border:1px solid var(--mh-border-2);display:flex;align-items:center;justify-content:center;font-size:20px;cursor:default"
                 aria-label="${esc(b.name)}">
                ${esc(b.icon ?? '🏅')}
            </div>
        `).join('');
        }

        // Next badges progress
        if (data.next_badges?.length && !content.querySelector('#mh-next-badges')) {
            const nextDiv = document.createElement('div');
            nextDiv.id = 'mh-next-badges';
            nextDiv.innerHTML = `
            <div class="mh-pane-header" style="margin-top:8px"><span class="mh-section-label">Up next</span></div>
            ${data.next_badges.map(n => `
                <div style="padding:6px 15px">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px">
                        <span style="color:var(--mh-text-mid);font-size:11.5px">${esc(n.icon ?? '🏅')} ${esc(n.name)}</span>
                        <span style="color:var(--mh-text-dimmer);font-size:10px">${n.percentage}%</span>
                    </div>
                    <div style="height:3px;background:var(--mh-bg-2);border-radius:3px">
                        <div style="height:100%;width:${n.percentage}%;background:linear-gradient(90deg,var(--mh-blue),var(--mh-blue-light));border-radius:3px"></div>
                    </div>
                </div>
            `).join('')}
        `;
            const activityHeader = content.querySelectorAll('.mh-pane-header')[1];
            if (activityHeader) content.insertBefore(nextDiv, activityHeader);
        }

        const ACTIVITY_ICONS = {comment: '💬', page_view: '👁', like: '❤️', badge: '🏆', purchase: '💳'};

        if (activity) {
            activity.innerHTML = (data.recent_activity ?? []).map(a => `
            <div style="display:flex;align-items:center;gap:10px;padding:8px 15px;border-bottom:1px solid var(--mh-bg-3)">
                <div style="width:28px;height:28px;border-radius:50%;background:var(--mh-bg-2);border:1px solid var(--mh-border-1);display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0">
                    ${ACTIVITY_ICONS[a.type] ?? '📌'}
                </div>
                <div style="flex:1;min-width:0">
                    <div style="color:var(--mh-text-mid);font-size:11.5px;text-transform:capitalize">${esc(a.type.replace(/_/g, ' '))}</div>
                    <div style="color:var(--mh-text-dimmest);font-size:10px">${esc(a.date)}</div>
                </div>
                ${a.points > 0 ? `<span style="color:var(--mh-amber);font-size:10.5px;font-weight:700">+${a.points}pts</span>` : ''}
            </div>
        `).join('');
        }
    }

    const markNotifsReadBtn = $('mh-mark-notifs-read');
    if (markNotifsReadBtn) {
        markNotifsReadBtn.addEventListener('click', async () => {
            state.notifications = [];
            renderNotifications();
            try {
                await api(CFG.routes.notificationsMarkRead, {method: 'POST'});
            } catch (err) {
                console.error('[MemberHub] markNotifsRead:', err);
            }
        });
    }

    /* =========================================================================
       PUBLIC API
       ========================================================================= */
    window.MemberHub = {
        open: (tab) => openPanel(tab),
        close: closePanel,
        setSaved: (saved) => {
            if (CFG.isArticlePage) {
                const btnSave = $('mh-btn-save');
                const saveIcon = $('mh-save-icon');
                const saveLabel = $('mh-save-label');
                if (btnSave) {
                    state.isSaved = saved;
                    btnSave.classList.toggle('mh-ub-btn--saved', saved);
                    if (saveIcon) saveIcon.setAttribute('fill', saved ? 'currentColor' : 'none');
                    if (saveLabel) saveLabel.textContent = saved ? 'Saved' : 'Save';
                }
            }
            syncCardButtons(CFG.pageId, saved);
        },
    };

    /* =========================================================================
           MOCK DATA — remove each block as you wire the real API route
           ========================================================================= */
    function mockFeed() {
        return [
            {
                id: 1,
                type: 'comment',
                user: 'James K.',
                action: 'replied to your comment on',
                target: 'Premier League Top 10',
                time: '2m ago',
                read: false
            },
            {
                id: 2,
                type: 'like',
                user: 'Maria T.',
                action: 'liked your comment on',
                target: 'Transfer Window Analysis',
                time: '14m ago',
                read: false
            },
            {
                id: 3,
                type: 'badge',
                user: null,
                action: 'You earned the',
                target: 'Commentator badge',
                time: '1h ago',
                read: false
            },
            {
                id: 4,
                type: 'comment',
                user: 'Alex P.',
                action: 'replied to your comment on',
                target: 'Champions League Preview',
                time: '3h ago',
                read: true
            },
            {
                id: 5,
                type: 'article',
                user: null,
                action: 'New from a topic you follow:',
                target: 'Tactical Breakdown: High Press',
                time: '5h ago',
                read: true
            },
        ];
    }

    function mockSaved() {
        return [
            {
                id: 1,
                page_id: 1,
                title: 'Premier League Season Preview 2025/26',
                category: 'Analysis',
                saved_at_label: 'Today',
                read_time: '8 min',
                url: '#',
                image_url: null
            },
            {
                id: 2,
                page_id: 2,
                title: 'The Tactical Revolution Reshaping European Football',
                category: 'Tactics',
                saved_at_label: 'Yesterday',
                read_time: '12 min',
                url: '#',
                image_url: null
            },
            {
                id: 3,
                page_id: 3,
                title: 'Top 50 Players to Watch This Season',
                category: 'Features',
                saved_at_label: 'Mon',
                read_time: '15 min',
                url: '#',
                image_url: null
            },
        ];
    }

    function mockCommunity() {
        return [
            {id: 1, type: 'poll', title: 'Who wins the title this season?', votes: 4821, active: true},
            {id: 2, type: 'predictor', title: 'Predict the top scorer — your pick?', entries: 1203, active: true},
            {id: 3, type: 'challenge', title: 'Challenge a friend to a prediction duel', active: true},
        ];
    }

    function mockDeals() {
        return [
            {
                id: 1,
                title: 'Nike Phantom Elite FG',
                original_price: 229.99,
                sale_price: 149.99,
                discount: 35,
                merchant: 'JD Sports',
                sponsored: false,
                tag: 'Saved',
                image_url: null
            },
            {
                id: 2,
                title: 'Adidas Predator Pro GK Gloves',
                original_price: 79.99,
                sale_price: 54.99,
                discount: 31,
                merchant: 'Pro:Direct',
                sponsored: false,
                tag: null,
                image_url: null
            },
            {
                id: 3,
                title: 'FourFourTwo Annual Subscription',
                original_price: 59.99,
                sale_price: 29.99,
                discount: 50,
                merchant: 'FFT Shop',
                sponsored: false,
                tag: 'Members Only',
                image_url: null
            },
            {
                id: 4,
                title: 'UEFA Champions League 2025 Book',
                original_price: 34.99,
                sale_price: 24.99,
                discount: 29,
                merchant: 'Amazon',
                sponsored: true,
                tag: null,
                image_url: null
            },
            {
                id: 5,
                title: 'EA FC 25 Standard Edition',
                original_price: 69.99,
                sale_price: 44.99,
                discount: 36,
                merchant: 'GAME',
                sponsored: false,
                tag: null,
                image_url: null
            },
        ];
    }

})();