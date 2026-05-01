@section('logic')
<?php
/**
 * Template: open-collab/settings/index.php
 * Variables: $profile, $currentUser, $contractSignatures, $guidelinesAck
 */

$pageTitle = 'Account Settings';
$activeNav = 'settings';
$breadcrumbs = [['label' => 'Dashboard', 'url' => '/contributor/dashboard'], ['label' => 'Settings']];
$pageClass = '';
?>
@endsection

@extends('open-collab/layouts/opencollab')

@section('content')
<div style="max-width:680px;">

    <!-- Profile section -->
    <div class="oc-card" style="margin-bottom:24px;animation:fadeSlideIn .4s ease;" id="profile">
        <div class="oc-card__header">
            <span class="oc-card__title">Profile</span>
        </div>
        <div class="oc-card__body">
            <div id="profile-success" class="oc-alert oc-alert--success" style="display:none;"></div>
            <div id="profile-errors" class="oc-form-errors" style="display:none;"></div>

            <form id="profile-form" novalidate>
                <div class="oc-form-group">
                    <label class="oc-label" for="display-name">Display name</label>
                    <input class="oc-input" type="text" id="display-name" name="name"
                           value="<?= htmlspecialchars($currentUser->name ?? '') ?>">
                </div>
                <div class="oc-form-group">
                    <label class="oc-label" for="bio">Bio</label>
                    <textarea class="oc-textarea" id="bio" name="bio"
                              rows="4"><?= htmlspecialchars($profile?->bio ?? '') ?></textarea>
                    <div class="oc-help">Visible to readers on your published articles.</div>
                </div>
                <button type="submit" class="oc-btn oc-btn--primary" id="profile-save-btn">Save profile</button>
            </form>
        </div>
    </div>

    <!-- Payment details section -->
    <div class="oc-card" style="margin-bottom:24px;animation:fadeSlideIn .45s ease;" id="payment">
        <div class="oc-card__header">
            <span class="oc-card__title">Payment & Tax</span>
        </div>
        <div class="oc-card__body">
            <div class="oc-alert oc-alert--info" style="margin-bottom:20px;">
                <svg viewBox="0 0 20 20" fill="currentColor" width="16">
                    <path fill-rule="evenodd"
                          d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z"
                          clip-rule="evenodd"/>
                </svg>
                Card details are tokenised by Stripe — we never store raw card numbers.
            </div>

            <?php if (!empty($profile?->payment_details)): ?>
                <div style="background:var(--slate-pale);border:1px solid var(--border);border-radius:var(--radius);padding:14px 16px;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;">
                    <div>
                        <div style="font-weight:500;color:var(--navy);">Payment method connected</div>
                        <div style="font-size:.78rem;color:var(--green);margin-top:2px;">● Active via Stripe</div>
                    </div>
                    <button onclick="document.getElementById('update-payment').style.display='block';this.style.display='none';"
                            class="oc-btn oc-btn--ghost oc-btn--sm">Update
                    </button>
                </div>
            <?php endif; ?>

            <div id="update-payment" style="display:<?= empty($profile?->payment_details) ? 'block' : 'none' ?>;">
                <div id="stripe-card-element"
                     style="border:1.5px solid var(--border);border-radius:var(--radius);padding:12px 14px;background:#fff;margin-bottom:16px;">
                </div>
                <div id="stripe-card-errors" style="font-size:.75rem;color:var(--red);margin-bottom:12px;"></div>

                <div class="oc-form-group">
                    <label class="oc-label" for="tax-country-settings">Country of tax residence</label>
                    <select class="oc-select" id="tax-country-settings" name="tax_country">
                        <option value="">Select country…</option>
                        <option value="GB" <?= ($profile?->tax_country ?? '') === 'GB' ? 'selected' : '' ?>>United
                            Kingdom
                        </option>
                        <option value="US" <?= ($profile?->tax_country ?? '') === 'US' ? 'selected' : '' ?>>United
                            States
                        </option>
                        <option value="CA" <?= ($profile?->tax_country ?? '') === 'CA' ? 'selected' : '' ?>>Canada
                        </option>
                        <option value="AU" <?= ($profile?->tax_country ?? '') === 'AU' ? 'selected' : '' ?>>Australia
                        </option>
                        <option value="OTHER">Other</option>
                    </select>
                </div>

                <button type="button" class="oc-btn oc-btn--primary" id="save-payment-btn"
                        onclick="savePaymentDetails()">
                    Save payment details
                </button>
            </div>

        </div>
    </div>

    <!-- Compliance section -->
    <div class="oc-card" style="margin-bottom:24px;animation:fadeSlideIn .5s ease;">
        <div class="oc-card__header">
            <span class="oc-card__title">Compliance & Agreements</span>
        </div>
        <div class="oc-card__body">
            <div style="display:flex;flex-direction:column;gap:12px;">

                <!-- Contract status -->
                <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid #f5f2ee;">
                    <div>
                        <div style="font-weight:500;font-size:.875rem;color:var(--navy);">Contributor Agreement</div>
                        <?php if (!empty($contractSignatures)): ?>
                            <div style="font-size:.75rem;color:var(--green);">
                                Signed <?= date('d M Y', strtotime($contractSignatures[0]->signed_at ?? 'now')) ?>
                            </div>
                        <?php else: ?>
                            <div style="font-size:.75rem;color:var(--red);">Not yet signed</div>
                        <?php endif; ?>
                    </div>
                    <?php if (empty($contractSignatures)): ?>
                        <a href="/onboarding" class="oc-btn oc-btn--amber oc-btn--sm">Sign now</a>
                    <?php else: ?>
                        <span class="oc-badge oc-badge--published">✓ Signed</span>
                    <?php endif; ?>
                </div>

                <!-- Guidelines status -->
                <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 0;">
                    <div>
                        <div style="font-weight:500;font-size:.875rem;color:var(--navy);">Brand Guidelines</div>
                        <?php if (!empty($guidelinesAck)): ?>
                            <div style="font-size:.75rem;color:var(--green);">
                                Acknowledged v<?= (int)$guidelinesAck->version ?>
                                on <?= $guidelinesAck->acknowledged_at?->format('d M Y') ?? 'now' ?>
                            </div>
                        <?php else: ?>
                            <div style="font-size:.75rem;color:var(--red);">Not acknowledged</div>
                        <?php endif; ?>
                    </div>
                    <?php if (empty($guidelinesAck)): ?>
                        <a href="/onboarding" class="oc-btn oc-btn--amber oc-btn--sm">Acknowledge</a>
                    <?php else: ?>
                        <span class="oc-badge oc-badge--published">✓ Done</span>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <!-- Notification Preferences section -->
    <div class="oc-card" style="margin-bottom:24px; animation:fadeSlideIn .5s ease;">
        <div class="oc-card__header">
            <span class="oc-card__title">Notification Preferences</span>
        </div>
        <div class="oc-card__body">
            <div style="font-size:.85rem; color:var(--slate); margin-bottom:16px;">
                Control how and when you receive updates from the platform.
            </div>

            <div class="oc-notif-prefs" id="notif-prefs-grid">
                <div class="oc-notif-prefs__header">
                    <div class="oc-notif-prefs__header-cell">Event</div>
                    <div class="oc-notif-prefs__header-cell">Email</div>
                    <div class="oc-notif-prefs__header-cell">In-app</div>
                </div>
                <!-- rows injected by JS -->
            </div>

            <button id="notif-save-btn"
                    class="oc-btn oc-btn--primary oc-btn--sm"
                    onclick="saveNotifPreferences()"
                    disabled>
                Save Changes
            </button>
        </div>
    </div>

    <!-- Danger zone -->
    <div class="oc-danger-zone" id="danger" style="animation:fadeSlideIn .55s ease;">
        <div class="oc-danger-zone__title">Danger Zone</div>

        <p style="font-size:.875rem;color:var(--slate);margin-bottom:16px;line-height:1.6;">
            Closing your account will permanently remove your contributor profile and prevent you from
            publishing new content. <strong>Existing published articles may remain live</strong> as per
            your contributor agreement. Outstanding earnings will be processed in the next payout cycle.
        </p>

        <div style="background:var(--red-pale);border:1px solid #fecaca;border-radius:var(--radius);padding:14px 16px;margin-bottom:16px;font-size:.85rem;color:var(--red);line-height:1.6;">
            <strong>Before closing your account, please note:</strong>
            <ul style="margin:6px 0 0 16px;">
                <li>Your contractual obligations remain in effect for existing content.</li>
                <li>You will lose access to your dashboard and earnings history.</li>
                <li>This action cannot be undone without contacting support.</li>
            </ul>
        </div>

        <button onclick="showCloseAccountModal()" class="oc-btn oc-btn--danger">
            <svg viewBox="0 0 20 20" fill="currentColor" width="15">
                <path fill-rule="evenodd"
                      d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z"
                      clip-rule="evenodd"/>
            </svg>
            Request account closure
        </button>
    </div>

</div>

<!-- Account closure modal -->
<div id="close-modal"
     style="display:none;position:fixed;inset:0;background:rgba(15,25,41,.6);z-index:500;display:none;place-items:center;"
     onclick="if(event.target===this)hideCloseAccountModal()">
    <div style="background:#fff;border-radius:var(--radius-xl);padding:32px;max-width:420px;width:90%;box-shadow:var(--shadow-xl);animation:fadeSlideIn .2s ease;">
        <h3 style="font-family:var(--font-display);font-size:1.25rem;color:var(--navy);margin-bottom:8px;">Close your
            account</h3>
        <p style="font-size:.875rem;color:var(--slate);margin-bottom:20px;line-height:1.6;">
            To confirm, type your email address below and tell us why you're leaving.
            Our team will review your request within 2 business days.
        </p>
        <div class="oc-form-group">
            <label class="oc-label" for="confirm-email">Your email address</label>
            <input class="oc-input" type="email" id="confirm-email"
                   placeholder="<?= htmlspecialchars($currentUser->email ?? '') ?>">
        </div>
        <div class="oc-form-group">
            <label class="oc-label" for="close-reason">Reason for leaving</label>
            <select class="oc-select" id="close-reason">
                <option value="">Select a reason…</option>
                <option value="not_using">No longer using the platform</option>
                <option value="content_issues">Issues with content policies</option>
                <option value="payment_issues">Payment concerns</option>
                <option value="privacy">Privacy concerns</option>
                <option value="other">Other</option>
            </select>
        </div>
        <div class="oc-form-group">
            <label class="oc-label oc-label--optional" for="close-notes">Additional notes</label>
            <textarea class="oc-textarea" id="close-notes" rows="3"
                      placeholder="Any additional context…" style="min-height:72px;"></textarea>
        </div>
        <div id="close-modal-errors" class="oc-form-errors" style="display:none;"></div>
        <div style="display:flex;gap:10px;margin-top:8px;">
            <button onclick="hideCloseAccountModal()" class="oc-btn oc-btn--ghost" style="flex:1;">Cancel</button>
            <button onclick="submitCloseAccount()" class="oc-btn oc-btn--danger" style="flex:1;" id="close-submit-btn">
                Request closure
            </button>
        </div>
    </div>
</div>

<script src="https://js.stripe.com/v3/"></script>

@endsection

@section('scripts')
<script>
    const CURRENT_USER_EMAIL = '<?= htmlspecialchars($currentUser->email ?? '') ?>';
    const STRIPE_KEY = '<?= htmlspecialchars($stripePublicKey ?? '') ?>';
    const SITE = '<?= htmlspecialchars($site ?? '') ?>';

    class NotificationPreferencesManager {
        #site;
        #token;
        #state = {};
        #dirty = false;

        constructor(site, token) {
            this.#site = site;
            this.#token = token;
        }

        async load() {
            try {
                const res = await fetch(`/api/${this.#site}/open-collab/notifications/preferences`, {
                    headers: {Authorization: `Bearer ${this.#token}`},
                });
                if (!res.ok) throw new Error('Failed to load preferences');
                const data = await res.json();
                this.#buildGrid(data.preferences);
            } catch {
                document.getElementById('notif-prefs-grid').innerHTML =
                    '<div class="oc-alert oc-alert--error">Could not load notification preferences.</div>';
            }
        }

        #buildGrid(preferences) {
            const grid = document.getElementById('notif-prefs-grid');
            grid.innerHTML = `
            <div class="oc-notif-prefs__header">
                <div class="oc-notif-prefs__header-cell">Event</div>
                <div class="oc-notif-prefs__header-cell">Email</div>
                <div class="oc-notif-prefs__header-cell">In-app</div>
            </div>`;

            const byType = {};
            for (const pref of preferences) {
                if (!byType[pref.consent_type_id]) {
                    byType[pref.consent_type_id] = {name: pref.name, code: pref.code, channels: {}};
                }
                byType[pref.consent_type_id].channels[pref.channel] = pref.is_granted;
                if (!this.#state[pref.consent_type_id]) this.#state[pref.consent_type_id] = {};
                this.#state[pref.consent_type_id][pref.channel] = pref.is_granted;
            }

            for (const [typeId, info] of Object.entries(byType)) {
                grid.insertAdjacentHTML('beforeend', `
                <div class="oc-notif-prefs__row">
                    <div class="oc-notif-prefs__label">${this.#esc(info.name)}</div>
                    <div class="oc-notif-prefs__check">
                        <input type="checkbox" data-id="${typeId}" data-channel="email"
                               ${info.channels['email'] ? 'checked' : ''}
                               onchange="notifManager.updateNotifState(${typeId}, 'email', this.checked)">
                    </div>
                    <div class="oc-notif-prefs__check">
                        <input type="checkbox" data-id="${typeId}" data-channel="in_app"
                               ${info.channels['in_app'] ? 'checked' : ''}
                               onchange="notifManager.updateNotifState(${typeId}, 'in_app', this.checked)">
                    </div>
                </div>`);
            }
        }

        updateState(id, channel, value) {
            if (!this.#state[id]) this.#state[id] = {};
            this.#state[id][channel] = value;
            this.#markDirty();
        }

        async save() {
            const payload = [];
            for (const typeId in this.#state) {
                for (const channel in this.#state[typeId]) {
                    payload.push({
                        consent_type_id: parseInt(typeId),
                        channel,
                        granted: !!this.#state[typeId][channel],
                    });
                }
            }

            const btn = document.getElementById('notif-save-btn');
            btn.disabled = true;
            btn.innerHTML = 'Saving...';

            try {
                const res = await fetch(`/api/${this.#site}/open-collab/notifications/preferences/batch`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', Authorization: `Bearer ${this.#token}`},
                    body: JSON.stringify({preferences: payload}),
                });
                if (!res.ok) throw new Error('Save failed');
                this.#dirty = false;
                btn.innerHTML = 'Saved ✓';
                setTimeout(() => {
                    btn.innerHTML = 'Save Changes';
                    btn.disabled = true;
                }, 2000);
            } catch {
                alert('Could not save preferences. Please try again.');
                btn.disabled = false;
                btn.innerHTML = 'Save Changes';
            }
        }

        #markDirty() {
            this.#dirty = true;
            const btn = document.getElementById('notif-save-btn');
            if (btn) btn.disabled = false;
        }

        #esc(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
    }

    class ProfileManager {
        #site;
        #token;

        constructor(site, token) {
            this.#site = site;
            this.#token = token;
            this.#bindForm();
        }

        #bindForm() {
            document.getElementById('profile-form')?.addEventListener('submit', (e) => {
                e.preventDefault();
                this.#save();
            });
        }

        async #save() {
            const btn = document.getElementById('profile-save-btn');
            btn.disabled = true;
            btn.innerHTML = '<div class="oc-spinner oc-spinner--dark"></div> Saving…';

            const res = await fetch(`/api/${this.#site}/open-collab/onboarding/profile`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', Authorization: `Bearer ${this.#token}`},
                body: JSON.stringify({bio: document.getElementById('bio').value}),
            });

            const data = await res.json();
            if (res.ok) {
                const ok = document.getElementById('profile-success');
                ok.textContent = '✓ Profile updated';
                ok.style.display = 'flex';
                setTimeout(() => {
                    ok.style.display = 'none';
                }, 3000);
            } else {
                const err = document.getElementById('profile-errors');
                err.textContent = data.message || 'Save failed.';
                err.style.display = 'block';
            }

            btn.disabled = false;
            btn.textContent = 'Save profile';
        }
    }

    class PaymentDetailsManager {
        #site;
        #token;
        #stripe = null;
        #cardElement = null;

        constructor(site, token, stripeKey) {
            this.#site = site;
            this.#token = token;
            if (stripeKey) this.#initStripe(stripeKey);
        }

        #initStripe(key) {
            this.#stripe = Stripe(key);
            const elems = this.#stripe.elements();
            this.#cardElement = elems.create('card', {
                style: {
                    base: {
                        fontFamily: "'DM Sans', sans-serif",
                        fontSize: '15px',
                        color: '#0f1929',
                        '::placeholder': {color: '#94a3b8'},
                    },
                },
            });
            this.#cardElement.mount('#stripe-card-element');
            this.#cardElement.on('change', ({error}) => {
                document.getElementById('stripe-card-errors').textContent = error ? error.message : '';
            });
        }

        async save() {
            const btn = document.getElementById('save-payment-btn');
            btn.disabled = true;
            btn.innerHTML = '<div class="oc-spinner oc-spinner--dark"></div> Saving…';

            let stripeToken = 'bank';
            if (this.#stripe && this.#cardElement) {
                const {token, error} = await this.#stripe.createToken(this.#cardElement);
                if (error) {
                    document.getElementById('stripe-card-errors').textContent = error.message;
                    btn.disabled = false;
                    btn.textContent = 'Save payment details';
                    return;
                }
                stripeToken = token.id;
            }

            const res = await fetch(`/api/${this.#site}/open-collab/onboarding/payment`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', Authorization: `Bearer ${this.#token}`},
                body: JSON.stringify({
                    payment_method_type: 'stripe',
                    stripe_token: stripeToken,
                    tax_country: document.getElementById('tax-country-settings')?.value || '',
                }),
            });

            if (res.ok) {
                window.location.reload();
            } else {
                const data = await res.json();
                alert(data.message || 'Failed to save payment details.');
                btn.disabled = false;
                btn.textContent = 'Save payment details';
            }
        }
    }

    class AccountClosureManager {
        #site;
        #token;
        #expectedEmail;

        constructor(site, token, expectedEmail) {
            this.#site = site;
            this.#token = token;
            this.#expectedEmail = expectedEmail;
        }

        openModal() {
            document.getElementById('close-modal').style.display = 'grid';
        }

        closeModal() {
            document.getElementById('close-modal').style.display = 'none';
        }

        async submit() {
            const email = document.getElementById('confirm-email').value.trim();
            const reason = document.getElementById('close-reason').value;
            const notes = document.getElementById('close-notes').value.trim();
            const errBox = document.getElementById('close-modal-errors');
            const btn = document.getElementById('close-submit-btn');
            errBox.style.display = 'none';

            if (email.toLowerCase() !== this.#expectedEmail.toLowerCase()) {
                errBox.textContent = 'Email does not match your account email.';
                errBox.style.display = 'block';
                return;
            }
            if (!reason) {
                errBox.textContent = 'Please select a reason.';
                errBox.style.display = 'block';
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<div class="oc-spinner"></div> Submitting…';

            try {
                const res = await fetch(`/api/${this.#site}/open-collab/contributor/close-account`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', Authorization: `Bearer ${this.#token}`},
                    body: JSON.stringify({reason, notes}),
                });

                if (res.ok) {
                    this.closeModal();
                    document.body.innerHTML = `
                    <div style="min-height:100vh;display:grid;place-items:center;font-family:'DM Sans',sans-serif;background:var(--cream);">
                        <div style="text-align:center;max-width:400px;padding:32px;">
                            <div style="width:56px;height:56px;background:#dcfce7;border-radius:50%;display:grid;place-items:center;margin:0 auto 16px;">
                                <svg viewBox="0 0 20 20" fill="#16a34a" width="24"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            </div>
                            <h2 style="font-family:'Playfair Display',serif;font-size:1.5rem;color:#0f1929;margin-bottom:8px;">Request received</h2>
                            <p style="color:#64748b;font-size:.9rem;line-height:1.6;">We've received your account closure request. Our team will review it and contact you within 2 business days.</p>
                        </div>
                    </div>`;
                } else {
                    const data = await res.json();
                    errBox.textContent = data.message || 'Submission failed. Please try again.';
                    errBox.style.display = 'block';
                    btn.disabled = false;
                    btn.textContent = 'Request closure';
                }
            } catch {
                errBox.textContent = 'Network error. Please try again.';
                errBox.style.display = 'block';
                btn.disabled = false;
                btn.textContent = 'Request closure';
            }
        }
    }

    class SettingsManager {
        #profile;
        #payment;
        #closure;
        #notif;

        constructor({site, token, stripeKey, expectedEmail}) {
            this.#notif = new NotificationPreferencesManager(site, token);
            this.#profile = new ProfileManager(site, token);
            this.#payment = new PaymentDetailsManager(site, token, stripeKey);
            this.#closure = new AccountClosureManager(site, token, expectedEmail);
        }

        init() {
            this.#notif.load();
        }

        // Delegated public surface for inline handlers
        updateNotifState(id, channel, value) {
            this.#notif.updateState(id, channel, value);
        }

        saveNotifPreferences() {
            this.#notif.save();
        }

        savePaymentDetails() {
            this.#payment.save();
        }

        showCloseAccountModal() {
            this.#closure.openModal();
        }

        hideCloseAccountModal() {
            this.#closure.closeModal();
        }

        submitCloseAccount() {
            this.#closure.submit();
        }
    }

    const settingsManager = new SettingsManager({
        site: SITE,
        token: localStorage.getItem('oc_token') || '',
        stripeKey: STRIPE_KEY,
        expectedEmail: CURRENT_USER_EMAIL,
    });

    settingsManager.init();

    // Shims for PHP-rendered inline handlers
    const notifManager = settingsManager; // updateNotifState called directly on settingsManager
    const saveNotifPreferences = () => settingsManager.saveNotifPreferences();
    const savePaymentDetails = () => settingsManager.savePaymentDetails();
    const showCloseAccountModal = () => settingsManager.showCloseAccountModal();
    const hideCloseAccountModal = () => settingsManager.hideCloseAccountModal();
    const submitCloseAccount = () => settingsManager.submitCloseAccount();

</script>
@endsection