<?php
/**
 * View: subscriptions/account/billing.php
 *
 * Shared PressStack account billing view for:
 * - Payment methods
 * - Manage Addresses
 */

$billingSection = $billing_section ?? 'payment_methods';
$countries = $countries ?? ['GB' => 'United Kingdom'];
$pageTitleBySection = [
    'payment_methods' => 'Payment methods',
    'addresses' => 'Manage Addresses',
];
$pageSubBySection = [
    'payment_methods' => 'Manage saved cards for your PressStack subscription payments.',
    'addresses' => 'Create, edit, delete and choose default addresses for your subscriptions.',
];
$page_title = $page_title ?? ($pageTitleBySection[$billingSection] ?? 'Billing');
$page_subtitle = $pageSubBySection[$billingSection] ?? 'Manage your payment methods and billing details.';
?>
<script src="https://js.stripe.com/v3/"></script>

<style>
    .billing-grid { display: flex; flex-direction: column; gap: 20px; }
    .pm-list { display: flex; flex-direction: column; gap: 10px; }
    .pm-card { display: flex; align-items: center; gap: 16px; padding: 15px 18px; border: 1.5px solid var(--border); border-radius: var(--radius-sm); background: var(--white); transition: var(--transition); }
    .pm-card.is-default { border-color: var(--ink); box-shadow: var(--shadow-xs); }
    .pm-card:hover { box-shadow: var(--shadow-sm); }
    .pm-card__network { width: 50px; height: 32px; border-radius: 5px; background: var(--paper-dark); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0; }
    .pm-card__info { flex: 1; min-width: 0; }
    .pm-card__number { font-size: 14px; font-weight: 600; color: var(--ink); margin-bottom: 2px; }
    .pm-card__expiry { font-size: 12px; color: var(--ink-muted); }
    .pm-card__default-badge, .address-card__default-badge { font-size: 9.5px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; background: var(--gold-light); color: var(--gold); padding: 3px 9px; border-radius: 100px; flex-shrink: 0; border: 1px solid rgba(184, 134, 11, .2); }
    .pm-card__actions, .address-card__actions { display: flex; gap: 6px; flex-shrink: 0; flex-wrap: wrap; }
    .add-card-btn { display: flex; align-items: center; justify-content: center; gap: 10px; padding: 15px 18px; border: 1.5px dashed var(--border); border-radius: var(--radius-sm); background: none; cursor: pointer; font-family: var(--font-body); font-size: 14px; color: var(--ink-muted); transition: var(--transition); width: 100%; }
    .add-card-btn:hover { border-color: var(--ink); color: var(--ink); background: var(--paper); }
    .address-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; }
    .address-card { padding: 16px; border: 1px solid var(--border); border-radius: var(--radius-sm); background: var(--paper-light); position: relative; transition: transform .18s cubic-bezier(.4,0,.2,1), border-color .18s cubic-bezier(.4,0,.2,1), box-shadow .18s cubic-bezier(.4,0,.2,1), background-color .18s cubic-bezier(.4,0,.2,1); }
    .address-card:hover, .address-card:focus-within { transform: translateY(-3px); border-color: var(--gold-mid); background: var(--white); box-shadow: 0 12px 30px rgba(13, 13, 15, .12); }
    .address-card.is-default { border-color: var(--gold); background: var(--white); box-shadow: var(--shadow-xs); }
    .address-card.is-default:hover, .address-card.is-default:focus-within { box-shadow: 0 14px 34px rgba(184, 134, 11, .2); }
    .address-card:hover .address-card__label, .address-card:focus-within .address-card__label { color: var(--gold); }
    .address-card__head { display:flex; justify-content:space-between; gap:12px; margin-bottom:10px; }
    .address-card__label { font-weight:700; color:var(--ink); transition: color .18s cubic-bezier(.4,0,.2,1); }
    .address-card__badge { display: inline-block; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; padding: 2px 6px; border-radius: 4px; margin-top: 5px; background: var(--border); color: var(--ink-soft); }
    .address-card__badge.is-billing { background: rgba(0, 102, 204, 0.1); color: #0066cc; }
    .address-card__badge.is-shipping { background: rgba(0, 153, 76, 0.1); color: #00994c; }
    .address-card__body { font-size:14px; color:var(--ink-soft); line-height:1.7; margin-bottom:14px; }
    .address-card__actions .btn:hover:not(:disabled), .address-card__actions .btn:focus-visible:not(:disabled) { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(13, 13, 15, .12); }
    .address-card__actions .btn--ghost:hover:not(:disabled), .address-card__actions .btn--ghost:focus-visible:not(:disabled) { border-color: var(--ink); background: var(--ink); color: #fff; }
    .address-card__actions .btn--danger:hover:not(:disabled), .address-card__actions .btn--danger:focus-visible:not(:disabled) { border-color: var(--red); background: var(--red); color: #fff; }
    .stripe-field-wrapper, .address-field { border: 1.5px solid var(--border); border-radius: var(--radius-sm); padding: 12px 14px; background: var(--white); transition: var(--transition); width:100%; box-sizing:border-box; font-family:var(--font-body); font-size:14px; }
    .stripe-field-wrapper:focus-within, .address-field:focus { border-color: var(--ink); outline:none; }
    .stripe-field-label, .address-label { font-size: 10.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .09em; color: var(--ink-muted); margin-bottom: 8px; display:block; }
    .address-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .address-form-group { margin-bottom:12px; }
    .security-note { display: flex; align-items: center; gap: 8px; font-size: 12px; color: var(--ink-muted); margin-top: 8px; }
    .security-note svg { width: 14px; height: 14px; flex-shrink: 0; }
    .no-payment-state { text-align: center; padding: 44px 24px; }
    .no-payment-state__icon { font-size: 40px; margin-bottom: 12px; opacity: .4; }
    .no-payment-state__title { font-family: var(--font-display); font-size: 18px; margin-bottom: 6px; }
    .no-payment-state__sub { font-size: 14px; color: var(--ink-muted); margin-bottom: 20px; }
    .pm-skeleton { display: flex; flex-direction: column; gap: 10px; }
    .pm-skeleton__row { height: 64px; border-radius: var(--radius-sm); background: linear-gradient(90deg, var(--paper-dark) 25%, var(--paper) 50%, var(--paper-dark) 75%); background-size: 200% 100%; animation: shimmer 1.4s infinite; }
    .inline-error { color:var(--red); font-size:13px; margin-bottom:12px; display:none; }
    @keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
    @media (max-width:680px) { .address-row { grid-template-columns:1fr; } }

    <?php if ($billingSection === 'payment_methods'): ?>
    .billing-card--addresses { display: none; }
    <?php elseif ($billingSection === 'addresses'): ?>
    .billing-card--payment-methods, .billing-security-note { display: none; }
    <?php endif; ?>
</style>

@include('subscriptions/account/_layout')

<main class="page-content" data-press-stack-billing data-member-id="<?= (int) $member->id ?>" data-member-name="<?= htmlspecialchars((string) ($member->name ?? ''), ENT_QUOTES) ?>" data-member-email="<?= htmlspecialchars((string) ($member->email ?? ''), ENT_QUOTES) ?>" data-stripe-public-key="<?= htmlspecialchars((string) ($_ENV['STRIPE_PUBLIC_KEY'] ?? config('payment.stripe.public_key')), ENT_QUOTES) ?>">
    <div class="page-heading">
        <div class="page-heading__eyebrow">Account</div>
        <h1 class="page-heading__title"><?= htmlspecialchars($page_title) ?></h1>
        <p class="page-heading__sub"><?= htmlspecialchars($page_subtitle) ?></p>
    </div>

    <div class="billing-grid">
        <section class="card billing-card billing-card--payment-methods" aria-labelledby="payment-methods-title">
            <div class="card__header">
                <span class="card__title" id="payment-methods-title">Payment Methods</span>
                <button class="btn btn--ghost btn--sm" id="open-add-card-btn">+ Add card</button>
            </div>
            <div class="card__body" id="payment-methods-body">
                <div class="pm-skeleton"><div class="pm-skeleton__row"></div><div class="pm-skeleton__row"></div></div>
            </div>
        </section>

        <section class="card billing-card billing-card--addresses" aria-labelledby="addresses-title">
            <div class="card__header">
                <span class="card__title" id="addresses-title">Saved Addresses</span>
                <button class="btn btn--ghost btn--sm" id="open-address-modal-btn">+ Add address</button>
            </div>
            <div class="card__body" id="billing-address-body">
                <div class="pm-skeleton"><div class="pm-skeleton__row" style="height:90px"></div></div>
            </div>
        </section>

        <div class="billing-security-note" style="display:flex; align-items:flex-start; gap:12px; padding:15px 18px; background:var(--white); border:1px solid var(--border); border-radius:var(--radius-sm); font-size:13px; color:var(--ink-muted); line-height:1.65; box-shadow:var(--shadow-xs);">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;flex-shrink:0;margin-top:1px;color:var(--green);"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            <span>Your payment information is encrypted and stored securely via Stripe. PressStack never stores your card number directly.</span>
        </div>
    </div>

    <div class="modal-overlay" id="add-card-modal" role="dialog" aria-modal="true" aria-labelledby="add-card-title">
        <div class="modal">
            <div class="modal__header"><h2 class="modal__title" id="add-card-title">Add Payment Method</h2><button class="modal__close" id="close-add-card-btn" aria-label="Close">×</button></div>
            <div class="modal__body">
                <div class="stripe-field-label">Card details</div>
                <div class="stripe-field-wrapper" id="stripe-card-element"></div>
                <div id="card-errors" class="inline-error" role="alert"></div>
                <label style="display:flex; align-items:center; gap:10px; font-size:14px; cursor:pointer; margin-bottom:16px;"><input type="checkbox" id="set-as-default" checked style="accent-color:var(--ink); width:16px; height:16px;">Set as default payment method</label>
                <div class="security-note"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>Secured by Stripe. Your card details are encrypted.</div>
            </div>
            <div class="modal__footer"><button class="btn btn--ghost" id="cancel-add-card-btn">Cancel</button><button class="btn btn--primary" id="submit-add-card-btn">Add Card</button></div>
        </div>
    </div>

    <div class="modal-overlay" id="remove-card-modal" role="dialog" aria-modal="true" aria-labelledby="remove-card-title">
        <div class="modal">
            <div class="modal__header"><h2 class="modal__title" id="remove-card-title">Remove Card</h2><button class="modal__close" id="close-remove-card-btn" aria-label="Close">×</button></div>
            <div class="modal__body"><p style="font-size:14px; color:var(--ink-soft); line-height:1.65;">Are you sure you want to remove this card? This action cannot be undone. Any active subscriptions using this card will need to be updated.</p></div>
            <div class="modal__footer"><button class="btn btn--ghost" id="cancel-remove-card-btn">Cancel</button><button class="btn btn--danger" id="confirm-remove-card-btn">Remove Card</button></div>
        </div>
    </div>

    <div class="modal-overlay" id="address-modal" role="dialog" aria-modal="true" aria-labelledby="address-modal-title">
        <div class="modal">
            <div class="modal__header"><h2 class="modal__title" id="address-modal-title">Add Address</h2><button class="modal__close" id="close-address-modal-btn" aria-label="Close">×</button></div>
            <form id="address-form">
                <div class="modal__body">
                    <input type="hidden" id="address-id" name="id">
                    <input type="hidden" name="member_id" value="<?= (int) $member->id ?>">
                    <div id="address-errors" class="inline-error" role="alert"></div>
                    <div class="address-form-group"><label class="address-label" for="address-label">Label</label><input class="address-field" id="address-label" name="label" placeholder="Home, Work"></div>
                    <div class="address-form-group"><label class="address-label" for="address-type">Type</label><select class="address-field" id="address-type" name="type" required><option value="both">Shipping & Billing</option><option value="shipping">Shipping only</option><option value="billing">Billing only</option></select></div>
                    <div class="address-form-group"><label class="address-label" for="address-line-1">Address line 1</label><input class="address-field" id="address-line-1" name="address_line_1" required></div>
                    <div class="address-form-group"><label class="address-label" for="address-line-2">Address line 2</label><input class="address-field" id="address-line-2" name="address_line_2"></div>
                    <div class="address-row"><div class="address-form-group"><label class="address-label" for="address-city">City</label><input class="address-field" id="address-city" name="city" required></div><div class="address-form-group"><label class="address-label" for="address-state">County / State</label><input class="address-field" id="address-state" name="state"></div></div>
                    <div class="address-row"><div class="address-form-group"><label class="address-label" for="address-postcode">Postcode</label><input class="address-field" id="address-postcode" name="postcode" required></div><div class="address-form-group"><label class="address-label" for="address-country">Country</label><select class="address-field" id="address-country" name="country" required><?php foreach ($countries as $code => $name): ?><option value="<?= htmlspecialchars((string) $code) ?>" <?= (string) $code === 'GB' ? 'selected' : '' ?>><?= htmlspecialchars((string) $name) ?></option><?php endforeach; ?></select></div></div>
                    <label style="display:flex; align-items:center; gap:10px; font-size:14px; cursor:pointer;"><input type="checkbox" id="address-default" name="is_default" value="1" style="accent-color:var(--ink); width:16px; height:16px;">Set as default address</label>
                </div>
                <div class="modal__footer"><button type="button" class="btn btn--ghost" id="cancel-address-modal-btn">Cancel</button><button type="submit" class="btn btn--primary" id="save-address-btn">Save Address</button></div>
            </form>
        </div>
    </div>
</main>

<script src="/public/js/press-stack-account-billing.js" defer></script>
</div>
</body>
</html>
