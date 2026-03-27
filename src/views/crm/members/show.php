<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($member->first_name . ' ' . $member->last_name) ?> &mdash; CRM</title>
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --color-bg: #f4f5f7;
            --color-surface: #ffffff;
            --color-border: #dde1e7;
            --color-primary: #2563eb;
            --color-primary-h: #1d4ed8;
            --color-danger: #dc2626;
            --color-text: #111827;
            --color-muted: #6b7280;
            --color-badge-on: #dcfce7;
            --color-badge-on-t: #166534;
            --color-badge-off: #fee2e2;
            --color-badge-off-t: #991b1b;
            --radius: 6px;
            --shadow: 0 1px 3px rgba(0, 0, 0, .10);
        }

        body {
            font-family: system-ui, -apple-system, sans-serif;
            font-size: 14px;
            background: var(--color-bg);
            color: var(--color-text);
            line-height: 1.5;
        }

        .crm-shell {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 220px;
            flex-shrink: 0;
            background: #1e293b;
            color: #cbd5e1;
            padding: 24px 0;
        }

        .sidebar-logo {
            font-size: 16px;
            font-weight: 700;
            color: #f8fafc;
            padding: 0 20px 24px;
            border-bottom: 1px solid #334155;
            margin-bottom: 16px;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 20px;
            color: #94a3b8;
            text-decoration: none;
            font-size: 13px;
            transition: background .15s, color .15s;
        }

        .sidebar-nav a:hover, .sidebar-nav a.active {
            background: #334155;
            color: #f8fafc;
        }

        .main {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .topbar {
            background: var(--color-surface);
            border-bottom: 1px solid var(--color-border);
            padding: 14px 28px;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .topbar-back {
            color: var(--color-muted);
            text-decoration: none;
            font-size: 13px;
        }

        .topbar-back:hover {
            color: var(--color-text);
        }

        .topbar h1 {
            font-size: 18px;
            font-weight: 600;
        }

        .content {
            padding: 28px;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        /* ── Profile header ── */
        .profile-header {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 24px;
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .avatar-lg {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: var(--color-primary);
            color: #fff;
            font-size: 22px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .profile-info {
            flex: 1;
        }

        .profile-name {
            font-size: 20px;
            font-weight: 700;
        }

        .profile-email {
            color: var(--color-muted);
            font-size: 13px;
            margin-top: 2px;
        }

        .profile-meta {
            display: flex;
            gap: 16px;
            margin-top: 10px;
            flex-wrap: wrap;
        }

        .profile-meta-item {
            font-size: 12px;
            color: var(--color-muted);
        }

        .profile-meta-item strong {
            color: var(--color-text);
        }

        .profile-actions {
            display: flex;
            gap: 8px;
            flex-shrink: 0;
        }

        /* ── Badges ── */
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 100px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-active {
            background: var(--color-badge-on);
            color: var(--color-badge-on-t);
        }

        .badge-inactive {
            background: var(--color-badge-off);
            color: var(--color-badge-off-t);
        }

        /* ── Two-col layout ── */
        .two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        @media (max-width: 900px) {
            .two-col {
                grid-template-columns: 1fr;
            }
        }

        /* ── Card ── */
        .card {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }

        .card-header {
            padding: 14px 20px;
            border-bottom: 1px solid var(--color-border);
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-body {
            padding: 20px;
        }

        /* ── Detail list ── */
        .detail-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .detail-row {
            display: flex;
            gap: 8px;
        }

        .detail-label {
            font-size: 12px;
            color: var(--color-muted);
            width: 140px;
            flex-shrink: 0;
            padding-top: 1px;
        }

        .detail-value {
            font-size: 13px;
            color: var(--color-text);
        }

        /* ── CRM Notes ── */
        .notes-text {
            font-size: 13px;
            color: var(--color-text);
            white-space: pre-wrap;
            line-height: 1.6;
        }

        .notes-empty {
            font-size: 13px;
            color: var(--color-muted);
            font-style: italic;
        }

        /* ── Addresses ── */
        .address-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .address-card {
            border: 1px solid var(--color-border);
            border-radius: var(--radius);
            padding: 14px 16px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }

        .address-card.is-default {
            border-color: var(--color-primary);
        }

        .address-card-body {
            flex: 1;
            font-size: 13px;
            line-height: 1.6;
        }

        .address-card-type {
            font-size: 11px;
            font-weight: 600;
            color: var(--color-muted);
            text-transform: uppercase;
            letter-spacing: .04em;
            margin-bottom: 2px;
        }

        .address-card-default {
            font-size: 11px;
            color: var(--color-primary);
            font-weight: 600;
        }

        .address-card-actions {
            display: flex;
            gap: 6px;
            flex-shrink: 0;
        }

        /* ── Buttons ── */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 0 14px;
            height: 36px;
            border-radius: var(--radius);
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            text-decoration: none;
            transition: background .15s;
        }

        .btn-primary {
            background: var(--color-primary);
            color: #fff;
        }

        .btn-primary:hover {
            background: var(--color-primary-h);
        }

        .btn-ghost {
            background: transparent;
            color: var(--color-muted);
            border: 1px solid var(--color-border);
        }

        .btn-ghost:hover {
            background: var(--color-bg);
            color: var(--color-text);
        }

        .btn-danger {
            background: var(--color-danger);
            color: #fff;
        }

        .btn-sm {
            height: 28px;
            padding: 0 10px;
            font-size: 12px;
        }

        /* ── Toast ── */
        .toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            background: #1e293b;
            color: #f8fafc;
            padding: 12px 18px;
            border-radius: var(--radius);
            font-size: 13px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, .2);
            opacity: 0;
            transform: translateY(8px);
            transition: opacity .25s, transform .25s;
            pointer-events: none;
            z-index: 9999;
        }

        .toast.show {
            opacity: 1;
            transform: translateY(0);
        }

        .toast.toast-error {
            background: var(--color-danger);
        }

        /* ── Confirm modal ── */
        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .45);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .modal-backdrop.open {
            display: flex;
        }

        .modal {
            background: var(--color-surface);
            border-radius: 8px;
            width: 360px;
            max-width: 94vw;
            box-shadow: 0 8px 32px rgba(0, 0, 0, .2);
            overflow: hidden;
        }

        .modal-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--color-border);
            font-weight: 600;
            font-size: 15px;
        }

        .modal-body {
            padding: 20px;
            color: var(--color-muted);
            font-size: 13px;
            line-height: 1.6;
        }

        .modal-footer {
            padding: 12px 20px;
            border-top: 1px solid var(--color-border);
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }
    </style>
</head>
<body>
<div class="crm-shell">

    <aside class="sidebar">
        <div class="sidebar-logo">&#9635; CRM</div>
        <nav class="sidebar-nav">
            <a href="/crm/members" class="active">&#128100; Members</a>
        </nav>
    </aside>

    <div class="main">
        <div class="topbar">
            <a href="/crm/members" class="topbar-back">&larr; Members</a>
            <h1><?= htmlspecialchars($member->first_name . ' ' . $member->last_name) ?></h1>
        </div>

        <div class="content">

            <!-- Profile header -->
            <div class="profile-header">
                <div class="avatar-lg">
                    <?= htmlspecialchars(strtoupper(substr($member->first_name, 0, 1) . substr($member->last_name, 0, 1))) ?>
                </div>
                <div class="profile-info">
                    <div class="profile-name">
                        <?= htmlspecialchars($member->first_name . ' ' . $member->last_name) ?>
                        <span class="badge <?= $member->is_active ? 'badge-active' : 'badge-inactive' ?>"
                              style="margin-left:8px;vertical-align:middle">
                            <?= $member->is_active ? 'Active' : 'Inactive' ?>
                        </span>
                    </div>
                    <div class="profile-email"><?= htmlspecialchars($member->email) ?></div>
                    <div class="profile-meta">
                        <div class="profile-meta-item">Joined
                            <strong><?= $member->created_at ? $member->created_at->format('d M Y') : '—' ?></strong>
                        </div>
                        <?php if ($member->last_login_at): ?>
                            <div class="profile-meta-item">Last login
                                <strong><?= $member->last_login_at->format('d M Y') ?></strong></div>
                        <?php endif; ?>
                        <?php if ($member->assigned_agent_id): ?>
                            <?php $assignedAgent = $agents->first(fn($a) => $a->id === $member->assigned_agent_id); ?>
                            <div class="profile-meta-item">Agent
                                <strong><?= $assignedAgent ? htmlspecialchars($assignedAgent->name) : '—' ?></strong>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="profile-actions">
                    <a href="/crm/members/<?= (int)$member->id ?>/edit" class="btn btn-primary">Edit Member</a>
                </div>
            </div>

            <div class="two-col">

                <!-- Details -->
                <div class="card">
                    <div class="card-header">Member Details</div>
                    <div class="card-body">
                        <div class="detail-list">
                            <div class="detail-row">
                                <div class="detail-label">Email</div>
                                <div class="detail-value"><?= htmlspecialchars($member->email) ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Email verified</div>
                                <div class="detail-value"><?= $member->email_verified_at ? $member->email_verified_at->format('d M Y') : '<span style="color:var(--color-muted)">Not verified</span>' ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Status</div>
                                <div class="detail-value">
                                    <span class="badge <?= $member->is_active ? 'badge-active' : 'badge-inactive' ?>">
                                        <?= $member->is_active ? 'Active' : 'Inactive' ?>
                                    </span>
                                </div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Assigned agent</div>
                                <div class="detail-value">
                                    <?php if ($member->assigned_agent_id && isset($assignedAgent) && $assignedAgent): ?>
                                        <?= htmlspecialchars($assignedAgent->name) ?>
                                    <?php else: ?>
                                        <span style="color:var(--color-muted)">Unassigned</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Member ID</div>
                                <div class="detail-value" style="color:var(--color-muted)">
                                    #<?= (int)$member->id ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CRM Notes -->
                <div class="card">
                    <div class="card-header">
                        CRM Notes
                        <a href="/crm/members/<?= (int)$member->id ?>/edit" class="btn btn-ghost btn-sm">Edit</a>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($member->crm_notes)): ?>
                            <div class="notes-text"><?= htmlspecialchars($member->crm_notes) ?></div>
                        <?php else: ?>
                            <div class="notes-empty">No notes recorded for this member.</div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <!-- Addresses (full width) -->
            <div class="card">
                <div class="card-header">
                    Addresses
                    <a href="/crm/members/<?= (int)$member->id ?>/addresses/create" class="btn btn-ghost btn-sm">+ Add
                        Address</a>
                </div>
                <div class="card-body">
                    <?php if ($addresses->isEmpty()): ?>
                        <p style="color:var(--color-muted);font-size:13px">No addresses on file.</p>
                    <?php else: ?>
                        <div class="address-list" id="address-list">
                            <?php foreach ($addresses as $address): ?>
                                <div class="address-card <?= $address->is_default ? 'is-default' : '' ?>"
                                     id="addr-<?= (int)$address->id ?>">
                                    <div class="address-card-body">
                                        <div class="address-card-type">
                                            <?= htmlspecialchars(ucfirst($address->type ?? 'address')) ?>
                                            <?php if ($address->is_default): ?>
                                                &nbsp;<span class="address-card-default">&#10003; Default</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($address->label): ?>
                                            <div style="font-weight:600;margin-bottom:2px"><?= htmlspecialchars($address->label) ?></div>
                                        <?php endif; ?>
                                        <div>
                                            <?= htmlspecialchars(implode(', ', array_filter([
                                                    $address->address_line_1,
                                                    $address->address_line_2,
                                                    $address->city,
                                                    $address->state,
                                                    $address->postcode,
                                                    $address->country,
                                            ]))) ?>
                                        </div>
                                    </div>
                                    <div class="address-card-actions">
                                        <?php if (!$address->is_default): ?>
                                            <button
                                                    class="btn btn-ghost btn-sm"
                                                    data-set-default-member="<?= (int)$member->id ?>"
                                                    data-set-default-address="<?= (int)$address->id ?>"
                                            >
                                                Set default
                                            </button>
                                        <?php endif; ?>
                                        <a href="/crm/members/<?= (int)$member->id ?>/addresses/<?= (int)$address->id ?>/edit"
                                           class="btn btn-ghost btn-sm">Edit</a>
                                        <button
                                                class="btn btn-danger btn-sm"
                                                data-delete-address="<?= (int)$address->id ?>"
                                                data-member="<?= (int)$member->id ?>"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div><!-- .content -->
    </div><!-- .main -->
</div><!-- .crm-shell -->

<!-- Delete address confirm modal -->
<div class="modal-backdrop" id="delete-modal">
    <div class="modal">
        <div class="modal-header">Delete Address</div>
        <div class="modal-body">Are you sure you want to delete this address? This cannot be undone.</div>
        <div class="modal-footer">
            <button class="btn btn-ghost" id="modal-cancel">Cancel</button>
            <button class="btn btn-danger" id="modal-confirm">Delete</button>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
    (function () {
        'use strict';

        var toast = document.getElementById('toast');
        var deleteModal = document.getElementById('delete-modal');
        var modalCancel = document.getElementById('modal-cancel');
        var modalConfirm = document.getElementById('modal-confirm');
        var pendingDelete = null;

        function showToast(msg, isError) {
            toast.textContent = msg;
            toast.className = 'toast' + (isError ? ' toast-error' : '');
            void toast.offsetWidth;
            toast.classList.add('show');
            setTimeout(function () {
                toast.classList.remove('show');
            }, 3000);
        }

        // Set default address
        document.querySelectorAll('[data-set-default-address]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var memberId = btn.dataset.setDefaultMember;
                var addressId = btn.dataset.setDefaultAddress;
                btn.disabled = true;

                fetch('/crm/members/' + memberId + '/addresses/' + addressId + '/default', {
                    method: 'POST',
                    headers: {'X-Requested-With': 'XMLHttpRequest'}
                })
                    .then(function (r) {
                        return r.json();
                    })
                    .then(function (data) {
                        if (data.success) {
                            showToast('Default address updated.');
                            setTimeout(function () {
                                location.reload();
                            }, 600);
                        } else {
                            showToast(data.message || 'Failed.', true);
                            btn.disabled = false;
                        }
                    })
                    .catch(function () {
                        showToast('Something went wrong.', true);
                        btn.disabled = false;
                    });
            });
        });

        // Delete address
        document.querySelectorAll('[data-delete-address]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                pendingDelete = {memberId: btn.dataset.member, addressId: btn.dataset.deleteAddress};
                deleteModal.classList.add('open');
            });
        });

        modalCancel.addEventListener('click', function () {
            deleteModal.classList.remove('open');
            pendingDelete = null;
        });

        deleteModal.addEventListener('click', function (e) {
            if (e.target === deleteModal) {
                deleteModal.classList.remove('open');
                pendingDelete = null;
            }
        });

        modalConfirm.addEventListener('click', function () {
            if (!pendingDelete) return;
            modalConfirm.disabled = true;
            modalConfirm.textContent = 'Deleting…';

            fetch('/crm/members/' + pendingDelete.memberId + '/addresses/' + pendingDelete.addressId, {
                method: 'DELETE',
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            })
                .then(function (r) {
                    return r.json();
                })
                .then(function (data) {
                    deleteModal.classList.remove('open');
                    if (data.success) {
                        var el = document.getElementById('addr-' + pendingDelete.addressId);
                        if (el) el.remove();
                        showToast('Address deleted.');
                    } else {
                        showToast(data.message || 'Failed to delete address.', true);
                    }
                })
                .catch(function () {
                    showToast('Something went wrong.', true);
                })
                .finally(function () {
                    modalConfirm.disabled = false;
                    modalConfirm.textContent = 'Delete';
                    pendingDelete = null;
                });
        });
    })();
</script>
</body>
</html>