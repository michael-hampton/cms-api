/**
 * Member Hub — member-hub.js
 *
 * Depends on window.MH being written by member-hub.php before this loads.
 * No framework dependencies. Vanilla ES2017+.
 *
 * Two rendering contexts (set by PHP in window.MH.isArticlePage):
 *
 *   isArticlePage = true
 *     ● Utility bar is present (#mh-utility-bar)
 *     ● #mh-hub-trigger lives inside the utility bar
 *     ● Save / Like / Share / progress bar all active
 *
 *   isArticlePage = false
 *     ● Utility bar is absent
 *     ● #mh-hub-trigger lives in the header (member-badge.php)
 *     ● No save/like/share/progress — only hub panel open/close
 *     ● Card bookmark buttons still work everywhere
 */

(function () {
    'use strict';

    if (!window.MH) {
        console.warn('[MemberHub] window.MH not found — check member-hub.php is included before this script.');
        return;
    }

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
        community: null,
        deals: null,
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
        // 204 No Content
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

    if (hubTrigger) {
        hubTrigger.addEventListener('click', () => {
            state.panelOpen ? closePanel() : openPanel();
        });
    }

    if (closeBtn) closeBtn.addEventListener('click', closePanel);
    if (overlay) overlay.addEventListener('click', closePanel);

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && state.panelOpen) closePanel();
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
            case 'community':
                loadCommunity();
                break;
            case 'deals':
                loadDeals();
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
       UTILITY BAR — only runs when isArticlePage = true
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

        /* ── Save button ── */
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

            // Sync every card bookmark that matches this page
            syncCardButtons(CFG.pageId, saved);
        }

        setSaveState(state.isSaved);

        if (btnSave) {
            btnSave.addEventListener('click', async () => {
                if (!CFG.isLoggedIn) {
                    showGuestPrompt(CFG.pageId);
                    return;
                }
                const newSaved = !state.isSaved;
                setSaveState(newSaved, true);
                state.saved = null; // invalidate cache

                try {
                    if (newSaved) {
                        // TODO: uncomment once route confirmed
                        // await api(CFG.routes.saveCreate, {
                        //     method: 'POST',
                        //     body: JSON.stringify({ page_id: CFG.pageId }),
                        // });
                    } else {
                        // TODO: uncomment once route confirmed
                        // await api(route(CFG.routes.saveDelete, { id: CFG.pageId }), {
                        //     method: 'DELETE',
                        // });
                    }
                } catch (err) {
                    console.error('[MemberHub] save toggle:', err);
                    setSaveState(!newSaved); // rollback
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

        setLikeState(state.isLiked);

        if (btnLike) {
            btnLike.addEventListener('click', async () => {
                const newLiked = !state.isLiked;
                state.likeCount = Math.max(0, state.likeCount + (newLiked ? 1 : -1));
                setLikeState(newLiked, true);

                try {
                    // TODO: uncomment once route confirmed
                    // await api(route(CFG.routes.likePage, { id: CFG.pageId }), {
                    //     method: newLiked ? 'POST' : 'DELETE',
                    // });
                } catch (err) {
                    console.error('[MemberHub] like toggle:', err);
                    state.likeCount = Math.max(0, state.likeCount + (newLiked ? -1 : 1));
                    setLikeState(!newLiked); // rollback
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
                    } catch (_) { /* cancelled */
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
                } catch (_) { /* clipboard blocked */
                }
            });
        }
    } // end initUtilityBar

    /* =========================================================================
       CARD bookmark buttons
       Wired on page load and re-wired whenever new cards are injected
       (infinite scroll / load more patterns).
       ========================================================================= */

    /**
     * Update every .mh-card-save[data-page-id="{id}"] to match `saved`.
     * Called when the utility bar save button changes, so cards stay in sync.
     */
    function syncCardButtons(pageId, saved) {
        if (!pageId) return;
        $$(`[data-page-id="${pageId}"].mh-card-save`).forEach(btn => {
            applyCardSaveState(btn, saved);
        });
    }

    function applyCardSaveState(btn, saved) {
        btn.dataset.saved = saved ? '1' : '0';
        btn.setAttribute('aria-pressed', String(saved));
        btn.setAttribute('title', saved ? 'Remove from saved' : 'Save article');
        btn.setAttribute('aria-label', saved ? 'Remove article from saved' : 'Save article');
        btn.classList.toggle('is-saved', saved);

        const empty = btn.querySelector('.mh-card-save-icon--empty');
        const filled = btn.querySelector('.mh-card-save-icon--filled');
        if (empty) empty.style.display = saved ? 'none' : '';
        if (filled) filled.style.display = saved ? '' : 'none';
    }

    async function handleCardSaveClick(btn) {
        const pageId = btn.dataset.pageId;
        if (!pageId) return;

        // Guest — show capture prompt with this card's page ID
        if (!CFG.isLoggedIn) {
            showGuestPrompt(pageId);
            return;
        }

        const wasSaved = btn.dataset.saved === '1';
        const nowSaved = !wasSaved;

        // Optimistic UI — update this card and the utility bar if same page
        applyCardSaveState(btn, nowSaved);
        if (CFG.isArticlePage && String(pageId) === String(CFG.pageId)) {
            // Delegate back to the utility bar save state setter
            const saveIcon = $('mh-save-icon');
            const saveLabel = $('mh-save-label');
            const btnSave = $('mh-btn-save');
            if (btnSave) {
                btnSave.classList.toggle('mh-ub-btn--saved', nowSaved);
                if (saveIcon) saveIcon.setAttribute('fill', nowSaved ? 'currentColor' : 'none');
                if (saveLabel) saveLabel.textContent = nowSaved ? 'Saved' : 'Save';
                state.isSaved = nowSaved;
            }
        }

        state.saved = null; // invalidate cache

        try {
            if (nowSaved) {
                // TODO: uncomment once route confirmed
                // await api(CFG.routes.saveCreate, {
                //     method: 'POST',
                //     body: JSON.stringify({ page_id: pageId }),
                // });
            } else {
                // TODO: uncomment once route confirmed
                // await api(route(CFG.routes.saveDelete, { id: pageId }), {
                //     method: 'DELETE',
                // });
            }
        } catch (err) {
            console.error('[MemberHub] card save:', err);
            applyCardSaveState(btn, wasSaved); // rollback
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

    // Re-wire whenever new cards are injected (infinite scroll, load-more)
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

    function showGuestPrompt(pageId) {
        if (!guestPrompt) return;
        // Update the hidden page_id so the right article is captured,
        // whether triggered from the utility bar or a card button
        if (mgpPageId && pageId) mgpPageId.value = String(pageId);
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
                // TODO: uncomment once route confirmed
                // await api(CFG.routes.guestSave, {
                //     method: 'POST',
                //     body: JSON.stringify({ page_id: pageId, email, newsletter_consent: consent }),
                // });
                console.info('[MemberHub] guest save (mock):', {pageId, email, consent});

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
        comment: '💬',
        like: '❤️',
        badge: '🏆',
        article: '📰',
        mention: '📣',
    };

    async function loadFeed() {
        if (state.feed !== null || !CFG.isLoggedIn) return;

        const loading = $('mh-feed-loading');
        const content = $('mh-feed-content');

        try {
            // TODO: swap for real call once route confirmed
            // state.feed = await api(CFG.routes.feedIndex);
            state.feed = mockFeed();
            renderFeed();
        } catch (err) {
            console.error('[MemberHub] loadFeed:', err);
            if (loading) loading.innerHTML =
                '<p style="color:#334155;padding:15px;font-size:12px">Failed to load activity.</p>';
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

        const unread = (state.feed ?? []).filter(f => !f.read).length;

        // Tab badge
        if (feedBadge) {
            feedBadge.textContent = unread > 0 ? String(unread) : '';
            feedBadge.style.display = unread > 0 ? '' : 'none';
        }

        // Hub pill badge
        state.unread = unread;
        setHubBadge(unread);

        // Mark-all button
        if (markRead) markRead.style.display = unread > 0 ? '' : 'none';

        feedList.innerHTML = (state.feed ?? []).map(item => `
            <div class="mh-feed-item ${!item.read ? 'mh-feed-item--unread' : ''}"
                 data-id="${esc(String(item.id))}">
                <div class="mh-feed-icon">${FEED_ICONS[item.type] ?? '📌'}</div>
                <div class="mh-feed-body">
                    <div class="mh-feed-text">
                        ${item.user ? `<span class="fn-user">${esc(item.user)}</span> ` : ''}
                        <span class="fn-action">${esc(item.action)}</span>
                        <span class="fn-target"> ${esc(item.target)}</span>
                    </div>
                    <div class="mh-feed-time">${esc(item.time)}</div>
                </div>
                ${!item.read ? '<div class="mh-feed-dot" aria-hidden="true"></div>' : ''}
            </div>
        `).join('');

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
                // TODO: await api(CFG.routes.feedReadAll, { method: 'POST' });
            } catch (err) {
                console.error('[MemberHub] markAllRead:', err);
            }
        });
    }

    if (seeCommunity) {
        seeCommunity.addEventListener('click', () => activateTab('community'));
    }

    async function renderLivePreview() {
        const liveList = $('mh-live-list');
        if (!liveList) return;
        try {
            const data = state.community ?? mockCommunity();
            liveList.innerHTML = data.slice(0, 2).map(item => `
                <div class="mh-live-item">
                    <div class="mh-live-info">
                        <div class="mh-live-title">${esc(item.title)}</div>
                        <div class="mh-live-count">
                            ${item.votes
                ? `${Number(item.votes).toLocaleString()} votes`
                : `${Number(item.entries ?? 0).toLocaleString()} entries`}
                        </div>
                    </div>
                    <button class="mh-live-btn">${item.type === 'poll' ? 'Vote' : 'Join'}</button>
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
            // TODO: state.saved = await api(CFG.routes.savesIndex);
            state.saved = mockSaved();
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
            ? `<img src="${esc(item.image_url)}"
                                alt="${esc(item.title)}" loading="lazy">`
            : '📄'}
                </div>
                <div class="mh-saved-body">
                    <div class="mh-saved-title">${esc(item.title)}</div>
                    <div class="mh-saved-meta">
                        ${item.category
            ? `<span class="mh-saved-cat">${esc(item.category)}</span>`
            : ''}
                        ${item.read_time
            ? `<span class="mh-saved-meta-text">
                                   <svg width="9" height="9" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="2">
                                       <circle cx="12" cy="12" r="10"/>
                                       <polyline points="12 6 12 12 16 14"/>
                                   </svg>
                                   ${esc(item.read_time)}
                               </span>`
            : ''}
                        ${item.saved_at_label
            ? `<span class="mh-saved-meta-text">${esc(item.saved_at_label)}</span>`
            : ''}
                    </div>
                </div>
                <button class="mh-saved-remove"
                        data-id="${esc(String(item.id))}"
                        aria-label="Remove saved article">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <polyline points="3 6 5 6 21 6"/>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
                    </svg>
                </button>
            </div>
        `).join('');

        // Row → navigate to article
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

        // Remove buttons
        savedList.querySelectorAll('.mh-saved-remove').forEach(btn => {
            btn.addEventListener('click', e => {
                e.stopPropagation();
                removeSaved(btn.dataset.id, btn.closest('.mh-saved-item'));
            });
        });

        // Append upsell once
        if (!content.querySelector('.mh-upsell')) {
            content.insertAdjacentHTML('beforeend', `
                <div class="mh-upsell">
                    <div class="mh-upsell-inner">
                        <span class="mh-upsell-icon">⚡</span>
                        <div>
                            <div class="mh-upsell-title">Unlimited saves &amp; offline access</div>
                            <div class="mh-upsell-body">
                                Premium members sync their reading list across
                                every device — even offline.
                            </div>
                            <a href="/${esc(CFG.siteSlug)}/member/upgrade"
                               class="mh-btn-primary">Explore Premium →</a>
                        </div>
                    </div>
                </div>
            `);
        }
    }

    async function removeSaved(id, rowEl) {
        rowEl?.classList.add('is-removing');
        try {
            // TODO: await api(route(CFG.routes.saveDelete, { id }), { method: 'DELETE' });
            setTimeout(() => {
                state.saved = (state.saved ?? []).filter(i => String(i.id) !== String(id));
                // If removed item was the current article, unsave the utility bar too
                if (CFG.isArticlePage && String(id) === String(CFG.pageId)) {
                    const saveIcon = $('mh-save-icon');
                    const saveLabel = $('mh-save-label');
                    const btnSave = $('mh-btn-save');
                    if (btnSave) {
                        state.isSaved = false;
                        btnSave.classList.remove('mh-ub-btn--saved');
                        if (saveIcon) saveIcon.setAttribute('fill', 'none');
                        if (saveLabel) saveLabel.textContent = 'Save';
                    }
                }
                syncCardButtons(id, false);
                renderSaved();
            }, 290);
        } catch (err) {
            console.error('[MemberHub] removeSaved:', err);
            rowEl?.classList.remove('is-removing');
        }
    }

    /* =========================================================================
       COMMUNITY TAB
       ========================================================================= */
    async function loadCommunity() {
        if (state.community !== null) return;
        try {
            // TODO: state.community = await api(CFG.routes.communityIndex);
            state.community = mockCommunity();
            renderCommunity();
        } catch (err) {
            console.error('[MemberHub] loadCommunity:', err);
        }
    }

    function renderCommunity() {
        const loading = $('mh-community-loading');
        const content = $('mh-community-content');
        const list = $('mh-community-list');
        const lbRows = $('mh-leaderboard-rows');

        if (!loading || !content) return;
        loading.style.display = 'none';
        content.style.display = 'block';

        const TYPE = {
            poll: {label: '📊 Poll', cls: 'mh-type--poll', btn: 'Vote now'},
            predictor: {label: '🔮 Predictor', cls: 'mh-type--predictor', btn: 'Predict'},
            challenge: {label: '⚔️ Challenge', cls: 'mh-type--challenge', btn: 'Accept'},
        };

        if (list) {
            list.innerHTML = (state.community ?? []).map(item => {
                const m = TYPE[item.type] ?? TYPE.poll;
                const count = item.votes
                    ? `${Number(item.votes).toLocaleString()} votes`
                    : `${Number(item.entries ?? 0).toLocaleString()} entries`;
                const disabled = !CFG.isLoggedIn ? 'disabled class="is-disabled"' : '';
                return `
                    <div style="padding:0 15px 9px">
                        <div class="mh-community-card-inner">
                            <div class="mh-community-card-header">
                                <span class="mh-community-type ${m.cls}">${m.label}</span>
                                <div class="mh-live-dot" aria-hidden="true"></div>
                            </div>
                            <div class="mh-community-card-title">${esc(item.title)}</div>
                            <div class="mh-community-card-footer">
                                <span class="mh-community-count">${count}</span>
                                <button class="mh-community-action-btn" ${disabled}>
                                    ${m.btn}
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // Leaderboard
        // TODO: fetch from CFG.routes.leaderboard
        if (lbRows) {
            const lb = [['@TacticalTom', 980], ['@GoalGuru', 740], ['@PressBoxPete', 620]];
            lbRows.innerHTML = lb.map(([name, pts], i) => `
                <div class="mh-lb-row">
                    <div class="mh-lb-left">
                        <span class="mh-lb-rank ${i === 0 ? 'mh-lb-rank--gold' : ''}">${i + 1}</span>
                        <span class="mh-lb-name">${esc(name)}</span>
                    </div>
                    <span class="mh-lb-pts">${Number(pts).toLocaleString()} pts</span>
                </div>
            `).join('');
        }
    }

    /* =========================================================================
       DEALS TAB
       ========================================================================= */
    async function loadDeals() {
        if (state.deals !== null) return;
        try {
            // TODO: state.deals = await api(CFG.routes.dealsIndex);
            state.deals = mockDeals();
            renderDeals();
        } catch (err) {
            console.error('[MemberHub] loadDeals:', err);
        }
    }

    function renderDeals() {
        const loading = $('mh-deals-loading');
        const content = $('mh-deals-content');
        const list = $('mh-deals-list');

        if (!loading || !content || !list) return;
        loading.style.display = 'none';
        content.style.display = 'block';

        const TAG_CLS = {
            'Members Only': 'mh-deal-tag--members',
            'Saved': 'mh-deal-tag--saved',
        };

        list.innerHTML = (state.deals ?? []).map(deal => `
            <div class="mh-deal-item">
                ${deal.sponsored
            ? '<div class="mh-deal-sponsored-label">Sponsored</div>' : ''}
                <div class="mh-deal-inner">
                    <div class="mh-deal-thumb">
                        ${deal.image_url
            ? `<img src="${esc(deal.image_url)}"
                                    alt="${esc(deal.title)}" loading="lazy">`
            : '🏷️'}
                    </div>
                    <div class="mh-deal-body">
                        <div class="mh-deal-title-row">
                            <div class="mh-deal-title">${esc(deal.title)}</div>
                            ${deal.tag && !deal.sponsored
            ? `<span class="mh-deal-tag ${TAG_CLS[deal.tag] ?? 'mh-deal-tag--other'}">
                                       ${esc(deal.tag)}
                                   </span>`
            : ''}
                        </div>
                        <div class="mh-deal-prices">
                            <span class="mh-deal-sale">
                                £${Number(deal.sale_price).toFixed(2)}
                            </span>
                            <span class="mh-deal-orig">
                                £${Number(deal.original_price).toFixed(2)}
                            </span>
                            <span class="mh-deal-discount">-${deal.discount}%</span>
                        </div>
                        <div class="mh-deal-merchant">via ${esc(deal.merchant)}</div>
                    </div>
                </div>
            </div>
        `).join('');
    }

    /* =========================================================================
       PUBLIC API
       Lets other scripts open the hub programmatically, e.g.:
         window.MemberHub.open('saved')
         window.MemberHub.open()          // opens to last active tab
       ========================================================================= */
    window.MemberHub = {
        open: (tab) => openPanel(tab),
        close: closePanel,
        // Expose so page-specific scripts can update save state after a
        // server-side action (e.g. after a user subscribes and saves are migrated)
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