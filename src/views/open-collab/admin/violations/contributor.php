@section('logic')
<?php
/**
 * Template: open-collab/admin/violations/contributor.php
 * Variables:
 *   $contributor — array
 *   $violations  — Collection
 *   $site        — string
 */

$severityColors = ['high' => '#ef4444', 'medium' => '#f97316', 'low' => '#eab308'];
$actionBadges = ['warning' => 'oc-badge--waiting-approval', 'suspension' => 'oc-badge--revoked', 'ban' => 'oc-badge--revoked'];
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
    <div style="background:#fff;border-radius:12px;padding:28px 32px;max-width:440px;width:92%;box-shadow:0 20px 60px rgba(0,0,0,.2);">
        <h3 style="font-family:var(--font-display);font-size:1.15rem;color:var(--navy);margin-bottom:6px;">Resolve
            violation</h3>
        <p style="font-size:.85rem;color:var(--slate);margin-bottom:20px;">
            Resolving will lift any suspension/ban if no other active violations remain.
        </p>
        <input type="hidden" id="resolve-violation-id">
        <div class="oc-form-group">
            <label class="oc-label oc-label--optional" for="resolve-notes">Resolution notes</label>
            <textarea class="oc-textarea" id="resolve-notes" rows="3" style="min-height:72px;"
                      placeholder="Optional…"></textarea>
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

<div class="oc-grid-sidebar" style="align-items:start;gap:24px;">

    <!-- Violations list -->
    <div style="display:flex;flex-direction:column;gap:20px;">

        <!-- Contributor summary bar -->
        <div class="oc-card" style="padding:16px 20px;">
            <div style="display:flex;align-items:center;gap:14px;">
                <div style="width:40px;height:40px;border-radius:50%;background:var(--navy);display:grid;place-items:center;
                            font-weight:700;font-size:.95rem;color:var(--amber);flex-shrink:0;">
                    <?= strtoupper(substr($contributor['name'] ?? 'C', 0, 1)) ?>
                </div>
                <div>
                    <div style="font-weight:600;color:var(--navy);"><?= htmlspecialchars($contributor['name'] ?? '') ?></div>
                    <div style="font-size:.78rem;color:var(--slate);"><?= htmlspecialchars($contributor['email'] ?? '') ?></div>
                </div>
                <a href="/<?= htmlspecialchars($site) ?>/open-collab/admin/contributors/<?= (int)$contributor['id'] ?>"
                   class="oc-btn oc-btn--ghost oc-btn--sm" style="margin-left:auto;">← Profile</a>
            </div>
        </div>

        <?php if ($violations->isEmpty()): ?>
            <div class="oc-card" style="padding:48px 24px;text-align:center;">
                <svg viewBox="0 0 20 20" fill="currentColor" width="32"
                     style="opacity:.2;display:block;margin:0 auto 12px;color:var(--green);">
                    <path fill-rule="evenodd"
                          d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                          clip-rule="evenodd"/>
                </svg>
                <div style="font-weight:500;color:var(--navy);">No violations for this contributor</div>
            </div>
        <?php else: ?>
            <div class="oc-card" style="overflow:hidden;">
                <div class="oc-card__header">
                    <span class="oc-card__title">Violation History</span>
                    <span style="font-size:.72rem;background:var(--slate-pale);color:var(--slate);padding:2px 8px;border-radius:10px;font-weight:600;">
                        <?= $violations->count() ?>
                    </span>
                </div>
                <div style="display:flex;flex-direction:column;">
                    <?php foreach ($violations as $i => $v):
                        $vArr = is_array($v) ? $v : (method_exists($v, 'toArray') ? $v->toArray() : (array)$v);
                        $isResolved = !empty($vArr['resolved_at']);
                        $severity = $vArr['severity'] ?? 'low';
                        $action = $vArr['action_taken'] ?? 'warning';
                        $isLast = $i === $violations->count() - 1;
                        ?>
                        <div style="padding:16px 20px;<?= !$isLast ? 'border-bottom:1px solid var(--border);' : '' ?>">
                            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:8px;">
                                <div>
                                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px;">
                                        <span style="font-weight:600;font-size:.875rem;color:var(--navy);">
                                            <?= htmlspecialchars(str_replace('_', ' ', ucfirst($vArr['type'] ?? ''))) ?>
                                        </span>
                                        <span class="oc-badge <?= $actionBadges[$action] ?? 'oc-badge--draft' ?>"
                                              style="font-size:.65rem;">
                                            <?= ucfirst($action) ?>
                                        </span>
                                        <span style="font-size:.72rem;font-weight:600;color:<?= $severityColors[$severity] ?? '#64748b' ?>;">
                                            <?= ucfirst($severity) ?> severity
                                        </span>
                                    </div>
                                    <div style="font-size:.8rem;color:var(--slate);">
                                        <?= htmlspecialchars($vArr['reason'] ?? '') ?>
                                    </div>
                                </div>
                                <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;flex-shrink:0;">
                                    <div style="font-size:.72rem;color:var(--slate-light);">
                                        <?= !empty($vArr['created_at']) ? date('d M Y', strtotime($vArr['created_at'])) : '' ?>
                                    </div>
                                    <?php if ($isResolved): ?>
                                        <span class="oc-badge oc-badge--published"
                                              style="font-size:.65rem;">Resolved</span>
                                    <?php else: ?>
                                        <button onclick="openResolveModal(<?= (int)$vArr['id'] ?>)"
                                                class="oc-btn oc-btn--primary oc-btn--sm" style="font-size:.72rem;">
                                            Resolve
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ($isResolved && !empty($vArr['resolution_notes'])): ?>
                                <div style="font-size:.75rem;color:var(--slate);padding:8px 12px;background:var(--cream-dark);border-radius:6px;margin-top:6px;">
                                    <strong>Resolution:</strong> <?= htmlspecialchars($vArr['resolution_notes']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <!-- Record new violation sidebar -->
    <div style="position:sticky;top:calc(var(--header-h,64px) + 20px);">
        <div class="oc-card">
            <div class="oc-card__header">
                <span class="oc-card__title" style="font-size:.95rem;">Record Violation</span>
            </div>
            <div class="oc-card__body">
                <div id="record-errors" class="oc-form-errors" style="display:none;margin-bottom:12px;"></div>

                <div class="oc-form-group">
                    <label class="oc-label" for="v-type">Type</label>
                    <select class="oc-select" id="v-type">
                        <option value="plagiarism">Plagiarism</option>
                        <option value="spam">Spam</option>
                        <option value="misinformation">Misinformation</option>
                        <option value="policy">Policy violation</option>
                        <option value="quality">Quality issues</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="oc-form-group">
                    <label class="oc-label" for="v-severity">Severity</label>
                    <select class="oc-select" id="v-severity">
                        <option value="low">Low — Warning after 5</option>
                        <option value="medium">Medium — Suspension after 3</option>
                        <option value="high">High — Immediate ban</option>
                    </select>
                </div>
                <div class="oc-form-group">
                    <label class="oc-label oc-label--optional" for="v-action">Action override</label>
                    <select class="oc-select" id="v-action">
                        <option value="">Auto (based on threshold)</option>
                        <option value="warning">Warning</option>
                        <option value="suspension">Suspension</option>
                        <option value="ban">Ban</option>
                    </select>
                    <div class="oc-help">Leave blank to use threshold rules.</div>
                </div>
                <div class="oc-form-group">
                    <label class="oc-label" for="v-reason">Reason</label>
                    <textarea class="oc-textarea" id="v-reason" rows="3" style="min-height:72px;"
                              placeholder="Describe the violation in detail (min 10 chars)…"></textarea>
                </div>

                <button onclick="recordViolation()" class="oc-btn oc-btn--danger oc-btn--block" id="record-btn">
                    <svg viewBox="0 0 20 20" fill="currentColor" width="14">
                        <path fill-rule="evenodd"
                              d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                              clip-rule="evenodd"/>
                    </svg>
                    Record violation
                </button>
            </div>
        </div>
    </div>

</div>

@endsection

@section('scripts')
<script>
    const SITE = '<?= htmlspecialchars($site ?? '') ?>';
    const CONTRIBUTOR_ID = <?= (int)$contributor['id'] ?>;
    const TOKEN = () => localStorage.getItem('oc_token') || '';

    async function recordViolation() {
        const type = document.getElementById('v-type').value;
        const severity = document.getElementById('v-severity').value;
        const action = document.getElementById('v-action').value;
        const reason = document.getElementById('v-reason').value.trim();
        const errBox = document.getElementById('record-errors');
        const btn = document.getElementById('record-btn');
        errBox.style.display = 'none';

        if (!reason || reason.length < 10) {
            errBox.textContent = 'Reason must be at least 10 characters.';
            errBox.style.display = 'block';
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<div class="oc-spinner"></div> Recording…';

        const payload = {type, severity, reason};
        if (action) payload.action_taken = action;

        const res = await fetch(`/api/${SITE}/open-collab/admin/contributors/${CONTRIBUTOR_ID}/violations`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${TOKEN()}`,
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload),
        });
        const data = await res.json();
        if (res.ok) {
            showToast('✓ Violation recorded');
            setTimeout(() => location.reload(), 800);
        } else {
            errBox.textContent = data.error || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Failed.');
            errBox.style.display = 'block';
            btn.disabled = false;
            btn.innerHTML = '⚠ Record violation';
        }
    }

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
            showToast('✓ Resolved');
            setTimeout(() => location.reload(), 800);
        } else {
            const d = await res.json();
            errBox.textContent = d.error || 'Failed.';
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