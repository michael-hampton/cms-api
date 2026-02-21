/**
 * utility-bar.js
 *
 * Handles all interactions for the utility bar partial (utility-bar.php).
 * No framework dependencies — plain ES2017+.
 *
 * API endpoints assumed (adjust to match your routes):
 *   POST /api/pages/{pageId}/save      → { saved: bool }
 *   DELETE /api/pages/{pageId}/save    → { saved: bool }
 *   POST /api/pages/{pageId}/like      → { liked: bool, count: int }
 *   DELETE /api/pages/{pageId}/like    → { liked: bool, count: int }
 *   POST /api/guest/save               → { ok: bool }  (body: { page_id, email, newsletter_consent })
 */

const utilityBar = (() => {

    // ── State ───────────────────────────────────────────────────────────────
    let _saved = false;
    let _liked = false;
    let _likeCount = 0;

    // ── Init ────────────────────────────────────────────────────────────────
    function init() {
        _initScrollProgress();
        _initStateFromDOM();
        _bindGlobalClose();
    }

    function _initStateFromDOM() {
        const bar = document.getElementById('utilityBar');
        if (!bar) return;

        // Like count may be seeded from PHP via data attr on the button
        const likeBtn = document.getElementById('utilityBarLikeBtn');
        if (likeBtn) {
            _likeCount = parseInt(likeBtn.dataset.likeCount ?? '0', 10);
            _liked = likeBtn.dataset.liked === 'true';
            _updateLikeUI();
        }

        // Save state may be seeded from PHP
        const saveBtn = document.getElementById('utilityBarSaveBtn');
        if (saveBtn) {
            _saved = saveBtn.dataset.saved === 'true';
            _updateSaveUI();
        }
    }

    // ── Scroll progress (sticky context only) ───────────────────────────────
    function _initScrollProgress() {
        const bar = document.querySelector('.utility-bar--sticky');
        if (!bar) return;

        const fill = document.querySelector('.utility-bar-progress__fill');
        if (!fill) return;

        const progressBar = document.getElementById('utilityBarProgress');

        const update = () => {
            const scrollTop = window.scrollY;
            const docHeight = document.documentElement.scrollHeight - window.innerHeight;
            const progress = docHeight > 0 ? Math.min(100, (scrollTop / docHeight) * 100) : 0;
            fill.style.width = progress + '%';
            if (progressBar) progressBar.setAttribute('aria-valuenow', Math.round(progress));
        };

        window.addEventListener('scroll', update, {passive: true});
        update();
    }

    // ── Save ────────────────────────────────────────────────────────────────
    function handleSave(btn) {
        const isLoggedIn = document.body.dataset.memberLoggedIn === 'true';

        if (!isLoggedIn) {
            showGuestSavePrompt();
            return;
        }

        _toggleSaveAPI(btn);
    }

    async function _toggleSaveAPI(btn) {
        const pageId = btn.dataset.pageId;
        const method = _saved ? 'DELETE' : 'POST';

        btn.disabled = true;

        try {
            const res = await fetch(`/api/pages/${pageId}/save`, {
                method,
                headers: {'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': _csrfToken()},
            });
            const data = await res.json();
            _saved = data.saved;
            _updateSaveUI();
        } catch (e) {
            console.error('Save failed', e);
        } finally {
            btn.disabled = false;
        }
    }

    function _updateSaveUI() {
        const btn = document.getElementById('utilityBarSaveBtn');
        const label = btn?.querySelector('.utility-bar__label');
        if (!btn) return;

        btn.classList.toggle('is-saved', _saved);
        btn.setAttribute('aria-pressed', String(_saved));
        if (label) label.textContent = _saved ? 'Saved' : 'Save';
    }

    // ── Like ─────────────────────────────────────────────────────────────────
    async function toggleLike(btn) {
        const pageId = btn.dataset.pageId;
        const method = _liked ? 'DELETE' : 'POST';

        btn.disabled = true;

        try {
            const res = await fetch(`/api/pages/${pageId}/like`, {
                method,
                headers: {'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': _csrfToken()},
            });
            const data = await res.json();
            _liked = data.liked;
            _likeCount = data.count;
            _updateLikeUI();
        } catch (e) {
            console.error('Like failed', e);
        } finally {
            btn.disabled = false;
        }
    }

    function _updateLikeUI() {
        const btn = document.getElementById('utilityBarLikeBtn');
        const label = btn?.querySelector('.utility-bar__label--like-count');
        if (!btn) return;

        btn.classList.toggle('is-liked', _liked);
        btn.setAttribute('aria-pressed', String(_liked));
        if (label) label.textContent = _likeCount > 0 ? _likeCount : 'Like';
    }

    // ── Guest Save Prompt ────────────────────────────────────────────────────
    function showGuestSavePrompt() {
        const prompt = document.getElementById('guestSavePrompt');
        if (!prompt) return;
        prompt.hidden = false;
        prompt.querySelector('input[type="email"]')?.focus();
    }

    function hideGuestSavePrompt() {
        const prompt = document.getElementById('guestSavePrompt');
        if (prompt) prompt.hidden = true;
    }

    async function submitGuestSave(event, pageId) {
        event.preventDefault();
        const form = event.target;
        const email = form.email.value.trim();
        const consent = form.newsletter_consent?.checked ? 1 : 0;

        const submitBtn = form.querySelector('[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;

        try {
            await fetch('/api/guest/save', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': _csrfToken(),
                },
                body: JSON.stringify({page_id: pageId, email, newsletter_consent: consent}),
            });

            // Swap to success state
            const prompt = document.getElementById('guestSavePrompt');
            if (prompt) {
                prompt.querySelector('.guest-save-prompt__form').hidden = true;
                prompt.querySelector('.guest-save-prompt__success').hidden = false;
            }

            // Auto-close after 2.5 s
            setTimeout(hideGuestSavePrompt, 2500);

        } catch (e) {
            console.error('Guest save failed', e);
            if (submitBtn) submitBtn.disabled = false;
        }
    }

    // ── Hub ──────────────────────────────────────────────────────────────────
    /**
     * Opens the member hub slide-out.
     * Dispatches a custom event so member-hub.php / its own JS can listen
     * without tight coupling.
     *
     * @param {string} [tab]  - 'feed' | 'saved' | 'community' | 'deals'
     */
    function openHub(tab) {
        document.dispatchEvent(new CustomEvent('memberHub:open', {
            detail: {tab: tab ?? null}
        }));
    }

    // ── Close share dropdown when clicking outside ───────────────────────────
    function _bindGlobalClose() {
        document.addEventListener('click', (e) => {
            // Share dropdowns
            if (!e.target.closest('.utility-bar__btn--share')) {
                document.querySelectorAll('.share-dropdown').forEach(d => d.classList.remove('is-open'));
            }
            // Guest save prompt
            if (
                !e.target.closest('#guestSavePrompt') &&
                !e.target.closest('#utilityBarSaveBtn')
            ) {
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
        toggleLike,
        openHub,
        showGuestSavePrompt,
        hideGuestSavePrompt,
        submitGuestSave,
    };

})();

document.addEventListener('DOMContentLoaded', () => utilityBar.init());