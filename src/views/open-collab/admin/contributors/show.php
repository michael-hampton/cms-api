@section('logic')
<?php
/**
 * Template: open-collab/admin/contributors/show.php
 * Variables:
 *   $contributor  — array of contributor data
 *   $invitations  — Collection of Invitation models for this contributor's email
 *   $site         — string
 *   $currentUser  — AuthenticatedUser
 */

$isActive = (bool)($contributor['is_active'] ?? true);
?>
@endsection

@extends('open-collab/layouts/admin')

@section('content')

<div id="status-toast"
     style="position:fixed;bottom:24px;left:50%;transform:translateX(-50%);
            background:var(--navy);color:#fff;padding:9px 20px;border-radius:20px;
            font-size:.8rem;font-weight:500;opacity:0;transition:opacity .3s;
            z-index:300;pointer-events:none;"></div>

<!-- Close account modal -->
<div id="close-modal"
     style="display:none;position:fixed;inset:0;background:rgba(15,25,41,.55);z-index:500;place-items:center;"
     onclick="if(event.target===this)closeCloseModal()">
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
            <button onclick="closeCloseModal()" class="oc-btn oc-btn--ghost" style="flex:1;">Cancel</button>
            <button onclick="submitClose()" class="oc-btn oc-btn--danger" style="flex:1;" id="close-confirm-btn">
                Close account
            </button>
        </div>
    </div>
</div>

<div class="oc-grid-sidebar" style="align-items:start;gap:24px;">

    <!-- Main profile card -->
    <div style="display:flex;flex-direction:column;gap:20px;">

        <div class="oc-card">
            <div class="oc-card__header">
                <div style="display:flex;align-items:center;gap:14px;">
                    <div style="width:48px;height:48px;border-radius:50%;background:var(--navy);
                                display:grid;place-items:center;font-weight:700;font-size:1.2rem;
                                color:var(--amber);flex-shrink:0;">
                        <?= strtoupper(substr($contributor['name'] ?? 'C', 0, 1)) ?>
                    </div>
                    <div>
                        <div style="font-size:1.1rem;font-weight:700;color:var(--navy);">
                            <?= htmlspecialchars($contributor['name'] ?? 'Unknown') ?>
                        </div>
                        <div style="font-size:.82rem;color:var(--slate);">
                            <?= htmlspecialchars($contributor['email'] ?? '') ?>
                        </div>
                    </div>
                    <?php if ($isActive): ?>
                        <span class="oc-badge oc-badge--published" style="margin-left:auto;">Active</span>
                    <?php else: ?>
                        <span class="oc-badge oc-badge--revoked" style="margin-left:auto;">Inactive</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="oc-card__body">
                <dl style="display:grid;grid-template-columns:140px 1fr;gap:10px 16px;font-size:.875rem;">
                    <dt style="color:var(--slate);font-weight:500;">ID</dt>
                    <dd style="color:var(--navy);font-family:monospace;">#<?= (int)$contributor['id'] ?></dd>
                    <dt style="color:var(--slate);font-weight:500;">Role</dt>
                    <dd>
                        <select id="role-select" class="oc-select" style="font-size:.8rem;padding:4px 8px;"
                                onchange="updateRole(<?= (int)$contributor['id'] ?>, this.value)">
                            <?php foreach (['contributor', 'editor', 'admin'] as $r): ?>
                                <option value="<?= $r ?>" <?= ($contributor['role'] ?? 'contributor') === $r ? 'selected' : '' ?>>
                                    <?= ucfirst($r) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </dd>
                    <dt style="color:var(--slate);font-weight:500;">Joined</dt>
                    <dd style="color:var(--navy);">
                        <?= !empty($contributor['created_at']) ? $contributor['created_at'] : '–' ?>
                    </dd>
                    <dt style="color:var(--slate);font-weight:500;">Is contributor</dt>
                    <dd><?= ($contributor['is_contributor'] ?? false) ? '<span class="oc-badge oc-badge--published">Yes</span>' : '<span class="oc-badge oc-badge--draft">No</span>' ?></dd>
                </dl>
            </div>
        </div>

        <!-- Site access -->
        <div class="oc-card">
            <div class="oc-card__header">
                <span class="oc-card__title">Site Access</span>
            </div>
            <div class="oc-card__body">
                <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 0;">
                    <div>
                        <div style="font-weight:500;font-size:.875rem;color:var(--navy);">
                            <?= htmlspecialchars($site) ?>
                        </div>
                        <div style="font-size:.75rem;color:var(--slate);">Current site</div>
                    </div>
                    <div style="display:flex;gap:8px;">
                        <button onclick="grantAccess(<?= (int)$contributor['id'] ?>)"
                                class="oc-btn oc-btn--ghost oc-btn--sm"
                                style="border-color:#bbf7d0;color:var(--green);">Grant
                        </button>
                        <button onclick="revokeAccess(<?= (int)$contributor['id'] ?>)"
                                class="oc-btn oc-btn--ghost oc-btn--sm"
                                style="border-color:#fecaca;color:var(--red);">Revoke
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Invitation history -->
        <?php if ($invitations && count($invitations) > 0): ?>
            <div class="oc-card">
                <div class="oc-card__header">
                    <span class="oc-card__title">Invitation History</span>
                    <span style="font-size:.72rem;background:var(--slate-pale);color:var(--slate);
                             padding:2px 8px;border-radius:10px;font-weight:600;">
                    <?= count($invitations) ?>
                </span>
                </div>
                <table class="oc-table">
                    <thead>
                    <tr>
                        <th>Sent</th>
                        <th>Status</th>
                        <th>Expires</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($invitations as $inv): ?>
                        <?php
                        $status = $inv->resolveStatus()->value;
                        $statusBadge = match ($status) {
                            'pending' => 'oc-badge--waiting-approval',
                            'used' => 'oc-badge--published',
                            'expired' => 'oc-badge--draft',
                            'revoked' => 'oc-badge--revoked',
                            default => 'oc-badge--draft',
                        };
                        ?>
                        <tr>
                            <td style="font-size:.78rem;color:var(--slate);">
                                <?= $inv->created_at ? $inv->created_at->format('d M Y') : '–' ?>
                            </td>
                            <td><span class="oc-badge <?= $statusBadge ?>"><?= ucfirst($status) ?></span></td>
                            <td style="font-size:.78rem;color:var(--slate);">
                                <?= $inv->expires_at ? $inv->expires_at->format('d M Y') : '–' ?>
                            </td>
                            <td style="text-align:right;">
                                <?php if ($status === 'pending'): ?>
                                    <button onclick="revokeInvitation(<?= (int)$inv->id ?>, this)"
                                            class="oc-btn oc-btn--ghost oc-btn--sm"
                                            style="font-size:.7rem;border-color:#fecaca;color:var(--red);">
                                        Revoke
                                    </button>
                                <?php elseif (in_array($status, ['expired', 'revoked'])): ?>
                                    <button onclick="resendInvitation(<?= (int)$inv->id ?>, this)"
                                            class="oc-btn oc-btn--ghost oc-btn--sm" style="font-size:.7rem;">
                                        Resend
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>

    <!-- Sidebar actions -->
    <div style="position:sticky;top:calc(var(--header-h,64px) + 20px);display:flex;flex-direction:column;gap:16px;">

        <div class="oc-card">
            <div class="oc-card__body" style="padding:18px 20px;display:flex;flex-direction:column;gap:10px;">
                <div style="font-size:.72rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;
                             color:var(--slate);margin-bottom:4px;">Quick actions
                </div>

                <?php if ($isActive): ?>
                    <button onclick="openActionModal(<?= (int)$contributor['id'] ?>, 'deactivate')"
                            class="oc-btn oc-btn--ghost oc-btn--block"
                            style="border-color:#fecaca;color:var(--red);">
                        <svg viewBox="0 0 20 20" fill="currentColor" width="14">
                            <path fill-rule="evenodd"
                                  d="M13.477 14.89A6 6 0 015.11 6.524L13.476 14.89zm1.414-1.414L6.524 5.11A6 6 0 0114.89 13.476zM18 10a8 8 0 11-16 0 8 8 0 0116 0z"
                                  clip-rule="evenodd"/>
                        </svg>
                        Deactivate account
                    </button>
                <?php else: ?>
                    <button onclick="openActionModal(<?= (int)$contributor['id'] ?>, 'reactivate')"
                            class="oc-btn oc-btn--ghost oc-btn--block"
                            style="border-color:#bbf7d0;color:var(--green);">
                        <svg viewBox="0 0 20 20" fill="currentColor" width="14">
                            <path fill-rule="evenodd"
                                  d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                  clip-rule="evenodd"/>
                        </svg>
                        Reactivate account
                    </button>
                <?php endif; ?>

                <button onclick="sendNewInvite()"
                        class="oc-btn oc-btn--ghost oc-btn--block">
                    <svg viewBox="0 0 20 20" fill="currentColor" width="14">
                        <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                        <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
                    </svg>
                    Send new invitation
                </button>

                <a href="/<?= htmlspecialchars($site) ?>/open-collab/admin/contributors/<?= (int)$contributor['id'] ?>/violations"
                   class="oc-btn oc-btn--ghost oc-btn--block">
                    <svg viewBox="0 0 20 20" fill="currentColor" width="14">
                        <path fill-rule="evenodd"
                              d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                              clip-rule="evenodd"/>
                    </svg>
                    View violations
                </a>
            </div>
        </div>

        <div class="oc-danger-zone">
            <div class="oc-danger-zone__title">Danger Zone</div>
            <button onclick="document.getElementById('close-modal').style.display='grid'"
                    class="oc-btn oc-btn--danger oc-btn--block oc-btn--sm">
                Close account permanently
            </button>
        </div>

    </div>

</div>

<div id="action-modal"
     style="display:none;position:fixed;inset:0;background:rgba(15,25,41,.55);z-index:500;place-items:center;"
     onclick="if(event.target===this)closeActionModal()">
    <div style="background:#fff;border-radius:12px;padding:28px 32px;max-width:400px;width:92%;box-shadow:0 20px 60px rgba(0,0,0,.2);">
        <h3 id="action-modal-title"
            style="font-family:var(--font-display);font-size:1.2rem;color:var(--navy);margin-bottom:6px;">Update
            Status</h3>
        <p id="action-modal-desc" style="font-size:.85rem;color:var(--slate);margin-bottom:20px;"></p>

        <div class="oc-form-group">
            <label class="oc-label" for="action-reason">Reason for change</label>
            <textarea class="oc-input" id="action-reason" rows="3"
                      placeholder="Explain why this status is changing..."></textarea>
        </div>

        <div id="action-modal-errors" class="oc-form-errors" style="display:none;margin-bottom:12px;"></div>

        <div style="display:flex;gap:10px;">
            <button onclick="closeActionModal()" class="oc-btn oc-btn--ghost" style="flex:1;">Cancel</button>
            <button id="action-confirm-btn" class="oc-btn" style="flex:1;">Confirm</button>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
    const SITE = '<?= htmlspecialchars($site ?? '') ?>';
    const CONTRIBUTOR_ID = <?= (int)$contributor['id'] ?>;
    const CONTRIBUTOR_EMAIL = '<?= htmlspecialchars($contributor['email'] ?? '') ?>';
    const TOKEN = () => localStorage.getItem('oc_token') || '';
    let currentActionId = null;
    let currentActionType = ''; // 'deactivate' or 'reactivate'
    let pendingRole = null; // Store the role temporarily until the modal is confirmed

    function openActionModal(id, type, role = null) {
        currentActionId = id;
        currentActionType = type;
        pendingRole = role; // Store the role for the submit function

        const isDeactivate = type === 'deactivate';
        const isReactivate = type === 'reactivate';
        const isRole = type === 'role';

        const titleEl = document.getElementById('action-modal-title');
        const descEl = document.getElementById('action-modal-desc');
        const btn = document.getElementById('action-confirm-btn');

        if (isRole) {
            titleEl.textContent = 'Change Contributor Role';
            descEl.textContent = `Please provide a reason for changing the role to "${role}".`;
            btn.className = 'oc-btn oc-btn--amber';
            btn.textContent = 'Update Role';
        } else {
            titleEl.textContent = isDeactivate ? 'Deactivate Account' : 'Reactivate Account';
            descEl.textContent = isDeactivate
                ? 'The contributor will lose access immediately. Please provide a reason.'
                : 'This will restore the contributor\'s access. Please provide a reason.';

            btn.className = isDeactivate ? 'oc-btn oc-btn--danger' : 'oc-btn oc-btn--amber';
            btn.textContent = isDeactivate ? 'Deactivate account' : 'Reactivate account';
        }

        btn.onclick = submitStatusChange;

        document.getElementById('action-modal').style.display = 'grid';
        document.getElementById('action-reason').focus();
    }

    function closeActionModal() {
        document.getElementById('action-modal').style.display = 'none';
        document.getElementById('action-reason').value = '';
        document.getElementById('action-modal-errors').style.display = 'none';
    }

    async function submitStatusChange() {
        const reason = document.getElementById('action-reason').value.trim();
        const errBox = document.getElementById('action-modal-errors');
        const btn = document.getElementById('action-confirm-btn');

        if (!reason) {
            errBox.textContent = 'Please provide a reason for this action.';
            errBox.style.display = 'block';
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<div class="oc-spinner"></div> Processing...';

        if (currentActionType === 'role') {
            url = `/api/${SITE}/open-collab/admin/contributors/${currentActionId}/role`;
            body = JSON.stringify({role: pendingRole, reason: reason}); // Now has the value
        } else {
            url = `/api/${SITE}/open-collab/admin/contributors/${currentActionId}/${currentActionType}`;
            body = JSON.stringify({reason: reason});
        }

        const res = await fetch(`/api/${SITE}/open-collab/admin/contributors/${currentActionId}/${currentActionType}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${TOKEN()}`,
                'Accept': 'application/json'
            },
            body: body,
        });

        if (res.ok) {
            showToast(currentActionType === 'deactivate' ? 'Deactivated' : 'Reactivated');
            setTimeout(() => location.reload(), 800);
        } else {
            const data = await res.json();
            errBox.textContent = data.error || 'Request failed.';
            errBox.style.display = 'block';
            btn.disabled = false;
            btn.textContent = 'Confirm';
        }
    }

    async function grantAccess(id) {
        const res = await fetch(`/api/${SITE}/open-collab/admin/contributors/${id}/grant-access`, {
            method: 'POST', headers: {'Authorization': `Bearer ${TOKEN()}`, 'Accept': 'application/json'},
        });
        res.ok ? showToast('Access granted') : showToast('Failed', false);
    }

    async function revokeAccess(id) {
        if (!confirm('Revoke site access for this contributor?')) return;
        const res = await fetch(`/api/${SITE}/open-collab/admin/contributors/${id}/revoke-access`, {
            method: 'POST', headers: {'Authorization': `Bearer ${TOKEN()}`, 'Accept': 'application/json'},
        });
        res.ok ? showToast('Access revoked') : showToast('Failed', false);
    }

    async function updateRole(id, role) {
        // Save the target role and ID
        currentActionId = id;
        currentActionType = 'role';
        pendingRole = role;

        // Configure the modal for Role Change
        document.getElementById('action-modal-title').textContent = 'Change Contributor Role';
        document.getElementById('action-modal-desc').textContent = `You are changing the role to "${role.charAt(0).toUpperCase() + role.slice(1)}". Please provide a reason for this change.`;

        const btn = document.getElementById('action-confirm-btn');
        btn.className = 'oc-btn oc-btn--amber';
        btn.textContent = 'Update Role';
        btn.onclick = submitStatusChange; // Reuses the same submission logic

        document.getElementById('action-modal').style.display = 'grid';
        document.getElementById('action-reason').focus();
    }

    async function sendNewInvite() {
        if (!confirm(`Send a new invitation to ${CONTRIBUTOR_EMAIL}?`)) return;
        const res = await fetch(`/api/${SITE}/open-collab/invitations`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${TOKEN()}`,
                'Accept': 'application/json'
            },
            body: JSON.stringify({email: CONTRIBUTOR_EMAIL}),
        });
        const data = await res.json();
        res.ok ? showToast('Invitation sent') : showToast(data.error || 'Failed', false);
    }

    async function resendInvitation(id, btn) {
        btn.disabled = true;
        const res = await fetch(`/api/${SITE}/open-collab/admin/invitations/${id}/resend`, {
            method: 'POST', headers: {'Authorization': `Bearer ${TOKEN()}`, 'Accept': 'application/json'},
        });
        if (res.ok) {
            showToast('New invitation sent');
            setTimeout(() => location.reload(), 800);
        } else {
            showToast('Failed', false);
            btn.disabled = false;
        }
    }

    async function revokeInvitation(id, btn) {
        if (!confirm('Revoke this invitation?')) return;
        btn.disabled = true;
        const res = await fetch(`/api/${SITE}/open-collab/admin/invitations/${id}`, {
            method: 'DELETE', headers: {'Authorization': `Bearer ${TOKEN()}`, 'Accept': 'application/json'},
        });
        if (res.ok) {
            showToast('Invitation revoked');
            setTimeout(() => location.reload(), 800);
        } else {
            showToast('Failed', false);
            btn.disabled = false;
        }
    }

    function closeCloseModal() {
        document.getElementById('close-modal').style.display = 'none';
    }

    async function submitClose() {
        const reason = document.getElementById('close-reason').value;
        const errBox = document.getElementById('close-modal-errors');
        const btn = document.getElementById('close-confirm-btn');
        errBox.style.display = 'none';

        if (!reason) {
            errBox.textContent = 'Please select a reason.';
            errBox.style.display = 'block';
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<div class="oc-spinner"></div> Closing…';

        const res = await fetch(`/api/${SITE}/open-collab/admin/contributors/${CONTRIBUTOR_ID}/close`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${TOKEN()}`,
                'Accept': 'application/json'
            },
            body: JSON.stringify({reason}),
        });

        if (res.ok) {
            closeCloseModal();
            showToast('Account closed');
            setTimeout(() => location.reload(), 1000);
        } else {
            const data = await res.json();
            errBox.textContent = data.error || 'Failed to close account.';
            errBox.style.display = 'block';
            btn.disabled = false;
            btn.textContent = 'Close account';
        }
    }

    function showToast(msg, ok = true) {
        const el = document.getElementById('status-toast');
        el.textContent = msg;
        el.style.background = ok ? 'var(--navy)' : 'var(--red)';
        el.style.opacity = '1';
        setTimeout(() => el.style.opacity = '0', 2800);
    }
</script>
@endsection