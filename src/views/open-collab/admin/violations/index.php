@section('logic')
<?php
/**
 * Template: open-collab/admin/violations/index.php
 * Variables:
 *   $violations — array of ContributorViolation data
 *   $site       — string
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

<!-- Resolve modal -->
<div id="resolve-modal"
     style="display:none;position:fixed;inset:0;background:rgba(15,25,41,.55);z-index:500;place-items:center;"
     onclick="if(event.target===this)closeResolveModal()">
    <div style="background:#fff;border-radius:12px;padding:28px 32px;max-width:440px;width:92%;
                box-shadow:0 20px 60px rgba(0,0,0,.2);">
        <h3 style="font-family:var(--font-display);font-size:1.15rem;color:var(--navy);margin-bottom:6px;">
            Resolve violation
        </h3>
        <p style="font-size:.85rem;color:var(--slate);margin-bottom:20px;">
            Resolving will lift any associated suspension or ban if no other active violations remain.
        </p>
        <input type="hidden" id="resolve-violation-id">
        <div class="oc-form-group">
            <label class="oc-label oc-label--optional" for="resolve-notes">Resolution notes</label>
            <textarea class="oc-textarea" id="resolve-notes" rows="3"
                      placeholder="Optional notes…" style="min-height:72px;"></textarea>
        </div>
        <div id="resolve-errors" class="oc-form-errors" style="display:none;margin-bottom:12px;"></div>
        <div style="display:flex;gap:10px;">
            <button onclick="closeResolveModal()" class="oc-btn oc-btn--ghost" style="flex:1;">Cancel</button>
            <button onclick="submitResolve()" class="oc-btn oc-btn--primary" style="flex:1;" id="resolve-confirm-btn">
                Resolve
            </button>
        </div>
    </div>
</div>

<?php if (empty($violations)): ?>
    <div class="oc-card" style="padding:64px 24px;text-align:center;">
        <svg viewBox="0 0 20 20" fill="currentColor" width="36"
             style="opacity:.15;display:block;margin:0 auto 16px;color:var(--green);">
            <path fill-rule="evenodd"
                  d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                  clip-rule="evenodd"/>
        </svg>
        <div style="font-size:1.05rem;font-weight:600;color:var(--navy);">No violations recorded</div>
        <div style="font-size:.875rem;color:var(--slate);margin-top:4px;">Contributors are behaving well.</div>
    </div>
<?php else: ?>

    <?php
    $severityColors = ['high' => '#ef4444', 'medium' => '#f97316', 'low' => '#eab308'];
    $actionLabels = ['warning' => 'Warning', 'suspension' => 'Suspended', 'ban' => 'Banned'];
    $actionBadges = ['warning' => 'oc-badge--waiting-approval', 'suspension' => 'oc-badge--revoked', 'ban' => 'oc-badge--revoked'];
    ?>

    <div class="oc-card" style="overflow:hidden;">
        <div class="oc-card__header">
            <span class="oc-card__title">All Violations</span>
            <span style="font-size:.72rem;background:var(--slate-pale);color:var(--slate);
                         padding:2px 8px;border-radius:10px;font-weight:600;">
                <?= count($violations) ?>
            </span>
        </div>

        <table class="oc-table">
            <thead>
            <tr>
                <th>Contributor</th>
                <th>Type</th>
                <th>Severity</th>
                <th>Action</th>
                <th>Date</th>
                <th>Status</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($violations as $v):
                $vArr = is_array($v) ? $v : (method_exists($v, 'toArray') ? $v->toArray() : (array)$v);
                $isResolved = !empty($vArr['resolved_at']);
                $severity = $vArr['severity'] ?? 'low';
                $action = $vArr['action_taken'] ?? 'warning';
                ?>
                <tr>
                    <td>
                        <a href="/<?= htmlspecialchars($site) ?>/open-collab/admin/contributors/<?= (int)$vArr['user_id'] ?>"
                           style="font-weight:500;color:var(--navy);text-decoration:none;">
                            User #<?= (int)$vArr['user_id'] ?>
                        </a>
                    </td>
                    <td style="font-size:.82rem;color:var(--navy);">
                        <?= htmlspecialchars(str_replace('_', ' ', ucfirst($vArr['type'] ?? ''))) ?>
                    </td>
                    <td>
                        <span style="display:inline-flex;align-items:center;gap:5px;font-size:.78rem;font-weight:600;
                                color:<?= $severityColors[$severity] ?? '#64748b' ?>;">
                            <span style="width:6px;height:6px;border-radius:50%;background:currentColor;"></span>
                            <?= ucfirst($severity) ?>
                        </span>
                    </td>
                    <td>
                        <span class="oc-badge <?= $actionBadges[$action] ?? 'oc-badge--draft' ?>">
                            <?= $actionLabels[$action] ?? ucfirst($action) ?>
                        </span>
                    </td>
                    <td style="font-size:.78rem;color:var(--slate);">
                        <?= !empty($vArr['created_at']) ? date('d M Y', strtotime($vArr['created_at'])) : '–' ?>
                    </td>
                    <td>
                        <?php if ($isResolved): ?>
                            <span class="oc-badge oc-badge--published">Resolved</span>
                        <?php else: ?>
                            <span class="oc-badge oc-badge--draft">Open</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right;">
                        <div style="display:flex;gap:6px;justify-content:flex-end;">
                            <a href="/<?= htmlspecialchars($site) ?>/open-collab/admin/contributors/<?= (int)$vArr['user_id'] ?>/violations"
                               class="oc-btn oc-btn--ghost oc-btn--sm">Profile</a>
                            <?php if (!$isResolved): ?>
                                <button onclick="openResolveModal(<?= (int)$vArr['id'] ?>)"
                                        class="oc-btn oc-btn--primary oc-btn--sm">Resolve
                                </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php if (!empty($vArr['reason'])): ?>
                <tr>
                    <td colspan="7" style="padding:0 16px 10px;font-size:.78rem;color:var(--slate);
                                               background:var(--cream-dark);">
                        <strong>Reason:</strong> <?= htmlspecialchars($vArr['reason']) ?>
                    </td>
                </tr>
            <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php endif; ?>

@endsection

@section('scripts')
<script>
    const SITE = '<?= htmlspecialchars($site ?? '') ?>';
    const TOKEN = () => localStorage.getItem('oc_token') || '';

    function openResolveModal(id) {
        document.getElementById('resolve-violation-id').value = id;
        document.getElementById('resolve-notes').value = '';
        document.getElementById('resolve-errors').style.display = 'none';
        document.getElementById('resolve-modal').style.display = 'grid';
    }

    function closeResolveModal() {
        document.getElementById('resolve-modal').style.display = 'none';
    }

    async function submitResolve() {
        const id = document.getElementById('resolve-violation-id').value;
        const notes = document.getElementById('resolve-notes').value.trim();
        const errBox = document.getElementById('resolve-errors');
        const btn = document.getElementById('resolve-confirm-btn');
        errBox.style.display = 'none';
        btn.disabled = true;
        btn.innerHTML = '<div class="oc-spinner"></div>';

        const res = await fetch(`/api/${SITE}/open-collab/admin/violations/${id}/resolve`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${TOKEN()}`,
                'Accept': 'application/json'
            },
            body: JSON.stringify({notes: notes || undefined}),
        });

        if (res.ok) {
            closeResolveModal();
            showToast('✓ Violation resolved');
            setTimeout(() => location.reload(), 800);
        } else {
            const data = await res.json();
            errBox.textContent = data.error || 'Failed to resolve.';
            errBox.style.display = 'block';
            btn.disabled = false;
            btn.textContent = 'Resolve';
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