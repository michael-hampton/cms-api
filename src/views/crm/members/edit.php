<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit <?= htmlspecialchars($member->first_name . ' ' . $member->last_name) ?> &mdash; CRM</title>
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
            --color-error-bg: #fef2f2;
            --color-error-b: #fca5a5;
            --color-error-t: #991b1b;
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
        }

        /* ── Card ── */
        .card {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            max-width: 680px;
        }

        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--color-border);
            font-weight: 600;
            font-size: 15px;
        }

        .card-body {
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .card-footer {
            padding: 16px 20px;
            border-top: 1px solid var(--color-border);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
        }

        /* ── Form ── */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        @media (max-width: 540px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        label {
            font-size: 12px;
            font-weight: 600;
            color: var(--color-muted);
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        input[type="text"],
        input[type="email"],
        select,
        textarea {
            padding: 8px 12px;
            border: 1px solid var(--color-border);
            border-radius: var(--radius);
            font-size: 14px;
            font-family: inherit;
            color: var(--color-text);
            background: var(--color-surface);
            outline: none;
            transition: border-color .15s, box-shadow .15s;
            width: 100%;
        }

        input:focus, select:focus, textarea:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
        }

        input.has-error, select.has-error, textarea.has-error {
            border-color: var(--color-danger);
        }

        textarea {
            resize: vertical;
            min-height: 100px;
        }

        .field-error {
            font-size: 12px;
            color: var(--color-danger);
            margin-top: 2px;
        }

        /* ── Toggle (is_active) ── */
        .toggle-group {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
        }

        .toggle {
            position: relative;
            display: inline-block;
            width: 42px;
            height: 24px;
            flex-shrink: 0;
        }

        .toggle input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-track {
            position: absolute;
            inset: 0;
            background: var(--color-border);
            border-radius: 100px;
            cursor: pointer;
            transition: background .2s;
        }

        .toggle-track::after {
            content: '';
            position: absolute;
            width: 18px;
            height: 18px;
            background: #fff;
            border-radius: 50%;
            top: 3px;
            left: 3px;
            transition: transform .2s;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .2);
        }

        .toggle input:checked + .toggle-track {
            background: var(--color-primary);
        }

        .toggle input:checked + .toggle-track::after {
            transform: translateX(18px);
        }

        .toggle-label {
            font-size: 14px;
            color: var(--color-text);
        }

        .toggle-hint {
            font-size: 12px;
            color: var(--color-muted);
            margin-top: 2px;
        }

        /* ── Section divider ── */
        .section-title {
            font-size: 11px;
            font-weight: 700;
            color: var(--color-muted);
            text-transform: uppercase;
            letter-spacing: .08em;
            padding-bottom: 4px;
            border-bottom: 1px solid var(--color-border);
        }

        /* ── Buttons ── */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 0 16px;
            height: 38px;
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

        .btn-primary:disabled {
            opacity: .6;
            cursor: not-allowed;
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

        /* ── Alert ── */
        .alert {
            padding: 12px 16px;
            border-radius: var(--radius);
            font-size: 13px;
        }

        .alert-error {
            background: var(--color-error-bg);
            border: 1px solid var(--color-error-b);
            color: var(--color-error-t);
        }

        .alert-success {
            background: #f0fdf4;
            border: 1px solid #86efac;
            color: #166534;
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
    </style>
</head>
<body>
<div class="crm-shell">

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

    <div class="main">
        <div class="topbar">
            <a href="/crm/members/<?= (int)$member->id ?>"
               class="topbar-back">&larr; <?= htmlspecialchars($member->first_name . ' ' . $member->last_name) ?></a>
            <h1>Edit Member</h1>
        </div>

        <div class="content">

            <div id="form-alert" style="display:none;margin-bottom:16px;max-width:680px"></div>

            <div class="card">
                <div class="card-header">Member Details</div>

                <div class="card-body">
                    <div class="section-title">Personal Information</div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">First Name</label>
                            <input
                                    type="text"
                                    id="first_name"
                                    name="first_name"
                                    value="<?= htmlspecialchars($member->first_name) ?>"
                                    autocomplete="given-name"
                            >
                            <div class="field-error" id="err-first_name"></div>
                        </div>
                        <div class="form-group">
                            <label for="last_name">Last Name</label>
                            <input
                                    type="text"
                                    id="last_name"
                                    name="last_name"
                                    value="<?= htmlspecialchars($member->last_name) ?>"
                                    autocomplete="family-name"
                            >
                            <div class="field-error" id="err-last_name"></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input
                                type="email"
                                id="email"
                                name="email"
                                value="<?= htmlspecialchars($member->email) ?>"
                                autocomplete="email"
                        >
                        <div class="field-error" id="err-email"></div>
                    </div>

                    <div class="section-title" style="margin-top:4px">Account</div>

                    <div class="form-group">
                        <label>Status</label>
                        <div class="toggle-group">
                            <label class="toggle">
                                <input type="checkbox" id="is_active"
                                       name="is_active" <?= $member->is_active ? 'checked' : '' ?>>
                                <span class="toggle-track"></span>
                            </label>
                            <div>
                                <div class="toggle-label"
                                     id="status-label"><?= $member->is_active ? 'Active' : 'Inactive' ?></div>
                                <div class="toggle-hint">Inactive members cannot log in.</div>
                            </div>
                        </div>
                    </div>

                    <div class="section-title" style="margin-top:4px">CRM</div>

                    <div class="form-group">
                        <label for="assigned_agent_id">Assigned Agent</label>
                        <select id="assigned_agent_id" name="assigned_agent_id">
                            <option value="">Unassigned</option>
                            <?php foreach ($agents as $agent): ?>
                                <option
                                        value="<?= (int)$agent['id'] ?>"
                                        <?= (int)$member->assigned_agent_id === (int)$agent['id'] ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($agent['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="field-error" id="err-assigned_agent_id"></div>
                    </div>

                    <div class="form-group">
                        <label for="crm_notes">CRM Notes</label>
                        <textarea id="crm_notes" name="crm_notes"
                                  rows="5"><?= htmlspecialchars($member->crm_notes ?? '') ?></textarea>
                        <div class="field-error" id="err-crm_notes"></div>
                    </div>
                </div>

                <div class="card-footer">
                    <a href="/crm/members/<?= (int)$member->id ?>" class="btn btn-ghost">Cancel</a>
                    <button class="btn btn-primary" id="save-btn" type="button">Save Changes</button>
                </div>
            </div>

        </div><!-- .content -->
    </div><!-- .main -->
</div><!-- .crm-shell -->

<div class="toast" id="toast"></div>

<script>
    (function () {
        'use strict';

        var memberId = <?= (int)$member->id ?>;
        var saveBtn = document.getElementById('save-btn');
        var formAlert = document.getElementById('form-alert');
        var toast = document.getElementById('toast');
        var statusLabel = document.getElementById('status-label');
        var isActiveChk = document.getElementById('is_active');

        // Live status label
        isActiveChk.addEventListener('change', function () {
            statusLabel.textContent = this.checked ? 'Active' : 'Inactive';
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

        function clearErrors() {
            document.querySelectorAll('.field-error').forEach(function (el) {
                el.textContent = '';
            });
            document.querySelectorAll('.has-error').forEach(function (el) {
                el.classList.remove('has-error');
            });
            formAlert.style.display = 'none';
        }

        function showFieldError(field, message) {
            var errEl = document.getElementById('err-' + field);
            var input = document.getElementById(field);
            if (errEl) errEl.textContent = message;
            if (input) input.classList.add('has-error');
        }

        function showAlert(message, type) {
            formAlert.className = 'alert alert-' + type;
            formAlert.textContent = message;
            formAlert.style.display = 'block';
            formAlert.scrollIntoView({behavior: 'smooth', block: 'nearest'});
        }

        saveBtn.addEventListener('click', function () {
            clearErrors();

            var payload = {
                first_name: document.getElementById('first_name').value.trim(),
                last_name: document.getElementById('last_name').value.trim(),
                email: document.getElementById('email').value.trim(),
                is_active: isActiveChk.checked ? 1 : 0,
                assigned_agent_id: document.getElementById('assigned_agent_id').value || null,
                crm_notes: document.getElementById('crm_notes').value,
            };

            // Client-side required check
            var hasError = false;
            ['first_name', 'last_name', 'email'].forEach(function (field) {
                if (!payload[field]) {
                    showFieldError(field, 'This field is required.');
                    hasError = true;
                }
            });

            if (hasError) return;

            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving…';

            fetch('/crm/members/' + memberId, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(payload)
            })
                .then(function (r) {
                    return r.json().then(function (d) {
                        return {status: r.status, data: d};
                    });
                })
                .then(function (res) {
                    if (res.data.success) {
                        showToast('Member updated successfully.');
                        setTimeout(function () {
                            window.location.href = '/crm/members/' + memberId;
                        }, 700);
                    } else if (res.status === 422) {
                        // Validation errors from server
                        var msg = res.data.message || 'Please correct the errors below.';
                        showAlert(msg, 'error');
                        // Map known field errors if returned as object
                        if (res.data.errors) {
                            Object.entries(res.data.errors).forEach(function (pair) {
                                showFieldError(pair[0], Array.isArray(pair[1]) ? pair[1][0] : pair[1]);
                            });
                        }
                    } else {
                        showAlert(res.data.message || 'Failed to update member.', 'error');
                    }
                })
                .catch(function () {
                    showAlert('Something went wrong. Please try again.', 'error');
                })
                .finally(function () {
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Save Changes';
                });
        });
    })();
</script>
</body>
</html>