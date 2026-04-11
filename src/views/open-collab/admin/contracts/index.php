@section('logic')
<?php
/**
 * Template: open-collab/admin/contracts/index.php
 * Variables:
 *   $contracts   — array of contract data from API (will be fetched client-side)
 *   $site        — string
 *   $currentUser — AuthenticatedUser
 */

$pageTitle = 'Contributor Contracts';
$activeNav = 'contracts';
$breadcrumbs = [['label' => 'Contracts']];
?>
@endsection

@extends('open-collab/layouts/admin')

@section('content')

<div id="status-toast"
     style="position:fixed;bottom:24px;left:50%;transform:translateX(-50%);
            background:var(--navy);color:#fff;padding:9px 20px;border-radius:20px;
            font-size:.8rem;font-weight:500;opacity:0;transition:opacity .3s;
            z-index:300;pointer-events:none;"></div>

<div class="oc-grid-sidebar" style="align-items:start;gap:24px;">

    <!-- Versions list -->
    <div style="display:flex;flex-direction:column;gap:0;">

        <div class="oc-card" style="margin-bottom:20px;">
            <div class="oc-card__header">
                <span class="oc-card__title">Contract Versions</span>
                <span id="contract-count"
                      style="font-size:.72rem;background:var(--slate-pale);color:var(--slate);
                     padding:2px 8px;border-radius:10px;font-weight:600;">—</span>
            </div>

            <div id="contracts-loading"
                 style="padding:40px;text-align:center;color:var(--slate);font-size:.875rem;">
                <div class="oc-spinner" style="margin:0 auto 12px;"></div>
                Loading contracts…
            </div>

            <div id="contracts-empty"
                 style="display:none;padding:48px 24px;text-align:center;color:var(--slate);">
                <svg viewBox="0 0 20 20" fill="currentColor" width="32"
                     style="opacity:.2;display:block;margin:0 auto 12px;">
                    <path fill-rule="evenodd"
                          d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"
                          clip-rule="evenodd"/>
                </svg>
                <div style="font-weight:500;margin-bottom:6px;">No contracts yet</div>
                <div style="font-size:.85rem;">Create the first version using the form.</div>
            </div>

            <div id="contracts-list" style="display:none;"></div>
        </div>

    </div>

    <!-- Create new version -->
    <div style="position:sticky;top:calc(var(--header-h,64px) + 20px);">
        <div class="oc-card">
            <div class="oc-card__header">
                <span class="oc-card__title" style="font-size:.95rem;">New Contract Version</span>
            </div>
            <div class="oc-card__body">
                <div class="oc-alert oc-alert--info" style="margin-bottom:16px;font-size:.8rem;">
                    <svg viewBox="0 0 20 20" fill="currentColor" width="14">
                        <path fill-rule="evenodd"
                              d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                              clip-rule="evenodd"/>
                    </svg>
                    Each new version auto-increments. Existing signed contributors
                    are unaffected until they re-onboard.
                </div>

                <div id="create-errors" class="oc-form-errors" style="display:none;margin-bottom:12px;"></div>

                <div class="oc-form-group">
                    <label class="oc-label" for="contract-content">Contract content</label>
                    <textarea class="oc-textarea" id="contract-content" rows="12"
                              placeholder="Enter the full contributor agreement text (HTML or plain text)…"
                              style="min-height:220px;font-family:monospace;font-size:.82rem;"></textarea>
                    <div class="oc-help">Minimum 50 characters. Supports basic HTML.</div>
                </div>

                <button onclick="createContract()" class="oc-btn oc-btn--amber oc-btn--block" id="create-btn">
                    Publish new version
                </button>
            </div>
        </div>
    </div>

</div>

<!-- View modal -->
<div id="view-modal"
     style="display:none;position:fixed;inset:0;background:rgba(15,25,41,.55);
            z-index:500;place-items:center;"
     onclick="if(event.target===this)closeViewModal()">
    <div style="background:#fff;border-radius:var(--radius-xl,12px);
              max-width:680px;width:94%;max-height:80vh;display:flex;flex-direction:column;
              box-shadow:0 20px 60px rgba(0,0,0,.2);">
        <div style="padding:20px 24px;border-bottom:1px solid var(--border);
                display:flex;align-items:center;justify-content:space-between;">
            <span style="font-weight:700;color:var(--navy);" id="modal-title">Contract v—</span>
            <button onclick="closeViewModal()"
                    style="background:none;border:none;cursor:pointer;color:var(--slate);font-size:1.2rem;">
                ✕
            </button>
        </div>
        <div id="modal-content"
             style="padding:24px;overflow-y:auto;font-size:.875rem;line-height:1.75;color:var(--navy);">
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
    const SITE = '<?= htmlspecialchars($site ?? '') ?>';
    const TOKEN = () => localStorage.getItem('oc_token') || '';

    // ── Load contracts ────────────────────────────────────────
    async function loadContracts() {
        try {
            const res = await fetch(`/api/${SITE}/open-collab/admin/contracts`, {
                headers: {'Authorization': `Bearer ${TOKEN()}`, 'Accept': 'application/json'},
            });
            const data = await res.json();
            const items = Array.isArray(data) ? data : (data.data ?? []);

            document.getElementById('contracts-loading').style.display = 'none';
            document.getElementById('contract-count').textContent = items.length;

            if (!items.length) {
                document.getElementById('contracts-empty').style.display = 'block';
                return;
            }

            const list = document.getElementById('contracts-list');
            list.style.display = 'block';
            list.innerHTML = '';

            items.forEach((c, i) => {
                const isLatest = i === 0;
                const div = document.createElement('div');
                div.style.cssText = 'padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:12px;';
                div.innerHTML = `
          <div>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:3px;">
              <span style="font-weight:600;color:var(--navy);">Version ${c.version}</span>
              ${isLatest ? '<span class="oc-badge oc-badge--published" style="font-size:.65rem;">Current</span>' : ''}
            </div>
            <div style="font-size:.75rem;color:var(--slate);">
              Created ${c.created_at ? new Date(c.created_at).toLocaleDateString('en-GB', {
                    day: 'numeric',
                    month: 'short',
                    year: 'numeric'
                }) : '—'}
              · ${c.content ? c.content.replace(/<[^>]+>/g, '').slice(0, 60) + '…' : '—'}
            </div>
          </div>
          <button onclick="viewContract(${c.id}, ${c.version})"
                  class="oc-btn oc-btn--ghost oc-btn--sm" style="flex-shrink:0;">
            View
          </button>`;
                list.appendChild(div);
            });
        } catch (e) {
            document.getElementById('contracts-loading').innerHTML =
                '<div style="color:var(--red);font-size:.85rem;">Failed to load contracts.</div>';
        }
    }

    // ── Create contract ───────────────────────────────────────
    async function createContract() {
        const content = document.getElementById('contract-content').value.trim();
        const errBox = document.getElementById('create-errors');
        const btn = document.getElementById('create-btn');
        errBox.style.display = 'none';

        if (!content) {
            errBox.textContent = 'Contract content is required.';
            errBox.style.display = 'block';
            return;
        }
        if (content.length < 50) {
            errBox.textContent = 'Contract must be at least 50 characters.';
            errBox.style.display = 'block';
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<div class="oc-spinner"></div> Publishing…';

        try {
            const res = await fetch(`/api/${SITE}/open-collab/admin/contracts`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${TOKEN()}`,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({content}),
            });
            const data = await res.json();
            if (res.ok) {
                document.getElementById('contract-content').value = '';
                showToast('✓ Contract version published');
                document.getElementById('contracts-list').style.display = 'none';
                document.getElementById('contracts-loading').style.display = 'block';
                document.getElementById('contracts-loading').innerHTML =
                    '<div class="oc-spinner" style="margin:0 auto 12px;"></div>Loading…';
                loadContracts();
            } else {
                errBox.textContent = data.error || data.message || 'Failed to create contract.';
                errBox.style.display = 'block';
            }
        } catch {
            errBox.textContent = 'Network error. Please try again.';
            errBox.style.display = 'block';
        } finally {
            btn.disabled = false;
            btn.textContent = 'Publish new version';
        }
    }

    // ── View modal ────────────────────────────────────────────
    async function viewContract(id, version) {
        document.getElementById('modal-title').textContent = 'Contract v' + version;
        document.getElementById('modal-content').innerHTML =
            '<div class="oc-spinner" style="margin:20px auto;"></div>';
        document.getElementById('view-modal').style.display = 'grid';

        try {
            const res = await fetch(`/api/${SITE}/open-collab/admin/contracts/${id}`, {
                headers: {'Authorization': `Bearer ${TOKEN()}`},
            });
            const data = await res.json();
            const contract = data.data?.contract ?? data.contract ?? {};
            document.getElementById('modal-content').innerHTML =
                contract.content || '<em>No content</em>';
        } catch {
            document.getElementById('modal-content').innerHTML =
                '<span style="color:var(--red)">Failed to load.</span>';
        }
    }

    function closeViewModal() {
        document.getElementById('view-modal').style.display = 'none';
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

    loadContracts();
</script>
@endsection