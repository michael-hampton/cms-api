@section('logic')
<?php
/**
 * Template: open-collab/admin/contributors/index.php
 * Variables:
 *   $contributors — array of contributor data
 *   $query        — string|null current search query
 *   $site         — string
 *   $currentUser  — AuthenticatedUser
 */
?>
@endsection

@extends('open-collab/layouts/admin')

@section('content')

<div id="status-toast"
     style="position:fixed;bottom:24px;left:50%;transform:translateX(-50%);
            background:var(--navy);color:#fff;padding:9px 20px;border-radius:20px;
            font-size:.8rem;font-weight:500;opacity:0;transition:opacity .3s;
            z-index:300;pointer-events:none;"></div>

<!-- Search bar -->
<div class="oc-card" style="margin-bottom:20px;padding:16px 20px;">
    <form method="GET" style="display:flex;gap:10px;align-items:center;">
        <div style="position:relative;flex:1;">
            <svg viewBox="0 0 20 20" fill="currentColor" width="16"
                 style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--slate-light);pointer-events:none;">
                <path fill-rule="evenodd"
                      d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z"
                      clip-rule="evenodd"/>
            </svg>
            <input class="oc-input" type="text" name="q"
                   value="<?= htmlspecialchars($query ?? '') ?>"
                   placeholder="Search by name or email…"
                   style="padding-left:38px;">
        </div>
        <button type="submit" class="oc-btn oc-btn--primary">Search</button>
        <?php if ($query): ?>
            <a href="?q=" class="oc-btn oc-btn--ghost">Clear</a>
        <?php endif; ?>
    </form>
</div>

<!-- Invite new contributor -->
<div class="oc-card" style="margin-bottom:20px;">
    <div class="oc-card__header">
        <span class="oc-card__title">Invite Contributor</span>
    </div>
    <div class="oc-card__body">
        <div id="invite-errors" class="oc-form-errors" style="display:none;margin-bottom:12px;"></div>
        <div style="display:flex;gap:10px;align-items:flex-end;">
            <div class="oc-form-group" style="flex:1;margin-bottom:0;">
                <label class="oc-label" for="invite-email">Email address</label>
                <input class="oc-input" type="email" id="invite-email" placeholder="contributor@example.com">
            </div>
            <button onclick="sendInvite()" class="oc-btn oc-btn--amber" id="invite-btn">
                Send invitation
            </button>
        </div>
    </div>
</div>

<!-- Contributors list -->
<div class="oc-card">
    <div class="oc-card__header">
        <span class="oc-card__title">
            <?= $query ? 'Search results for "' . htmlspecialchars($query) . '"' : 'All Contributors' ?>
        </span>
        <span style="font-size:.72rem;background:var(--slate-pale);color:var(--slate);
                     padding:2px 8px;border-radius:10px;font-weight:600;">
            <?= count($contributors) ?>
        </span>
    </div>

    <?php if (empty($contributors)): ?>
        <div style="padding:48px 24px;text-align:center;color:var(--slate);">
            <svg viewBox="0 0 20 20" fill="currentColor" width="32"
                 style="opacity:.2;display:block;margin:0 auto 12px;">
                <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z"/>
            </svg>
            <div style="font-weight:500;margin-bottom:6px;">
                <?= $query ? 'No contributors found matching "' . htmlspecialchars($query) . '"' : 'No contributors yet' ?>
            </div>
            <div style="font-size:.85rem;">
                <?= $query ? 'Try a different search term.' : 'Send invitations to add contributors to this site.' ?>
            </div>
        </div>
    <?php else: ?>
        <table class="oc-table">
            <thead>
            <tr>
                <th>Contributor</th>
                <th>Status</th>
                <th>Role</th>
                <th>Joined</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($contributors as $c):
                $values = is_array($c) ? $c : (method_exists($c, 'toArray') ? $c->toArray() : (array)$c);
                $isActive = (bool)($values['is_active'] ?? true);
                ?>
                <tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div style="width:32px;height:32px;border-radius:50%;background:var(--navy);
                                        display:grid;place-items:center;font-weight:700;font-size:.8rem;
                                        color:var(--amber);flex-shrink:0;">
                                <?= strtoupper(substr($values['name'] ?? 'C', 0, 1)) ?>
                            </div>
                            <div>
                                <div style="font-weight:500;color:var(--navy);">
                                    <?= htmlspecialchars($values['name'] ?? '–') ?>
                                </div>
                                <div style="font-size:.75rem;color:var(--slate);">
                                    <?= htmlspecialchars($values['email'] ?? '') ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <?php if ($isActive): ?>
                            <span class="oc-badge oc-badge--published">Active</span>
                        <?php else: ?>
                            <span class="oc-badge oc-badge--revoked">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:.82rem;color:var(--slate);">
                        <?= htmlspecialchars($values['role'] ?? 'contributor') ?>
                    </td>
                    <td style="font-size:.78rem;color:var(--slate);">
                        <?= !empty($values['created_at']) ? date('d M Y', strtotime($values['created_at'])) : '–' ?>
                    </td>
                    <td style="text-align:right;">
                        <div style="display:flex;gap:6px;justify-content:flex-end;">
                            <a href="/<?= htmlspecialchars($site) ?>/open-collab/admin/contributors/<?= (int)$values['id'] ?>"
                               class="oc-btn oc-btn--ghost oc-btn--sm">View</a>
                            <?php if ($isActive): ?>
                                <button onclick="deactivate(<?= (int)$values['id'] ?>, this)"
                                        class="oc-btn oc-btn--ghost oc-btn--sm"
                                        style="border-color:#fecaca;color:var(--red);">Deactivate
                                </button>
                            <?php else: ?>
                                <button onclick="reactivate(<?= (int)$values['id'] ?>, this)"
                                        class="oc-btn oc-btn--ghost oc-btn--sm"
                                        style="border-color:#bbf7d0;color:var(--green);">Reactivate
                                </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

@endsection

@section('scripts')
<script>
    const SITE = '<?= htmlspecialchars($site ?? '') ?>';
    const TOKEN = () => localStorage.getItem('oc_token') || '';

    async function sendInvite() {
        const email = document.getElementById('invite-email').value.trim();
        const errBox = document.getElementById('invite-errors');
        const btn = document.getElementById('invite-btn');
        errBox.style.display = 'none';

        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            errBox.textContent = 'A valid email address is required.';
            errBox.style.display = 'block';
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<div class="oc-spinner"></div> Sending…';

        try {
            const res = await fetch(`/api/${SITE}/open-collab/invitations`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${TOKEN()}`,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({email}),
            });
            const data = await res.json();
            if (res.ok) {
                document.getElementById('invite-email').value = '';
                showToast('✓ Invitation sent to ' + email);
            } else {
                errBox.textContent = data.error || data.message || 'Failed to send invitation.';
                errBox.style.display = 'block';
            }
        } catch {
            errBox.textContent = 'Network error. Please try again.';
            errBox.style.display = 'block';
        } finally {
            btn.disabled = false;
            btn.textContent = 'Send invitation';
        }
    }

    async function deactivate(id, btn) {
        if (!confirm('Deactivate this contributor? They will lose access immediately.')) return;
        btn.disabled = true;
        try {
            const res = await fetch(`/api/${SITE}/open-collab/admin/contributors/${id}/deactivate`, {
                method: 'POST',
                headers: {'Authorization': `Bearer ${TOKEN()}`, 'Accept': 'application/json'},
            });
            if (res.ok) {
                showToast('Contributor deactivated');
                setTimeout(() => location.reload(), 800);
            } else {
                showToast('Deactivation failed', false);
                btn.disabled = false;
            }
        } catch {
            showToast('Network error', false);
            btn.disabled = false;
        }
    }

    async function reactivate(id, btn) {
        btn.disabled = true;
        try {
            const res = await fetch(`/api/${SITE}/open-collab/admin/contributors/${id}/reactivate`, {
                method: 'POST',
                headers: {'Authorization': `Bearer ${TOKEN()}`, 'Accept': 'application/json'},
            });
            if (res.ok) {
                showToast('Contributor reactivated');
                setTimeout(() => location.reload(), 800);
            } else {
                showToast('Reactivation failed', false);
                btn.disabled = false;
            }
        } catch {
            showToast('Network error', false);
            btn.disabled = false;
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