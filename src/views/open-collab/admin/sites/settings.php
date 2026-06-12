@extends('open-collab/layouts/admin')

@section('content')

<div class="oc-page__header">
    <div>
        <h1 class="oc-page__title">Site Settings</h1>
        <p class="oc-page__subtitle">Manage contributor workflow requirements for
            <strong><?= htmlspecialchars($currentSite->name) ?></strong>.
        </p>
    </div>
</div>

<div class="oc-tabbar" role="tablist" aria-label="Site settings sections">
    <button class="oc-tabbar__tab active" data-tab-target="site-settings-panel" type="button">Settings</button>
    <button class="oc-tabbar__tab" data-tab-target="rbac-roles-panel" type="button">Roles</button>
    <button class="oc-tabbar__tab" data-tab-target="rbac-matrix-panel" type="button">Permissions Matrix</button>
    <button class="oc-tabbar__tab" data-tab-target="rbac-members-panel" type="button">Members</button>
    <button class="oc-tabbar__tab" data-tab-target="rbac-overrides-panel" type="button">Overrides</button>
    <button class="oc-tabbar__tab" data-tab-target="rbac-audit-panel" type="button">Audit Log</button>
</div>

<div class="oc-card oc-tab-panel active" id="site-settings-panel" style="max-width:640px;">

    <form
            method="POST"
            action="/<?= htmlspecialchars($site) ?>/open-collab/admin/sites/settings"
            id="site-settings-form"
            novalidate>

        @csrf

        <div class="oc-form-group">
            <label class="oc-label" for="guidelines_version">
                Guidelines Version
                <span class="oc-label__hint">
                    Bump this when guidelines change to force re-acknowledgement.
                </span>
            </label>

            <input
                    type="text"
                    id="guidelines_version"
                    name="guidelines_version"
                    class="oc-input <?= $errors['guidelines_version'] ?? false ? 'oc-input--error' : '' ?>"
                    value="<?= htmlspecialchars($currentSite->guidelines_version ?? '') ?>"
                    placeholder="e.g. 2024-06"
                    maxlength="50">

            <?php if (!empty($errors['guidelines_version'])): ?>
                <span class="oc-field-error"><?= htmlspecialchars($errors['guidelines_version']) ?></span>
            <?php endif; ?>
        </div>

        <fieldset class="oc-fieldset" style="margin-top:28px;">
            <legend class="oc-fieldset__legend">Contributor Requirements</legend>
            <p class="oc-fieldset__description">
                Enabling a requirement blocks contributors from submitting content
                until the condition is met.
            </p>

            <div class="oc-toggle-row">
                <div class="oc-toggle-row__body">
                    <span class="oc-toggle-row__label">Require payment setup</span>
                    <span class="oc-toggle-row__description">
                        Contributors must add a payout method before submitting articles.
                    </span>
                </div>
                <label class="oc-toggle" aria-label="Require payment setup">
                    <input
                            type="checkbox"
                            name="require_payment_setup"
                            value="1"
                            <?= $currentSite->require_payment_setup ? 'checked' : '' ?>>
                    <span class="oc-toggle__track"></span>
                </label>
            </div>

            <div class="oc-toggle-row">
                <div class="oc-toggle-row__body">
                    <span class="oc-toggle-row__label">Require KYC verification</span>
                    <span class="oc-toggle-row__description">
                        Contributors must have a verified Stripe Connect account before requesting payouts.
                    </span>
                </div>
                <label class="oc-toggle" aria-label="Require KYC verification">
                    <input
                            type="checkbox"
                            name="require_kyc_verification"
                            value="1"
                            <?= ($currentSite->require_kyc_verification ?? false) ? 'checked' : '' ?>>
                    <span class="oc-toggle__track"></span>
                </label>
            </div>

            <div class="oc-toggle-row">
                <div class="oc-toggle-row__body">
                    <span class="oc-toggle-row__label">Require signed contract</span>
                    <span class="oc-toggle-row__description">
                        Contributors must sign the active contract before submitting.
                    </span>
                </div>
                <label class="oc-toggle" aria-label="Require signed contract">
                    <input
                            type="checkbox"
                            name="require_contracts"
                            value="1"
                            <?= $currentSite->require_contracts ? 'checked' : '' ?>>
                    <span class="oc-toggle__track"></span>
                </label>
            </div>

            <div class="oc-toggle-row">
                <div class="oc-toggle-row__body">
                    <span class="oc-toggle-row__label">Require guidelines acknowledgement</span>
                    <span class="oc-toggle-row__description">
                        Contributors must acknowledge the current guidelines version.
                        Tied to <em>Guidelines Version</em> above.
                    </span>
                </div>
                <label class="oc-toggle" aria-label="Require guidelines acknowledgement">
                    <input
                            type="checkbox"
                            name="require_guidelines_ack"
                            value="1"
                            <?= $currentSite->require_guidelines_ack ? 'checked' : '' ?>>
                    <span class="oc-toggle__track"></span>
                </label>
            </div>

            <div class="oc-toggle-row">
                <div class="oc-toggle-row__body">
                    <span class="oc-toggle-row__label">Require age verification</span>
                    <span class="oc-toggle-row__description">
                        Contributors must confirm their date of birth and meet the minimum
                        contributor age before submitting.
                    </span>
                </div>
                <label class="oc-toggle" aria-label="Require age verification">
                    <input
                            type="checkbox"
                            name="require_age_verification"
                            value="1"
                            id="require_age_verification"
                            <?= $currentSite->require_age_verification ? 'checked' : '' ?>>
                    <span class="oc-toggle__track"></span>
                </label>
            </div>

            <!-- Minimum age — shown only when age verification is enabled -->
            <div id="minimum-age-row"
                 style="padding:16px 0 4px 0;<?= $currentSite->require_age_verification ? '' : 'display:none;' ?>">
                <label class="oc-label" for="minimum_contributor_age">
                    Minimum contributor age
                    <span class="oc-label__hint">Must be between 13 and 100.</span>
                </label>
                <input
                        type="number"
                        id="minimum_contributor_age"
                        name="minimum_contributor_age"
                        class="oc-input <?= $errors['minimum_contributor_age'] ?? false ? 'oc-input--error' : '' ?>"
                        value="<?= htmlspecialchars((string)($currentSite->minimum_contributor_age ?? 18)) ?>"
                        min="13"
                        max="100"
                        style="max-width:100px;">
                <?php if (!empty($errors['minimum_contributor_age'])): ?>
                    <span class="oc-field-error"><?= htmlspecialchars($errors['minimum_contributor_age']) ?></span>
                <?php endif; ?>
            </div>

        </fieldset>

        <div class="oc-form-actions" style="margin-top:32px;">
            <button
                    type="submit"
                    class="oc-btn oc-btn--primary"
                    id="save-btn">
                Save settings
            </button>
        </div>

    </form>

</div>

<!-- ── User Assignment ─────────────────────────────────────────────────── -->
<div class="oc-card oc-tab-panel" style="max-width:640px;margin-top:28px;" id="rbac-members-panel">

    <div class="oc-card__header">
        <span class="oc-card__title">Site Users</span>
        <span class="oc-card__subtitle">
            Users assigned here can access this site as contributors.
        </span>
    </div>

    <div class="oc-card__body">

        <div id="users-banner" style="display:none;margin-bottom:16px;"></div>

        <!-- Search & add -->
        <div style="display:flex;gap:10px;align-items:flex-start;margin-bottom:20px;">
            <div style="flex:1;position:relative;">
                <input
                        class="oc-input"
                        type="search"
                        id="user-search-input"
                        placeholder="Search by name or email…"
                        autocomplete="off"
                        oninput="siteUsersManager.onSearchInput(this.value)">
                <!-- Autocomplete dropdown -->
                <div id="user-search-results"
                     style="display:none;position:absolute;top:calc(100% + 4px);left:0;right:0;
                            background:#fff;border:1.5px solid var(--border);border-radius:var(--radius);
                            box-shadow:var(--shadow);z-index:50;overflow:hidden;max-height:220px;overflow-y:auto;">
                </div>
            </div>
            <button
                    type="button"
                    class="oc-btn oc-btn--primary"
                    id="user-add-btn"
                    disabled
                    onclick="siteUsersManager.addSelected()"
                    style="flex-shrink:0;">
                Add user
            </button>
        </div>

        <!-- Assigned users list -->
        <div id="assigned-users-list">
            <div class="oc-rbac-loading">
                Loading assigned users…
            </div>
        </div>

    </div>
</div>

<div class="oc-card oc-tab-panel" style="margin-top:28px;" id="rbac-roles-panel">
    <div class="oc-card__header">
        <span class="oc-card__title">Roles</span>
        <span class="oc-card__subtitle">List roles, edit their permissions, assign them to members, and audit changes.</span>
    </div>
    <div class="oc-card__body">
        <div id="rbac-banner" style="display:none;margin-bottom:16px;"></div>
        <form class="oc-rbac-role-create" onsubmit="rbacManager.createRole(event)">
            <input type="text" name="name" class="oc-input" placeholder="Role name" required>
            <input type="text" name="slug" class="oc-input" placeholder="role_slug (optional)">
            <button type="submit" class="oc-btn oc-btn--primary oc-btn--sm">Add role</button>
        </form>
        <div class="oc-rbac-member-list" id="rbac-role-list">
            <div class="oc-rbac-loading">Loading roles…</div>
        </div>
    </div>
</div>

<div class="oc-card oc-tab-panel" style="margin-top:28px;" id="rbac-matrix-panel">
    <div class="oc-card__header">
        <span class="oc-card__title">Permissions Matrix</span>
        <span class="oc-card__subtitle">Grid view of permissions across current site roles.</span>
    </div>
    <div class="oc-card__body">
        <div style="overflow:auto;">
            <table class="oc-rbac-table" id="rbac-matrix-table">
                <thead>
                <tr>
                    <th>Permission</th>
                </tr>
                </thead>
                <tbody id="rbac-matrix-body">
                <tr>
                    <td class="oc-rbac-loading">Loading permissions matrix…</td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="oc-card oc-tab-panel" style="margin-top:28px;" id="rbac-overrides-panel">
    <div class="oc-card__header">
        <span class="oc-card__title">Overrides</span>
        <span class="oc-card__subtitle">Grant or revoke specific permissions for individual site members.</span>
    </div>
    <div class="oc-card__body">
        <div class="oc-rbac-member-list" id="rbac-overrides-list">
            <div class="oc-rbac-loading">Loading overrides…</div>
        </div>
    </div>
</div>

<!-- ── Override drawer ──────────────────────────────────────────────────── -->
<div id="override-drawer-backdrop"
     style="display:none;position:fixed;inset:0;background:rgba(15,25,41,.45);z-index:400;"
     onclick="rbacManager.closeOverrideDrawer()"></div>
<div id="override-drawer"
     style="display:none;position:fixed;top:0;right:0;bottom:0;width:360px;max-width:95vw;
            background:#fff;z-index:401;box-shadow:-8px 0 40px rgba(0,0,0,.12);
            display:flex;flex-direction:column;transform:translateX(100%);transition:transform .25s cubic-bezier(.4,0,.2,1);">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:20px 24px 18px;border-bottom:1px solid var(--border);">
        <div>
            <div style="font-weight:700;font-size:1rem;color:var(--navy);">Add Override</div>
            <div id="override-drawer-subtitle" style="font-size:.78rem;color:var(--slate);margin-top:2px;"></div>
        </div>
        <button type="button"
                onclick="rbacManager.closeOverrideDrawer()"
                style="background:none;border:none;cursor:pointer;padding:4px;color:var(--slate);line-height:1;"
                aria-label="Close">
            <svg viewBox="0 0 20 20" fill="currentColor" width="18" height="18">
                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
            </svg>
        </button>
    </div>
    <div style="padding:20px 24px;flex:1;overflow-y:auto;">
        <div id="override-drawer-banner" style="display:none;margin-bottom:16px;"></div>
        <div class="oc-form-group">
            <label class="oc-label" for="override-drawer-permission">Permission</label>
            <select class="oc-select" id="override-drawer-permission">
                <option value="">Loading permissions…</option>
            </select>
        </div>
        <div class="oc-form-group" style="margin-top:16px;">
            <label class="oc-label">Type</label>
            <div style="display:flex;gap:10px;margin-top:6px;">
                <label class="oc-override-type-option" id="override-type-grant">
                    <input type="radio" name="override_type" value="true" checked style="position:absolute;opacity:0;pointer-events:none;">
                    <span class="oc-override-type-option__icon oc-override-type-option__icon--grant">
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13">
                            <polyline points="2,8 6,12 14,4"/>
                        </svg>
                    </span>
                    <span>Grant</span>
                </label>
                <label class="oc-override-type-option" id="override-type-deny">
                    <input type="radio" name="override_type" value="false" style="position:absolute;opacity:0;pointer-events:none;">
                    <span class="oc-override-type-option__icon oc-override-type-option__icon--deny">
                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13">
                            <line x1="3" y1="3" x2="13" y2="13"/><line x1="13" y1="3" x2="3" y2="13"/>
                        </svg>
                    </span>
                    <span>Deny</span>
                </label>
            </div>
        </div>
    </div>
    <div style="padding:16px 24px;border-top:1px solid var(--border);display:flex;gap:10px;">
        <button type="button" class="oc-btn oc-btn--ghost" style="flex:1;" onclick="rbacManager.closeOverrideDrawer()">Cancel</button>
        <button type="button" class="oc-btn oc-btn--primary" style="flex:1;" id="override-drawer-save" onclick="rbacManager.saveDrawerOverride()">Save override</button>
    </div>
</div>

<div class="oc-card oc-tab-panel" style="margin-top:28px;" id="rbac-audit-panel">
    <div class="oc-card__header">
        <span class="oc-card__title">Audit Log</span>
        <span class="oc-card__subtitle">Recent RBAC changes for this site.</span>
    </div>
    <div class="oc-card__body">
        <div class="oc-rbac-audit-list" id="rbac-audit-list">
            <div class="oc-rbac-loading">Loading audit log…</div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<style>
    .oc-tabbar {
        display:flex;
        gap:8px;
        flex-wrap:wrap;
        margin:0 0 20px;
    }

    .oc-tabbar__tab {
        border:1px solid var(--border);
        background:#fff;
        color:var(--navy);
        border-radius:999px;
        padding:8px 14px;
        font-size:.82rem;
        font-weight:600;
        cursor:pointer;
    }

    .oc-tabbar__tab.active {
        background:var(--navy);
        color:#fff;
        border-color:var(--navy);
    }

    .oc-tab-panel {
        display:none;
    }

    .oc-tab-panel.active {
        display:block;
    }

    .oc-user-row {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 0;
        border-bottom: 1px solid var(--border);
    }

    .oc-user-row:last-child {
        border-bottom: none;
    }

    .oc-user-row__avatar {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: var(--slate-pale);
        display: grid;
        place-items: center;
        font-size: .8rem;
        font-weight: 700;
        color: var(--navy);
        flex-shrink: 0;
    }

    .oc-user-row__body {
        flex: 1;
        min-width: 0;
    }

    .oc-user-row__name {
        display: block;
        font-size: .875rem;
        font-weight: 600;
        color: var(--navy);
    }

    .oc-user-row__email {
        display: block;
        font-size: .78rem;
        color: var(--slate);
    }

    .oc-rbac-grid {
        display:grid;
        grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));
        gap:20px;
    }

    .oc-rbac-section__title {
        font-size:1rem;
        font-weight:700;
        color:var(--navy);
        margin:0 0 12px;
    }

    .oc-rbac-table {
        width:100%;
        border-collapse:collapse;
        font-size:.82rem;
    }

    .oc-rbac-table th,
    .oc-rbac-table td {
        padding:8px 10px;
        border-bottom:1px solid var(--border);
        text-align:left;
        vertical-align:top;
    }

    .oc-rbac-meta {
        color:var(--slate);
        font-size:.74rem;
    }

    .oc-rbac-member-list,
    .oc-rbac-audit-list {
        display:flex;
        flex-direction:column;
        gap:10px;
    }

    .oc-rbac-loading {
        border:1px dashed var(--border);
        border-radius:12px;
        padding:14px;
        color:var(--slate);
        font-size:.82rem;
        background:#fff;
    }

    .oc-rbac-member,
    .oc-rbac-override-row,
    .oc-rbac-audit-item {
        border:1px solid var(--border);
        border-radius:12px;
        padding:12px;
        background:#fff;
    }

    .oc-rbac-member {
        display:flex;
        justify-content:space-between;
        gap:12px;
        align-items:flex-start;
    }

    .oc-rbac-role-create {
        display:grid;
        grid-template-columns:minmax(0, 1.2fr) minmax(0, 1fr) auto;
        gap:10px;
        margin-bottom:16px;
    }

    .oc-rbac-member__name {
        font-weight:600;
        color:var(--navy);
    }

    .oc-rbac-override-card {
        border:1px solid var(--border);
        border-radius:12px;
        padding:14px 16px;
        background:#fff;
    }

    .oc-rbac-override-card__header {
        display:flex;
        justify-content:space-between;
        align-items:flex-start;
        gap:12px;
        margin-bottom:12px;
    }

    .oc-rbac-current-overrides {
        display:flex;
        flex-wrap:wrap;
        gap:6px;
        min-height:24px;
    }

    .oc-rbac-empty-overrides {
        line-height:24px;
    }

    .oc-rbac-override-pill {
        display:inline-flex;
        align-items:center;
        gap:5px;
        padding:4px 6px 4px 8px;
        border-radius:999px;
        font-size:.72rem;
        font-weight:600;
        background:var(--slate-pale);
        color:var(--navy);
        border:1px solid transparent;
    }

    .oc-rbac-override-pill[data-granted="false"] {
        background:var(--red-pale);
        color:var(--red);
    }

    .oc-rbac-override-pill__type {
        text-transform:uppercase;
        font-size:.65rem;
        letter-spacing:.04em;
        opacity:.7;
    }

    .oc-rbac-override-pill__slug {
        font-weight:700;
    }

    .oc-rbac-override-pill__remove {
        display:inline-flex;
        align-items:center;
        justify-content:center;
        background:none;
        border:none;
        cursor:pointer;
        padding:2px;
        border-radius:50%;
        color:inherit;
        opacity:.6;
        line-height:1;
        transition:opacity .15s, background .15s;
    }

    .oc-rbac-override-pill__remove:hover {
        opacity:1;
        background:rgba(0,0,0,.1);
    }

    .oc-override-type-option {
        display:flex;
        align-items:center;
        gap:8px;
        padding:9px 14px;
        border-radius:8px;
        border:1.5px solid var(--border);
        cursor:pointer;
        font-size:.82rem;
        font-weight:600;
        color:var(--navy);
        transition:border-color .15s, background .15s;
        position:relative;
        flex:1;
    }

    .oc-override-type-option:has(input:checked) {
        border-color:var(--navy);
        background:var(--slate-pale);
    }

    #override-type-deny:has(input:checked) {
        border-color:var(--red);
        background:var(--red-pale);
        color:var(--red);
    }

    .oc-override-type-option__icon {
        width:24px;
        height:24px;
        border-radius:50%;
        display:grid;
        place-items:center;
        flex-shrink:0;
    }

    .oc-override-type-option__icon--grant {
        background:#dcfce7;
        color:var(--green);
    }

    .oc-override-type-option__icon--deny {
        background:var(--red-pale);
        color:var(--red);
    }
</style>
<script>
    /**
     * SiteSettingsForm
     * ─────────────────
     * Serialises the form and PATCHes the API, then shows inline feedback.
     * Falls back to native POST if JS is unavailable.
     */
    class SiteSettingsForm {

        constructor(formId) {
            this.form = document.getElementById(formId);
            this.saveBtn = document.getElementById('save-btn');
            this.siteSlug = <?= json_encode($site) ?>;
            this.apiUrl = `/api/${this.siteSlug}/open-collab/admin/sites/settings`;
            this.token = localStorage.getItem('oc_token') || '';

            if (this.form) {
                this._bindEvents();
            }
        }

        _bindEvents() {
            this.form.addEventListener('submit', (e) => {
                e.preventDefault();
                this._submit();
            });
        }

        async _submit() {
            if (this._saving) return;
            this._saving = true;
            this._setLoading(true);

            const payload = this._buildPayload();

            try {
                const res = await fetch(this.apiUrl, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(this.token ? {Authorization: `Bearer ${this.token}`} : {}),
                    },
                    body: JSON.stringify(payload),
                });

                const data = await res.json().catch(() => ({}));

                if (!res.ok) {
                    throw new Error(data.message || `Server error (${res.status})`);
                }

                this._showBanner('success', 'Site settings saved.');

            } catch (err) {
                this._showBanner('danger', err.message || 'Could not save settings.');
            } finally {
                this._setLoading(false);
                this._saving = false;
            }
        }

        /**
         * Checkboxes that are unchecked are not included in FormData,
         * so we build the payload explicitly to ensure false is sent.
         */
        _buildPayload() {
            const fd = new FormData(this.form);

            return {
                guidelines_version: fd.get('guidelines_version') || null,
                require_payment_setup: fd.has('require_payment_setup'),
                require_kyc_verification: fd.has('require_kyc_verification'),
                require_contracts: fd.has('require_contracts'),
                require_guidelines_ack: fd.has('require_guidelines_ack'),
                require_age_verification: fd.has('require_age_verification'),
                minimum_contributor_age: fd.has('require_age_verification')
                    ? parseInt(fd.get('minimum_contributor_age'), 10) || 18
                    : null,
            };
        }

        _setLoading(on) {
            if (!this.saveBtn) return;
            this.saveBtn.disabled = on;
            this.saveBtn.textContent = on ? 'Saving…' : 'Save settings';
        }

        _showBanner(type, message) {
            // Remove any existing banner
            document.querySelector('.oc-settings-banner')?.remove();

            const iconPath = type === 'success'
                ? 'M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z'
                : 'M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z';

            const banner = document.createElement('div');
            banner.className = `oc-alert oc-alert--${type} oc-settings-banner`;
            banner.style.cssText = 'margin:0 0 20px;';
            banner.innerHTML = `
                <svg viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="${this._escape(iconPath)}" clip-rule="evenodd"/>
                </svg>
                ${this._escape(message)}
            `;

            this.form.prepend(banner);

            setTimeout(() => {
                banner.style.transition = 'opacity .4s';
                banner.style.opacity = '0';
                setTimeout(() => banner.remove(), 400);
            }, 4500);
        }

        _escape(s) {
            return String(s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }
    }

    new SiteSettingsForm('site-settings-form');

    // Show/hide minimum age input when age verification toggle changes
    document.getElementById('require_age_verification')?.addEventListener('change', function () {
        const row = document.getElementById('minimum-age-row');
        if (row) row.style.display = this.checked ? '' : 'none';
    });

    // ── SiteUsersManager ──────────────────────────────────────────────────────
    class SiteUsersManager {
        #site;
        #token;
        #searchTimer = null;
        #selectedUser = null; // { id, name, email }

        constructor(site, token) {
            this.#site = site;
            this.#token = token;

            // Close dropdown when clicking outside
            document.addEventListener('click', (e) => {
                if (!e.target.closest('#user-search-input') &&
                    !e.target.closest('#user-search-results')) {
                    this.#hideResults();
                }
            });
        }

        async init() {
            await this.loadAssignedUsers();
        }

        async loadAssignedUsers() {
            const list = document.getElementById('assigned-users-list');

            if (!list) return;

            list.innerHTML = `
        <div class="oc-rbac-loading">
            Loading assigned users…
        </div>
    `;

            try {
                const res = await fetch(`/api/${this.#site}/open-collab/admin/sites/users`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(this.#token ? {Authorization: `Bearer ${this.#token}`} : {}),
                    },
                });

                const data = await res.json().catch(() => ({}));

                if (!res.ok) {
                    throw new Error(data.message || `Error ${res.status}`);
                }

                this.#renderAssignedUsers(data.users || data.data?.users || []);

            } catch (err) {
                list.innerHTML = `
            <div class="oc-alert oc-alert--danger">
                ${this.#esc(err.message || 'Could not load assigned users.')}
            </div>
        `;
            }
        }

        #renderAssignedUsers(users) {
            const list = document.getElementById('assigned-users-list');

            if (!list) return;

            list.innerHTML = '';

            if (!users.length) {
                list.innerHTML = this.#emptyStateHtml();
                return;
            }

            for (const user of users) {
                this.#appendUserRow(user);
            }
        }

        #emptyStateHtml() {
            return `
        <div id="no-users-msg" style="font-size:.85rem;color:var(--slate);padding:12px 0;">
            No users have been assigned to this site yet.
        </div>
    `;
        }

        onSearchInput(value) {
            // Reset selection whenever the input changes
            this.#selectedUser = null;
            document.getElementById('user-add-btn').disabled = true;

            clearTimeout(this.#searchTimer);

            if (value.trim().length < 2) {
                this.#hideResults();
                return;
            }

            this.#searchTimer = setTimeout(() => this.#search(value.trim()), 300);
        }

        async addSelected() {
            if (!this.#selectedUser) return;

            const btn = document.getElementById('user-add-btn');
            btn.disabled = true;
            btn.textContent = 'Adding…';

            try {
                const res = await fetch(`/api/${this.#site}/open-collab/admin/sites/users`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(this.#token ? {Authorization: `Bearer ${this.#token}`} : {}),
                    },
                    body: JSON.stringify({user_id: this.#selectedUser.id}),
                });

                const data = await res.json().catch(() => ({}));

                if (!res.ok) throw new Error(data.message || `Error ${res.status}`);

                this.#appendUserRow(data.user);
                this.#showBanner('success', data.message);
                document.getElementById('user-search-input').value = '';
                this.#selectedUser = null;
                this.#hideResults();

            } catch (err) {
                this.#showBanner('danger', err.message || 'Could not add user.');
            }

            btn.disabled = false;
            btn.textContent = 'Add user';
        }

        async removeUser(userId, triggerBtn) {
            if (!confirm('Remove this user from the site?')) return;

            triggerBtn.disabled = true;
            triggerBtn.textContent = 'Removing…';

            try {
                const res = await fetch(`/api/${this.#site}/open-collab/admin/sites/users/${userId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(this.#token ? {Authorization: `Bearer ${this.#token}`} : {}),
                    },
                });

                const data = await res.json().catch(() => ({}));

                if (!res.ok) throw new Error(data.message || `Error ${res.status}`);

                // Remove the row from the DOM
                document.querySelector(`.oc-user-row[data-user-id="${userId}"]`)?.remove();

                // Show empty state if no rows remain
                if (document.querySelectorAll('.oc-user-row').length === 0) {
                    const list = document.getElementById('assigned-users-list');
                    list.innerHTML = this.#emptyStateHtml();
                }

                this.#showBanner('success', data.message || 'User removed.');

            } catch (err) {
                this.#showBanner('danger', err.message || 'Could not remove user.');
                triggerBtn.disabled = false;
                triggerBtn.textContent = 'Remove';
            }
        }

        // ── Private ─────────────────────────────────────────────────────────

        async #search(query) {
            try {
                const url = `/api/${this.#site}/open-collab/admin/users/search?q=${encodeURIComponent(query)}`;
                const res = await fetch(url, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(this.#token ? {Authorization: `Bearer ${this.#token}`} : {}),
                    },
                });

                if (!res.ok) return;

                const data = await res.json();
                this.#renderResults(data.users || []);

            } catch {
                // Search failure is non-critical — don't surface an error banner
            }
        }

        #renderResults(users) {
            const box = document.getElementById('user-search-results');
            box.innerHTML = '';

            if (users.length === 0) {
                box.innerHTML = `<div style="padding:10px 14px;font-size:.85rem;color:var(--slate);">No users found.</div>`;
                box.style.display = 'block';
                return;
            }

            for (const user of users) {
                const row = document.createElement('div');
                row.style.cssText = 'display:flex;align-items:center;gap:10px;padding:9px 14px;cursor:pointer;';
                row.innerHTML = `
                    <div style="width:30px;height:30px;border-radius:50%;background:var(--slate-pale);
                                display:grid;place-items:center;font-size:.75rem;font-weight:700;
                                color:var(--navy);flex-shrink:0;">
                        ${this.#esc(user.name.charAt(0).toUpperCase())}
                    </div>
                    <div style="min-width:0;">
                        <div style="font-size:.85rem;font-weight:600;color:var(--navy);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            ${this.#esc(user.name)}
                        </div>
                        <div style="font-size:.75rem;color:var(--slate);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            ${this.#esc(user.email)}
                        </div>
                    </div>`;

                row.addEventListener('mouseenter', () => {
                    row.style.background = 'var(--slate-pale)';
                });
                row.addEventListener('mouseleave', () => {
                    row.style.background = '';
                });
                row.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    this.#selectUser(user);
                });

                box.appendChild(row);
            }

            box.style.display = 'block';
        }

        #selectUser(user) {
            this.#selectedUser = user;
            document.getElementById('user-search-input').value = `${user.name} (${user.email})`;
            document.getElementById('user-add-btn').disabled = false;
            this.#hideResults();
        }

        #appendUserRow(user) {
            // Remove the empty-state message if present
            document.getElementById('no-users-msg')?.remove();

            const list = document.getElementById('assigned-users-list');
            const row = document.createElement('div');
            row.className = 'oc-user-row';
            row.dataset.userId = user.id;
            row.innerHTML = `
                <div class="oc-user-row__avatar">${this.#esc(user.name.charAt(0).toUpperCase())}</div>
                <div class="oc-user-row__body">
                    <span class="oc-user-row__name">${this.#esc(user.name)}</span>
                    <span class="oc-user-row__email">${this.#esc(user.email)}</span>
                </div>
                <button type="button"
                        class="oc-btn oc-btn--ghost oc-btn--sm oc-btn--danger"
                        onclick="siteUsersManager.removeUser(${user.id}, this)"
                        aria-label="Remove ${this.#esc(user.name)}">
                    Remove
                </button>`;

            list.appendChild(row);
        }

        #hideResults() {
            const box = document.getElementById('user-search-results');
            if (box) box.style.display = 'none';
        }

        #showBanner(type, message) {
            const el = document.getElementById('users-banner');
            if (!el) return;

            const isSuccess = type === 'success';
            el.className = `oc-alert oc-alert--${isSuccess ? 'success' : 'danger'}`;
            el.textContent = message;
            el.style.display = 'flex';

            clearTimeout(this._bannerTimer);
            this._bannerTimer = setTimeout(() => {
                el.style.display = 'none';
            }, 4500);
        }

        #esc(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }
    }

    const siteUsersManager = new SiteUsersManager(
            <?= json_encode($site) ?>,
        localStorage.getItem('oc_token') || '',
    );

    siteUsersManager.init();

    const rbacManager = {
        state: {
            permissions: [],
            roles: [],
            members: [],
            overrides: [],
            audit: [],
        },
        endpoints: {
            permissions: '/api/<?= htmlspecialchars($site) ?>/open-collab/admin/rbac/permissions',
            roles: '/api/<?= htmlspecialchars($site) ?>/open-collab/admin/rbac/roles',
            members: '/api/<?= htmlspecialchars($site) ?>/open-collab/admin/rbac/members',
            overrides: '/api/<?= htmlspecialchars($site) ?>/open-collab/admin/rbac/overrides',
            audit: '/api/<?= htmlspecialchars($site) ?>/open-collab/admin/rbac/audit',
        },
        async init() {
            await this.loadAll();
        },
        async loadAll() {
            const [permissions, roles, members, overrides, audit] = await Promise.all([
                this.fetchSegment('permissions'),
                this.fetchSegment('roles'),
                this.fetchSegment('members'),
                this.fetchSegment('overrides'),
                this.fetchSegment('audit'),
            ]);

            this.state.permissions = permissions;
            this.state.roles = roles;
            this.state.members = members;
            this.state.overrides = overrides;
            this.state.audit = audit;
            this.renderAll();
        },
        async fetchSegment(segment) {
            const response = await fetch(this.endpoints[segment], {
                headers: {
                    'Authorization': `Bearer ${localStorage.getItem('oc_token') || ''}`,
                    'Accept': 'application/json',
                },
            });

            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(payload.message || payload.error || `Failed to load ${segment}.`);
            }

            const body = payload.data ?? payload;
            return Array.isArray(body?.[segment]) ? body[segment] : [];
        },
        renderAll() {
            this.renderPermissionOptions();
            this.renderRoles();
            this.renderMatrix();
            this.renderOverrides();
            this.renderAudit();
        },
        focusRole(roleId) {
            document.querySelector('[data-tab-target="rbac-matrix-panel"]')?.click();
            document.getElementById(`role-column-${roleId}`)?.scrollIntoView({behavior: 'smooth', inline: 'center', block: 'nearest'});
        },
        async toggleRolePermission(checkbox) {
            const roleId = checkbox.dataset.roleId;
            const inputs = Array.from(document.querySelectorAll(`input[data-role-id="${roleId}"]`));
            const permissionSlugs = inputs.filter(input => input.checked).map(input => input.dataset.permissionSlug);

            await this.request(`/api/<?= htmlspecialchars($site) ?>/open-collab/admin/rbac/role-permissions/${roleId}`, {
                method: 'POST',
                body: JSON.stringify({permission_slugs: permissionSlugs}),
            });
        },

        async updateMemberRoles(select) {
            const userId = select.dataset.userId;
            const roleIds = Array.from(select.selectedOptions).map(option => Number(option.value));

            await this.request(`/api/<?= htmlspecialchars($site) ?>/open-collab/admin/contributors/${userId}/roles`, {
                method: 'POST',
                body: JSON.stringify({role_ids: roleIds}),
            });
        },

        // ── Override drawer ────────────────────────────────────────────────────

        _overrideDrawerUserId: null,

        openOverrideDrawer(userId, userName) {
            this._overrideDrawerUserId = userId;
            document.getElementById('override-drawer-subtitle').textContent = userName;
            document.getElementById('override-drawer-banner').style.display = 'none';

            document.getElementById('override-drawer-permission').selectedIndex = 0;
            const grantRadio = document.querySelector('#override-drawer input[value="true"]');
            if (grantRadio) grantRadio.checked = true;

            const backdrop = document.getElementById('override-drawer-backdrop');
            const drawer   = document.getElementById('override-drawer');
            backdrop.style.display = 'block';
            drawer.style.display   = 'flex';
            requestAnimationFrame(() => drawer.style.transform = 'translateX(0)');
        },

        closeOverrideDrawer() {
            const drawer = document.getElementById('override-drawer');
            drawer.style.transform = 'translateX(100%)';
            drawer.addEventListener('transitionend', () => {
                drawer.style.display = 'none';
                document.getElementById('override-drawer-backdrop').style.display = 'none';
            }, {once: true});
        },

        async saveDrawerOverride() {
            const userId = this._overrideDrawerUserId;
            if (!userId) return;

            const permissionSlug = document.getElementById('override-drawer-permission').value;
            const grantedRadio   = document.querySelector('#override-drawer input[name="override_type"]:checked');
            const granted        = grantedRadio ? grantedRadio.value === 'true' : true;
            const btn            = document.getElementById('override-drawer-save');
            const banner         = document.getElementById('override-drawer-banner');

            btn.disabled    = true;
            btn.textContent = 'Saving…';
            banner.style.display = 'none';

            const result = await this.request(`/api/<?= htmlspecialchars($site) ?>/open-collab/admin/rbac/overrides/${userId}`, {
                method: 'POST',
                body: JSON.stringify({permission_slug: permissionSlug, granted}),
            });

            btn.disabled    = false;
            btn.textContent = 'Save override';

            if (result?.response?.ok) {
                this.upsertOverridePill(userId, permissionSlug, granted);
                this.closeOverrideDrawer();
            } else {
                banner.className     = 'oc-alert oc-alert--danger';
                banner.textContent   = result?.payload?.message || 'Failed to save override.';
                banner.style.display = 'block';
            }
        },

        async removeOverride(userId, permissionSlug, triggerBtn) {
            triggerBtn.disabled = true;

            const result = await this.request(`/api/<?= htmlspecialchars($site) ?>/open-collab/admin/rbac/overrides/${userId}/${encodeURIComponent(permissionSlug)}`, {
                method: 'DELETE',
            });

            if (result?.response?.ok) {
                this.state.overrides = this.state.overrides.filter((override) => !(Number(override.user_id) === Number(userId) && override.permission_slug === permissionSlug));
                const pill = triggerBtn.closest('[data-permission-slug]');
                pill?.remove();
                const container = document.getElementById(`member-overrides-${userId}`);
                if (container && container.querySelectorAll('.oc-rbac-override-pill').length === 0) {
                    const empty = document.createElement('span');
                    empty.className = 'oc-rbac-meta oc-rbac-empty-overrides';
                    empty.dataset.emptyState = 'true';
                    empty.textContent = 'No direct overrides set.';
                    container.appendChild(empty);
                }
            } else {
                triggerBtn.disabled = false;
            }
        },

        async createRole(event) {
            event.preventDefault();
            const form = event.target;
            const name = form.name.value.trim();
            const slug = form.slug.value.trim();

            const result = await this.request(`/api/<?= htmlspecialchars($site) ?>/open-collab/admin/rbac/roles`, {
                method: 'POST',
                body: JSON.stringify({
                    name,
                    slug: slug || null,
                    permission_slugs: [],
                }),
            });

            if (result?.response?.ok) {
                form.reset();
                if (result.payload?.role) {
                    this.state.roles.push({...result.payload.role, permission_ids: result.payload.role.permission_ids || []});
                    this.renderAll();
                }
            }
        },

        async deleteRole(roleId, roleName) {
            if (!confirm(`Delete the role "${roleName}"?`)) {
                return;
            }

            const result = await this.request(`/api/<?= htmlspecialchars($site) ?>/open-collab/admin/rbac/roles/${roleId}`, {
                method: 'DELETE',
            });

            if (result?.response?.ok) {
                this.state.roles = this.state.roles.filter((role) => Number(role.id) !== Number(roleId));
                this.state.members = this.state.members.map((member) => ({
                    ...member,
                    role_ids: (member.role_ids || []).filter((id) => Number(id) !== Number(roleId)),
                }));
                this.renderAll();
            }
        },

        async request(url, options) {
            const response = await fetch(url, {
                ...options,
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${localStorage.getItem('oc_token') || ''}`,
                    'Accept': 'application/json',
                },
            });

            const payload = await response.json().catch(() => ({}));
            const banner = document.getElementById('rbac-banner');
            if (!banner) return;

            banner.style.display = 'block';
            banner.className = response.ok ? 'oc-alert oc-alert--success' : 'oc-alert oc-alert--danger';
            banner.textContent = payload.message || payload.error || (response.ok ? 'Saved.' : 'Failed to save.');

            return {response, payload};
        },

        renderPermissionOptions() {
            const select = document.getElementById('override-drawer-permission');
            if (!select) return;

            if (!this.state.permissions.length) {
                select.innerHTML = '<option value="">No permissions available</option>';
                return;
            }

            select.innerHTML = this.state.permissions.map((permission) => `
                <option value="${this.escapeHtml(permission.slug)}">${this.escapeHtml(permission.slug)}</option>
            `).join('');
        },

        renderRoles() {
            const list = document.getElementById('rbac-role-list');
            if (list) {
                if (!this.state.roles.length) {
                    list.innerHTML = '<div class="oc-rbac-loading">No roles found.</div>';
                    return;
                }

                list.innerHTML = this.state.roles.map((role) => `
                    <div class="oc-rbac-member" data-role-id="${Number(role.id)}">
                        <div>
                            <div class="oc-rbac-member__name">${this.escapeHtml(role.name)}</div>
                            <div class="oc-rbac-meta">${this.escapeHtml(role.slug)}${role.is_system ? ' · system role' : ''}</div>
                        </div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <button type="button" class="oc-btn oc-btn--ghost oc-btn--sm" onclick="rbacManager.focusRole(${Number(role.id)})">Edit permissions</button>
                            ${role.is_system ? '' : `<button type="button" class="oc-btn oc-btn--ghost oc-btn--sm oc-btn--danger" onclick="rbacManager.deleteRole(${Number(role.id)}, '${this.escapeJsString(role.name)}')">Delete</button>`}
                        </div>
                    </div>
                `).join('');
            }
        },

        renderMatrix() {
            const table = document.getElementById('rbac-matrix-table');
            const body = document.getElementById('rbac-matrix-body');
            if (!table || !body) return;

            const headRow = table.querySelector('thead tr');
            headRow.innerHTML = `<th>Permission</th>${this.state.roles.map((role) => `<th id="role-column-${Number(role.id)}">${this.escapeHtml(role.name)}</th>`).join('')}`;

            if (!this.state.permissions.length) {
                body.innerHTML = '<tr><td class="oc-rbac-loading">No permissions found.</td></tr>';
                return;
            }

            body.innerHTML = this.state.permissions.map((permission) => `
                <tr>
                    <td>
                        <strong>${this.escapeHtml(permission.slug)}</strong><br>
                        <span class="oc-rbac-meta">${this.escapeHtml(permission.group)}</span>
                    </td>
                    ${this.state.roles.map((role) => `
                        <td>
                            <input type="checkbox"
                                   data-role-id="${Number(role.id)}"
                                   data-permission-slug="${this.escapeHtml(permission.slug)}"
                                   ${Array.isArray(role.permission_ids) && role.permission_ids.includes(permission.id) ? 'checked' : ''}
                                   onchange="rbacManager.toggleRolePermission(this)">
                        </td>
                    `).join('')}
                </tr>
            `).join('');
        },

        renderOverrides() {
            const list = document.getElementById('rbac-overrides-list');
            if (!list) return;

            if (!this.state.members.length) {
                list.innerHTML = '<div class="oc-rbac-loading">No members found.</div>';
                return;
            }

            list.innerHTML = this.state.members.map((member) => {
                const memberRoleNames = (member.role_ids || [])
                    .map((roleId) => this.state.roles.find((role) => Number(role.id) === Number(roleId))?.name)
                    .filter(Boolean);
                const memberOverrides = this.state.overrides.filter((override) => Number(override.user_id) === Number(member.id));

                return `
                    <div class="oc-rbac-override-card">
                        <div class="oc-rbac-override-card__header">
                            <div>
                                <div class="oc-rbac-member__name">${this.escapeHtml(member.name)}</div>
                                <div class="oc-rbac-meta">Roles: ${this.escapeHtml(memberRoleNames.length ? memberRoleNames.join(', ') : 'none')}</div>
                            </div>
                            <button type="button"
                                    class="oc-btn oc-btn--ghost oc-btn--sm"
                                    onclick="rbacManager.openOverrideDrawer(${Number(member.id)}, '${this.escapeJsString(member.name)}')"
                                    aria-label="Add override for ${this.escapeHtml(member.name)}">
                                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" width="13" height="13" style="vertical-align:-1px;">
                                    <line x1="8" y1="2" x2="8" y2="14"/><line x1="2" y1="8" x2="14" y2="8"/>
                                </svg>
                                Add override
                            </button>
                        </div>
                        <div class="oc-rbac-current-overrides" id="member-overrides-${Number(member.id)}">
                            ${memberOverrides.length ? memberOverrides.map((override) => `
                                <span class="oc-rbac-override-pill"
                                      data-permission-slug="${this.escapeHtml(override.permission_slug)}"
                                      data-user-id="${Number(member.id)}"
                                      data-granted="${override.granted ? 'true' : 'false'}">
                                    <span class="oc-rbac-override-pill__type">${override.granted ? 'grant' : 'deny'}</span>
                                    <span class="oc-rbac-override-pill__slug">${this.escapeHtml(override.permission_slug)}</span>
                                    <button type="button"
                                            class="oc-rbac-override-pill__remove"
                                            aria-label="Remove override for ${this.escapeHtml(override.permission_slug)}"
                                            onclick="rbacManager.removeOverride(${Number(member.id)}, '${this.escapeJsString(override.permission_slug)}', this)">
                                        <svg viewBox="0 0 10 10" fill="none" stroke="currentColor" stroke-width="1.8" width="9" height="9">
                                            <line x1="1.5" y1="1.5" x2="8.5" y2="8.5"/><line x1="8.5" y1="1.5" x2="1.5" y2="8.5"/>
                                        </svg>
                                    </button>
                                </span>
                            `).join('') : '<span class="oc-rbac-meta oc-rbac-empty-overrides" data-empty-state="true">No direct overrides set.</span>'}
                        </div>
                    </div>
                `;
            }).join('');
        },

        renderAudit() {
            const list = document.getElementById('rbac-audit-list');
            if (!list) return;

            if (!this.state.audit.length) {
                list.innerHTML = '<div class="oc-rbac-loading">No audit entries yet.</div>';
                return;
            }

            list.innerHTML = this.state.audit.map((entry) => `
                <div class="oc-rbac-audit-item">
                    <div class="oc-rbac-member__name">${this.escapeHtml(entry.action)}</div>
                    <div class="oc-rbac-meta">
                        actor ${this.escapeHtml(entry.actor_user_id ?? 'system')} ·
                        target ${this.escapeHtml(entry.target_user_id ?? 'n/a')} ·
                        ${this.escapeHtml(entry.created_at ?? '')}
                    </div>
                </div>
            `).join('');
        },

        escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        },

        escapeJsString(value) {
            return String(value)
                .replace(/\\/g, '\\\\')
                .replace(/'/g, "\\'");
        },

        upsertOverridePill(userId, permissionSlug, granted) {
            const existing = this.state.overrides.find((override) => Number(override.user_id) === Number(userId) && override.permission_slug === permissionSlug);
            if (existing) {
                existing.granted = granted;
            } else {
                this.state.overrides.push({user_id: Number(userId), permission_slug: permissionSlug, granted});
            }

            const container = document.getElementById(`member-overrides-${userId}`);
            if (!container) return;

            container.querySelector('[data-empty-state="true"]')?.remove();

            let pill = container.querySelector(`.oc-rbac-override-pill[data-permission-slug="${CSS.escape(permissionSlug)}"]`);
            if (!pill) {
                pill = document.createElement('span');
                pill.className = 'oc-rbac-override-pill';
                pill.dataset.permissionSlug = permissionSlug;
                pill.dataset.userId = String(userId);
                container.appendChild(pill);
            }

            pill.dataset.granted = granted ? 'true' : 'false';
            pill.innerHTML = `
                <span class="oc-rbac-override-pill__type">${granted ? 'grant' : 'deny'}</span>
                <span class="oc-rbac-override-pill__slug">${this.escapeHtml(permissionSlug)}</span>
                <button type="button"
                        class="oc-rbac-override-pill__remove"
                        aria-label="Remove override for ${this.escapeHtml(permissionSlug)}"
                        onclick="rbacManager.removeOverride(${Number(userId)}, '${this.escapeJsString(permissionSlug)}', this)">
                    <svg viewBox="0 0 10 10" fill="none" stroke="currentColor" stroke-width="1.8" width="9" height="9">
                        <line x1="1.5" y1="1.5" x2="8.5" y2="8.5"/><line x1="8.5" y1="1.5" x2="1.5" y2="8.5"/>
                    </svg>
                </button>`;
        }
    };

    document.querySelectorAll('.oc-tabbar__tab').forEach((tab) => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.oc-tabbar__tab').forEach((button) => button.classList.remove('active'));
            document.querySelectorAll('.oc-tab-panel').forEach((panel) => panel.classList.remove('active'));
            tab.classList.add('active');
            document.getElementById(tab.dataset.tabTarget)?.classList.add('active');
        });
    });

    rbacManager.init().catch((error) => {
        const banner = document.getElementById('rbac-banner');
        if (banner) {
            banner.style.display = 'block';
            banner.className = 'oc-alert oc-alert--danger';
            banner.textContent = error.message || 'Failed to load RBAC data.';
        }
    });
</script>
@endsection
