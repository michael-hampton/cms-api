@section('logic')
<?php
/**
 * Template: open-collab/admin/guidelines/index.php
 * Variables:
 *   $site        — string
 *   $currentUser — AuthenticatedUser
 */

$pageTitle = 'Brand Guidelines';
$activeNav = 'guidelines';
$breadcrumbs = [['label' => 'Guidelines']];
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
    <div>
        <div class="oc-card">
            <div class="oc-card__header">
                <span class="oc-card__title">Guideline Versions</span>
                <span id="guideline-count"
                      style="font-size:.72rem;background:var(--slate-pale);color:var(--slate);
                     padding:2px 8px;border-radius:10px;font-weight:600;">—</span>
            </div>

            <div id="guidelines-loading"
                 style="padding:40px;text-align:center;color:var(--slate);font-size:.875rem;">
                <div class="oc-spinner" style="margin:0 auto 12px;"></div>
                Loading guidelines…
            </div>

            <div id="guidelines-empty"
                 style="display:none;padding:48px 24px;text-align:center;color:var(--slate);">
                <svg viewBox="0 0 20 20" fill="currentColor" width="32"
                     style="opacity:.2;display:block;margin:0 auto 12px;">
                    <path fill-rule="evenodd"
                          d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                          clip-rule="evenodd"/>
                </svg>
                <div style="font-weight:500;margin-bottom:6px;">No guidelines yet</div>
                <div style="font-size:.85rem;">Create the first version to get started.</div>
            </div>

            <div id="guidelines-list" style="display:none;"></div>
        </div>
    </div>

    <!-- Create new version -->
    <div style="position:sticky;top:calc(var(--header-h,64px) + 20px);">
        <div class="oc-card">
            <div class="oc-card__header">
                <span class="oc-card__title" style="font-size:.95rem;">New Guidelines Version</span>
            </div>
            <div class="oc-card__body">
                <div class="oc-alert oc-alert--info" style="margin-bottom:16px;font-size:.8rem;">
                    <svg viewBox="0 0 20 20" fill="currentColor" width="14">
                        <path fill-rule="evenodd"
                              d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                              clip-rule="evenodd"/>
                    </svg>
                    Publishing a new version prompts all contributors to re-acknowledge
                    before they can submit new articles.
                </div>

                <div id="create-errors" class="oc-form-errors" style="display:none;margin-bottom:12px;"></div>

                <div class="oc-form-group">
                    <label class="oc-label" for="guideline-content">Guidelines content</label>
                    <textarea class="oc-textarea" id="guideline-content" rows="12"
                              placeholder="Enter your brand and editorial guidelines (HTML or plain text)…"
                              style="min-height:220px;font-family:monospace;font-size:.82rem;"></textarea>
                    <div class="oc-help">Minimum 50 characters. Supports basic HTML.</div>
                </div>

                <button onclick="createGuideline()" class="oc-btn oc-btn--amber oc-btn--block" id="create-btn">
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
            <span style="font-weight:700;color:var(--navy);" id="modal-title">Guidelines v—</span>
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

    async function loadGuidelines() {
        try {
            const res = await fetch(`/api/${SITE}/open-collab/admin/guidelines`, {
                headers: {'Authorization': `Bearer ${TOKEN()}`, 'Accept': 'application/json'},
            });
            const data = await res.json();
            const items = Array.isArray(data) ? data : (data.data ?? []);

            document.getElementById('guidelines-loading').style.display = 'none';
            document.getElementById('guideline-count').textContent = items.length;

            if (!items.length) {
                document.getElementById('guidelines-empty').style.display = 'block';
                return;
            }

            const list = document.getElementById('guidelines-list');
            list.style.display = 'block';
            list.innerHTML = '';

            items.forEach((g, i) => {
                const isLatest = i === 0;
                const div = document.createElement('div');
                div.style.cssText = 'padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:12px;';
                div.innerHTML = `
          <div>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:3px;">
              <span style="font-weight:600;color:var(--navy);">Version ${g.version}</span>
              ${isLatest ? '<span class="oc-badge oc-badge--published" style="font-size:.65rem;">Current</span>' : ''}
            </div>
            <div style="font-size:.75rem;color:var(--slate);">
              Created ${g.created_at ? new Date(g.created_at).toLocaleDateString('en-GB', {
                    day: 'numeric',
                    month: 'short',
                    year: 'numeric'
                }) : '—'}
              · ${g.content ? g.content.replace(/<[^>]+>/g, '').slice(0, 60) + '…' : '—'}
            </div>
          </div>
          <button onclick="viewGuideline(${g.id}, ${g.version})"
                  class="oc-btn oc-btn--ghost oc-btn--sm" style="flex-shrink:0;">
            View
          </button>`;
                list.appendChild(div);
            });
        } catch {
            document.getElementById('guidelines-loading').innerHTML =
                '<div style="color:var(--red);font-size:.85rem;padding:20px;">Failed to load guidelines.</div>';
        }
    }

    async function createGuideline() {
        const content = document.getElementById('guideline-content').value.trim();
        const errBox = document.getElementById('create-errors');
        const btn = document.getElementById('create-btn');
        errBox.style.display = 'none';

        if (!content) {
            errBox.textContent = 'Guidelines content is required.';
            errBox.style.display = 'block';
            return;
        }
        if (content.length < 50) {
            errBox.textContent = 'Guidelines must be at least 50 characters.';
            errBox.style.display = 'block';
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<div class="oc-spinner"></div> Publishing…';

        try {
            const res = await fetch(`/api/${SITE}/open-collab/admin/guidelines`, {
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
                document.getElementById('guideline-content').value = '';
                showToast('✓ Guidelines version published — contributors will be prompted to re-acknowledge');
                document.getElementById('guidelines-list').style.display = 'none';
                document.getElementById('guidelines-loading').style.display = 'block';
                document.getElementById('guidelines-loading').innerHTML =
                    '<div class="oc-spinner" style="margin:0 auto 12px;"></div>Loading…';
                loadGuidelines();
            } else {
                errBox.textContent = data.error || data.message || 'Failed to publish guidelines.';
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

    async function viewGuideline(id, version) {
        document.getElementById('modal-title').textContent = 'Guidelines v' + version;
        document.getElementById('modal-content').innerHTML =
            '<div class="oc-spinner" style="margin:20px auto;"></div>';
        document.getElementById('view-modal').style.display = 'grid';

        try {
            const res = await fetch(`/api/${SITE}/open-collab/admin/guidelines/${id}`, {
                headers: {'Authorization': `Bearer ${TOKEN()}`},
            });
            const data = await res.json();
            const g = data.data?.guideline ?? data.guideline ?? {};
            document.getElementById('modal-content').innerHTML = g.content || '<em>No content</em>';
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
        }, 3500);
    }

    loadGuidelines();
</script>
@endsection