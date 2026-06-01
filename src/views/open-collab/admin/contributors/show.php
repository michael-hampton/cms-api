@section('logic')
<?php
/**
 * Template: open-collab/admin/contributors/show.php
 * Variables:
 * $contributor  — array of contributor data
 * $invitations  — Collection of Invitation models for this contributor's email
 * $site         — string
 * $currentUser  — AuthenticatedUser
 */

$isActive = (bool)($contributor['is_active'] ?? true);
$allowedComponentKeys = $allowedComponentKeys ?? [];
$overviewPanels = $overviewPanels ?? [];
$capabilitiesPanels = $capabilitiesPanels ?? [];
$hasOverviewPanels = !empty($overviewPanels);
$hasCapabilitiesPanels = !empty($capabilitiesPanels);
$canSendInvitation = in_array('contributor.invitation_action', $allowedComponentKeys, true);
$canViewViolations = in_array('contributor.violation_action', $allowedComponentKeys, true);
$canManageStatus = in_array('contributor.manage_status_action', $allowedComponentKeys, true);
$canManageSiteAccess = in_array('contributor.site_access_action', $allowedComponentKeys, true);
$canManageRole = in_array('contributor.role_action', $allowedComponentKeys, true);
$canManageCapabilities = in_array('contributor.capabilities_manage_action', $allowedComponentKeys, true);
?>
@endsection

@extends('open-collab/layouts/admin')

@section('content')

<style>
    .oc-tab-panel { display: none; }
    .oc-tab-panel.active { display: flex; flex-direction: column; gap: 20px; }
    .oc-tabbar__tab { border: none; background: none; border-bottom: 2px solid transparent; color: var(--slate); padding: 8px 4px; font-weight: 500; cursor: pointer; font-size: .95rem; }
    .oc-tabbar__tab.active { border-bottom-color: var(--navy); color: var(--navy); font-weight: 600; }

    /* ── Capabilities Table Grid Styles ────────────────────────────────────── */
    .oc-capabilities-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        margin-top: 4px;
        text-align: left;
    }
    .oc-capabilities-table th {
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--slate);
        padding: 12px 16px;
        border-bottom: 2px solid #e2e8f0;
    }
    .oc-capabilities-table td {
        padding: 14px 16px;
        border-bottom: 1px solid #e2e8f0;
        vertical-align: middle;
    }
    .oc-capabilities-table tbody tr:hover {
        background-color: #f8fafc;
    }
    .oc-cap-label {
        font-weight: 600;
        color: var(--navy);
        font-size: 0.92rem;
    }
    .oc-cap-key {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 0.75rem;
        color: var(--slate);
        background: #f1f5f9;
        padding: 2px 6px;
        border-radius: 4px;
        display: inline-block;
        margin-top: 4px;
    }
    .oc-cap-desc {
        font-size: 0.8rem;
        color: var(--slate);
        margin-top: 6px;
        line-height: 1.4;
        max-width: 520px;
    }
    .oc-cap-effective {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.82rem;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 6px;
    }
    .oc-cap-effective--yes {
        color: #16a34a;
        background-color: #f0fdf4;
    }
    .oc-cap-effective--no {
        color: var(--red);
        background-color: #fef2f2;
    }
    .oc-cap-effective svg {
        flex-shrink: 0;
    }
    .oc-cap-source-badge {
        font-size: 0.75rem;
        font-weight: 500;
        padding: 4px 10px;
        border-radius: 12px;
        display: inline-block;
    }
    .oc-cap-source-badge--role {
        background-color: #eff6ff;
        color: #1d4ed8;
    }
    .oc-cap-source-badge--direct_grant {
        background-color: #f0fdf4;
        color: #16a34a;
    }
    .oc-cap-source-badge--direct_deny {
        background-color: #fef2f2;
        color: var(--red);
    }
    .oc-cap-source-badge--system {
        background-color: #f1f5f9;
        color: #475569;
    }
    .oc-cap-actions {
        display: flex;
        gap: 8px;
        justify-content: flex-end;
    }
    .oc-capabilities-loading,
    .oc-capabilities-empty {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        padding: 48px 24px;
        color: var(--slate);
        background: #fff;
        border: 1px dashed #cbd5e1;
        border-radius: 8px;
        font-size: 0.9rem;
    }
</style>

<div id="status-toast"
     style="position:fixed;bottom:24px;left:50%;transform:translateX(-50%);
            background:var(--navy);color:#fff;padding:9px 20px;border-radius:20px;
            font-size:.8rem;font-weight:500;opacity:0;transition:opacity .3s;
            z-index:300;pointer-events:none;"></div>

<div id="close-modal"
     style="display:none;position:fixed;inset:0;background:rgba(15,25,41,.55);z-index:500;place-items:center;">
    <div style="background:#fff;border-radius:12px;padding:28px 32px;max-width:440px;width:92%;
                box-shadow:0 20px 60px rgba(0,0,0,.2);">
        <h3 style="font-family:var(--font-display);font-size:1.2rem;color:var(--navy);margin-bottom:6px;">
            Close contributor account
        </h3>
        <p style="font-size:.85rem;color:var(--slate);margin-bottom:20px;">
            This is irreversible through the normal UI. All pending payouts will be cancelled
            and unpublished articles archived.
        </p>
        <div class="oc-form-group">
            <label class="oc-label" for="close-reason">Reason</label>
            <select class="oc-select" id="close-reason">
                <option value="">Select reason…</option>
                <option value="Repeated policy breaches.">Repeated policy breaches</option>
                <option value="Fraud or misrepresentation.">Fraud or misrepresentation</option>
                <option value="Inactive account — no activity in 6+ months.">Inactive account</option>
                <option value="Contributor requested closure.">Contributor requested closure</option>
                <option value="Other.">Other</option>
            </select>
        </div>
        <div id="close-modal-errors" class="oc-form-errors" style="display:none;margin-bottom:12px;"></div>
        <div style="display:flex;gap:10px;">
            <button data-action="close-close-modal" class="oc-btn oc-btn--ghost" style="flex:1;">Cancel</button>
            <button class="oc-btn oc-btn--danger" style="flex:1;" id="close-confirm-btn">
                Close account
            </button>
        </div>
    </div>
</div>

<div class="oc-grid-sidebar" style="align-items:start;gap:24px;">

    <div style="display:flex;flex-direction:column;gap:20px;">

        <?php if ($hasOverviewPanels || $hasCapabilitiesPanels): ?>
            <div class="oc-tabbar" style="display:flex;gap:20px;border-bottom:1px solid #e2e8f0;margin-bottom:4px;">
                <?php if ($hasOverviewPanels): ?>
                    <button type="button" class="oc-tabbar__tab active" data-tab-target="contributor-overview-panel">Overview</button>
                <?php endif; ?>
                <?php if ($hasCapabilitiesPanels): ?>
                    <button type="button" class="oc-tabbar__tab <?= !$hasOverviewPanels ? 'active' : '' ?>" data-tab-target="contributor-capabilities-panel">Capabilities Overrides</button>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($hasOverviewPanels): ?>
            <div id="contributor-overview-panel" class="oc-tab-panel active">
                <?php foreach ($overviewPanels as $panel): ?>
                    <?= $panel->render([
                            'contributor' => $contributor,
                            'invitations' => $invitations,
                            'site' => $site,
                            'isActive' => $isActive,
                            'canManageRole' => $canManageRole,
                            'canManageSiteAccess' => $canManageSiteAccess,
                            'canSendInvitation' => $canSendInvitation,
                            'canManageCapabilities' => $canManageCapabilities,
                    ]) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($hasCapabilitiesPanels): ?>
            <div id="contributor-capabilities-panel" class="oc-tab-panel <?= !$hasOverviewPanels ? 'active' : '' ?>">
                <div class="oc-card">
                    <div class="oc-card__body" id="capabilities-list">
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$hasOverviewPanels && !$hasCapabilitiesPanels): ?>
            <div class="oc-card">
                <div class="oc-card__body" style="padding:32px 24px;text-align:center;color:var(--slate);">
                    No panels are available for this contributor.
                </div>
            </div>
        <?php endif; ?>

    </div>

    <div style="position:sticky;top:calc(var(--header-h,64px) + 20px);display:flex;flex-direction:column;gap:16px;">

        <div class="oc-card">
            <div class="oc-card__body" style="padding:18px 20px;display:flex;flex-direction:column;gap:10px;">
                <div style="font-size:.72rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;
                             color:var(--slate);margin-bottom:4px;">Quick actions
                </div>

                <?php if ($canManageStatus): ?>
                    <button data-role="status-toggle-btn" data-action="<?= $isActive ? 'deactivate' : 'reactivate' ?>"
                            class="oc-btn oc-btn--ghost oc-btn--block"
                            style="<?= $isActive ? 'border-color:#fecaca;color:var(--red);' : 'border-color:#bbf7d0;color:var(--green);' ?>">
                        <?php if ($isActive): ?>
                            <svg viewBox="0 0 20 20" fill="currentColor" width="14">
                                <path fill-rule="evenodd"
                                      d="M13.477 14.89A6 6 0 015.11 6.524L13.476 14.89zm1.414-1.414L6.524 5.11A6 6 0 0114.89 13.476zM18 10a8 8 0 11-16 0 8 8 0 0116 0z"
                                      clip-rule="evenodd"/>
                            </svg>
                            Deactivate account
                        <?php else: ?>
                            <svg viewBox="0 0 20 20" fill="currentColor" width="14">
                                <path fill-rule="evenodd"
                                      d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                      clip-rule="evenodd"/>
                            </svg>
                            Reactivate account
                        <?php endif; ?>
                    </button>
                <?php endif; ?>

                <?php if ($canSendInvitation): ?>
                    <button data-action="send-invitation"
                            class="oc-btn oc-btn--ghost oc-btn--block">
                        <svg viewBox="0 0 20 20" fill="currentColor" width="14">
                            <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                            <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
                        </svg>
                        Send new invitation
                    </button>
                <?php endif; ?>

                <?php if ($canViewViolations): ?>
                    <a href="/<?= htmlspecialchars($site) ?>/open-collab/admin/contributors/<?= (int)$contributor['id'] ?>/violations"
                       class="oc-btn oc-btn--ghost oc-btn--block">
                        <svg viewBox="0 0 20 20" fill="currentColor" width="14">
                            <path fill-rule="evenodd"
                                  d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                  clip-rule="evenodd"/>
                        </svg>
                        View violations
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($canManageStatus): ?>
            <div class="oc-danger-zone">
                <div class="oc-danger-zone__title">Danger Zone</div>
                <button data-action="open-close-modal"
                        class="oc-btn oc-btn--danger oc-btn--block oc-btn--sm">
                    Close account permanently
                </button>
            </div>
        <?php endif; ?>

    </div>

</div>

<div id="action-modal"
     style="display:none;position:fixed;inset:0;background:rgba(15,25,41,.55);z-index:500;place-items:center;">
    <div style="background:#fff;border-radius:12px;padding:28px 32px;max-width:400px;width:92%;box-shadow:0 20px 60px rgba(0,0,0,.2);">
        <h3 id="action-modal-title"
            style="font-family:var(--font-display);font-size:1.2rem;color:var(--navy);margin-bottom:6px;">Update Status</h3>
        <p id="action-modal-desc" style="font-size:.85rem;color:var(--slate);margin-bottom:20px;"></p>

        <div class="oc-form-group">
            <label class="oc-label" for="action-reason">Reason for change</label>
            <textarea class="oc-input" id="action-reason" rows="3"
                      placeholder="Explain why this status is changing..."></textarea>
        </div>

        <div id="action-modal-errors" class="oc-form-errors" style="display:none;margin-bottom:12px;"></div>

        <div style="display:flex;gap:10px;">
            <button data-action="close-action-modal" class="oc-btn oc-btn--ghost" style="flex:1;">Cancel</button>
            <button id="action-confirm-btn" class="oc-btn" style="flex:1;">Confirm</button>
        </div>
    </div>
</div>

@include('open-collab/admin/contributors/panels/capabilities', ['canManageCapabilities' => $canManageCapabilities])

@endsection

@section('scripts')

<script>
    const CAN_MANAGE_CAPABILITIES = <?= $canManageCapabilities ? 'true' : 'false' ?>;

    class ContributorApi {
        /** @param {string} site  @param {()=>string} tokenFn */
        constructor(site, tokenFn) {
            this._site = site;
            this._token = tokenFn;
        }

        // ── URL helpers ──────────────────────────────────────────────────────────

        _base() {
            return `/api/${this._site}/open-collab/admin`;
        }

        _contributor(id) {
            return `${this._base()}/contributors/${id}`;
        }

        _invitation(id) {
            return `${this._base()}/invitations/${id}`;
        }

        _invitations() {
            return `/api/${this._site}/open-collab/invitations`;
        }

        _capability(cid, key) {
            return `${this._contributor(cid)}/capabilities/${encodeURIComponent(key)}`;
        }

        urls = {
            contributorStatus: (id, type) => `${this._contributor(id)}/${type}`,
            contributorRole: (id) => `${this._contributor(id)}/role`,
            grantAccess: (id) => `${this._contributor(id)}/grant-access`,
            revokeAccess: (id) => `${this._contributor(id)}/revoke-access`,
            closeAccount: (id) => `${this._contributor(id)}/close`,
            invitationSend: () => this._invitations(),
            invitationResend: (id) => `${this._invitation(id)}/resend`,
            invitationRevoke: (id) => this._invitation(id),
            capabilityList: (cid) => `${this._contributor(cid)}/capabilities`,
            capabilityGrant: (cid, key) => `${this._capability(cid, key)}/grant`,
            capabilityRevoke: (cid, key) => `${this._capability(cid, key)}/revoke`,
            capabilityReset: (cid, key) => `${this._capability(cid, key)}/override`,
        };

        // ── Fetch wrapper ────────────────────────────────────────────────────────

        async request(url, {method = 'GET', body = null} = {}) {
            const headers = {
                'Accept': 'application/json',
                'Authorization': `Bearer ${this._token()}`,
            };
            if (body !== null) {
                headers['Content-Type'] = 'application/json';
            }

            const res = await fetch(url, {method, headers, body: body ? JSON.stringify(body) : undefined});
            const data = await res.json().catch(() => ({}));
            return {ok: res.ok, status: res.status, data};
        }

        // ── Named methods ────────────────────────────────────────────────────────

        updateStatus(id, type, reason) {
            return this.request(this.urls.contributorStatus(id, type), {method: 'POST', body: {reason}});
        }

        updateRole(id, role, reason) {
            return this.request(this.urls.contributorRole(id), {method: 'POST', body: {role, reason}});
        }

        grantAccess(id) {
            return this.request(this.urls.grantAccess(id), {method: 'POST'});
        }

        revokeAccess(id) {
            return this.request(this.urls.revokeAccess(id), {method: 'POST'});
        }

        closeAccount(id, reason) {
            return this.request(this.urls.closeAccount(id), {method: 'POST', body: {reason}});
        }

        sendInvitation(email) {
            return this.request(this.urls.invitationSend(), {method: 'POST', body: {email}});
        }

        resendInvitation(id) {
            return this.request(this.urls.invitationResend(id), {method: 'POST'});
        }

        revokeInvitation(id) {
            return this.request(this.urls.invitationRevoke(id), {method: 'DELETE'});
        }

        listCapabilities(cid) {
            return this.request(this.urls.capabilityList(cid));
        }

        grantCapability(cid, key) {
            return this.request(this.urls.capabilityGrant(cid, key), {method: 'POST'});
        }

        revokeCapability(cid, key) {
            return this.request(this.urls.capabilityRevoke(cid, key), {method: 'POST'});
        }

        resetCapability(cid, key) {
            return this.request(this.urls.capabilityReset(cid, key), {method: 'DELETE'});
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // ContributorState — immutable-ish snapshot; emits 'statechange' on the page root
    // ─────────────────────────────────────────────────────────────────────────────
    class ContributorState {
        #state;
        #root;

        constructor(initial, root) {
            this.#state = {...initial};
            this.#root = root;
        }

        get() {
            return {...this.#state};
        }

        update(patch) {
            this.#state = {...this.#state, ...patch};
            this.#root.dispatchEvent(new CustomEvent('statechange', {detail: this.get()}));
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // ToastService
    // ─────────────────────────────────────────────────────────────────────────────
    class ToastService {
        #el;
        #timer;

        constructor(elementId) {
            this.#el = document.getElementById(elementId);
        }

        show(message, success = true) {
            if (!this.#el) return;
            clearTimeout(this.#timer);
            this.#el.textContent = message;
            this.#el.style.background = success ? 'var(--navy)' : 'var(--red)';
            this.#el.style.opacity = '1';
            this.#timer = setTimeout(() => {
                this.#el.style.opacity = '0';
            }, 2800);
        }

        success(msg) {
            this.show(msg, true);
        }

        error(msg) {
            this.show(msg, false);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // ModalController — drives the shared action modal (deactivate/reactivate/role)
    //                   and the close-account modal
    // ─────────────────────────────────────────────────────────────────────────────
    class ModalController {
        #onSubmit = null; // set per-open

        // ── Action modal (status / role) ─────────────────────────────────────────

        openAction({title, description, confirmLabel, confirmClass, onSubmit}) {
            document.getElementById('action-modal-title').textContent = title;
            document.getElementById('action-modal-desc').textContent = description;
            document.getElementById('action-reason').value = '';
            document.getElementById('action-modal-errors').style.display = 'none';

            const btn = document.getElementById('action-confirm-btn');
            btn.className = `oc-btn ${confirmClass}`;
            btn.textContent = confirmLabel;

            this.#onSubmit = onSubmit;
            document.getElementById('action-modal').style.display = 'grid';
            document.getElementById('action-reason').focus();
        }

        closeAction() {
            document.getElementById('action-modal').style.display = 'none';
            document.getElementById('action-reason').value = '';
            document.getElementById('action-modal-errors').style.display = 'none';
            this.#onSubmit = null;
        }

        async submitAction() {
            const reason = document.getElementById('action-reason').value.trim();
            const errBox = document.getElementById('action-modal-errors');
            const btn = document.getElementById('action-confirm-btn');

            if (!reason) {
                errBox.textContent = 'Please provide a reason for this action.';
                errBox.style.display = 'block';
                return;
            }

            const originalLabel = btn.textContent;
            btn.disabled = true;
            btn.innerHTML = '<div class="oc-spinner"></div> Processing…';
            errBox.style.display = 'none';

            try {
                await this.#onSubmit(reason);
                this.closeAction();
            } catch (err) {
                errBox.textContent = err.message || 'Request failed.';
                errBox.style.display = 'block';
                btn.disabled = false;
                btn.textContent = originalLabel;
            }
        }

        // ── Close-account modal ──────────────────────────────────────────────────

        openClose() {
            document.getElementById('close-reason').value = '';
            document.getElementById('close-modal-errors').style.display = 'none';
            document.getElementById('close-modal').style.display = 'grid';
        }

        closeClose() {
            document.getElementById('close-modal').style.display = 'none';
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // CapabilityDrawer
    // ─────────────────────────────────────────────────────────────────────────────
    class CapabilityDrawer {
        #onSave = null;

        open({title, subtitle, capKey, capLabel, capDescription, defaultAction, onSave}) {
            document.getElementById('capability-drawer-title').textContent = title;
            document.getElementById('capability-drawer-subtitle').textContent = subtitle;
            document.getElementById('capability-drawer-banner').style.display = 'none';

            const sel = document.getElementById('capability-drawer-select');
            sel.innerHTML = `<option value="${_esc(capKey)}">${_esc(capLabel || capKey)}</option>`;

            document.getElementById('capability-drawer-desc').textContent = capDescription || '';

            const radio = document.querySelector(`#capability-drawer input[name="cap_action"][value="${defaultAction}"]`);
            if (radio) radio.checked = true;

            const saveBtn = document.getElementById('capability-drawer-save');
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save';

            this.#onSave = onSave;

            const backdrop = document.getElementById('capability-drawer-backdrop');
            const drawer = document.getElementById('capability-drawer');
            backdrop.style.display = 'block';
            drawer.style.display = 'flex';
            requestAnimationFrame(() => {
                drawer.style.transform = 'translateX(0)';
            });
        }

        close() {
            const drawer = document.getElementById('capability-drawer');
            drawer.style.transform = 'translateX(100%)';
            drawer.addEventListener('transitionend', () => {
                drawer.style.display = 'none';
                document.getElementById('capability-drawer-backdrop').style.display = 'none';
            }, {once: true});
            this.#onSave = null;
        }

        async save() {
            if (!this.#onSave) return;

            const action = document.querySelector('#capability-drawer input[name="cap_action"]:checked')?.value || 'grant';
            const btn = document.getElementById('capability-drawer-save');
            const banner = document.getElementById('capability-drawer-banner');
            const original = btn.textContent;

            btn.disabled = true;
            btn.textContent = 'Saving…';
            banner.style.display = 'none';

            try {
                await this.#onSave(action);
                this.close();
            } catch (err) {
                banner.className = 'oc-alert oc-alert--danger';
                banner.textContent = err.message || 'Failed to save.';
                banner.style.display = 'block';
                btn.disabled = false;
                btn.textContent = original;
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // ContributorRenderer — all DOM writes
    // ─────────────────────────────────────────────────────────────────────────────
    class ContributorRenderer {
        // ── Status badge ─────────────────────────────────────────────────────────

        updateStatusBadge(isActive) {
            const badge = document.querySelector('[data-role="status-badge"]');
            if (!badge) return;
            badge.className = `oc-badge ${isActive ? 'oc-badge--published' : 'oc-badge--revoked'}`;
            badge.textContent = isActive ? 'Active' : 'Inactive';
        }

        updateStatusButton(isActive) {
            const btn = document.querySelector('[data-role="status-toggle-btn"]');
            if (!btn) return;

            const deactivateSvg = `<svg viewBox="0 0 20 20" fill="currentColor" width="14"><path fill-rule="evenodd" d="M13.477 14.89A6 6 0 015.11 6.524L13.476 14.89zm1.414-1.414L6.524 5.11A6 6 0 0114.89 13.476zM18 10a8 8 0 11-16 0 8 8 0 0116 0z" clip-rule="evenodd"/></svg>`;
            const reactivateSvg = `<svg viewBox="0 0 20 20" fill="currentColor" width="14"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>`;

            if (isActive) {
                btn.dataset.action = 'deactivate';
                btn.style.cssText = 'border-color:#fecaca;color:var(--red);';
                btn.innerHTML = `${deactivateSvg} Deactivate account`;
            } else {
                btn.dataset.action = 'reactivate';
                btn.style.cssText = 'border-color:#bbf7d0;color:var(--green);';
                btn.innerHTML = `${reactivateSvg} Reactivate account`;
            }
        }

        // ── Role select ──────────────────────────────────────────────────────────

        updateRoleSelect(role) {
            const sel = document.getElementById('role-select');
            if (sel) sel.value = role;
        }

        // ── Invitation row ───────────────────────────────────────────────────────

        markInvitationRevoked(invId) {
            const row = document.querySelector(`[data-invitation-id="${invId}"]`);
            if (!row) return;

            const statusCell = row.querySelector('[data-role="invitation-status"]');
            if (statusCell) {
                statusCell.innerHTML = '<span class="oc-badge oc-badge--revoked">Revoked</span>';
            }
            const actionCell = row.querySelector('[data-role="invitation-action"]');
            if (actionCell) {
                // Replace revoke btn with resend btn
                actionCell.innerHTML = `<button type="button"
                class="oc-btn oc-btn--ghost oc-btn--sm" style="font-size:.7rem;"
                data-action="resend-invitation"
                data-invitation-id="${invId}">Resend</button>`;
            }
        }

        markInvitationResent(invId) {
            const row = document.querySelector(`[data-invitation-id="${invId}"]`);
            if (!row) return;

            const statusCell = row.querySelector('[data-role="invitation-status"]');
            if (statusCell) {
                statusCell.innerHTML = '<span class="oc-badge oc-badge--waiting-approval">Pending</span>';
            }
            const actionCell = row.querySelector('[data-role="invitation-action"]');
            if (actionCell) {
                actionCell.innerHTML = `<button type="button"
                class="oc-btn oc-btn--ghost oc-btn--sm" style="font-size:.7rem;border-color:#fecaca;color:var(--red);"
                data-action="revoke-invitation"
                data-invitation-id="${invId}">Revoke</button>`;
            }
        }

        // ── Capabilities ─────────────────────────────────────────────────────────

        renderCapabilitiesLoading() {
            const el = document.getElementById('capabilities-list');
            if (el) el.innerHTML = `<div class="oc-capabilities-loading">
            <div class="oc-spinner" style="width:20px;height:20px;"></div>
            <span style="font-size:.85rem;color:var(--slate);">Loading capabilities…</span>
        </div>`;
        }

        renderCapabilitiesError(message) {
            const el = document.getElementById('capabilities-list');
            if (el) el.innerHTML = `<div class="oc-capabilities-empty" style="color:var(--red);">${_esc(message)}</div>`;
        }

        renderCapabilities(capabilities) {
            const el = document.getElementById('capabilities-list');
            if (!el) return;

            if (!capabilities.length) {
                el.innerHTML = '<div class="oc-capabilities-empty">No capabilities defined for this site.</div>';
                return;
            }

            const sourceLabels = {
                role: 'Role-derived',
                direct_grant: 'Direct grant',
                direct_deny: 'Direct deny',
                system: 'System',
            };

            const rows = capabilities.map((cap) => {
                const sourceLabel = sourceLabels[cap.source] ?? cap.source ?? '—';
                const checkSvg = `<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5" width="12" height="12"><polyline points="2,8 6,12 14,4"/></svg>`;
                const crossSvg = `<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5" width="10" height="10"><line x1="3" y1="3" x2="13" y2="13"/><line x1="13" y1="3" x2="3" y2="13"/></svg>`;
                const effectiveIcon = cap.effective ? checkSvg : crossSvg;
                const canGrant = !cap.directGrant;
                const canDeny = !cap.directDeny;
                const canReset = cap.directGrant || cap.directDeny;

                const grantBtn = CAN_MANAGE_CAPABILITIES && canGrant
                    ? `<button type="button" class="oc-btn oc-btn--ghost oc-btn--sm"
                           style="border-color:#bbf7d0;color:#16a34a;font-size:.72rem;"
                           data-action="grant-capability"
                           data-cap-key="${_esc(cap.key)}">Grant</button>`
                    : '';

                const denyBtn = CAN_MANAGE_CAPABILITIES && canDeny
                    ? `<button type="button" class="oc-btn oc-btn--ghost oc-btn--sm"
                           style="border-color:#fecaca;color:var(--red);font-size:.72rem;"
                           data-action="deny-capability"
                           data-cap-key="${_esc(cap.key)}">Deny</button>`
                    : '';

                const resetBtn = CAN_MANAGE_CAPABILITIES && canReset
                    ? `<button type="button" class="oc-btn oc-btn--ghost oc-btn--sm"
                           style="font-size:.72rem;"
                           data-action="reset-capability"
                           data-cap-key="${_esc(cap.key)}">Reset</button>`
                    : '';

                return `<tr data-cap-key="${_esc(cap.key)}">
                <td>
                    <div class="oc-cap-label">${_esc(cap.label || cap.key)}</div>
                    <div class="oc-cap-key">${_esc(cap.key)}</div>
                    ${cap.description ? `<div class="oc-cap-desc">${_esc(cap.description)}</div>` : ''}
                </td>
                <td>
                    <span class="oc-cap-effective ${cap.effective ? 'oc-cap-effective--yes' : 'oc-cap-effective--no'}">
                        ${effectiveIcon}${cap.effective ? 'Allowed' : 'Denied'}
                    </span>
                </td>
                <td>
                    <span class="oc-cap-source-badge oc-cap-source-badge--${_esc(cap.source ?? 'role')}">${_esc(sourceLabel)}</span>
                </td>
                <td>
                    <div class="oc-cap-actions">${grantBtn}${denyBtn}${resetBtn}</div>
                </td>
            </tr>`;
            }).join('');

            el.innerHTML = `<div style="overflow-x:auto; margin: -18px -20px;">
            <table class="oc-capabilities-table">
                <thead>
                    <tr>
                        <th style="padding-left: 20px;">Capability</th>
                        <th>Effective</th>
                        <th>Source</th>
                        <th style="text-align:right; padding-right: 20px;">Actions</th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
        </div>`;
        }

        setButtonLoading(btn, loadingText) {
            btn.disabled = true;
            btn.dataset.originalText = btn.textContent;
            btn.textContent = loadingText;
        }

        restoreButton(btn, fallbackText = null) {
            btn.disabled = false;
            btn.textContent = btn.dataset.originalText ?? fallbackText ?? 'Error';
            delete btn.dataset.originalText;
        }
    }

    function _esc(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // ContributorAdminPage — entry point; owns all event wiring
    // ─────────────────────────────────────────────────────────────────────────────
    const ContributorAdminPage = {
        // ── Bootstrap ────────────────────────────────────────────────────────────

        init(config) {
            const token = () => localStorage.getItem('oc_token') || '';

            this._api = new ContributorApi(config.site, token);
            this._state = new ContributorState({
                contributorId: config.contributor.id,
                contributorEmail: config.contributor.email,
                isActive: config.contributor.is_active,
                role: config.contributor.role,
                capabilities: [],
                capabilitiesLoaded: false,
            }, document.documentElement);

            this._toast = new ToastService('status-toast');
            this._modal = new ModalController();
            this._drawer = new CapabilityDrawer();
            this._renderer = new ContributorRenderer();

            this._bindEvents();
            this._bindTabs();
            this._bindModalBackdrops();
        },

        // ── Event delegation ─────────────────────────────────────────────────────

        _bindEvents() {
            // Single delegated listener on the document body covers all buttons,
            // including those rendered dynamically (invitation rows, capability rows).
            document.body.addEventListener('click', (e) => {
                const btn = e.target.closest('[data-action]');
                if (!btn) return;
                e.preventDefault();
                this._dispatch(btn.dataset.action, btn);
            });

            // Role select — change event
            document.getElementById('role-select')?.addEventListener('change', (e) => {
                this._onRoleChange(e.target.value, e.target);
            });

            // Action modal confirm button
            document.getElementById('action-confirm-btn')?.addEventListener('click', () => {
                this._modal.submitAction();
            });

            // Close-account confirm button
            document.getElementById('close-confirm-btn')?.addEventListener('click', () => {
                this._submitClose();
            });

            // Capability drawer save button
            document.getElementById('capability-drawer-save')?.addEventListener('click', () => {
                this._drawer.save();
            });
        },

        _dispatch(action, btn) {
            const id = this._state.get().contributorId;
            const invId = btn.dataset.invitationId ? Number(btn.dataset.invitationId) : null;
            const capKey = btn.dataset.capKey ?? null;

            switch (action) {
                case 'deactivate':
                    return this._onStatusToggle(id, 'deactivate');
                case 'reactivate':
                    return this._onStatusToggle(id, 'reactivate');
                case 'grant-access':
                    return this._onGrantAccess(id, btn);
                case 'revoke-access':
                    return this._onRevokeAccess(id, btn);
                case 'send-invitation':
                    return this._onSendInvitation();
                case 'resend-invitation':
                    return this._onResendInvitation(invId, btn);
                case 'revoke-invitation':
                    return this._onRevokeInvitation(invId, btn);
                case 'open-close-modal':
                    return this._modal.openClose();
                case 'close-action-modal':
                    return this._modal.closeAction();
                case 'close-close-modal':
                    return this._modal.closeClose();
                case 'grant-capability':
                    return this._onOpenCapabilityDrawer(capKey, 'grant');
                case 'deny-capability':
                    return this._onOpenCapabilityDrawer(capKey, 'revoke');
                case 'reset-capability':
                    return this._onResetCapability(capKey, btn);
                case 'close-capability-drawer':
                    return this._drawer.close();
            }
        },

        // ── Tab switching ─────────────────────────────────────────────────────────

        _bindTabs() {
            document.querySelectorAll('.oc-tabbar__tab').forEach((tab) => {
                tab.addEventListener('click', () => {
                    document.querySelectorAll('.oc-tabbar__tab').forEach((b) => b.classList.remove('active'));
                    document.querySelectorAll('.oc-tab-panel').forEach((p) => p.classList.remove('active'));
                    tab.classList.add('active');
                    const panel = document.getElementById(tab.dataset.tabTarget);
                    if (panel) panel.classList.add('active');

                    if (tab.dataset.tabTarget === 'contributor-capabilities-panel' && !this._state.get().capabilitiesLoaded) {
                        this._loadCapabilities();
                    }
                });
            });
        },

        // ── Modal backdrop click-away ─────────────────────────────────────────────

        _bindModalBackdrops() {
            document.getElementById('action-modal')?.addEventListener('click', (e) => {
                if (e.target === e.currentTarget) this._modal.closeAction();
            });
            document.getElementById('close-modal')?.addEventListener('click', (e) => {
                if (e.target === e.currentTarget) this._modal.closeClose();
            });
            document.getElementById('capability-drawer-backdrop')?.addEventListener('click', () => {
                this._drawer.close();
            });
        },

        // ── Status (deactivate / reactivate) ─────────────────────────────────────

        _onStatusToggle(id, type) {
            const isDeactivate = type === 'deactivate';
            this._modal.openAction({
                title: isDeactivate ? 'Deactivate Account' : 'Reactivate Account',
                description: isDeactivate
                    ? 'The contributor will lose access immediately. Please provide a reason.'
                    : "This will restore the contributor's access. Please provide a reason.",
                confirmLabel: isDeactivate ? 'Deactivate account' : 'Reactivate account',
                confirmClass: isDeactivate ? 'oc-btn--danger' : 'oc-btn--amber',
                onSubmit: async (reason) => {
                    const {ok, data} = await this._api.updateStatus(id, type, reason);
                    if (!ok) throw new Error(data.error || data.message || 'Request failed.');

                    const newIsActive = type === 'reactivate';
                    this._state.update({isActive: newIsActive});
                    this._renderer.updateStatusBadge(newIsActive);
                    this._renderer.updateStatusButton(newIsActive);
                    this._toast.success(isDeactivate ? 'Account deactivated.' : 'Account reactivated.');
                },
            });
        },

        // ── Role ─────────────────────────────────────────────────────────────────

        _onRoleChange(newRole, selectEl) {
            const id = this._state.get().contributorId;
            this._modal.openAction({
                title: 'Change Contributor Role',
                description: `You are changing the role to "${newRole.charAt(0).toUpperCase() + newRole.slice(1)}". Please provide a reason.`,
                confirmLabel: 'Update Role',
                confirmClass: 'oc-btn--amber',
                onSubmit: async (reason) => {
                    const {ok, data} = await this._api.updateRole(id, newRole, reason);
                    if (!ok) {
                        // Revert select to previous value on failure
                        selectEl.value = this._state.get().role;
                        throw new Error(data.error || data.message || 'Request failed.');
                    }
                    this._state.update({role: newRole});
                    this._toast.success(`Role updated to ${newRole}.`);
                },
            });
        },

        // ── Site access ──────────────────────────────────────────────────────────

        async _onGrantAccess(id, btn) {
            this._renderer.setButtonLoading(btn, 'Granting…');
            const {ok, data} = await this._api.grantAccess(id);
            if (ok) {
                this._toast.success('Access granted.');
            } else {
                this._toast.error(data.message || 'Failed to grant access.');
            }
            this._renderer.restoreButton(btn);
        },

        async _onRevokeAccess(id, btn) {
            if (!confirm('Revoke site access for this contributor?')) return;
            this._renderer.setButtonLoading(btn, 'Revoking…');
            const {ok, data} = await this._api.revokeAccess(id);
            if (ok) {
                this._toast.success('Access revoked.');
            } else {
                this._toast.error(data.message || 'Failed to revoke access.');
            }
            this._renderer.restoreButton(btn);
        },

        // ── Invitations ──────────────────────────────────────────────────────────

        async _onSendInvitation() {
            const email = this._state.get().contributorEmail;
            if (!confirm(`Send a new invitation to ${email}?`)) return;
            const {ok, data} = await this._api.sendInvitation(email);
            ok ? this._toast.success('Invitation sent.') : this._toast.error(data.error || 'Failed.');
        },

        async _onResendInvitation(invId, btn) {
            this._renderer.setButtonLoading(btn, 'Sending…');
            const {ok} = await this._api.resendInvitation(invId);
            if (ok) {
                this._toast.success('New invitation sent.');
                this._renderer.markInvitationResent(invId);
            } else {
                this._toast.error('Failed to resend invitation.');
                this._renderer.restoreButton(btn);
            }
        },

        async _onRevokeInvitation(invId, btn) {
            if (!confirm('Revoke this invitation?')) return;
            this._renderer.setButtonLoading(btn, 'Revoking…');
            const {ok} = await this._api.revokeInvitation(invId);
            if (ok) {
                this._toast.success('Invitation revoked.');
                this._renderer.markInvitationRevoked(invId);
            } else {
                this._toast.error('Failed to revoke invitation.');
                this._renderer.restoreButton(btn);
            }
        },

        // ── Close account ────────────────────────────────────────────────────────

        async _submitClose() {
            const reason = document.getElementById('close-reason').value;
            const errBox = document.getElementById('close-modal-errors');
            const btn = document.getElementById('close-confirm-btn');
            errBox.style.display = 'none';

            if (!reason) {
                errBox.textContent = 'Please select a reason.';
                errBox.style.display = 'block';
                return;
            }

            const id = this._state.get().contributorId;
            this._renderer.setButtonLoading(btn, 'Closing…');
            btn.innerHTML = '<div class="oc-spinner"></div> Closing…';

            const {ok, data} = await this._api.closeAccount(id, reason);

            if (ok) {
                this._modal.closeClose();
                this._toast.success('Account closed.');
                this._state.update({isActive: false});
                this._renderer.updateStatusBadge(false);
                this._renderer.updateStatusButton(false);
            } else {
                errBox.textContent = data.error || data.message || 'Failed to close account.';
                errBox.style.display = 'block';
                this._renderer.restoreButton(btn, 'Close account');
            }
        },

        // ── Capabilities ─────────────────────────────────────────────────────────

        async _loadCapabilities() {
            const id = this._state.get().contributorId;
            this._renderer.renderCapabilitiesLoading();

            const {ok, data} = await this._api.listCapabilities(id);

            if (!ok) {
                this._renderer.renderCapabilitiesError(data.message || 'Failed to load capabilities.');
                return;
            }

            const capabilities = data.capabilities || [];
            this._state.update({capabilities, capabilitiesLoaded: true});
            this._renderer.renderCapabilities(capabilities);
        },

        _onOpenCapabilityDrawer(capKey, defaultAction) {
            const cap = this._state.get().capabilities.find((c) => c.key === capKey);
            if (!cap) return;

            const id = this._state.get().contributorId;

            this._drawer.open({
                title: defaultAction === 'grant' ? 'Grant Capability' : 'Deny Capability',
                subtitle: cap.label || capKey,
                capKey: cap.key,
                capLabel: cap.label,
                capDescription: cap.description,
                defaultAction,
                onSave: async (action) => {
                    const apiCall = action === 'grant'
                        ? this._api.grantCapability(id, capKey)
                        : this._api.revokeCapability(id, capKey);

                    const {ok, data} = await apiCall;
                    if (!ok) throw new Error(data.message || 'Failed to save.');

                    // Reload capabilities from server to get fresh effective state
                    await this._loadCapabilities();
                    this._toast.success(action === 'grant' ? 'Capability granted.' : 'Capability denied.');
                },
            });
        },

        async _onResetCapability(capKey, btn) {
            const id = this._state.get().contributorId;
            this._renderer.setButtonLoading(btn, 'Resetting…');

            const {ok, data} = await this._api.resetCapability(id, capKey);

            if (ok) {
                this._toast.success('Override removed.');
                await this._loadCapabilities();
            } else {
                this._toast.error(data.message || 'Failed to reset capability.');
                this._renderer.restoreButton(btn);
            }
        },
    };

    ContributorAdminPage.init({
        site: '<?= $site ?>',
        contributor: <?= json_encode(['id' => $contributor['id'], 'email' => $contributor['email'], 'role' => $contributor['role'], 'is_active' => $isActive]) ?>,
    });
</script>
@endsection