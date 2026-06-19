<?php
/**
 * Newsletter signup widget.
 *
 * Expected variables:
 *   $siteId         – int
 *   $siteSlug       – string
 *   $newsletterName – string|null
 *   $newsletterDesc – string|null
 */

$siteId = \App\Framework\Support\SiteContext::getId();
$siteSlug = \App\Framework\Support\SiteContext::slug();
$newsletterName = $newsletterName ?? 'Stay informed';
$newsletterDesc = $newsletterDesc ?? 'Get our best stories, useful updates and carefully selected offers delivered to your inbox.';
$storageKey = 'newsletter_dismissed_' . $siteId;

$escapedSiteSlug = htmlspecialchars($siteSlug, ENT_QUOTES, 'UTF-8');
$escapedStorageKey = htmlspecialchars($storageKey, ENT_QUOTES, 'UTF-8');
$escapedName = htmlspecialchars($newsletterName, ENT_QUOTES, 'UTF-8');
$escapedDescription = htmlspecialchars($newsletterDesc, ENT_QUOTES, 'UTF-8');
?>

<section class="nl-signup"
         id="nl-teaser-<?= $siteId ?>"
         data-site-id="<?= $siteId ?>"
         data-storage-key="<?= $escapedStorageKey ?>"
         aria-labelledby="nl-title-<?= $siteId ?>">
    <div class="nl-signup__content"
         onmouseenter="nlHandleHover(document.getElementById('nl-teaser-<?= $siteId ?>'))">
        <div class="nl-signup__eyebrow">
            <span class="nl-signup__eyebrow-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 4h16v16H4z"/>
                    <path d="m4 7 8 6 8-6"/>
                </svg>
            </span>
            Free newsletter
        </div>

        <h2 class="nl-signup__title" id="nl-title-<?= $siteId ?>"><?= $escapedName ?></h2>
        <p class="nl-signup__subtitle"><?= $escapedDescription ?></p>

        <ul class="nl-signup__benefits" aria-label="Newsletter benefits">
            <li>Top stories selected for you</li>
            <li>Useful updates without the noise</li>
            <li>Unsubscribe whenever you like</li>
        </ul>

        <button type="button" class="nl-signup__preview" onclick="nlOpenModal(<?= $siteId ?>)">
            Preview signup
            <span aria-hidden="true">→</span>
        </button>
    </div>

    <div class="nl-signup__form-panel">
        <form class="nl-signup__form"
              id="nl-inline-form-<?= $siteId ?>"
              onsubmit="nlHandleSubmit(event, <?= $siteId ?>, '<?= $escapedSiteSlug ?>', 'inline')">
            <div class="nl-signup__field">
                <label for="nl-inline-email-<?= $siteId ?>">Email address</label>
                <div class="nl-signup__input-wrap">
                    <span class="nl-signup__input-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 4h16v16H4z"/>
                            <path d="m4 7 8 6 8-6"/>
                        </svg>
                    </span>
                    <input type="email"
                           id="nl-inline-email-<?= $siteId ?>"
                           name="email"
                           placeholder="you@example.com"
                           autocomplete="email"
                           required>
                </div>
            </div>

            <fieldset class="nl-signup__consents">
                <legend class="sr-only">Email preferences</legend>

                <label class="nl-check">
                    <input type="checkbox" name="newsletter_consent" value="1" required>
                    <span class="nl-check__box" aria-hidden="true"></span>
                    <span>I agree to receive email updates from us.</span>
                </label>

                <label class="nl-check">
                    <input type="checkbox" name="offers_consent" value="1">
                    <span class="nl-check__box" aria-hidden="true"></span>
                    <span>Contact me with relevant offers and promotions.</span>
                </label>
            </fieldset>

            <button type="submit" class="nl-signup__submit">
                <span>Subscribe free</span>
                <span aria-hidden="true">→</span>
            </button>

            <p class="nl-signup__privacy">
                By subscribing, you confirm that you have read our privacy information.
            </p>

            <p class="nl-signup__message nl-signup__message--error"
               id="nl-inline-error-<?= $siteId ?>"
               role="alert"
               hidden></p>
        </form>

        <div class="nl-signup__success" id="nl-inline-success-<?= $siteId ?>" role="status" hidden>
            <span class="nl-signup__success-icon" aria-hidden="true">✓</span>
            <div>
                <strong>You’re subscribed</strong>
                <p>Check your inbox for confirmation.</p>
            </div>
        </div>
    </div>
</section>

<div class="nl-modal-backdrop"
     id="nl-modal-<?= $siteId ?>"
     role="dialog"
     aria-modal="true"
     aria-labelledby="nl-modal-title-<?= $siteId ?>"
     hidden
     onclick="nlBackdropClick(event, <?= $siteId ?>)">
    <div class="nl-modal">
        <button type="button"
                class="nl-modal__close"
                onclick="nlCloseModal(<?= $siteId ?>)"
                aria-label="Close newsletter signup">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                <path d="m6 6 12 12M18 6 6 18"/>
            </svg>
        </button>

        <div class="nl-modal__badge">Free newsletter</div>
        <div class="nl-modal__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4 4h16v16H4z"/>
                <path d="m4 7 8 6 8-6"/>
            </svg>
        </div>
        <h2 class="nl-modal__title" id="nl-modal-title-<?= $siteId ?>"><?= $escapedName ?></h2>
        <p class="nl-modal__desc"><?= $escapedDescription ?></p>

        <form class="nl-modal__form"
              id="nl-modal-form-<?= $siteId ?>"
              onsubmit="nlHandleSubmit(event, <?= $siteId ?>, '<?= $escapedSiteSlug ?>', 'modal')">
            <label for="nl-modal-email-<?= $siteId ?>">Email address</label>
            <input type="email"
                   id="nl-modal-email-<?= $siteId ?>"
                   name="email"
                   placeholder="you@example.com"
                   autocomplete="email"
                   required>

            <div class="nl-modal__consents">
                <label class="nl-check nl-check--modal">
                    <input type="checkbox" name="newsletter_consent" value="1" required>
                    <span class="nl-check__box" aria-hidden="true"></span>
                    <span>I agree to receive email updates from us.</span>
                </label>

                <label class="nl-check nl-check--modal">
                    <input type="checkbox" name="offers_consent" value="1">
                    <span class="nl-check__box" aria-hidden="true"></span>
                    <span>Contact me with relevant offers and promotions.</span>
                </label>
            </div>

            <button type="submit" class="nl-modal__submit">Subscribe free</button>
            <p class="nl-signup__message nl-signup__message--error"
               id="nl-modal-error-<?= $siteId ?>"
               role="alert"
               hidden></p>
        </form>

        <div class="nl-modal__success" id="nl-modal-success-<?= $siteId ?>" role="status" hidden>
            <span class="nl-modal__success-icon" aria-hidden="true">✓</span>
            <strong>You’re subscribed</strong>
            <p>Check your inbox for confirmation.</p>
        </div>
    </div>
</div>

<style>
    .nl-signup,
    .nl-signup * {
        box-sizing: border-box;
    }

    .nl-signup {
        --nl-primary: var(--primary-color, #2563eb);
        --nl-primary-dark: color-mix(in srgb, var(--nl-primary) 80%, #000);
        --nl-surface: var(--surface-color, #fff);
        --nl-text: var(--text-primary, #111827);
        --nl-muted: var(--text-secondary, #64748b);
        --nl-border: var(--border-color, #e2e8f0);
        display: grid;
        grid-template-columns: minmax(0, 1.05fr) minmax(320px, 0.95fr);
        overflow: hidden;
        margin: 0 0 2rem;
        border: 1px solid color-mix(in srgb, var(--nl-primary) 18%, var(--nl-border));
        border-radius: 1.25rem;
        background: var(--nl-surface);
        box-shadow: 0 18px 50px rgba(15, 23, 42, 0.08);
    }

    .nl-signup__content {
        position: relative;
        padding: clamp(1.5rem, 4vw, 2.75rem);
        color: #fff;
        background:
            radial-gradient(circle at 85% 15%, rgba(255, 255, 255, 0.22), transparent 30%),
            linear-gradient(135deg, var(--nl-primary), var(--nl-primary-dark));
    }

    .nl-signup__content::after {
        content: '';
        position: absolute;
        right: -4rem;
        bottom: -5rem;
        width: 13rem;
        height: 13rem;
        border: 1px solid rgba(255, 255, 255, 0.18);
        border-radius: 50%;
    }

    .nl-signup__eyebrow,
    .nl-modal__badge {
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        width: fit-content;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 800;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .nl-signup__eyebrow {
        padding: 0.45rem 0.7rem;
        background: rgba(255, 255, 255, 0.14);
    }

    .nl-signup__eyebrow-icon,
    .nl-signup__input-icon,
    .nl-modal__icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .nl-signup__eyebrow-icon svg {
        width: 1rem;
        height: 1rem;
    }

    .nl-signup__title {
        position: relative;
        z-index: 1;
        max-width: 14ch;
        margin: 1.2rem 0 0.7rem;
        font-size: clamp(1.75rem, 4vw, 2.65rem);
        line-height: 1.08;
        letter-spacing: -0.035em;
        color: #fff;
    }

    .nl-signup__subtitle {
        position: relative;
        z-index: 1;
        max-width: 52ch;
        margin: 0;
        color: rgba(255, 255, 255, 0.86);
        line-height: 1.65;
    }

    .nl-signup__benefits {
        position: relative;
        z-index: 1;
        display: grid;
        gap: 0.55rem;
        margin: 1.35rem 0 0;
        padding: 0;
        list-style: none;
        font-size: 0.875rem;
    }

    .nl-signup__benefits li {
        display: flex;
        gap: 0.55rem;
        align-items: center;
    }

    .nl-signup__benefits li::before {
        content: '✓';
        display: grid;
        place-items: center;
        width: 1.15rem;
        height: 1.15rem;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.18);
        font-size: 0.7rem;
        font-weight: 900;
    }

    .nl-signup__preview {
        position: relative;
        z-index: 1;
        display: inline-flex;
        align-items: center;
        gap: 0.45rem;
        margin-top: 1.4rem;
        padding: 0;
        border: 0;
        color: #fff;
        background: transparent;
        font: inherit;
        font-size: 0.875rem;
        font-weight: 800;
        cursor: pointer;
    }

    .nl-signup__form-panel {
        display: flex;
        align-items: center;
        padding: clamp(1.5rem, 4vw, 2.5rem);
        background: var(--nl-surface);
    }

    .nl-signup__form,
    .nl-signup__success {
        width: 100%;
    }

    .nl-signup__field label,
    .nl-modal__form > label {
        display: block;
        margin: 0 0 0.45rem;
        color: var(--nl-text);
        font-size: 0.875rem;
        font-weight: 750;
    }

    .nl-signup__input-wrap {
        position: relative;
    }

    .nl-signup__input-icon {
        position: absolute;
        top: 50%;
        left: 0.9rem;
        color: var(--nl-muted);
        transform: translateY(-50%);
        pointer-events: none;
    }

    .nl-signup__input-icon svg {
        width: 1.1rem;
        height: 1.1rem;
    }

    .nl-signup input[type='email'],
    .nl-modal input[type='email'] {
        width: 100%;
        min-height: 3rem;
        border: 1.5px solid var(--nl-border);
        border-radius: 0.75rem;
        background: #fff;
        color: var(--nl-text);
        font: inherit;
        outline: none;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }

    .nl-signup input[type='email'] {
        padding: 0.75rem 0.9rem 0.75rem 2.75rem;
    }

    .nl-modal input[type='email'] {
        padding: 0.75rem 0.9rem;
    }

    .nl-signup input[type='email']:focus,
    .nl-modal input[type='email']:focus {
        border-color: var(--nl-primary);
        box-shadow: 0 0 0 4px color-mix(in srgb, var(--nl-primary) 14%, transparent);
    }

    .nl-signup__consents,
    .nl-modal__consents {
        display: grid;
        gap: 0.7rem;
        margin: 1rem 0;
        padding: 0;
        border: 0;
    }

    .nl-check {
        position: relative;
        display: grid;
        grid-template-columns: 1.2rem 1fr;
        gap: 0.65rem;
        align-items: start;
        color: var(--nl-muted);
        font-size: 0.79rem;
        line-height: 1.45;
        cursor: pointer;
    }

    .nl-check input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }

    .nl-check__box {
        display: grid;
        place-items: center;
        width: 1.2rem;
        height: 1.2rem;
        margin-top: 0.05rem;
        border: 1.5px solid var(--nl-border);
        border-radius: 0.35rem;
        background: #fff;
        transition: 0.15s ease;
    }

    .nl-check input:checked + .nl-check__box {
        border-color: var(--nl-primary);
        background: var(--nl-primary);
    }

    .nl-check input:checked + .nl-check__box::after {
        content: '✓';
        color: #fff;
        font-size: 0.75rem;
        font-weight: 900;
    }

    .nl-check input:focus-visible + .nl-check__box {
        outline: 3px solid color-mix(in srgb, var(--nl-primary) 25%, transparent);
        outline-offset: 2px;
    }

    .nl-signup__submit,
    .nl-modal__submit {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        width: 100%;
        min-height: 3rem;
        border: 0;
        border-radius: 0.75rem;
        background: var(--nl-primary);
        color: #fff;
        font: inherit;
        font-weight: 800;
        cursor: pointer;
        transition: transform 0.15s ease, filter 0.15s ease;
    }

    .nl-signup__submit:hover,
    .nl-modal__submit:hover {
        filter: brightness(0.95);
        transform: translateY(-1px);
    }

    .nl-signup__submit:disabled,
    .nl-modal__submit:disabled {
        cursor: wait;
        opacity: 0.7;
        transform: none;
    }

    .nl-signup__privacy {
        margin: 0.75rem 0 0;
        color: var(--nl-muted);
        font-size: 0.72rem;
        line-height: 1.5;
        text-align: center;
    }

    .nl-signup__message {
        margin: 0.8rem 0 0;
        font-size: 0.8rem;
        line-height: 1.4;
    }

    .nl-signup__message--error {
        color: #b91c1c;
    }

    .nl-signup__success,
    .nl-modal__success {
        align-items: center;
        justify-content: center;
        gap: 0.9rem;
        color: var(--nl-text);
        text-align: left;
    }

    .nl-signup__success:not([hidden]),
    .nl-modal__success:not([hidden]) {
        display: flex;
    }

    .nl-signup__success-icon,
    .nl-modal__success-icon {
        display: grid;
        place-items: center;
        flex: 0 0 auto;
        width: 3rem;
        height: 3rem;
        border-radius: 50%;
        background: #dcfce7;
        color: #15803d;
        font-size: 1.25rem;
        font-weight: 900;
    }

    .nl-signup__success p,
    .nl-modal__success p {
        margin: 0.15rem 0 0;
        color: var(--nl-muted);
        font-size: 0.85rem;
    }

    .nl-modal-backdrop {
        position: fixed;
        inset: 0;
        z-index: 9000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        background: rgba(15, 23, 42, 0.58);
        backdrop-filter: blur(6px);
        animation: nlFadeIn 0.18s ease;
    }

    .nl-modal-backdrop[hidden],
    [hidden] {
        display: none !important;
    }

    .nl-modal {
        --nl-primary: var(--primary-color, #2563eb);
        --nl-text: var(--text-primary, #111827);
        --nl-muted: var(--text-secondary, #64748b);
        --nl-border: var(--border-color, #e2e8f0);
        position: relative;
        width: min(100%, 30rem);
        padding: 2rem;
        border-radius: 1.25rem;
        background: #fff;
        box-shadow: 0 24px 80px rgba(15, 23, 42, 0.25);
        animation: nlSlideUp 0.22s ease;
    }

    .nl-modal__close {
        position: absolute;
        top: 0.9rem;
        right: 0.9rem;
        display: grid;
        place-items: center;
        width: 2.25rem;
        height: 2.25rem;
        border: 0;
        border-radius: 50%;
        background: #f1f5f9;
        color: var(--nl-muted);
        cursor: pointer;
    }

    .nl-modal__close svg {
        width: 1rem;
        height: 1rem;
    }

    .nl-modal__badge {
        padding: 0.4rem 0.65rem;
        color: var(--nl-primary);
        background: color-mix(in srgb, var(--nl-primary) 10%, #fff);
    }

    .nl-modal__icon {
        width: 3.25rem;
        height: 3.25rem;
        margin: 1.1rem 0 0.9rem;
        border-radius: 1rem;
        background: color-mix(in srgb, var(--nl-primary) 12%, #fff);
        color: var(--nl-primary);
    }

    .nl-modal__icon svg {
        width: 1.55rem;
        height: 1.55rem;
    }

    .nl-modal__title {
        margin: 0;
        color: var(--nl-text);
        font-size: 1.55rem;
        line-height: 1.2;
        letter-spacing: -0.025em;
    }

    .nl-modal__desc {
        margin: 0.6rem 0 1.25rem;
        color: var(--nl-muted);
        line-height: 1.6;
    }

    .nl-check--modal {
        text-align: left;
    }

    @keyframes nlFadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    @keyframes nlSlideUp {
        from { opacity: 0; transform: translateY(14px) scale(0.985); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }

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

    @media (max-width: 760px) {
        .nl-signup {
            grid-template-columns: 1fr;
        }

        .nl-signup__title {
            max-width: none;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .nl-modal,
        .nl-modal-backdrop,
        .nl-signup__submit,
        .nl-modal__submit {
            animation: none;
            transition: none;
        }
    }
</style>

<script>
    (function () {
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

        function setSubmitting(form, submitting) {
            var button = form.querySelector('button[type="submit"]');
            if (!button) {
                return;
            }

            button.disabled = submitting;
            button.setAttribute('aria-busy', submitting ? 'true' : 'false');
        }

        function syncEmail(siteId, source, value) {
            var target = document.getElementById(
                source === 'inline' ? 'nl-modal-email-' + siteId : 'nl-inline-email-' + siteId
            );

            if (target && !target.value) {
                target.value = value;
            }
        }

        window.nlHandleHover = function (teaser) {
            if (!teaser || isDismissed(teaser.dataset.storageKey)) {
                return;
            }

            nlOpenModal(teaser.dataset.siteId);
        };

        window.nlOpenModal = function (siteId) {
            var modal = document.getElementById('nl-modal-' + siteId);
            if (!modal) {
                return;
            }

            modal.removeAttribute('hidden');
            var input = modal.querySelector('input[type="email"]');
            if (input) {
                window.setTimeout(function () {
                    input.focus();
                }, 40);
            }
        };

        window.nlCloseModal = function (siteId) {
            var modal = document.getElementById('nl-modal-' + siteId);
            var teaser = document.getElementById('nl-teaser-' + siteId);

            if (modal) {
                modal.setAttribute('hidden', '');
            }

            if (teaser) {
                markDismissed(teaser.dataset.storageKey);
            }
        };

        window.nlBackdropClick = function (event, siteId) {
            if (event.target === event.currentTarget) {
                nlCloseModal(siteId);
            }
        };

        window.nlHandleSubmit = function (event, siteId, siteSlug, source) {
            event.preventDefault();

            var form = event.currentTarget;
            var success = document.getElementById('nl-' + source + '-success-' + siteId);
            var error = document.getElementById('nl-' + source + '-error-' + siteId);
            var email = form.querySelector('input[name="email"]');
            var newsletterConsent = form.querySelector('input[name="newsletter_consent"]');
            var offersConsent = form.querySelector('input[name="offers_consent"]');

            if (!form.reportValidity()) {
                return;
            }

            error.setAttribute('hidden', '');
            error.textContent = '';
            setSubmitting(form, true);

            fetch('/' + siteSlug + '/newsletter/subscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    email: email.value.trim(),
                    site_id: siteId,
                    newsletter_consent: newsletterConsent.checked,
                    offers_consent: offersConsent.checked
                })
            })
                .then(function (response) {
                    if (!response.ok) {
                        return response.json()
                            .catch(function () { return {}; })
                            .then(function (body) {
                                throw new Error(body.message || 'We could not complete your subscription. Please try again.');
                            });
                    }

                    return response.json();
                })
                .then(function () {
                    syncEmail(siteId, source, email.value.trim());
                    form.setAttribute('hidden', '');
                    success.removeAttribute('hidden');

                    var teaser = document.getElementById('nl-teaser-' + siteId);
                    if (teaser) {
                        markDismissed(teaser.dataset.storageKey);
                    }
                })
                .catch(function (requestError) {
                    error.textContent = requestError.message || 'We could not complete your subscription. Please try again.';
                    error.removeAttribute('hidden');
                })
                .finally(function () {
                    setSubmitting(form, false);
                });
        };

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') {
                return;
            }

            document.querySelectorAll('.nl-modal-backdrop:not([hidden])').forEach(function (backdrop) {
                nlCloseModal(backdrop.id.replace('nl-modal-', ''));
            });
        });
    })();
</script>
