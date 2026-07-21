<?php
/**
 * Newsletter signup island.
 *
 * Subscribed state comes top-down via NewsletterWidgetState — never hard-coded.
 *
 * Expected: $newsletterState (NewsletterWidgetState|null), $siteId, $siteSlug
 */

use App\DTO\PublicContent\NewsletterWidgetState;

$state = $newsletterState ?? null;
if (!$state instanceof NewsletterWidgetState) {
    $state = new NewsletterWidgetState(
        authenticated: false,
        subscribed: false,
        loginUrl: '/' . rawurlencode((string) ($siteSlug ?? '')) . '/member/login',
        newsletterName: $newsletterName ?? 'Stay informed',
        newsletterDescription: $newsletterDesc ?? 'Get our best stories, useful updates and carefully selected offers delivered to your inbox.',
    );
}

$siteId = (int) ($siteId ?? \App\Framework\Support\SiteContext::getId());
$siteSlug = (string) ($siteSlug ?? \App\Framework\Support\SiteContext::slug());
$newsletterName = $state->newsletterName ?? 'Stay informed';
$newsletterDesc = $state->newsletterDescription ?? 'Get our best stories, useful updates and carefully selected offers delivered to your inbox.';
$storageKey = 'newsletter_dismissed_' . $siteId;

$escapedSiteSlug = htmlspecialchars($siteSlug, ENT_QUOTES, 'UTF-8');
$escapedStorageKey = htmlspecialchars($storageKey, ENT_QUOTES, 'UTF-8');
$escapedName = htmlspecialchars($newsletterName, ENT_QUOTES, 'UTF-8');
$escapedDescription = htmlspecialchars($newsletterDesc, ENT_QUOTES, 'UTF-8');
$escapedLoginUrl = htmlspecialchars((string) ($state->loginUrl ?? ''), ENT_QUOTES, 'UTF-8');
$escapedManageUrl = htmlspecialchars((string) ($state->manageUrl ?? ''), ENT_QUOTES, 'UTF-8');
?>

<section class="nl-signup"
         id="nl-teaser-<?= $siteId ?>"
         data-site-id="<?= $siteId ?>"
         data-site-slug="<?= $escapedSiteSlug ?>"
         data-storage-key="<?= $escapedStorageKey ?>"
         data-authenticated="<?= $state->authenticated ? 'true' : 'false' ?>"
         data-subscribed="<?= $state->subscribed ? 'true' : 'false' ?>"
         data-login-url="<?= $escapedLoginUrl ?>"
         aria-labelledby="nl-title-<?= $siteId ?>">
    <div class="nl-signup__content">
        <div class="nl-signup__eyebrow">
            <span class="nl-signup__eyebrow-icon" aria-hidden="true">✉</span>
            Free newsletter
        </div>

        <h2 class="nl-signup__title" id="nl-title-<?= $siteId ?>"><?= $escapedName ?></h2>
        <p class="nl-signup__subtitle"><?= $escapedDescription ?></p>

        <?php if ($state->subscribed): ?>
            <p class="nl-signup__member-note">You’re already subscribed<?= $escapedManageUrl !== '' ? ' — <a href="' . $escapedManageUrl . '">manage preferences</a>' : '' ?>.</p>
        <?php elseif (!$state->authenticated): ?>
            <p class="nl-signup__member-note">
                <a href="<?= $escapedLoginUrl ?>" data-nl-login>Sign in as a member</a> to sync your subscription status.
            </p>
            <button type="button" class="nl-signup__preview" data-nl-open>Preview signup <span aria-hidden="true">→</span></button>
        <?php else: ?>
            <button type="button" class="nl-signup__preview" data-nl-open>Preview signup <span aria-hidden="true">→</span></button>
        <?php endif; ?>
    </div>

    <div class="nl-signup__form-panel">
        <?php if ($state->subscribed): ?>
            <div class="nl-signup__success" role="status">
                <span class="nl-signup__success-icon" aria-hidden="true">✓</span>
                <div>
                    <strong>You’re subscribed</strong>
                    <p>Thanks for being part of the list.</p>
                </div>
            </div>
        <?php else: ?>
            <form class="nl-signup__form"
                  id="nl-inline-form-<?= $siteId ?>"
                  data-nl-form="inline">
                <div class="nl-signup__field">
                    <label for="nl-inline-email-<?= $siteId ?>">Email address</label>
                    <div class="nl-signup__input-wrap">
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

                <p class="nl-signup__privacy">By subscribing, you confirm that you have read our privacy information.</p>
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
        <?php endif; ?>
    </div>
</section>

<?php if (!$state->subscribed): ?>
<div class="nl-modal-backdrop"
     id="nl-modal-<?= $siteId ?>"
     role="dialog"
     aria-modal="true"
     aria-labelledby="nl-modal-title-<?= $siteId ?>"
     hidden
     data-nl-modal>
    <div class="nl-modal">
        <button type="button" class="nl-modal__close" data-nl-close aria-label="Close newsletter signup">×</button>
        <div class="nl-modal__badge">Free newsletter</div>
        <h2 class="nl-modal__title" id="nl-modal-title-<?= $siteId ?>"><?= $escapedName ?></h2>
        <p class="nl-modal__desc"><?= $escapedDescription ?></p>

        <form class="nl-modal__form" id="nl-modal-form-<?= $siteId ?>" data-nl-form="modal">
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
<?php endif; ?>

<style>
    .nl-signup, .nl-signup * { box-sizing: border-box; }
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
        padding: clamp(1.5rem, 4vw, 2.75rem);
        color: #fff;
        background: linear-gradient(135deg, var(--nl-primary), var(--nl-primary-dark));
    }
    .nl-signup__title { margin: 1.2rem 0 0.7rem; font-size: clamp(1.75rem, 4vw, 2.65rem); line-height: 1.08; color: #fff; }
    .nl-signup__subtitle, .nl-signup__member-note { margin: 0; color: rgba(255,255,255,.86); line-height: 1.65; }
    .nl-signup__member-note { margin-top: 1rem; }
    .nl-signup__member-note a { color: #fff; font-weight: 700; }
    .nl-signup__preview {
        margin-top: 1.4rem; padding: 0; border: 0; color: #fff; background: transparent;
        font: inherit; font-weight: 800; cursor: pointer;
    }
    .nl-signup__form-panel { display: flex; align-items: center; padding: clamp(1.5rem, 4vw, 2.5rem); }
    .nl-signup__form, .nl-signup__success { width: 100%; }
    .nl-signup input[type='email'], .nl-modal input[type='email'] {
        width: 100%; min-height: 3rem; border: 1.5px solid var(--nl-border); border-radius: .75rem;
        padding: .75rem .9rem; font: inherit;
    }
    .nl-signup__consents, .nl-modal__consents { display: grid; gap: .7rem; margin: 1rem 0; padding: 0; border: 0; }
    .nl-check { display: grid; grid-template-columns: 1.2rem 1fr; gap: .65rem; color: var(--nl-muted); font-size: .79rem; }
    .nl-check input { position: absolute; opacity: 0; }
    .nl-check__box { width: 1.2rem; height: 1.2rem; border: 1.5px solid var(--nl-border); border-radius: .35rem; background: #fff; }
    .nl-check input:checked + .nl-check__box { border-color: var(--nl-primary); background: var(--nl-primary); }
    .nl-signup__submit, .nl-modal__submit {
        display: flex; align-items: center; justify-content: center; gap: .5rem; width: 100%;
        min-height: 3rem; border: 0; border-radius: .75rem; background: var(--nl-primary); color: #fff;
        font: inherit; font-weight: 800; cursor: pointer;
    }
    .nl-signup__privacy { margin: .75rem 0 0; color: var(--nl-muted); font-size: .72rem; text-align: center; }
    .nl-signup__message--error { color: #b91c1c; }
    .nl-signup__success:not([hidden]), .nl-modal__success:not([hidden]) { display: flex; gap: .9rem; align-items: center; }
    .nl-signup__success-icon, .nl-modal__success-icon {
        display: grid; place-items: center; width: 3rem; height: 3rem; border-radius: 50%;
        background: #dcfce7; color: #15803d; font-weight: 900;
    }
    .nl-modal-backdrop {
        position: fixed; inset: 0; z-index: 9000; display: flex; align-items: center; justify-content: center;
        padding: 1rem; background: rgba(15, 23, 42, .58);
    }
    .nl-modal-backdrop[hidden], [hidden] { display: none !important; }
    .nl-modal { position: relative; width: min(100%, 30rem); padding: 2rem; border-radius: 1.25rem; background: #fff; }
    .nl-modal__close { position: absolute; top: .9rem; right: .9rem; border: 0; background: #f1f5f9; border-radius: 50%; width: 2.25rem; height: 2.25rem; cursor: pointer; }
    .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); border: 0; }
    @media (max-width: 760px) { .nl-signup { grid-template-columns: 1fr; } }
</style>
