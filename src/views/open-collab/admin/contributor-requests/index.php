@section('logic')
<?php
/**
 * Template: open-collab/admin/contributor-requests/index.php
 * Variables:
 *   $requests — Collection of ContributorRequest models
 *   $site     — string
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

<!-- Reject modal -->
<div id="reject-modal"
     style="display:none;position:fixed;inset:0;background:rgba(15,25,41,.55);z-index:500;place-items:center;"
     onclick="if(event.target===this)closeRejectModal()">
    <div style="background:#fff;border-radius:12px;padding:28px 32px;max-width:440px;width:92%;
                box-shadow:0 20px 60px rgba(0,0,0,.2);">
        <h3 style="font-family:var(--font-display);font-size:1.15rem;color:var(--navy);margin-bottom:6px;">
            Reject access request
        </h3>
        <p style="font-size:.85rem;color:var(--slate);margin-bottom:20px;">
            Optionally provide a reason. The applicant will not be notified automatically.
        </p>
        <input type="hidden" id="reject-request-id">
        <div class="oc-form-group">
            <label class="oc-label oc-label--optional" for="reject-reason">Reason</label>
            <textarea class="oc-textarea" id="reject-reason" rows="3" style="min-height:72px;"
                      placeholder="e.g. Not currently accepting contributors in this category…"></textarea>
        </div>
        <div id="reject-errors" class="oc-form-errors" style="display:none;margin-bottom:12px;"></div>
        <div style="display:flex;gap:10px;">
            <button onclick="closeRejectModal()" class="oc-btn oc-btn--ghost" style="flex:1;">Cancel</button>
            <button onclick="submitReject()" class="oc-btn oc-btn--danger" style="flex:1;" id="reject-confirm-btn">
                Reject
            </button>
        </div>
    </div>
</div>

<?php if ($requests->isEmpty()): ?>
    <div class="oc-card" style="padding:64px 24px;text-align:center;">
        <svg viewBox="0 0 20 20" fill="currentColor" width="36"
             style="opacity:.15;display:block;margin:0 auto 16px;color:var(--green);">
            <path fill-rule="evenodd"
                  d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                  clip-rule="evenodd"/>
        </svg>
        <div style="font-size:1.05rem;font-weight:600;color:var(--navy);">No pending requests</div>
        <div style="font-size:.875rem;color:var(--slate);margin-top:4px;">
            When contributors submit an access request it will appear here for review.
        </div>
    </div>
<?php else: ?>

    <div class="oc-card" style="overflow:hidden;">
        <div class="oc-card__header">
            <span class="oc-card__title">Pending Access Requests</span>
            <span style="font-size:.72rem;background:var(--amber-pale,#fffbeb);color:var(--amber-dark,#b45309);
                         padding:2px 8px;border-radius:10px;font-weight:600;">
                <?= $requests->count() ?> pending
            </span>
        </div>

        <div style="display:flex;flex-direction:column;">
            <?php foreach ($requests as $i => $req):
                $rArr = is_array($req) ? $req : (method_exists($req, 'toArray') ? $req->toArray() : (array)$req);
                $isLast = $i === $requests->count() - 1;
                ?>
                <div id="request-row-<?= (int)$rArr['id'] ?>"
                     style="padding:18px 20px;<?= !$isLast ? 'border-bottom:1px solid var(--border);' : '' ?>">

                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
                        <div style="flex:1;min-width:0;">
                            <div style="display:flex;align-items:center;gap:10px;margin-bottom:5px;flex-wrap:wrap;">
                                <div style="width:34px;height:34px;border-radius:50%;background:var(--navy);
                                            display:grid;place-items:center;font-weight:700;font-size:.82rem;
                                            color:var(--amber);flex-shrink:0;">
                                    <?= strtoupper(substr($rArr['name'] ?? 'C', 0, 1)) ?>
                                </div>
                                <div>
                                    <div style="font-weight:600;color:var(--navy);font-size:.9rem;">
                                        <?= htmlspecialchars($rArr['name'] ?? '') ?>
                                    </div>
                                    <div style="font-size:.75rem;color:var(--slate);">
                                        <?= htmlspecialchars($rArr['email'] ?? '') ?>
                                    </div>
                                </div>
                                <span style="font-size:.72rem;color:var(--slate-light);">
                                    Submitted <?= !empty($rArr['created_at']) ? date('d M Y', strtotime($rArr['created_at'])) : '' ?>
                                </span>
                            </div>

                            <!-- Bio -->
                            <div style="background:var(--cream-dark);border:1px solid var(--border);border-radius:6px;
                                        padding:10px 14px;font-size:.82rem;color:var(--navy);line-height:1.55;
                                        margin-left:44px;">
                                <?= htmlspecialchars($rArr['bio'] ?? '') ?>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div style="display:flex;flex-direction:column;gap:8px;align-items:flex-end;flex-shrink:0;">
                            <button onclick="approveRequest(<?= (int)$rArr['id'] ?>, this)"
                                    class="oc-btn oc-btn--primary oc-btn--sm"
                                    id="approve-btn-<?= (int)$rArr['id'] ?>">
                                <svg viewBox="0 0 20 20" fill="currentColor" width="13">
                                    <path fill-rule="evenodd"
                                          d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                          clip-rule="evenodd"/>
                                </svg>
                                Approve & invite
                            </button>
                            <button onclick="openRejectModal(<?= (int)$rArr['id'] ?>)"
                                    class="oc-btn oc-btn--ghost oc-btn--sm"
                                    style="border-color:#fecaca;color:var(--red);">
                                Reject
                            </button>
                        </div>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>
    </div>

<?php endif; ?>

@endsection

@section('scripts')
<script>
    const SITE = '<?= htmlspecialchars($site ?? '') ?>';
    const TOKEN = () => localStorage.getItem('oc_token') || '';

    async function approveRequest(id, btn) {
        if (!confirm('Approve this request and send an invitation?')) return;
        btn.disabled = true;
        btn.innerHTML = '<div class="oc-spinner"></div>';

        const res = await fetch(`/api/${SITE}/open-collab/admin/contributor-requests/${id}/approve`, {
            method: 'POST',
            headers: {'Authorization': `Bearer ${TOKEN()}`, 'Accept': 'application/json'},
        });
        const data = await res.json();
        if (res.ok) {
            showToast('✓ Invitation sent to ' + (data.data?.invitation?.email ?? ''));
            const row = document.getElementById('request-row-' + id);
            row.style.opacity = '.4';
            row.style.pointerEvents = 'none';
            setTimeout(() => row?.remove(), 1200);
        } else {
            showToast(data.error || 'Approval failed', false);
            btn.disabled = false;
            btn.textContent = '✓ Approve & invite';
        }
    }

    function openRejectModal(id) {
        document.getElementById('reject-request-id').value = id;
        document.getElementById('reject-reason').value = '';
        document.getElementById('reject-errors').style.display = 'none';
        document.getElementById('reject-modal').style.display = 'grid';
    }

    function closeRejectModal() {
        document.getElementById('reject-modal').style.display = 'none';
    }

    async function submitReject() {
        const id = document.getElementById('reject-request-id').value;
        const reason = document.getElementById('reject-reason').value.trim();
        const errBox = document.getElementById('reject-errors');
        const btn = document.getElementById('reject-confirm-btn');
        errBox.style.display = 'none';
        btn.disabled = true;
        btn.innerHTML = '<div class="oc-spinner"></div>';

        const res = await fetch(`/api/${SITE}/open-collab/admin/contributor-requests/${id}/reject`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${TOKEN()}`,
                'Accept': 'application/json'
            },
            body: JSON.stringify({reason: reason || undefined}),
        });
        if (res.ok) {
            closeRejectModal();
            showToast('Request rejected');
            const row = document.getElementById('request-row-' + id);
            row.style.opacity = '.4';
            row.style.pointerEvents = 'none';
            setTimeout(() => row?.remove(), 1200);
        } else {
            const data = await res.json();
            errBox.textContent = data.error || 'Rejection failed.';
            errBox.style.display = 'block';
            btn.disabled = false;
            btn.textContent = 'Reject';
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