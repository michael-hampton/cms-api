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

                <!-- Avatar upload -->
                <div class="oc-form-group" style="margin-bottom:24px;">
                    <label class="oc-label">Profile picture</label>
                    <div style="display:flex;align-items:center;gap:20px;">
                        <!-- Avatar preview -->
                        <div id="avatar-preview-wrap" style="position:relative;flex-shrink:0;">
                            <div id="avatar-preview"
                                 style="width:80px;height:80px;border-radius:50%;background:var(--slate-pale);border:2px solid var(--border);overflow:hidden;display:grid;place-items:center;cursor:pointer;"
                                 onclick="document.getElementById('avatar-file-input').click()"
                                 title="Click to change photo">
                                <?php if (!empty($profile?->avatar)): ?>
                                    <img id="avatar-img"
                                         src="<?= htmlspecialchars($profile->avatar) ?>"
                                         alt="Your avatar"
                                         style="width:100%;height:100%;object-fit:cover;">
                                <?php else: ?>
                                    <span id="avatar-initials"
                                          style="font-family:var(--font-display);font-size:1.5rem;color:var(--slate);user-select:none;">
                                        <?= strtoupper(substr($currentUser->name ?? 'U', 0, 1)) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <!-- Edit badge -->
                            <div onclick="document.getElementById('avatar-file-input').click()"
                                 style="position:absolute;bottom:0;right:0;width:24px;height:24px;background:var(--navy);border-radius:50%;display:grid;place-items:center;cursor:pointer;border:2px solid #fff;"
                                 title="Change photo">
                                <svg viewBox="0 0 20 20" fill="#fff" width="11">
                                    <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/>
                                </svg>
                            </div>
                        </div>

                        <!-- Upload controls -->
                        <div style="flex:1;">
                            <input type="file" id="avatar-file-input" accept="image/jpeg,image/png,image/webp"
                                   style="display:none;" onchange="avatarManager.onFileSelected(this)">
                            <button type="button" class="oc-btn oc-btn--ghost oc-btn--sm"
                                    onclick="document.getElementById('avatar-file-input').click()">
                                Choose photo
                            </button>
                            <?php if (!empty($profile?->avatar)): ?>
                                <button type="button" class="oc-btn oc-btn--ghost oc-btn--sm"
                                        style="margin-left:8px;color:var(--red);"
                                        onclick="avatarManager.remove()">
                                    Remove
                                </button>
                            <?php endif; ?>
                            <div class="oc-help" style="margin-top:6px;">
                                JPG, PNG or WebP · Max 2 MB · Square images work best
                            </div>
                            <div id="avatar-error"
                                 style="font-size:.75rem;color:var(--red);margin-top:4px;display:none;"></div>
                            <!-- Upload progress -->
                            <div id="avatar-progress-wrap" style="display:none;margin-top:8px;">
                                <div style="height:4px;background:var(--slate-pale);border-radius:99px;overflow:hidden;width:180px;">
                                    <div id="avatar-progress-bar"
                                         style="height:100%;width:0%;background:var(--navy);border-radius:99px;transition:width .2s ease;"></div>
                                </div>
                                <div style="font-size:.72rem;color:var(--slate);margin-top:3px;"
                                     id="avatar-progress-label">Uploading…
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

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

    <!-- Expertise section -->
    <div class="oc-card" style="margin-bottom:24px;animation:fadeSlideIn .41s ease;" id="expertise">
        <div class="oc-card__header">
            <span class="oc-card__title">Areas of expertise</span>
        </div>
        <div class="oc-card__body">
            <div id="expertise-success" class="oc-alert oc-alert--success" style="display:none;"></div>
            <div id="expertise-errors" class="oc-form-errors" style="display:none;"></div>

            <div class="oc-help" style="margin-bottom:16px;">
                Add up to 8 topics that describe your writing focus. These help editors match you with relevant briefs.
            </div>

            <!-- Tag list -->
            <div id="expertise-tags"
                 style="display:flex;flex-wrap:wrap;gap:8px;min-height:40px;margin-bottom:16px;padding:10px 12px;border:1.5px solid var(--border);border-radius:var(--radius);background:#fff;cursor:text;"
                 onclick="document.getElementById('expertise-input').focus()">
                <!-- Tags injected by JS -->
            </div>

            <!-- Input row -->
            <div style="display:flex;gap:10px;align-items:flex-start;">
                <div style="flex:1;position:relative;">
                    <input class="oc-input" type="text" id="expertise-input"
                           placeholder="e.g. Technology, Climate, Finance…"
                           maxlength="40"
                           autocomplete="off"
                           onkeydown="expertiseManager.onKeyDown(event)"
                           oninput="expertiseManager.onInput(this.value)">
                    <!-- Suggestions dropdown -->
                    <div id="expertise-suggestions"
                         style="display:none;position:absolute;top:calc(100% + 4px);left:0;right:0;background:#fff;border:1.5px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);z-index:50;overflow:hidden;">
                    </div>
                </div>
                <button type="button" class="oc-btn oc-btn--ghost oc-btn--sm"
                        onclick="expertiseManager.addFromInput()"
                        style="flex-shrink:0;margin-top:1px;">
                    Add
                </button>
            </div>
            <div class="oc-help" style="margin-top:6px;" id="expertise-hint">
                Press <kbd
                        style="font-size:.7rem;padding:1px 5px;border:1px solid var(--border);border-radius:4px;background:var(--slate-pale);">Enter</kbd>
                or comma to add · Click a tag to remove it
            </div>

            <div style="margin-top:16px;">
                <button type="button" class="oc-btn oc-btn--primary" id="expertise-save-btn"
                        onclick="expertiseManager.save()" disabled>
                    Save expertise
                </button>
            </div>
        </div>
    </div>

    <!-- Writing samples section -->
    <div class="oc-card" style="margin-bottom:24px;animation:fadeSlideIn .415s ease;" id="writing-samples">
        <div class="oc-card__header">
            <span class="oc-card__title">Writing Sample Links</span>
        </div>
        <div class="oc-card__body">
            <div id="sample-links-success" class="oc-alert oc-alert--success" style="display:none;"></div>
            <div id="sample-links-errors" class="oc-form-errors" style="display:none;"></div>

            <div id="sample-links-list" style="display:flex;flex-direction:column;gap:14px;margin-bottom:16px;"></div>

            <div style="display:flex;gap:10px;align-items:center;justify-content:space-between;">
                <button type="button" class="oc-btn oc-btn--ghost oc-btn--sm" id="sample-link-add-btn"
                        onclick="sampleLinksManager.add()">
                    Add link
                </button>
                <button type="button" class="oc-btn oc-btn--primary" id="sample-links-save-btn"
                        onclick="sampleLinksManager.save()" disabled>
                    Save writing samples
                </button>
            </div>
        </div>
    </div>

    <!-- Payment details section -->
    <div class="oc-card" style="margin-bottom:24px;animation:fadeSlideIn .42s ease;" id="stripe-connect">
        <div class="oc-card__header">
            <span class="oc-card__title">Stripe Connect Payouts</span>
        </div>
        <div class="oc-card__body">
            <div id="stripe-connect-status" class="oc-alert oc-alert--info">Checking Stripe onboarding status…</div>
            <div id="stripe-connect-requirements" style="margin:10px 0;color:var(--slate);font-size:.85rem;"></div>
            <button type="button" class="oc-btn oc-btn--primary" id="stripe-connect-btn"
                    onclick="startStripeOnboarding()">
                Connect Stripe account
            </button>
        </div>
    </div>

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
    const INITIAL_EXPERTISE = <?= json_encode(array_filter(array_map('trim', explode(',', $profile?->expertise ?? ''))) ?: []) ?>;
    const INITIAL_SAMPLE_LINKS = <?= json_encode($profile?->sample_links ?? []) ?>;

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

    // ── AvatarManager ─────────────────────────────────────────────────────────
    class AvatarManager {
        #site;
        #token;
        #pendingUrl = null; // URL returned from upload API, not yet saved to profile
        #removing = false;

        static MAX_BYTES = 2 * 1024 * 1024; // 2 MB
        static ALLOWED = ['image/jpeg', 'image/png', 'image/webp'];

        constructor(site, token) {
            this.#site = site;
            this.#token = token;
        }

        onFileSelected(input) {
            const file = input.files?.[0];
            if (!file) return;

            const errEl = document.getElementById('avatar-error');
            errEl.style.display = 'none';

            if (!AvatarManager.ALLOWED.includes(file.type)) {
                errEl.textContent = 'Only JPG, PNG, and WebP images are accepted.';
                errEl.style.display = 'block';
                input.value = '';
                return;
            }

            if (file.size > AvatarManager.MAX_BYTES) {
                errEl.textContent = 'Image must be under 2 MB.';
                errEl.style.display = 'block';
                input.value = '';
                return;
            }

            // Local preview
            const reader = new FileReader();
            reader.onload = (e) => this.#setPreview(e.target.result);
            reader.readAsDataURL(file);

            this.#upload(file);
        }

        /**
         * Called by the profile form before submitting — returns the URL to
         * include in the profile save payload, or null if unchanged.
         */
        getPendingUrl() {
            if (this.#removing) return '';   // explicit removal
            return this.#pendingUrl;          // null = no change
        }

        remove() {
            this.#removing = true;
            this.#pendingUrl = null;
            const preview = document.getElementById('avatar-preview');
            preview.innerHTML = `<span style="font-family:var(--font-display);font-size:1.5rem;color:var(--slate);user-select:none;">
                ${(document.getElementById('display-name')?.value || 'U').trim().charAt(0).toUpperCase()}
            </span>`;
            // Mark the profile form dirty so the user knows to save
            document.getElementById('profile-save-btn').disabled = false;
        }

        // ── Private ─────────────────────────────────────────────────────────

        #setPreview(src) {
            const preview = document.getElementById('avatar-preview');
            let img = document.getElementById('avatar-img');
            if (!img) {
                preview.innerHTML = '';
                img = document.createElement('img');
                img.id = 'avatar-img';
                img.alt = 'Your avatar';
                img.style.cssText = 'width:100%;height:100%;object-fit:cover;';
                preview.appendChild(img);
            }
            img.src = src;
        }

        async #upload(file) {
            const progressWrap = document.getElementById('avatar-progress-wrap');
            const progressBar = document.getElementById('avatar-progress-bar');
            const progressLabel = document.getElementById('avatar-progress-label');
            const errEl = document.getElementById('avatar-error');

            progressWrap.style.display = 'block';
            progressBar.style.width = '0%';
            progressLabel.textContent = 'Uploading…';

            try {
                const formData = new FormData();
                formData.append('avatar', file);

                const xhr = new XMLHttpRequest();
                const url = `/api/${this.#site}/open-collab/contributor/avatar`;

                await new Promise((resolve, reject) => {
                    xhr.upload.onprogress = (e) => {
                        if (e.lengthComputable) {
                            const pct = Math.round((e.loaded / e.total) * 100);
                            progressBar.style.width = `${pct}%`;
                            progressLabel.textContent = `Uploading… ${pct}%`;
                        }
                    };
                    xhr.onload = () => {
                        if (xhr.status >= 200 && xhr.status < 300) {
                            resolve(JSON.parse(xhr.responseText));
                        } else {
                            reject(new Error(xhr.responseText));
                        }
                    };
                    xhr.onerror = () => reject(new Error('Network error'));
                    xhr.open('POST', url);
                    xhr.setRequestHeader('Authorization', `Bearer ${this.#token}`);
                    xhr.send(formData);
                }).then((data) => {
                    this.#pendingUrl = data.url || data.data?.url || null;
                    progressLabel.textContent = '✓ Ready — save your profile to apply';
                    progressBar.style.width = '100%';
                    progressBar.style.background = 'var(--green)';
                    document.getElementById('profile-save-btn').disabled = false;
                });
            } catch {
                progressWrap.style.display = 'none';
                errEl.textContent = 'Upload failed. Please try again.';
                errEl.style.display = 'block';
                // Revert preview to server state
                const existingAvatar = '<?= htmlspecialchars($profile?->avatar ?? '') ?>';
                if (existingAvatar) this.#setPreview(existingAvatar);
            }
        }
    }

    // ── ExpertiseManager ──────────────────────────────────────────────────────
    class ExpertiseManager {
        #site;
        #token;
        #tags = [];
        #dirty = false;

        static MAX_TAGS = 8;
        static SUGGESTIONS = [
            'Technology', 'Climate', 'Finance', 'Politics', 'Health',
            'Culture', 'Science', 'Sports', 'Travel', 'Food',
            'Education', 'Business', 'Entertainment', 'Lifestyle',
            'Environment', 'Law', 'Economics', 'Art', 'History',
        ];

        constructor(site, token, initialTags = []) {
            this.#site = site;
            this.#token = token;
            this.#tags = [...initialTags];
            this.#render();
        }

        onKeyDown(event) {
            if (event.key === 'Enter' || event.key === ',') {
                event.preventDefault();
                this.addFromInput();
            } else if (event.key === 'Backspace' && event.target.value === '' && this.#tags.length > 0) {
                this.#removeTag(this.#tags.length - 1);
            } else if (event.key === 'Escape') {
                this.#hideSuggestions();
            }
        }

        onInput(value) {
            const trimmed = value.trim().replace(/,+$/, '');
            if (!trimmed) {
                this.#hideSuggestions();
                return;
            }
            const lower = trimmed.toLowerCase();
            const matches = ExpertiseManager.SUGGESTIONS.filter(
                s => s.toLowerCase().startsWith(lower) && !this.#tags.includes(s)
            ).slice(0, 5);

            if (matches.length > 0) {
                this.#showSuggestions(matches, trimmed);
            } else {
                this.#hideSuggestions();
            }
        }

        addFromInput() {
            const input = document.getElementById('expertise-input');
            const raw = input.value.trim().replace(/,+$/, '');
            if (!raw) return;
            this.#addTag(raw);
            input.value = '';
            this.#hideSuggestions();
        }

        async save() {
            const btn = document.getElementById('expertise-save-btn');
            btn.disabled = true;
            btn.innerHTML = '<div class="oc-spinner oc-spinner--dark"></div> Saving…';

            const succEl = document.getElementById('expertise-success');
            const errEl = document.getElementById('expertise-errors');
            succEl.style.display = 'none';
            errEl.style.display = 'none';

            try {
                const res = await fetch(`/api/${this.#site}/open-collab/contributor/expertise`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', Authorization: `Bearer ${this.#token}`},
                    body: JSON.stringify({expertise: this.#tags}),
                });

                if (!res.ok) {
                    const data = await res.json();
                    throw new Error(data.message || 'Save failed.');
                }

                this.#dirty = false;
                succEl.textContent = '✓ Expertise saved';
                succEl.style.display = 'flex';
                setTimeout(() => {
                    succEl.style.display = 'none';
                }, 3000);
            } catch (err) {
                errEl.textContent = err.message;
                errEl.style.display = 'block';
                btn.disabled = false;
            }

            btn.textContent = 'Save expertise';
        }

        // ── Private ─────────────────────────────────────────────────────────

        #addTag(value) {
            const trimmed = value.trim();
            if (!trimmed || this.#tags.length >= ExpertiseManager.MAX_TAGS) return;
            if (this.#tags.map(t => t.toLowerCase()).includes(trimmed.toLowerCase())) return;

            this.#tags.push(trimmed);
            this.#render();
            this.#markDirty();
        }

        #removeTag(index) {
            this.#tags.splice(index, 1);
            this.#render();
            this.#markDirty();
        }

        #render() {
            const container = document.getElementById('expertise-tags');
            container.innerHTML = '';

            for (let i = 0; i < this.#tags.length; i++) {
                const tag = document.createElement('span');
                tag.style.cssText = `
                    display:inline-flex;align-items:center;gap:5px;
                    padding:4px 10px 4px 12px;border-radius:99px;
                    background:var(--navy);color:#fff;font-size:.78rem;font-weight:500;
                    cursor:pointer;transition:background .15s;user-select:none;
                `;
                tag.title = 'Click to remove';
                tag.innerHTML = `${this.#esc(this.#tags[i])}
                    <svg viewBox="0 0 20 20" fill="currentColor" width="12" style="opacity:.65;flex-shrink:0;">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>`;
                tag.addEventListener('click', () => this.#removeTag(i));
                tag.addEventListener('mouseenter', () => {
                    tag.style.background = '#0f1929cc';
                });
                tag.addEventListener('mouseleave', () => {
                    tag.style.background = 'var(--navy)';
                });
                container.appendChild(tag);
            }

            // Capacity hint
            const hint = document.getElementById('expertise-hint');
            if (this.#tags.length >= ExpertiseManager.MAX_TAGS) {
                hint.innerHTML = `<span style="color:var(--amber);">Maximum of ${ExpertiseManager.MAX_TAGS} topics reached.</span>`;
                document.getElementById('expertise-input').disabled = true;
            } else {
                hint.innerHTML = `Press <kbd style="font-size:.7rem;padding:1px 5px;border:1px solid var(--border);border-radius:4px;background:var(--slate-pale);">Enter</kbd> or comma to add · Click a tag to remove it`;
                document.getElementById('expertise-input').disabled = false;
            }
        }

        #showSuggestions(items, typed) {
            const box = document.getElementById('expertise-suggestions');
            box.innerHTML = '';
            box.style.display = 'block';

            for (const item of items) {
                const row = document.createElement('div');
                row.style.cssText = 'padding:9px 14px;font-size:.85rem;cursor:pointer;color:var(--navy);';
                // Bold-match the typed portion
                const idx = item.toLowerCase().indexOf(typed.toLowerCase());
                row.innerHTML = idx >= 0
                    ? item.slice(0, idx) + `<strong>${item.slice(idx, idx + typed.length)}</strong>` + item.slice(idx + typed.length)
                    : this.#esc(item);
                row.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    this.#addTag(item);
                    document.getElementById('expertise-input').value = '';
                    this.#hideSuggestions();
                });
                row.addEventListener('mouseenter', () => {
                    row.style.background = 'var(--slate-pale)';
                });
                row.addEventListener('mouseleave', () => {
                    row.style.background = '';
                });
                box.appendChild(row);
            }
        }

        #hideSuggestions() {
            document.getElementById('expertise-suggestions').style.display = 'none';
        }

        #markDirty() {
            this.#dirty = true;
            const btn = document.getElementById('expertise-save-btn');
            if (btn) btn.disabled = false;
        }

        #esc(str) {
            const d = document.createElement('div');
            d.textContent = str;
            return d.innerHTML;
        }
    }

    class SampleLinksManager {
        static MAX_LINKS = 5;
        #site;
        #token;
        #links = [];
        #dirty = false;

        constructor(site, token, initialLinks) {
            this.#site = site;
            this.#token = token;
            this.#links = Array.isArray(initialLinks) ? initialLinks.map((link) => ({
                url: link.url || '',
                title: link.title || '',
                description: link.description || '',
            })) : [];

            if (this.#links.length === 0) {
                this.#links.push({url: '', title: '', description: ''});
            }

            this.#render();
        }

        add() {
            if (this.#links.length >= SampleLinksManager.MAX_LINKS) return;
            this.#links.push({url: '', title: '', description: ''});
            this.#dirty = true;
            this.#render();
        }

        async save() {
            this.#read();
            this.#clearErrors();

            const btn = document.getElementById('sample-links-save-btn');
            btn.disabled = true;
            btn.innerHTML = '<div class="oc-spinner oc-spinner--dark"></div> Saving…';

            const payload = {
                sample_links: this.#links.map((link) => ({
                    url: link.url.trim(),
                    title: link.title.trim(),
                    description: link.description.trim(),
                })),
            };

            try {
                const res = await fetch(`/api/${this.#site}/open-collab/profile/sample-links`, {
                    method: 'PUT',
                    headers: {'Content-Type': 'application/json', Authorization: `Bearer ${this.#token}`},
                    body: JSON.stringify(payload),
                });
                const data = await res.json();

                if (!res.ok) {
                    this.#showErrors(data.errors || {'sample_links': [data.error || 'Save failed.']});
                    return;
                }

                this.#links = data.data?.profile?.sample_links || [];
                if (this.#links.length === 0) {
                    this.#links.push({url: '', title: '', description: ''});
                }
                this.#dirty = false;
                this.#render();

                const ok = document.getElementById('sample-links-success');
                ok.textContent = 'Writing sample links saved.';
                ok.style.display = 'flex';
                setTimeout(() => ok.style.display = 'none', 3000);
            } catch {
                this.#showErrors({'sample_links': ['Network error. Please try again.']});
            } finally {
                btn.textContent = 'Save writing samples';
                btn.disabled = !this.#dirty;
            }
        }

        #render() {
            const list = document.getElementById('sample-links-list');
            list.innerHTML = '';

            this.#links.forEach((link, index) => {
                list.insertAdjacentHTML('beforeend', `
                    <div class="sample-link-row" data-index="${index}" style="border:1px solid var(--border);border-radius:var(--radius);padding:14px;background:#fff;">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
                            <span style="font-size:.78rem;font-weight:700;color:var(--slate);">#${index + 1}</span>
                            <button type="button" class="oc-btn oc-btn--ghost oc-btn--sm" data-action="up" ${index === 0 ? 'disabled' : ''}>Up</button>
                            <button type="button" class="oc-btn oc-btn--ghost oc-btn--sm" data-action="down" ${index === this.#links.length - 1 ? 'disabled' : ''}>Down</button>
                            <button type="button" class="oc-btn oc-btn--ghost oc-btn--sm" data-action="remove" style="margin-left:auto;color:var(--red);">Remove</button>
                        </div>
                        <div class="oc-form-group">
                            <label class="oc-label">URL</label>
                            <input class="oc-input" data-field="url" type="url" value="${this.#esc(link.url || '')}" placeholder="https://example.com/article">
                            <div class="sample-link-field-error" data-error-for="url" style="display:none;font-size:.75rem;color:var(--red);margin-top:4px;"></div>
                        </div>
                        <div class="oc-form-group">
                            <label class="oc-label oc-label--optional">Title</label>
                            <input class="oc-input" data-field="title" maxlength="150" value="${this.#esc(link.title || '')}">
                            <div class="sample-link-field-error" data-error-for="title" style="display:none;font-size:.75rem;color:var(--red);margin-top:4px;"></div>
                        </div>
                        <div class="oc-form-group" style="margin-bottom:0;">
                            <label class="oc-label oc-label--optional">Description</label>
                            <textarea class="oc-textarea" data-field="description" maxlength="500" rows="2">${this.#esc(link.description || '')}</textarea>
                            <div class="sample-link-field-error" data-error-for="description" style="display:none;font-size:.75rem;color:var(--red);margin-top:4px;"></div>
                        </div>
                    </div>
                `);
            });

            list.querySelectorAll('input, textarea').forEach((el) => {
                el.addEventListener('input', () => {
                    this.#dirty = true;
                    document.getElementById('sample-links-save-btn').disabled = false;
                });
            });

            list.querySelectorAll('button[data-action]').forEach((button) => {
                button.addEventListener('click', () => this.#handleAction(button));
            });

            document.getElementById('sample-link-add-btn').disabled = this.#links.length >= SampleLinksManager.MAX_LINKS;
            document.getElementById('sample-links-save-btn').disabled = !this.#dirty;
        }

        #handleAction(button) {
            this.#read();
            const row = button.closest('.sample-link-row');
            const index = Number(row.dataset.index);
            const action = button.dataset.action;

            if (action === 'remove') {
                this.#links.splice(index, 1);
                if (this.#links.length === 0) this.#links.push({url: '', title: '', description: ''});
            }

            if (action === 'up' && index > 0) {
                [this.#links[index - 1], this.#links[index]] = [this.#links[index], this.#links[index - 1]];
            }

            if (action === 'down' && index < this.#links.length - 1) {
                [this.#links[index + 1], this.#links[index]] = [this.#links[index], this.#links[index + 1]];
            }

            this.#dirty = true;
            this.#render();
        }

        #read() {
            this.#links = Array.from(document.querySelectorAll('.sample-link-row')).map((row) => ({
                url: row.querySelector('[data-field="url"]').value,
                title: row.querySelector('[data-field="title"]').value,
                description: row.querySelector('[data-field="description"]').value,
            }));
        }

        #showErrors(errors) {
            const box = document.getElementById('sample-links-errors');
            const messages = [];

            document.querySelectorAll('.sample-link-field-error').forEach((el) => {
                el.textContent = '';
                el.style.display = 'none';
            });

            Object.entries(errors).forEach(([key, value]) => {
                const message = Array.isArray(value) ? value[0] : value;
                const match = key.match(/^sample_links\.(\d+)\.(url|title|description)$/);
                if (match) {
                    const row = document.querySelector(`.sample-link-row[data-index="${match[1]}"]`);
                    const target = row?.querySelector(`[data-error-for="${match[2]}"]`);
                    if (target) {
                        target.textContent = message;
                        target.style.display = 'block';
                        return;
                    }
                }
                messages.push(message);
            });

            box.innerHTML = messages.map((message) => `<div>${this.#esc(String(message))}</div>`).join('');
            box.style.display = messages.length ? 'block' : 'none';
            document.getElementById('sample-links-save-btn').disabled = false;
        }

        #clearErrors() {
            document.getElementById('sample-links-errors').style.display = 'none';
            document.querySelectorAll('.sample-link-field-error').forEach((el) => {
                el.textContent = '';
                el.style.display = 'none';
            });
        }

        #esc(str) {
            const d = document.createElement('div');
            d.textContent = str;
            return d.innerHTML;
        }
    }

    // ── ProfileManager ────────────────────────────────────────────────────────
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

            const payload = {bio: document.getElementById('bio').value};

            // Include avatar URL if the AvatarManager has a pending change
            const pendingAvatar = window.avatarManager?.getPendingUrl();
            if (pendingAvatar !== null && pendingAvatar !== undefined) {
                payload.avatar = pendingAvatar; // '' = remove, string = new URL
            }

            const res = await fetch(`/api/${this.#site}/open-collab/onboarding/profile`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', Authorization: `Bearer ${this.#token}`},
                body: JSON.stringify(payload),
            });

            const data = await res.json();
            if (res.ok) {
                const ok = document.getElementById('profile-success');
                ok.textContent = '✓ Profile updated';
                ok.style.display = 'flex';
                // Reset avatar progress indicator after successful save
                const progressWrap = document.getElementById('avatar-progress-wrap');
                if (progressWrap) progressWrap.style.display = 'none';
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
        #avatar;
        #expertise;
        #sampleLinks;

        constructor({site, token, stripeKey, expectedEmail, initialExpertise, initialSampleLinks}) {
            this.#notif = new NotificationPreferencesManager(site, token);
            this.#profile = new ProfileManager(site, token);
            this.#payment = new PaymentDetailsManager(site, token, stripeKey);
            this.#closure = new AccountClosureManager(site, token, expectedEmail);
            this.#avatar = new AvatarManager(site, token);
            this.#expertise = new ExpertiseManager(site, token, initialExpertise);
            this.#sampleLinks = new SampleLinksManager(site, token, initialSampleLinks);

            // Expose avatarManager globally for inline HTML handlers
            window.avatarManager = this.#avatar;
            window.expertiseManager = this.#expertise;
            window.sampleLinksManager = this.#sampleLinks;
        }

        init() {
            this.#notif.load();
            this.#loadStripeConnectStatus();
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

        async #loadStripeConnectStatus() {
            const token = localStorage.getItem('oc_token') || '';
            const res = await fetch(`/api/${SITE}/open-collab/stripe-connect/status`, {
                headers: {Authorization: `Bearer ${token}`, Accept: 'application/json'},
            });

            if (!res.ok) {
                document.getElementById('stripe-connect-status').textContent = 'Unable to load Stripe onboarding status.';
                return;
            }

            const status = await res.json();
            const box = document.getElementById('stripe-connect-status');
            const req = document.getElementById('stripe-connect-requirements');
            const btn = document.getElementById('stripe-connect-btn');

            if (!status.connected) {
                box.textContent = 'Stripe is not connected. Connect your account to receive payouts.';
                btn.textContent = 'Connect Stripe account';
                return;
            }

            if (status.status === 'enabled') {
                box.textContent = 'Payouts enabled. Your Stripe account is ready.';
                btn.textContent = 'Refresh onboarding link';
            } else if (status.status === 'incomplete') {
                box.textContent = 'Onboarding incomplete. Resume onboarding to finish setup.';
                btn.textContent = 'Resume onboarding';
            } else if (status.status === 'restricted') {
                box.textContent = 'Payouts disabled or restricted. Review required verification steps.';
                btn.textContent = 'Fix verification';
            } else {
                box.textContent = 'Verification is pending. Stripe may require more details.';
                btn.textContent = 'Refresh onboarding link';
            }

            if (Array.isArray(status.verification_required) && status.verification_required.length > 0) {
                req.textContent = 'Verification required: ' + status.verification_required.join(', ');
            } else {
                req.textContent = '';
            }
        }

        async startStripeOnboarding() {
            const token = localStorage.getItem('oc_token') || '';
            const res = await fetch(`/api/${SITE}/open-collab/stripe-connect/onboard`, {
                method: 'POST',
                headers: {Authorization: `Bearer ${token}`, Accept: 'application/json'},
            });

            if (!res.ok) {
                alert('Unable to create Stripe onboarding link right now.');
                return;
            }

            const data = await res.json();
            const url = data.data?.onboarding_url || data.onboarding_url;
            if (url) {
                window.location.href = url;
            }
        }
    }

    const settingsManager = new SettingsManager({
        site: SITE,
        token: localStorage.getItem('oc_token') || '',
        stripeKey: STRIPE_KEY,
        expectedEmail: CURRENT_USER_EMAIL,
        initialExpertise: INITIAL_EXPERTISE,
        initialSampleLinks: INITIAL_SAMPLE_LINKS,
    });

    settingsManager.init();

    // Shims for PHP-rendered inline handlers
    const notifManager = settingsManager; // updateNotifState called directly on settingsManager
    const saveNotifPreferences = () => settingsManager.saveNotifPreferences();
    const savePaymentDetails = () => settingsManager.savePaymentDetails();
    const startStripeOnboarding = () => settingsManager.startStripeOnboarding();
    const showCloseAccountModal = () => settingsManager.showCloseAccountModal();
    const hideCloseAccountModal = () => settingsManager.hideCloseAccountModal();
    const submitCloseAccount = () => settingsManager.submitCloseAccount();

</script>
@endsection
