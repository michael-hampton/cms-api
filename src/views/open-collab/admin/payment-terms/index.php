@section('logic')
<?php
/**
 * Template: open-collab/admin/payment-terms/index.php
 * Variables:
 *   $terms       — PaymentTerms model (may be an unsaved default)
 *   $site        — string
 *   $currentUser — AuthenticatedUser
 */
$delayDays = (int)($terms->payout_delay_days ?? 7);
$minimumPence = (int)($terms->minimum_payout_amount ?? 5000);
$minimumPounds = number_format($minimumPence / 100, 2);
$isDefault = !isset($terms->id) || !$terms->id;
?>
@endsection

@extends('open-collab/layouts/admin')

@section('content')

<div id="status-toast"
     style="position:fixed;bottom:24px;left:50%;transform:translateX(-50%);
            background:var(--navy);color:#fff;padding:9px 20px;border-radius:20px;
            font-size:.8rem;font-weight:500;opacity:0;transition:opacity .3s;
            z-index:300;pointer-events:none;"></div>

<div class="oc-grid-sidebar" style="align-items:start;gap:24px;">

    <!-- Form -->
    <div>
        <div class="oc-card">
            <div class="oc-card__header">
                <span class="oc-card__title">Configure Payout Rules</span>
                <?php if ($isDefault): ?>
                    <span style="font-size:.72rem;background:#fef3c7;color:#92400e;padding:2px 8px;border-radius:10px;font-weight:600;">
                        Using defaults
                    </span>
                <?php else: ?>
                    <span class="oc-badge oc-badge--published">Configured</span>
                <?php endif; ?>
            </div>

            <div class="oc-card__body">

                <?php if ($isDefault): ?>
                    <div class="oc-alert oc-alert--info" style="margin-bottom:20px;">
                        <svg viewBox="0 0 20 20" fill="currentColor" width="14">
                            <path fill-rule="evenodd"
                                  d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                  clip-rule="evenodd"/>
                        </svg>
                        No custom terms saved yet. These are the system defaults. Save to persist custom values.
                    </div>
                <?php endif; ?>

                <div id="form-errors" class="oc-form-errors" style="display:none;margin-bottom:16px;"></div>
                <div id="form-success" class="oc-alert oc-alert--success"
                     style="display:none;margin-bottom:16px;"></div>

                <form id="payment-terms-form" novalidate>

                    <div class="oc-form-group">
                        <label class="oc-label" for="payout-delay">
                            Payout delay (days)
                        </label>
                        <div style="position:relative;max-width:200px;">
                            <input class="oc-input" type="number" id="payout-delay"
                                   name="payout_delay_days"
                                   value="<?= $delayDays ?>"
                                   min="0" max="365" step="1"
                                   style="padding-right:52px;">
                            <span style="position:absolute;right:12px;top:50%;transform:translateY(-50%);
                                         font-size:.78rem;color:var(--slate);pointer-events:none;">days</span>
                        </div>
                        <div class="oc-help">
                            Earnings are held for this many days before becoming eligible for payout.
                            Set to 0 for no delay.
                        </div>
                    </div>

                    <div class="oc-form-group">
                        <label class="oc-label" for="minimum-payout">
                            Minimum payout amount (£)
                        </label>
                        <div style="display:flex;align-items:center;border:1.5px solid var(--border);
                                    border-radius:var(--radius);overflow:hidden;background:#fff;max-width:200px;">
                            <span style="padding:10px 12px;background:var(--slate-pale);
                                         border-right:1px solid var(--border);font-size:.9rem;
                                         color:var(--slate);">£</span>
                            <input type="number" id="minimum-payout" name="minimum_payout_amount"
                                   value="<?= $minimumPounds ?>"
                                   min="0.50" step="0.50"
                                   style="border:none;outline:none;padding:10px 12px;
                                          font-size:.9rem;width:100%;font-family:var(--font-body);">
                        </div>
                        <div class="oc-help">
                            Contributors must have at least this balance before a payout can be requested.
                            Minimum: £0.50.
                        </div>
                    </div>

                    <button type="submit" class="oc-btn oc-btn--amber" id="save-btn">
                        <svg viewBox="0 0 20 20" fill="currentColor" width="15">
                            <path fill-rule="evenodd"
                                  d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                  clip-rule="evenodd"/>
                        </svg>
                        Save payment terms
                    </button>

                </form>
            </div>
        </div>
    </div>

    <!-- Info sidebar -->
    <div style="position:sticky;top:calc(var(--header-h,64px) + 20px);">

        <!-- Current values summary -->
        <div class="oc-card" style="margin-bottom:16px;">
            <div class="oc-card__header">
                <span class="oc-card__title" style="font-size:.9rem;">Current Configuration</span>
            </div>
            <div class="oc-card__body">
                <dl style="display:flex;flex-direction:column;gap:12px;font-size:.875rem;">
                    <div>
                        <dt style="color:var(--slate);font-size:.72rem;font-weight:600;text-transform:uppercase;
                                   letter-spacing:.06em;margin-bottom:2px;">Payout delay
                        </dt>
                        <dd style="color:var(--navy);font-weight:600;" id="summary-delay">
                            <?= $delayDays ?> day<?= $delayDays !== 1 ? 's' : '' ?>
                        </dd>
                    </div>
                    <div>
                        <dt style="color:var(--slate);font-size:.72rem;font-weight:600;text-transform:uppercase;
                                   letter-spacing:.06em;margin-bottom:2px;">Minimum payout
                        </dt>
                        <dd style="color:var(--navy);font-weight:600;" id="summary-minimum">
                            £<?= $minimumPounds ?>
                        </dd>
                    </div>
                    <div>
                        <dt style="color:var(--slate);font-size:.72rem;font-weight:600;text-transform:uppercase;
                                   letter-spacing:.06em;margin-bottom:2px;">Currency
                        </dt>
                        <dd style="color:var(--slate);">GBP (£) — site default</dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- What these settings do -->
        <div class="oc-card">
            <div class="oc-card__header">
                <span class="oc-card__title" style="font-size:.9rem;">How this works</span>
            </div>
            <div class="oc-card__body">
                <div style="font-size:.8rem;color:var(--slate);line-height:1.65;display:flex;flex-direction:column;gap:10px;">
                    <div>
                        <strong style="color:var(--navy);">Payout delay</strong> — After a sale is recorded in the
                        earnings ledger, it won't become eligible for payout until the delay period has passed.
                        This allows time for refunds to be processed.
                    </div>
                    <div>
                        <strong style="color:var(--navy);">Minimum amount</strong> — Contributors can only request
                        (or be auto-scheduled for) a payout once their eligible balance reaches or exceeds this
                        threshold.
                    </div>
                    <div>
                        <strong style="color:var(--navy);">Scheduler</strong> — The automated payout scheduler uses
                        these settings when deciding which contributors to create pending payouts for.
                    </div>
                </div>
                <div style="margin-top:16px;padding-top:12px;border-top:1px solid var(--border);">
                    <a href="/admin/payouts/scheduled" class="oc-btn oc-btn--ghost oc-btn--sm oc-btn--block">
                        View payout schedule →
                    </a>
                </div>
            </div>
        </div>
    </div>

</div>

@endsection

@section('scripts')
<script>
    const SITE = '<?= htmlspecialchars($site ?? '') ?>';

    class PaymentTermsManager {
        #site;
        #token;

        constructor(site, token) {
            this.#site = site;
            this.#token = token;
            this.#bindLivePreview();
            this.#bindSubmit();
        }

        #bindLivePreview() {
            document.getElementById('payout-delay')?.addEventListener('input', function () {
                const v = parseInt(this.value) || 0;
                document.getElementById('summary-delay').textContent = `${v} day${v !== 1 ? 's' : ''}`;
            });
            document.getElementById('minimum-payout')?.addEventListener('input', function () {
                const v = parseFloat(this.value);
                document.getElementById('summary-minimum').textContent = isNaN(v) ? '—' : `£${v.toFixed(2)}`;
            });
        }

        #bindSubmit() {
            document.getElementById('payment-terms-form').addEventListener('submit', (e) => {
                e.preventDefault();
                this.#save();
            });
        }

        async #save() {
            const errBox = document.getElementById('form-errors');
            const btn = document.getElementById('save-btn');
            errBox.style.display = 'none';

            const delayDays = parseInt(document.getElementById('payout-delay').value);
            const minPounds = parseFloat(document.getElementById('minimum-payout').value);

            if (isNaN(delayDays) || delayDays < 0) {
                errBox.textContent = 'Payout delay must be 0 or more days.';
                errBox.style.display = 'block';
                return;
            }
            if (isNaN(minPounds) || minPounds < 0.50) {
                errBox.textContent = 'Minimum payout amount must be at least £0.50.';
                errBox.style.display = 'block';
                return;
            }

            const minimumPence = Math.round(minPounds * 100);
            btn.disabled = true;
            btn.innerHTML = '<div class="oc-spinner"></div> Saving…';

            try {
                const res = await fetch(`/api/${this.#site}/open-collab/admin/payment-terms`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Authorization: `Bearer ${this.#token()}`,
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({payout_delay_days: delayDays, minimum_payout_amount: minimumPence}),
                });
                const data = await res.json();
                if (res.ok) {
                    this.#showToast('✓ Payment terms saved');
                } else {
                    errBox.textContent = data.error || data.message || 'Save failed.';
                    errBox.style.display = 'block';
                }
            } catch {
                errBox.textContent = 'Network error. Please try again.';
                errBox.style.display = 'block';
            } finally {
                btn.disabled = false;
                btn.innerHTML = `<svg viewBox="0 0 20 20" fill="currentColor" width="15"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg> Save payment terms`;
            }
        }

        #showToast(msg, ok = true) {
            const el = document.getElementById('status-toast');
            el.textContent = msg;
            el.style.background = ok ? 'var(--navy)' : 'var(--red)';
            el.style.opacity = '1';
            setTimeout(() => {
                el.style.opacity = '0';
            }, 2800);
        }
    }

    const manager = new PaymentTermsManager(SITE, () => localStorage.getItem('oc_token') || '');
</script>
@endsection