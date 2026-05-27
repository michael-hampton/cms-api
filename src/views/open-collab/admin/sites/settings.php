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
            <?php if (empty($assignedUsers) || count($assignedUsers) === 0): ?>
                <div id="no-users-msg"
                     style="font-size:.85rem;color:var(--slate);padding:12px 0;">
                    No users have been assigned to this site yet.
                </div>
            <?php else: ?>
                <?php foreach ($assignedUsers as $u): ?>
                    <div class="oc-user-row" data-user-id="<?= $u['id'] ?>">
                        <div class="oc-user-row__avatar">
                            <?= strtoupper(substr($u['name'], 0, 1)) ?>
                        </div>
                        <div class="oc-user-row__body">
                            <span class="oc-user-row__name"><?= htmlspecialchars($u['name']) ?></span>
                            <span class="oc-user-row__email"><?= htmlspecialchars($u['email']) ?></span>
                        </div>
                        <button
                                type="button"
                                class="oc-btn oc-btn--ghost oc-btn--sm oc-btn--danger"
                                onclick="siteUsersManager.removeUser(<?= $u['id'] ?>, this)"
                                aria-label="Remove <?= htmlspecialchars($u['name']) ?>">
                            Remove
                        </button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
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
        <div class="oc-rbac-member-list">
            <?php foreach (($rbacSummary['roles'] ?? []) as $role): ?>
                <div class="oc-rbac-member">
                    <div>
                        <div class="oc-rbac-member__name"><?= htmlspecialchars($role['name']) ?></div>
                        <div class="oc-rbac-meta"><?= htmlspecialchars($role['slug']) ?><?= !empty($role['is_system']) ? ' · system role' : '' ?></div>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <button type="button" class="oc-btn oc-btn--ghost oc-btn--sm" onclick="rbacManager.focusRole(<?= (int) $role['id'] ?>)">Edit permissions</button>
                        <?php if (empty($role['is_system'])): ?>
                            <button type="button" class="oc-btn oc-btn--ghost oc-btn--sm oc-btn--danger" onclick="rbacManager.deleteRole(<?= (int) $role['id'] ?>, '<?= htmlspecialchars($role['name'], ENT_QUOTES, 'UTF-8') ?>')">Delete</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
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
            <table class="oc-rbac-table">
                <thead>
                <tr>
                    <th>Permission</th>
                    <?php foreach (($rbacSummary['roles'] ?? []) as $role): ?>
                        <th id="role-column-<?= (int) $role['id'] ?>"><?= htmlspecialchars($role['name']) ?></th>
                    <?php endforeach; ?>
                </tr>
                </thead>
                <tbody>
                <?php foreach (($rbacSummary['permissions'] ?? []) as $permission): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($permission['slug']) ?></strong><br>
                            <span class="oc-rbac-meta"><?= htmlspecialchars($permission['group']) ?></span>
                        </td>
                        <?php foreach (($rbacSummary['roles'] ?? []) as $role): ?>
                            <td>
                                <input type="checkbox"
                                       data-role-id="<?= (int) $role['id'] ?>"
                                       data-permission-slug="<?= htmlspecialchars($permission['slug']) ?>"
                                       <?= in_array($permission['id'], $role['permission_ids'] ?? [], true) ? 'checked' : '' ?>
                                       onchange="rbacManager.toggleRolePermission(this)">
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="oc-card oc-tab-panel" style="margin-top:28px;" id="rbac-overrides-panel">
    <div class="oc-card__header">
        <span class="oc-card__title">Overrides</span>
        <span class="oc-card__subtitle">Grant or revoke specific permissions for a site member.</span>
    </div>
    <div class="oc-card__body">
        <div class="oc-rbac-member-list">
            <?php foreach (($rbacSummary['members'] ?? []) as $member): ?>
                <form class="oc-rbac-override-row" onsubmit="rbacManager.saveOverride(event, <?= (int) $member['id'] ?>)">
                    <div>
                        <div class="oc-rbac-member__name"><?= htmlspecialchars($member['name']) ?></div>
                        <?php
                        $memberRoleNames = [];
                        foreach (($member['role_ids'] ?? []) as $memberRoleId) {
                            foreach (($rbacSummary['roles'] ?? []) as $roleOption) {
                                if ((int) $roleOption['id'] === (int) $memberRoleId) {
                                    $memberRoleNames[] = $roleOption['name'];
                                    break;
                                }
                            }
                        }
                        ?>
                        <div class="oc-rbac-meta">Current site roles: <?= htmlspecialchars($memberRoleNames ? implode(', ', $memberRoleNames) : 'none') ?></div>
                        <?php
                        $memberOverrides = array_values(array_filter(($rbacSummary['overrides'] ?? []), fn($override) => (int) $override['user_id'] === (int) $member['id']));
                        ?>
                        <div class="oc-rbac-current-overrides" id="member-overrides-<?= (int) $member['id'] ?>">
                            <?php if ($memberOverrides === []): ?>
                                <span class="oc-rbac-meta" data-empty-state="true">No direct overrides.</span>
                            <?php else: ?>
                                <?php foreach ($memberOverrides as $override): ?>
                                    <span class="oc-rbac-override-pill"
                                          data-permission-slug="<?= htmlspecialchars($override['permission_slug']) ?>"
                                          data-granted="<?= !empty($override['granted']) ? 'true' : 'false' ?>">
                                        <?= htmlspecialchars($override['permission_slug']) ?> · <?= !empty($override['granted']) ? 'grant' : 'deny' ?>
                                    </span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <select name="permission_slug">
                        <?php foreach (($rbacSummary['permissions'] ?? []) as $permission): ?>
                            <option value="<?= htmlspecialchars($permission['slug']) ?>"><?= htmlspecialchars($permission['slug']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="granted">
                        <option value="true">Grant</option>
                        <option value="false">Deny</option>
                    </select>
                    <button type="submit" class="oc-btn oc-btn--ghost oc-btn--sm">Save</button>
                </form>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="oc-card oc-tab-panel" style="margin-top:28px;" id="rbac-audit-panel">
    <div class="oc-card__header">
        <span class="oc-card__title">Audit Log</span>
        <span class="oc-card__subtitle">Recent RBAC changes for this site.</span>
    </div>
    <div class="oc-card__body">
        <div class="oc-rbac-audit-list">
            <?php foreach (($rbacSummary['audit'] ?? []) as $entry): ?>
                <div class="oc-rbac-audit-item">
                    <div class="oc-rbac-member__name"><?= htmlspecialchars($entry['action']) ?></div>
                    <div class="oc-rbac-meta">
                        actor <?= htmlspecialchars((string) ($entry['actor_user_id'] ?? 'system')) ?> ·
                        target <?= htmlspecialchars((string) ($entry['target_user_id'] ?? 'n/a')) ?> ·
                        <?= htmlspecialchars((string) $entry['created_at']) ?>
                    </div>
                </div>
            <?php endforeach; ?>
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

    .oc-rbac-override-row {
        display:grid;
        grid-template-columns:1.4fr 1fr 110px auto;
        gap:10px;
        align-items:center;
    }

    .oc-rbac-current-overrides {
        display:flex;
        flex-wrap:wrap;
        gap:6px;
        margin-top:8px;
    }

    .oc-rbac-override-pill {
        display:inline-flex;
        align-items:center;
        padding:4px 8px;
        border-radius:999px;
        font-size:.72rem;
        font-weight:600;
        background:var(--slate-pale);
        color:var(--navy);
    }

    .oc-rbac-override-pill[data-granted="false"] {
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
                    list.innerHTML = `<div id="no-users-msg" style="font-size:.85rem;color:var(--slate);padding:12px 0;">
                        No users have been assigned to this site yet.
                    </div>`;
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

    const rbacManager = {
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
            }, {reload: false});
        },

        async updateMemberRoles(select) {
            const userId = select.dataset.userId;
            const roleIds = Array.from(select.selectedOptions).map(option => Number(option.value));

            await this.request(`/api/<?= htmlspecialchars($site) ?>/open-collab/admin/contributors/${userId}/roles`, {
                method: 'POST',
                body: JSON.stringify({role_ids: roleIds}),
            }, {reload: false});
        },

        async saveOverride(event, userId) {
            event.preventDefault();
            const form = event.target;
            const permissionSlug = form.permission_slug.value;
            const granted = form.granted.value === 'true';

            const result = await this.request(`/api/<?= htmlspecialchars($site) ?>/open-collab/admin/rbac/overrides/${userId}`, {
                method: 'POST',
                body: JSON.stringify({permission_slug: permissionSlug, granted}),
            }, {reload: false});

            if (result?.response?.ok) {
                this.upsertOverridePill(userId, permissionSlug, granted);
            }
        },

        async createRole(event) {
            event.preventDefault();
            const form = event.target;
            const name = form.name.value.trim();
            const slug = form.slug.value.trim();

            await this.request(`/api/<?= htmlspecialchars($site) ?>/open-collab/admin/rbac/roles`, {
                method: 'POST',
                body: JSON.stringify({
                    name,
                    slug: slug || null,
                    permission_slugs: [],
                }),
            }, {reload: true});
        },

        async deleteRole(roleId, roleName) {
            if (!confirm(`Delete the role "${roleName}"?`)) {
                return;
            }

            await this.request(`/api/<?= htmlspecialchars($site) ?>/open-collab/admin/rbac/roles/${roleId}`, {
                method: 'DELETE',
            }, {reload: true});
        },

        async request(url, options, config = {}) {
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

            if (response.ok && config.reload === true) {
                setTimeout(() => window.location.reload(), 400);
            }

            return {response, payload};
        },

        upsertOverridePill(userId, permissionSlug, granted) {
            const container = document.getElementById(`member-overrides-${userId}`);
            if (!container) return;

            container.querySelector('[data-empty-state="true"]')?.remove();

            let pill = container.querySelector(`[data-permission-slug="${CSS.escape(permissionSlug)}"]`);
            if (!pill) {
                pill = document.createElement('span');
                pill.className = 'oc-rbac-override-pill';
                pill.dataset.permissionSlug = permissionSlug;
                container.appendChild(pill);
            }

            pill.dataset.granted = granted ? 'true' : 'false';
            pill.textContent = `${permissionSlug} · ${granted ? 'grant' : 'deny'}`;
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
</script>
@endsection
