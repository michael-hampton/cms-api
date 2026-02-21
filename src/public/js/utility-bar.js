/**
 * utility-bar.js  v2
 *
 * Handles all interactions for the utility bar ribbon partial.
 * Multiple bar instances can exist on a page (one per card).
 *
 * Buttons use data-action attributes — no inline onclick:
 *   data-action="save"       – toggle save state
 *   data-action="like"       – toggle like state
 *   data-action="share"      – toggle share dropdown
 *   data-action="comment"    – open comment modal
 *   data-action="open-hub"   – open member hub panel
 *
 * Share options: data-share="facebook|twitter|linkedin|whatsapp|copy"
 */

const utilityBar = (() => {

    // ── Init ────────────────────────────────────────────────────────────────
    function init() {
        _initScrollProgress();
        _wireAllBars();
        _bindGlobalClose();
        // Re-wire if new cards are injected (infinite scroll / load-more)
        _hoistGuestPrompt();
        new MutationObserver(_wireAllBars)
            .observe(document.body, {childList: true, subtree: true});
    }

    // ── Wire all bars on the page ───────────────────────────────────────────
    function _wireAllBars() {
        document.querySelectorAll('.utility-bar:not([data-ub-wired])').forEach(bar => {
            bar.dataset.ubWired = '1';
            bar.addEventListener('click', _handleBarClick);
        });
    }

    // ── Central click dispatcher ────────────────────────────────────────────
    function _handleBarClick(e) {
        const btn = e.target.closest('[data-action]');
        if (!btn) return;

        const bar = e.currentTarget;
        const action = btn.dataset.action;

        switch (action) {
            case 'save':
                handleSave(btn);
                break;
            case 'like':
                _toggleLike(btn);
                break;
            case 'share':
                _toggleShare(btn, e);
                break;
            case 'comment':
                _handleComment(btn);
                break;
            case 'open-hub':
                openHub();
                break;
        }
    }

    // ── Scroll progress (sticky context only) ───────────────────────────────
    function _initScrollProgress() {
        const fill = document.querySelector('.utility-bar-progress__fill');
        if (!fill) return;

        const progressBar = document.getElementById('utilityBarProgress');

        const update = () => {
            const docHeight = document.documentElement.scrollHeight - window.innerHeight;
            const pct = docHeight > 0 ? Math.min(100, (window.scrollY / docHeight) * 100) : 0;
            fill.style.width = pct + '%';
            if (progressBar) progressBar.setAttribute('aria-valuenow', Math.round(pct));
        };

        window.addEventListener('scroll', update, {passive: true});
        update();
    }

    // ── Save ────────────────────────────────────────────────────────────────
    function handleSave(btn) {
        console.log(document.body.dataset)
        const isLoggedIn = document.body.dataset.memberLoggedIn === 'true';

        alert(isLoggedIn)

        if (!isLoggedIn) {
            _updateGuestPromptPageId(btn.dataset.pageId);
            showGuestSavePrompt(btn);
            return;
        }

        alert('mike1')

        _toggleSaveAPI(btn);
    }

    function _hoistGuestPrompt() {
        const prompt = document.getElementById('guestSavePrompt');
        if (prompt && prompt.parentElement !== document.body) {
            document.body.appendChild(prompt);
        }
    }

    async function _toggleSaveAPI(btn) {
        const pageId = btn.dataset.pageId;
        const wasSaved = btn.dataset.saved === '1';
        const nowSaved = !wasSaved;

        // Optimistic update
        _applySaveState(btn, nowSaved, true);

        try {
            const method = nowSaved ? 'POST' : 'DELETE';
            const res = await fetch(`/api/pages/${pageId}/save`, {
                method,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': _csrfToken(),
                },
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();
            _applySaveState(btn, data.saved ?? nowSaved, false);
        } catch (err) {
            console.error('[UtilityBar] save toggle:', err);
            _applySaveState(btn, wasSaved, false); // rollback
        }
    }

    function _applySaveState(btn, saved, animate) {
        btn.dataset.saved = saved ? '1' : '0';
        btn.classList.toggle('is-saved', saved);
        btn.setAttribute('aria-pressed', String(saved));

        const label = btn.querySelector('.utility-bar__label');
        if (label) label.textContent = saved ? 'Saved' : 'Save';

        if (animate) {
            btn.classList.add('utility-bar__btn--popping');
            setTimeout(() => btn.classList.remove('utility-bar__btn--popping'), 300);
        }
    }


    // ── Like ─────────────────────────────────────────────────────────────────
    async function _toggleLike(btn) {
        const pageId = btn.dataset.pageId;
        const wasLiked = btn.dataset.liked === '1';
        const nowLiked = !wasLiked;
        let count = parseInt(btn.dataset.likeCount ?? '0', 10);

        // Optimistic update
        count = Math.max(0, count + (nowLiked ? 1 : -1));
        _applyLikeState(btn, nowLiked, count, true);

        try {
            const res = await fetch(`/${SITE}/pages/like/${pageId}`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': _csrfToken(),
                },
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();
            _applyLikeState(btn, data.liked ?? nowLiked, data.count ?? count, false);
        } catch (err) {
            console.error('[UtilityBar] like toggle:', err);
            // rollback
            const rollbackCount = Math.max(0, count + (nowLiked ? -1 : 1));
            _applyLikeState(btn, wasLiked, rollbackCount, false);
        }
    }

    function _applyLikeState(btn, liked, count, animate) {
        btn.dataset.liked = liked ? '1' : '0';
        btn.dataset.likeCount = String(count);
        btn.classList.toggle('is-liked', liked);
        btn.setAttribute('aria-pressed', String(liked));

        const label = btn.querySelector('.utility-bar__label--like-count');
        if (label) label.textContent = count > 0 ? count : 'Like';

        if (animate) {
            btn.classList.add('utility-bar__btn--popping');
            setTimeout(() => btn.classList.remove('utility-bar__btn--popping'), 300);
        }
    }

    // ── Share ────────────────────────────────────────────────────────────────
    function _toggleShare(btn, e) {
        e.stopPropagation();
        const dropdown = btn.querySelector('.utility-bar__share-dropdown');
        if (!dropdown) return;

        const isOpen = dropdown.classList.contains('is-open');

        // Close all other open dropdowns first
        _closeAllShareDropdowns();

        if (!isOpen) {
            dropdown.classList.add('is-open');
            btn.setAttribute('aria-expanded', 'true');

            // Wire share option clicks
            dropdown.querySelectorAll('[data-share]').forEach(opt => {
                opt.onclick = (ev) => {
                    ev.stopPropagation();
                    _handleShareOption(opt.dataset.share, btn, opt);
                };
            });
        }
    }

    function _closeAllShareDropdowns() {
        document.querySelectorAll('.utility-bar__share-dropdown.is-open').forEach(d => {
            d.classList.remove('is-open');
            d.closest('[data-action="share"]')?.setAttribute('aria-expanded', 'false');
        });
    }

    async function _handleShareOption(network, shareBtn, optEl) {
        const bar = shareBtn.closest('.utility-bar');
        const pageUrl = window.location.href;
        const title = document.title;

        const urls = {
            facebook: `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(pageUrl)}`,
            twitter: `https://twitter.com/intent/tweet?url=${encodeURIComponent(pageUrl)}&text=${encodeURIComponent(title)}`,
            linkedin: `https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(pageUrl)}`,
            whatsapp: `https://wa.me/?text=${encodeURIComponent(title + ' ' + pageUrl)}`,
        };

        if (network === 'copy') {
            try {
                await navigator.clipboard.writeText(pageUrl);
                const label = optEl.querySelector('.ub-copy-label');
                if (label) {
                    const orig = label.textContent;
                    label.textContent = 'Copied!';
                    setTimeout(() => {
                        label.textContent = orig;
                    }, 1800);
                }
            } catch (_) { /* clipboard blocked */
            }
        } else if (urls[network]) {
            window.open(urls[network], '_blank', 'width=600,height=400,noopener');
        }

        _closeAllShareDropdowns();
    }

    // ── Comment ───────────────────────────────────────────────────────────────
    function _handleComment(btn) {
        const url = btn.dataset.url;
        const pageId = btn.dataset.pageId;
        if (typeof openCommentModal === 'function') {
            openCommentModal(url, pageId);
        }
    }

    // ── Hub ───────────────────────────────────────────────────────────────────
    /**
     * Opens the member hub.
     * Dispatches a custom event so member-hub.js can listen without coupling.
     * Also falls back to window.MemberHub.open() if available.
     *
     * @param {string} [tab]  – 'feed' | 'saved' | 'community' | 'deals' | 'notifications' | 'activity'
     */
    function openHub(tab) {
        // Prefer the direct API if hub JS already loaded
        if (window.MemberHub?.open) {
            window.MemberHub.open(tab ?? null);
            return;
        }
        // Try clicking the real hub trigger button (works even if MemberHub isn't loaded yet)
        const hubTrigger = document.getElementById('mh-hub-trigger');
        if (hubTrigger) {
            hubTrigger.click();
            return;
        }
        // Final fallback: dispatch event for hub JS to pick up
        document.dispatchEvent(new CustomEvent('memberHub:open', {
            detail: {tab: tab ?? null}
        }));
    }

    // ── Guest Save Prompt ────────────────────────────────────────────────────
    function _updateGuestPromptPageId(pageId) {
        const el = document.getElementById('guestSavePageId');
        if (el && pageId) el.value = String(pageId);
    }

    function showGuestSavePrompt(triggerEl = null) {
        const prompt = document.getElementById('guestSavePrompt');
        if (!prompt) return;

        if (triggerEl) {
            const rect = triggerEl.getBoundingClientRect();
            const promptWidth = 330;
            const gap = 8;

            let left = rect.left;
            left = Math.max(8, Math.min(left, window.innerWidth - promptWidth - 8));

            const spaceBelow = window.innerHeight - rect.bottom;
            const promptHeight = 280;
            let top;

            if (spaceBelow >= promptHeight + gap) {
                top = rect.bottom + gap;
            } else {
                top = rect.top - promptHeight - gap;
            }

            top = Math.max(8, top);

            prompt.style.left = left + 'px';
            prompt.style.top = top + 'px';
            prompt.style.bottom = '';
            prompt.style.transform = 'none';
        }

        prompt.hidden = false;
        prompt.style.visibility = 'visible';
        prompt.querySelector('input[type="email"]')?.focus();
    }

    function hideGuestSavePrompt() {
        const prompt = document.getElementById('guestSavePrompt');
        if (!prompt) return;
        prompt.hidden = true;
        prompt.style.visibility = 'hidden';
        prompt.style.top = '';
        prompt.style.left = '';
        prompt.style.transform = '';

        const form = document.getElementById('guestSaveForm');
        const success = document.getElementById('guestSaveSuccess');
        if (form) {
            form.reset();
            form.hidden = false;
        }
        if (success) success.hidden = true;
    }

    async function submitGuestSave(event, pageId) {
        event.preventDefault();
        const form = event.target;
        const email = form.email.value.trim();
        const consent = form.newsletter_consent?.checked ? 1 : 0;

        const submitBtn = form.querySelector('[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;

        try {
            const res = await fetch('/api/guest/save', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': _csrfToken(),
                },
                body: JSON.stringify({page_id: pageId, email, newsletter_consent: consent}),
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);

            const form = document.getElementById('guestSaveForm');
            const success = document.getElementById('guestSaveSuccess');
            if (form) form.hidden = true;
            if (success) success.hidden = false;

            setTimeout(hideGuestSavePrompt, 2500);

        } catch (err) {
            console.error('[UtilityBar] guestSave:', err);
            if (submitBtn) submitBtn.disabled = false;
        }
    }

    // ── Close share dropdown when clicking outside ───────────────────────────
    function _bindGlobalClose() {
        document.addEventListener('click', (e) => {
            if (!e.target.closest('[data-action="share"]')) {
                _closeAllShareDropdowns();
            }
            if (
                !e.target.closest('#guestSavePrompt') &&
                !e.target.closest('[data-action="save"]')
            ) {
                hideGuestSavePrompt();
            }
        });

        // Close button inside guest prompt
        document.addEventListener('click', (e) => {
            if (e.target.closest('#guestSaveClose')) {
                hideGuestSavePrompt();
            }
        });

        // Esc key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                _closeAllShareDropdowns();
                hideGuestSavePrompt();
            }
        });
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    function _csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    }

    // ── Public API ────────────────────────────────────────────────────────────
    return {
        init,
        handleSave,
        openHub,
        showGuestSavePrompt,
        hideGuestSavePrompt,
        submitGuestSave,
    };

})();

document.addEventListener('DOMContentLoaded', () => utilityBar.init());