<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Members &mdash; CRM</title>
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
            --color-danger-h: #b91c1c;
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

        /* ── Layout ── */
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
            display: flex;
            flex-direction: column;
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

        .sidebar-nav a:hover,
        .sidebar-nav a.active {
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
            justify-content: space-between;
            gap: 12px;
        }

        .topbar h1 {
            font-size: 18px;
            font-weight: 600;
        }

        .content {
            padding: 28px;
            flex: 1;
        }

        /* ── Card ── */
        .card {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }

        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--color-border);
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        /* ── Filter bar ── */
        .filter-bar {
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
            flex-wrap: wrap;
        }

        .filter-bar input[type="text"],
        .filter-bar select {
            height: 36px;
            padding: 0 12px;
            border: 1px solid var(--color-border);
            border-radius: var(--radius);
            font-size: 13px;
            background: var(--color-surface);
            color: var(--color-text);
            outline: none;
            transition: border-color .15s;
        }

        .filter-bar input[type="text"] {
            width: 260px;
        }

        .filter-bar input[type="text"]:focus,
        .filter-bar select:focus {
            border-color: var(--color-primary);
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

        .btn-danger:hover {
            background: var(--color-danger-h);
        }

        .btn-sm {
            height: 28px;
            padding: 0 10px;
            font-size: 12px;
        }

        /* ── Table ── */
        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        thead th {
            text-align: left;
            padding: 10px 16px;
            font-weight: 600;
            color: var(--color-muted);
            border-bottom: 1px solid var(--color-border);
            white-space: nowrap;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        tbody tr {
            transition: background .1s;
        }

        tbody tr:hover {
            background: var(--color-bg);
        }

        tbody td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--color-border);
            vertical-align: middle;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .td-actions {
            display: flex;
            gap: 6px;
        }

        /* ── Badges ── */
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 100px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .02em;
        }

        .badge-active {
            background: var(--color-badge-on);
            color: var(--color-badge-on-t);
        }

        .badge-inactive {
            background: var(--color-badge-off);
            color: var(--color-badge-off-t);
        }

        /* ── Avatar ── */
        .avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--color-primary);
            color: #fff;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .member-cell {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .member-name {
            font-weight: 500;
        }

        .member-email {
            font-size: 12px;
            color: var(--color-muted);
        }

        /* ── Pagination ── */
        .pagination {
            padding: 14px 20px;
            border-top: 1px solid var(--color-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .pagination-info {
            font-size: 13px;
            color: var(--color-muted);
        }

        .pagination-links {
            display: flex;
            gap: 4px;
        }

        .page-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: var(--radius);
            border: 1px solid var(--color-border);
            font-size: 13px;
            text-decoration: none;
            color: var(--color-text);
            background: var(--color-surface);
            transition: background .15s, border-color .15s;
        }

        .page-link:hover {
            background: var(--color-bg);
            border-color: var(--color-primary);
            color: var(--color-primary);
        }

        .page-link.current {
            background: var(--color-primary);
            color: #fff;
            border-color: var(--color-primary);
        }

        .page-link.disabled {
            opacity: .4;
            pointer-events: none;
        }

        /* ── Empty state ── */
        .empty-state {
            padding: 60px 20px;
            text-align: center;
            color: var(--color-muted);
        }

        .empty-state p {
            margin-top: 8px;
            font-size: 13px;
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

        /* ── Modal ── */
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

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-logo">&#9635; CRM</div>
        <nav class="sidebar-nav">
            <a href="/crm/members" class="active">&#128100; Members</a>
        </nav>
        <form method="POST" action="/crm/logout" style="margin-top:auto;padding:16px 12px 0">
            <button type="submit" style="
        width:100%; padding:8px 10px; background:none;
        border:1px solid #334155; border-radius:4px;
        color:#94a3b8; font-size:13px; cursor:pointer;
        transition:background .15s, color .15s;
    ">Sign out
            </button>
        </form>
    </aside>

    <!-- Main -->
    <div class="main">
        <div class="topbar">
            <h1>Members</h1>
        </div>

        <div class="content">
            <div class="card">

                <!-- Filter bar -->
                <div class="card-header">
                    <form class="filter-bar" method="GET" action="/crm/members" id="filter-form">
                        <input
                                type="text"
                                name="search"
                                placeholder="Search name or email&hellip;"
                                value="<?= htmlspecialchars($filters['search'] ?? '') ?>"
                        >

                        <select name="status">
                            <option value="">All statuses</option>
                            <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>
                                Active
                            </option>
                            <option value="inactive" <?= ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>
                                Inactive
                            </option>
                        </select>

                        <select name="agent_id">
                            <option value="">All agents</option>
                            <?php foreach ($agents as $agent): ?>
                                <option
                                        value="<?= (int)$agent['id'] ?>"
                                        <?= (int)($filters['agent_id'] ?? 0) === (int)$agent['id'] ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($agent['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <button class="btn btn-primary" type="submit">Filter</button>

                        <?php if (!empty($filters['search']) || !empty($filters['status']) || !empty($filters['agent_id'])): ?>
                            <a href="/crm/members" class="btn btn-ghost">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Table -->
                <div class="table-wrap">
                    <?php if (empty($members) || $members->isEmpty()): ?>
                        <div class="empty-state">
                            <strong>No members found</strong>
                            <p>Try adjusting your filters.</p>
                        </div>
                    <?php else: ?>
                        <table>
                            <thead>
                            <tr>
                                <th>Member</th>
                                <th>Status</th>
                                <th>Assigned Agent</th>
                                <th>Joined</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($members as $member): ?>
                                <tr>
                                    <td>
                                        <div class="member-cell">
                                            <div class="avatar">
                                                <?= htmlspecialchars(strtoupper(substr($member->first_name, 0, 1) . substr($member->last_name, 0, 1))) ?>
                                            </div>
                                            <div>
                                                <div class="member-name">
                                                    <?= htmlspecialchars($member->first_name . ' ' . $member->last_name) ?>
                                                </div>
                                                <div class="member-email"><?= htmlspecialchars($member->email) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                            <span class="badge <?= $member->is_active ? 'badge-active' : 'badge-inactive' ?>">
                                                <?= $member->is_active ? 'Active' : 'Inactive' ?>
                                            </span>
                                    </td>
                                    <td>
                                        <?php if ($member->assigned_agent_id): ?>
                                            <?php
                                            $agent = $agents->first(fn($a) => $a->id === $member->assigned_agent_id);
                                            ?>
                                            <?= $agent ? htmlspecialchars($agent->name) : '&mdash;' ?>
                                        <?php else: ?>
                                            <span style="color:var(--color-muted)">Unassigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="color:var(--color-muted)">
                                        <?= $member->created_at ? $member->created_at->format('d M Y') : '&mdash;' ?>
                                    </td>
                                    <td>
                                        <div class="td-actions">
                                            <a href="/crm/members/<?= (int)$member->id ?>" class="btn btn-ghost btn-sm">View</a>
                                            <a href="/crm/members/<?= (int)$member->id ?>/edit"
                                               class="btn btn-ghost btn-sm">Edit</a>
                                            <button
                                                    class="btn btn-danger btn-sm"
                                                    data-deactivate="<?= (int)$member->id ?>"
                                                    data-name="<?= htmlspecialchars($member->first_name . ' ' . $member->last_name) ?>"
                                                    <?= !$member->is_active ? 'disabled style="opacity:.4;cursor:not-allowed"' : '' ?>
                                            >
                                                Deactivate
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Pagination -->
                <?php if ($pagination['last_page'] > 1): ?>
                    <div class="pagination">
                        <div class="pagination-info">
                            Showing page <?= (int)$pagination['current_page'] ?> of <?= (int)$pagination['last_page'] ?>
                            &mdash; <?= (int)$pagination['total'] ?> members
                        </div>
                        <div class="pagination-links">
                            <?php
                            $buildUrl = function (int $p) use ($filters): string {
                                $params = array_filter(array_merge($filters, ['page' => $p]), fn($v) => $v !== null && $v !== '');
                                return '/crm/members?' . http_build_query($params);
                            };
                            $current = (int)$pagination['current_page'];
                            $last = (int)$pagination['last_page'];
                            ?>
                            <a href="<?= $buildUrl($current - 1) ?>"
                               class="page-link <?= $current <= 1 ? 'disabled' : '' ?>">&lsaquo;</a>
                            <?php for ($p = max(1, $current - 2); $p <= min($last, $current + 2); $p++): ?>
                                <a href="<?= $buildUrl($p) ?>"
                                   class="page-link <?= $p === $current ? 'current' : '' ?>"><?= $p ?></a>
                            <?php endfor; ?>
                            <a href="<?= $buildUrl($current + 1) ?>"
                               class="page-link <?= $current >= $last ? 'disabled' : '' ?>">&rsaquo;</a>
                        </div>
                    </div>
                <?php endif; ?>

            </div><!-- .card -->
        </div><!-- .content -->
    </div><!-- .main -->
</div><!-- .crm-shell -->

<!-- Deactivate confirm modal -->
<div class="modal-backdrop" id="deactivate-modal">
    <div class="modal">
        <div class="modal-header">Deactivate Member</div>
        <div class="modal-body">
            Are you sure you want to deactivate <strong id="modal-member-name"></strong>?
            This will prevent them from logging in but will preserve all their data.
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" id="modal-cancel">Cancel</button>
            <button class="btn btn-danger" id="modal-confirm">Deactivate</button>
        </div>
    </div>
</div>

<!-- Toast -->
<div class="toast" id="toast"></div>

<script>
    (function () {
        'use strict';

        var pendingMemberId = null;
        var modal = document.getElementById('deactivate-modal');
        var modalName = document.getElementById('modal-member-name');
        var modalCancel = document.getElementById('modal-cancel');
        var modalConfirm = document.getElementById('modal-confirm');
        var toast = document.getElementById('toast');

        // Open modal
        document.querySelectorAll('[data-deactivate]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                pendingMemberId = btn.dataset.deactivate;
                modalName.textContent = btn.dataset.name;
                modal.classList.add('open');
            });
        });

        // Close modal
        modalCancel.addEventListener('click', function () {
            modal.classList.remove('open');
            pendingMemberId = null;
        });

        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                modal.classList.remove('open');
                pendingMemberId = null;
            }
        });

        // Confirm deactivation
        modalConfirm.addEventListener('click', function () {
            if (!pendingMemberId) return;

            modalConfirm.disabled = true;
            modalConfirm.textContent = 'Deactivating…';

            fetch('/crm/members/' + pendingMemberId, {
                method: 'DELETE',
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            })
                .then(function (r) {
                    return r.json();
                })
                .then(function (data) {
                    modal.classList.remove('open');
                    if (data.success) {
                        showToast('Member deactivated.');
                        setTimeout(function () {
                            location.reload();
                        }, 800);
                    } else {
                        showToast(data.message || 'Failed to deactivate.', true);
                    }
                })
                .catch(function () {
                    showToast('Something went wrong.', true);
                })
                .finally(function () {
                    modalConfirm.disabled = false;
                    modalConfirm.textContent = 'Deactivate';
                    pendingMemberId = null;
                });
        });

        function showToast(msg, isError) {
            toast.textContent = msg;
            toast.className = 'toast' + (isError ? ' toast-error' : '');
            void toast.offsetWidth;
            toast.classList.add('show');
            setTimeout(function () {
                toast.classList.remove('show');
            }, 3000);
        }
    })();
</script>
</body>
</html>