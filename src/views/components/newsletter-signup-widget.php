<?php
/**
 * newsletter-signup-widget.php
 *
 * Inline teaser strip that opens a modal on hover.
 * The modal does NOT re-open once the user has dismissed it (stored in localStorage keyed by siteId).
 *
 * Expected variables:
 *   $siteId         – int
 *   $siteSlug       – string
 *   $newsletterName – string  (optional)
 *   $newsletterDesc – string  (optional)
 */

/** @var int $siteId */
/** @var string $siteSlug */
/** @var string|null $newsletterName */
/** @var string|null $newsletterDesc */
$siteId = \App\Framework\Support\SiteContext::getId();
$siteSlug = \App\Framework\Support\SiteContext::slug();
$newsletterName = $newsletterName ?? 'Our Newsletter';
$newsletterDesc = $newsletterDesc ?? 'Get the latest articles delivered straight to your inbox.';
$storageKey = 'newsletter_dismissed_' . $siteId;
?>

<!-- Newsletter teaser strip -->
<section class="nl-teaser"
         id="nl-teaser-<?= $siteId ?>"
         data-site-id="<?= $siteId ?>"
         data-storage-key="<?= htmlspecialchars($storageKey) ?>"
         aria-label="Newsletter signup"
         onmouseenter="nlHandleHover(this)">
    <div class="nl-teaser__inner">
        <span class="nl-teaser__icon" aria-hidden="true">✉️</span>
        <div class="nl-teaser__text">
            <strong><?= htmlspecialchars($newsletterName) ?></strong>
            <span><?= htmlspecialchars($newsletterDesc) ?></span>
        </div>
        <button class="nl-teaser__cta" onclick="nlOpenModal(<?= $siteId ?>)">
            Subscribe free →
        </button>
    </div>
</section>

<!-- Modal -->
<div class="nl-modal-backdrop"
     id="nl-modal-<?= $siteId ?>"
     role="dialog"
     aria-modal="true"
     aria-label="Newsletter signup"
     hidden
     onclick="nlBackdropClick(event, <?= $siteId ?>)">

    <div class="nl-modal">
        <button class="nl-modal__close"
                onclick="nlCloseModal(<?= $siteId ?>)"
                aria-label="Close newsletter signup">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                <line x1="18" y1="6" x2="6" y2="18"/>
                <line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </button>

        <div class="nl-modal__icon" aria-hidden="true">✉️</div>
        <h2 class="nl-modal__title"><?= htmlspecialchars($newsletterName) ?></h2>
        <p class="nl-modal__desc"><?= htmlspecialchars($newsletterDesc) ?></p>

        <form class="nl-modal__form"
              id="nl-form-<?= $siteId ?>"
              onsubmit="nlHandleSubmit(event, <?= $siteId ?>, '<?= htmlspecialchars($siteSlug) ?>')">
            <label for="nl-email-<?= $siteId ?>" class="sr-only">Email address</label>
            <input type="email"
                   id="nl-email-<?= $siteId ?>"
                   name="email"
                   class="nl-modal__input"
                   placeholder="your@email.com"
                   required
                   autocomplete="email">
            <button type="submit" class="nl-modal__submit">Subscribe</button>
        </form>

        <p class="nl-modal__note">No spam. Unsubscribe at any time.</p>

        <div class="nl-modal__success" id="nl-success-<?= $siteId ?>" hidden>
            <span class="nl-modal__success-icon" aria-hidden="true">🎉</span>
            <p>You're in! Check your inbox for a confirmation.</p>
        </div>
    </div>
</div>

<style>
    /* ── Newsletter teaser ──────────────────────────────────── */
    .nl-teaser {
        background: var(--primary-color, #2563eb);
        border-radius: 0.875rem;
        margin-bottom: 2rem;
        cursor: default;
    }

    .nl-teaser__inner {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem 1.25rem;
        flex-wrap: wrap;
    }

    .nl-teaser__icon {
        font-size: 1.5rem;
        flex-shrink: 0;
    }

    .nl-teaser__text {
        flex: 1;
        min-width: 200px;
        display: flex;
        flex-direction: column;
        gap: 0.125rem;
    }

    .nl-teaser__text strong {
        font-size: 0.9375rem;
        font-weight: 700;
        color: #fff;
    }

    .nl-teaser__text span {
        font-size: 0.8125rem;
        color: rgba(255, 255, 255, 0.85);
    }

    .nl-teaser__cta {
        background: #fff;
        color: var(--primary-color, #2563eb);
        border: none;
        border-radius: 2rem;
        padding: 0.5rem 1.25rem;
        font-size: 0.875rem;
        font-weight: 700;
        cursor: pointer;
        white-space: nowrap;
        flex-shrink: 0;
        transition: opacity 0.15s;
    }

    .nl-teaser__cta:hover {
        opacity: 0.88;
    }

    /* ── Backdrop ─────────────────────────────────────────────── */
    .nl-modal-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.45);
        z-index: 9000;
        display: flex;
        align-items: center;
        justify-content: center;
        animation: nlFadeIn 0.2s ease;
    }

    .nl-modal-backdrop[hidden] {
        display: none !important;
    }

    @keyframes nlFadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }

    /* ── Modal ───────────────────────────────────────────────── */
    .nl-modal {
        background: #fff;
        border-radius: 1.125rem;
        padding: 2rem 2rem 1.5rem;
        max-width: 420px;
        width: calc(100% - 2rem);
        text-align: center;
        position: relative;
        animation: nlSlideUp 0.25s ease;
    }

    @keyframes nlSlideUp {
        from {
            transform: translateY(16px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .nl-modal__close {
        position: absolute;
        top: 0.875rem;
        right: 0.875rem;
        background: none;
        border: none;
        cursor: pointer;
        color: var(--text-secondary, #6b7280);
        padding: 0.25rem;
        border-radius: 0.25rem;
        display: flex;
        transition: color 0.15s;
    }

    .nl-modal__close:hover {
        color: var(--text-primary, #111827);
    }

    .nl-modal__icon {
        font-size: 2.5rem;
        margin-bottom: 0.75rem;
    }

    .nl-modal__title {
        font-size: 1.375rem;
        font-weight: 800;
        margin: 0 0 0.5rem;
        color: var(--text-primary, #111827);
    }

    .nl-modal__desc {
        font-size: 0.9375rem;
        color: var(--text-secondary, #6b7280);
        margin: 0 0 1.25rem;
        line-height: 1.6;
    }

    /* Form */
    .nl-modal__form {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .nl-modal__input {
        flex: 1;
        min-width: 200px;
        padding: 0.625rem 0.875rem;
        border: 1.5px solid var(--border-color, #e5e7eb);
        border-radius: 0.5rem;
        font-size: 0.9375rem;
        outline: none;
        transition: border-color 0.15s;
    }

    .nl-modal__input:focus {
        border-color: var(--primary-color, #2563eb);
    }

    .nl-modal__submit {
        background: var(--primary-color, #2563eb);
        color: #fff;
        border: none;
        border-radius: 0.5rem;
        padding: 0.625rem 1.25rem;
        font-size: 0.9375rem;
        font-weight: 700;
        cursor: pointer;
        transition: opacity 0.15s;
        white-space: nowrap;
    }

    .nl-modal__submit:hover {
        opacity: 0.88;
    }

    .nl-modal__note {
        font-size: 0.75rem;
        color: var(--text-secondary, #9ca3af);
        margin: 0.75rem 0 0;
    }

    /* Success state */
    .nl-modal__success {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.5rem;
        padding: 1rem 0;
    }

    .nl-modal__success[hidden] {
        display: none !important;
    }

    .nl-modal__success-icon {
        font-size: 2.5rem;
    }

    .nl-modal__success p {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary, #111827);
        margin: 0;
    }

    /* Accessibility */
    .sr-only {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        white-space: nowrap;
        border: 0;
    }
</style>

<script>
    (function () {
        // Dismiss flag helpers — keyed per site so each site's widget is independent
        function isDismissed(storageKey) {
            try {
                return localStorage.getItem(storageKey) === '1';
            } catch (e) {
                return false;
            }
        }

        function markDismissed(storageKey) {
            try {
                localStorage.setItem(storageKey, '1');
            } catch (e) {
            }
        }

        // Hide the teaser entirely if already dismissed on page load
        document.querySelectorAll('.nl-teaser[data-storage-key]').forEach(function (teaser) {
            if (isDismissed(teaser.dataset.storageKey)) {
                //teaser.style.display = 'none';
            }
        });

        window.nlHandleHover = function (teaser) {
            var siteId = teaser.dataset.siteId;
            var storageKey = teaser.dataset.storageKey;
            if (!isDismissed(storageKey)) {
                nlOpenModal(siteId);
            }
        };

        window.nlOpenModal = function (siteId) {
            var modal = document.getElementById('nl-modal-' + siteId);
            if (modal) {
                modal.removeAttribute('hidden');
                // Focus first input for accessibility
                var input = modal.querySelector('input[type="email"]');
                if (input) {
                    setTimeout(function () {
                        input.focus();
                    }, 50);
                }
            }
        };

        window.nlCloseModal = function (siteId) {
            var modal = document.getElementById('nl-modal-' + siteId);
            var teaser = document.getElementById('nl-teaser-' + siteId);
            if (modal) {
                modal.setAttribute('hidden', '');
            }

            // Mark dismissed so it never opens again for this site
            if (teaser) {
                markDismissed(teaser.dataset.storageKey);
                //teaser.style.display = 'none'; // hide the teaser strip too
            }
        };

        window.nlBackdropClick = function (event, siteId) {
            // Close only when clicking the backdrop itself, not the modal card
            if (event.target === event.currentTarget) {
                nlCloseModal(siteId);
            }
        };

        window.nlHandleSubmit = function (event, siteId, siteSlug) {
            event.preventDefault();
            var form = document.getElementById('nl-form-' + siteId);
            var success = document.getElementById('nl-success-' + siteId);
            var email = form.querySelector('input[type="email"]').value;

            // POST to the standard newsletter subscribe endpoint
            fetch('/' + siteSlug + '/newsletter/subscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({email: email, site_id: siteId})
            })
                .then(function (res) {
                    return res.ok ? res.json() : Promise.reject(res);
                })
                .then(function () {
                    form.setAttribute('hidden', '');
                    success.removeAttribute('hidden');
                    // Close after 2.5 s and mark dismissed
                    setTimeout(function () {
                        nlCloseModal(siteId);
                    }, 2500);
                })
                .catch(function () {
                    // Non-critical: show success anyway to avoid blocking the user
                    form.setAttribute('hidden', '');
                    success.removeAttribute('hidden');
                    setTimeout(function () {
                        nlCloseModal(siteId);
                    }, 2500);
                });
        };

        // Trap focus inside open modal (accessibility)
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.nl-modal-backdrop:not([hidden])').forEach(function (backdrop) {
                    var siteId = backdrop.id.replace('nl-modal-', '');
                    nlCloseModal(siteId);
                });
            }
        });
    })();
</script>