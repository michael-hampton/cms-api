<?php
/**
 * Shared saved-payment-methods panel.
 *
 * Included by BOTH:
 *   - src/views/subscriptions/account/billing.php (PressStack, cross-site)
 *   - src/views/member/subscriptions/payment-methods.php (site-scoped member area)
 *
 * This partial only renders markup - all behaviour lives in the shared
 * public/js/saved-payment-methods.js (class/state based), configured via
 * the small inline config block the including view must render before
 * loading that script. See both including views for the expected
 * `window.SavedPaymentMethodsConfig` shape.
 *
 * Expected variables (set by the including view before include):
 * @var string $pmHeadingId          Unique id for the panel heading (for aria-labelledby, avoids collisions if ever embedded twice)
 * @var bool   $pmShowHeader         Whether to render the built-in heading + "Add" button (billing.php renders its own tab header, so it passes false)
 */
$pmHeadingId ??= 'saved-payment-methods-heading';
$pmShowHeader ??= true;
?>
<div class="spm-panel" data-spm-panel>
    <?php if ($pmShowHeader): ?>
    <div class="spm-panel-header">
        <div>
            <h1 class="spm-panel-title" id="<?= htmlspecialchars($pmHeadingId) ?>">Payment Methods</h1>
            <p class="spm-panel-subtitle">Manage the saved cards used for your subscriptions.</p>
        </div>
        <button type="button" class="spm-btn spm-btn-primary" data-spm-open-add>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Add new payment method
        </button>
    </div>
    <?php endif; ?>

    <div data-spm-warnings></div>

    <div data-spm-list>
        <div class="spm-skeleton">
            <div class="spm-skeleton-row"></div>
            <div class="spm-skeleton-row"></div>
        </div>
    </div>
</div>

<!-- Add Card Modal -->
<div class="spm-modal-overlay" data-spm-modal="add" role="dialog" aria-modal="true" aria-labelledby="spm-add-title">
    <div class="spm-modal">
        <div class="spm-modal-header">
            <h2 class="spm-modal-title" id="spm-add-title">Add Payment Method</h2>
            <button type="button" class="spm-close-btn" data-spm-close="add" aria-label="Close">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="spm-modal-body">
            <div class="spm-inline-error" data-spm-error="add" role="alert"></div>
            <div class="spm-form-group">
                <label class="spm-form-label">Card information</label>
                <div data-spm-card-element="add" class="spm-stripe-element"></div>
            </div>
            <div class="spm-form-group">
                <label class="spm-checkbox-label">
                    <input type="checkbox" data-spm-set-default="add" checked>
                    <span>Set as default payment method</span>
                </label>
            </div>
            <div class="spm-security-note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                <span>Card details are captured directly by Stripe and never touch our servers.</span>
            </div>
        </div>
        <div class="spm-modal-footer">
            <button type="button" class="spm-btn spm-btn-secondary" data-spm-close="add">Cancel</button>
            <button type="button" class="spm-btn spm-btn-primary" data-spm-submit="add">Add Payment Method</button>
        </div>
    </div>
</div>

<!-- Update (replace) Card Modal -->
<div class="spm-modal-overlay" data-spm-modal="update" role="dialog" aria-modal="true" aria-labelledby="spm-update-title">
    <div class="spm-modal">
        <div class="spm-modal-header">
            <h2 class="spm-modal-title" id="spm-update-title">Update Payment Method</h2>
            <button type="button" class="spm-close-btn" data-spm-close="update" aria-label="Close">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="spm-modal-body">
            <p class="spm-modal-lead">
                Replacing <strong data-spm-update-current-card></strong>. Add a new card below - this does not
                edit the existing card in place.
            </p>
            <div class="spm-inline-error" data-spm-error="update" role="alert"></div>
            <div class="spm-form-group">
                <label class="spm-form-label">New card information</label>
                <div data-spm-card-element="update" class="spm-stripe-element"></div>
            </div>
            <div class="spm-form-group">
                <label class="spm-checkbox-label">
                    <input type="checkbox" data-spm-set-default="update">
                    <span>Set as default payment method</span>
                </label>
            </div>
        </div>
        <div class="spm-modal-footer">
            <button type="button" class="spm-btn spm-btn-secondary" data-spm-close="update">Cancel</button>
            <button type="button" class="spm-btn spm-btn-primary" data-spm-submit="update">Update Card</button>
        </div>
    </div>
</div>

<!-- Remove Card Modal -->
<div class="spm-modal-overlay" data-spm-modal="remove" role="dialog" aria-modal="true" aria-labelledby="spm-remove-title">
    <div class="spm-modal">
        <div class="spm-modal-header">
            <h2 class="spm-modal-title" id="spm-remove-title">Remove Card</h2>
            <button type="button" class="spm-close-btn" data-spm-close="remove" aria-label="Close">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="spm-modal-body">
            <p class="spm-modal-lead">Are you sure you want to remove this card? This action cannot be undone.</p>
            <div class="spm-inline-error" data-spm-error="remove" role="alert"></div>
        </div>
        <div class="spm-modal-footer">
            <button type="button" class="spm-btn spm-btn-secondary" data-spm-close="remove">Cancel</button>
            <button type="button" class="spm-btn spm-btn-danger" data-spm-submit="remove">Remove Card</button>
        </div>
    </div>
</div>

<style>
    .spm-panel-header { display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 2rem; flex-wrap: wrap; }
    .spm-panel-title { font-size: 2rem; font-weight: 700; margin: 0; }
    .spm-panel-subtitle { color: var(--spm-text-secondary, #6b7280); margin-top: 0.5rem; }

    .spm-btn { padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; font-weight: 600; font-size: 0.9375rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.2s; font-family: inherit; }
    .spm-btn:disabled { opacity: 0.6; cursor: not-allowed; }
    .spm-btn-primary { background: var(--spm-primary, #667eea); color: #fff; }
    .spm-btn-primary:hover:not(:disabled) { background: var(--spm-primary-dark, #5568d3); }
    .spm-btn-secondary { background: #fff; color: var(--spm-text, #1f2937); border: 2px solid var(--spm-border, #e5e7eb); }
    .spm-btn-secondary:hover:not(:disabled) { border-color: var(--spm-primary, #667eea); color: var(--spm-primary, #667eea); }
    .spm-btn-danger { background: var(--spm-danger, #ef4444); color: #fff; }
    .spm-btn-danger:hover:not(:disabled) { background: #dc2626; }
    .spm-btn-sm { padding: 0.5rem 1rem; font-size: 0.8125rem; }

    .spm-alert { padding: 1rem 1.25rem; border-radius: 0.5rem; margin-bottom: 1rem; display: flex; align-items: flex-start; gap: 0.75rem; font-size: 0.875rem; }
    .spm-alert-warning { background: #fef3c7; color: #92400e; border-left: 4px solid var(--spm-warning, #f59e0b); }
    .spm-alert-error { background: #fee2e2; color: #991b1b; border-left: 4px solid var(--spm-danger, #ef4444); }
    .spm-alert svg { width: 20px; height: 20px; flex-shrink: 0; margin-top: 1px; }

    .spm-grid { display: grid; gap: 1.25rem; margin-bottom: 1.5rem; }
    .spm-card { background: #fff; border-radius: 1rem; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,.1); border: 2px solid transparent; display: flex; justify-content: space-between; align-items: center; gap: 1.5rem; flex-wrap: wrap; transition: all .2s; }
    .spm-card:hover { box-shadow: 0 4px 6px -1px rgba(0,0,0,.1); }
    .spm-card.is-default { border-color: var(--spm-primary, #667eea); }
    .spm-card-main { display: flex; align-items: center; gap: 1rem; flex: 1; min-width: 240px; }
    .spm-card-logo { width: 52px; height: 36px; border-radius: 6px; background: var(--spm-bg, #f5f7fa); border: 1px solid var(--spm-border, #e5e7eb); display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
    .spm-card-info { flex: 1; min-width: 0; }
    .spm-card-brand { font-size: 1.0625rem; font-weight: 600; text-transform: capitalize; }
    .spm-card-details { color: var(--spm-text-secondary, #6b7280); font-size: 0.875rem; margin-top: 0.125rem; }
    .spm-badge-row { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.5rem; }
    .spm-badge { display: inline-flex; align-items: center; gap: 4px; padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
    .spm-badge-default { background: rgba(102,126,234,.12); color: var(--spm-primary, #667eea); }
    .spm-badge-active { background: rgba(16,185,129,.12); color: #067a56; }
    .spm-badge-expiring { background: rgba(245,158,11,.15); color: #92400e; }
    .spm-badge-expired { background: rgba(239,68,68,.12); color: var(--spm-danger, #ef4444); }
    .spm-badge-inuse { background: var(--spm-bg, #f5f7fa); color: var(--spm-text-secondary, #6b7280); }
    .spm-card-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }

    .spm-empty { text-align: center; padding: 4rem 2rem; background: #fff; border-radius: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
    .spm-empty-icon { font-size: 3.5rem; margin-bottom: 1rem; opacity: .5; }
    .spm-empty-title { font-size: 1.25rem; font-weight: 700; margin-bottom: .5rem; }
    .spm-empty-sub { color: var(--spm-text-secondary, #6b7280); margin-bottom: 1.5rem; }

    .spm-skeleton { display: flex; flex-direction: column; gap: 1.25rem; }
    .spm-skeleton-row { height: 92px; border-radius: 1rem; background: linear-gradient(90deg,#eef0f4 25%,#f7f8fa 50%,#eef0f4 75%); background-size: 200% 100%; animation: spm-shimmer 1.4s infinite; }
    @keyframes spm-shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

    .spm-inline-error { color: var(--spm-danger, #ef4444); background: #fee2e2; border-radius: .5rem; padding: .6rem .85rem; font-size: .8125rem; margin-bottom: 1rem; display: none; }
    .spm-form-group { margin-bottom: 1.5rem; }
    .spm-form-label { display: block; font-weight: 500; margin-bottom: .5rem; }
    .spm-stripe-element { padding: 1rem; border: 1px solid var(--spm-border, #e5e7eb); border-radius: .5rem; background: #fff; }
    .spm-checkbox-label { display: flex; align-items: center; gap: .5rem; cursor: pointer; font-size: .9375rem; }
    .spm-checkbox-label input { width: 16px; height: 16px; accent-color: var(--spm-primary, #667eea); }
    .spm-security-note { display: flex; align-items: center; gap: .5rem; font-size: .75rem; color: var(--spm-text-secondary, #6b7280); margin-top: .75rem; }
    .spm-security-note svg { width: 14px; height: 14px; flex-shrink: 0; }
    .spm-modal-lead { color: var(--spm-text-secondary, #6b7280); font-size: .9375rem; line-height: 1.6; margin: 0 0 1.5rem; }

    .spm-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.5); display: none; align-items: center; justify-content: center; z-index: 1000; padding: 1rem; }
    .spm-modal-overlay.show { display: flex; }
    .spm-modal { background: #fff; border-radius: 1rem; width: 100%; max-width: 500px; max-height: 90vh; overflow-y: auto; box-shadow: 0 10px 15px -3px rgba(0,0,0,.1); }
    .spm-modal-header { padding: 1.5rem; border-bottom: 1px solid var(--spm-border, #e5e7eb); display: flex; justify-content: space-between; align-items: center; }
    .spm-modal-title { font-size: 1.375rem; font-weight: 700; margin: 0; }
    .spm-modal-body { padding: 1.5rem; }
    .spm-modal-footer { padding: 1.5rem; border-top: 1px solid var(--spm-border, #e5e7eb); display: flex; gap: 1rem; justify-content: flex-end; }
    .spm-close-btn { background: none; border: none; cursor: pointer; padding: .5rem; color: var(--spm-text-secondary, #6b7280); font-size: 1.5rem; line-height: 1; }

    @media (max-width: 768px) {
        .spm-panel-header { flex-direction: column; align-items: flex-start; gap: 1rem; }
        .spm-card { flex-direction: column; align-items: flex-start; }
        .spm-card-actions { width: 100%; }

        /* Full-screen scrollable add/update flow on mobile */
        .spm-modal-overlay { padding: 0; align-items: stretch; }
        .spm-modal { max-width: 100%; max-height: 100%; height: 100%; border-radius: 0; display: flex; flex-direction: column; }
        .spm-modal-body { flex: 1; overflow-y: auto; }
    }
</style>
