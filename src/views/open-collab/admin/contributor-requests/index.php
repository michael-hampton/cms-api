@section('logic')
<?php
/**
 * Template: open-collab/admin/contributor-requests/index.php
 * Variables:
 *   $site        — string
 *   $currentUser — AuthenticatedUser
 *
 * Data is loaded client-side via ContributorRequestController::index,
 * ::approve and ::reject.
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

<!-- Search bar -->
<div class="oc-card" style="margin-bottom:20px;padding:16px 20px;">
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <div style="position:relative;flex:1;min-width:200px;">
            <svg viewBox="0 0 20 20" fill="currentColor" width="16"
                 style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--slate-light);pointer-events:none;">
                <path fill-rule="evenodd"
                      d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z"
                      clip-rule="evenodd"/>
            </svg>
            <input class="oc-input" type="text" id="search-input"
                   placeholder="Search by name or email…"
                   style="padding-left:38px;"
                   autocomplete="off">
        </div>
        <div style="font-size:.82rem;color:var(--slate);">
            <span id="results-count">—</span> requests
        </div>
    </div>
</div>

<!-- Results card -->
<div class="oc-card">
    <div class="oc-card__header">
        <span class="oc-card__title" id="results-title">Pending Access Requests</span>
        <span id="pending-badge"
              style="font-size:.72rem;background:#fef3c7;color:#92400e;
                     padding:2px 8px;border-radius:10px;font-weight:600;">—</span>
    </div>

    <div id="requests-loading" style="padding:48px 24px;text-align:center;color:var(--slate);">
        <div class="oc-spinner" style="margin:0 auto 12px;"></div>
        Loading requests…
    </div>

    <div id="requests-empty" style="display:none;padding:64px 24px;text-align:center;color:var(--slate);">
        <svg viewBox="0 0 20 20" fill="currentColor" width="36"
             style="opacity:.15;display:block;margin:0 auto 16px;color:var(--green);">
            <path fill-rule="evenodd"
                  d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                  clip-rule="evenodd"/>
        </svg>
        <div style="font-size:1.05rem;font-weight:600;color:var(--navy);" id="empty-message">No pending requests</div>
        <div style="font-size:.875rem;color:var(--slate);margin-top:4px;" id="empty-sub">
            When contributors submit an access request it will appear here for review.
        </div>
    </div>

    <div id="requests-error"
         style="display:none;padding:32px 24px;text-align:center;color:var(--red);font-size:.875rem;">
        Failed to load requests.
        <button onclick="loadRequests()" class="oc-btn oc-btn--ghost oc-btn--sm" style="margin-left:8px;">Retry</button>
    </div>

    <div id="requests-list" style="display:none;flex-direction:column;"></div>
</div>

@endsection

@section('scripts')
<script>
    const SITE = '<?= htmlspecialchars($site ?? '') ?>';
    const TOKEN = () => localStorage.getItem('oc_token') || '';

    let allRequests = [];
    let debounceTimer = null;

    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('search-input').addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => renderRequests(this.value.trim().toLowerCase()), 300);
        });

        loadRequests();
    });

    async function loadRequests() {
        showState('loading');
        try {
            const res = await fetch(`/api/${SITE}/open-collab/admin/contributor-requests`, {
                headers: {'Authorization': `Bearer ${TOKEN()}`, 'Accept': 'application/json'},
            });
            if (!res.ok) {
                showState('error');
                return;
            }
            const data = await res.json();
            allRequests = Array.isArray(data) ? data : (data.data ?? []);
            renderRequests('');
        } catch {
            showState('error');
        }
    }

    function renderRequests(query) {
        const filtered = query
            ? allRequests.filter(r =>
                (r.name ?? '').toLowerCase().includes(query) ||
                (r.email ?? '').toLowerCase().includes(query) ||
                (r.bio ?? '').toLowerCase().includes(query))
            : allRequests;

        document.getElementById('results-count').textContent = filtered.length;
        document.getElementById('pending-badge').textContent = `${filtered.length} pending`;

        if (!filtered.length) {
            showState('empty');
            document.getElementById('empty-message').textContent =
                query ? `No requests matching "${query}"` : 'No pending requests';
            document.getElementById('empty-sub').textContent =
                query ? 'Try a different search term.' : 'When contributors submit an access request it will appear here.';
            return;
        }

        const list = document.getElementById('requests-list');
        list.innerHTML = '';

        filtered.forEach((r, i) => {
            const isLast = i === filtered.length - 1;
            const submitted = r.created_at
                ? new Date(r.created_at).toLocaleDateString('en-GB', {day: 'numeric', month: 'short', year: 'numeric'})
                : '';
            const initial = (r.name || 'C').charAt(0).toUpperCase();

            const div = document.createElement('div');
            div.id = `request-row-${r.id}`;
            div.style.cssText = `padding:18px 20px;${!isLast ? 'border-bottom:1px solid var(--border);' : ''}`;
            div.innerHTML = `
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
                    <div style="flex:1;min-width:0;">
                        <div style="display:flex;align-items:center;gap:10px;margin-bottom:5px;flex-wrap:wrap;">
                            <div style="width:34px;height:34px;border-radius:50%;background:var(--navy);
                                        display:grid;place-items:center;font-weight:700;font-size:.82rem;
                                        color:var(--amber);flex-shrink:0;">${escHtml(initial)}</div>
                            <div>
                                <div style="font-weight:600;color:var(--navy);font-size:.9rem;">${escHtml(r.name ?? '')}</div>
                                <div style="font-size:.75rem;color:var(--slate);">${escHtml(r.email ?? '')}</div>
                            </div>
                            <span style="font-size:.72rem;color:var(--slate-light);">Submitted ${submitted}</span>
                        </div>
                        <div style="background:var(--cream-dark);border:1px solid var(--border);border-radius:6px;
                                    padding:10px 14px;font-size:.82rem;color:var(--navy);line-height:1.55;
                                    margin-left:44px;">
                            ${escHtml(r.bio ?? '')}
                        </div>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:8px;align-items:flex-end;flex-shrink:0;">
                        <button onclick="approveRequest(${r.id}, this)"
                                class="oc-btn oc-btn--primary oc-btn--sm"
                                id="approve-btn-${r.id}">
                            <svg viewBox="0 0 20 20" fill="currentColor" width="13">
                                <path fill-rule="evenodd"
                                      d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                      clip-rule="evenodd"/>
                            </svg>
                            Approve &amp; invite
                        </button>
                        <button onclick="openRejectModal(${r.id})"
                                class="oc-btn oc-btn--ghost oc-btn--sm"
                                style="border-color:#fecaca;color:var(--red);">
                            Reject
                        </button>
                    </div>
                </div>`;
            list.appendChild(div);
        });

        showState('list');
    }

    // ── Approve ───────────────────────────────────────────────
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
            allRequests = allRequests.filter(r => r.id !== id);
            renderRequests(document.getElementById('search-input').value.trim().toLowerCase());
        } else {
            showToast(data.error || 'Approval failed', false);
            btn.disabled = false;
            btn.textContent = '✓ Approve & invite';
        }
    }

    // ── Reject modal ──────────────────────────────────────────
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
                'Accept': 'application/json',
            },
            body: JSON.stringify({reason: reason || undefined}),
        });
        if (res.ok) {
            closeRejectModal();
            showToast('Request rejected');
            allRequests = allRequests.filter(r => r.id !== parseInt(id));
            renderRequests(document.getElementById('search-input').value.trim().toLowerCase());
        } else {
            const data = await res.json();
            errBox.textContent = data.error || 'Rejection failed.';
            errBox.style.display = 'block';
            btn.disabled = false;
            btn.textContent = 'Reject';
        }
    }

    // ── Helpers ───────────────────────────────────────────────
    function showState(state) {
        document.getElementById('requests-loading').style.display = state === 'loading' ? 'block' : 'none';
        document.getElementById('requests-empty').style.display = state === 'empty' ? 'block' : 'none';
        document.getElementById('requests-error').style.display = state === 'error' ? 'block' : 'none';
        document.getElementById('requests-list').style.display = state === 'list' ? 'flex' : 'none';
    }

    function escHtml(str) {
        if (str == null) return '';
        return String(str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function showToast(msg, ok = true) {
        const el = document.getElementById('status-toast');
        el.textContent = msg;
        el.style.background = ok ? 'var(--navy)' : 'var(--red)';
        el.style.opacity = '1';
        setTimeout(() => {
            el.style.opacity = '0';
        }, 2800);
    }
</script>
@endsection