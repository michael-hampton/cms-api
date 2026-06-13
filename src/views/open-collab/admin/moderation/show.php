@section('logic')
<?php
/**
 * Template: open-collab/admin/moderation/show.php
 * Variables:
 *   $queueEntryId — int
 *   $site         — string
 *   $currentUser  — AuthenticatedUser
 *
 * All content loaded via:
 *   GET  /api/{site}/open-collab/admin/moderation/{id}
 * Actions via:
 *   POST /api/{site}/open-collab/admin/moderation/{id}/claim|release
 *   POST /api/{site}/open-collab/admin/articles/{pageId}/approve|reject|request-changes
 *   POST /api/{site}/open-collab/admin/moderation/{id}/risks
 *   POST /api/{site}/open-collab/admin/risks/{riskId}/resolve|dismiss
 *   POST /api/{site}/open-collab/admin/moderation/{id}/escalate
 */

$pageTitle = 'Moderation Review';
$activeNav = 'moderation';
$breadcrumbs = [
    ['label' => 'Moderation Queue', 'href' => "/{$site}/open-collab/admin/moderation"],
    ['label' => 'Review'],
];
?>
@endsection

@extends('open-collab/layouts/admin')

@section('content')

<div id="status-toast"
     style="position:fixed;bottom:24px;left:50%;transform:translateX(-50%);
            background:var(--navy);color:#fff;padding:9px 20px;border-radius:20px;
            font-size:.8rem;font-weight:500;opacity:0;transition:opacity .3s;
            z-index:300;pointer-events:none;"></div>

<div id="md-loading" style="padding:64px;text-align:center;color:var(--slate);">Loading…</div>
<div id="md-error" style="display:none;padding:48px;text-align:center;">
    <div style="color:var(--red);margin-bottom:14px;">Unable to load this item.</div>
    <button class="oc-btn oc-btn--ghost oc-btn--sm" id="md-retry-btn">Retry</button>
</div>

<div id="md-content" style="display:none;">

    <!-- Header -->
    <div class="oc-card" style="padding:18px 22px;margin-bottom:14px;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;">
            <div>
                <h2 id="md-title" style="font-family:var(--font-display);font-size:1.3rem;color:var(--navy);margin:0 0 6px;"></h2>
                <div style="font-size:.8rem;color:var(--slate);display:flex;gap:14px;flex-wrap:wrap;">
                    <span id="md-contributor"></span>
                    <span id="md-submitted"></span>
                    <span id="md-assigned"></span>
                </div>
            </div>
            <div style="text-align:right;">
                <span id="md-status-badge" class="oc-badge"></span>
                <div id="md-risk-summary" style="margin-top:6px;font-size:.78rem;color:var(--slate);"></div>
            </div>
        </div>
    </div>

    <!-- Governance blockers -->
    <div id="md-governance-banner" style="display:none;margin-bottom:14px;padding:16px 20px;
         background:#fff1f2;border:1px solid #fecaca;border-radius:var(--radius-xl,12px);">
        <div style="font-weight:700;color:var(--red);margin-bottom:8px;">
            This article cannot be approved.
        </div>
        <ul id="md-governance-list" style="margin:0;padding-left:20px;font-size:.85rem;color:var(--navy);"></ul>
    </div>

    <!-- Two-column layout -->
    <div style="display:grid;grid-template-columns:1.4fr 1fr;gap:16px;" class="md-grid">

        <!-- Left: content preview -->
        <div class="oc-card" style="padding:0;overflow:hidden;">
            <div style="padding:14px 20px;border-bottom:1px solid var(--border);font-weight:700;color:var(--navy);font-size:.9rem;">
                Article Preview
            </div>
            <div style="padding:20px;max-height:70vh;overflow-y:auto;">
                <h3 id="md-preview-headline" style="font-family:var(--font-display);color:var(--navy);margin-top:0;"></h3>
                <p id="md-preview-deck" style="color:var(--slate);font-style:italic;"></p>
                <div id="md-preview-images" style="display:flex;flex-direction:column;gap:14px;margin:14px 0;"></div>
                <div id="md-preview-body" style="font-size:.9rem;line-height:1.75;color:var(--navy);"></div>
            </div>
        </div>

        <!-- Right: moderation panel -->
        <div style="display:flex;flex-direction:column;gap:14px;">

            <!-- Moderation status -->
            <div class="oc-card" style="padding:16px 20px;">
                <div style="font-weight:700;color:var(--navy);font-size:.85rem;margin-bottom:10px;">Moderation Status</div>
                <dl style="font-size:.8rem;color:var(--slate);display:grid;grid-template-columns:auto 1fr;gap:4px 12px;margin:0;">
                    <dt>Status</dt><dd id="md-panel-status"></dd>
                    <dt>Assigned</dt><dd id="md-panel-assigned"></dd>
                    <dt>Submitted</dt><dd id="md-panel-submitted"></dd>
                    <dt>Last reviewed</dt><dd id="md-panel-reviewed"></dd>
                </dl>
            </div>

            <!-- Governance summary -->
            <div class="oc-card" style="padding:16px 20px;">
                <div style="font-weight:700;color:var(--navy);font-size:.85rem;margin-bottom:10px;">Governance Summary</div>
                <dl style="font-size:.8rem;color:var(--slate);display:grid;grid-template-columns:auto 1fr;gap:6px 12px;margin:0;">
                    <dt>Contributor</dt><dd id="md-gov-contributor"></dd>
                    <dt>Image rights</dt><dd id="md-gov-image-rights"></dd>
                    <dt>Open risks</dt><dd id="md-gov-open-risks"></dd>
                    <dt>Escalations</dt><dd id="md-gov-escalations"></dd>
                </dl>
            </div>

            <!-- Risk markers -->
            <div class="oc-card" style="padding:16px 20px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                    <span style="font-weight:700;color:var(--navy);font-size:.85rem;">Risk Markers</span>
                    <button class="oc-btn oc-btn--ghost oc-btn--sm" id="md-add-risk-btn" style="display:none;">Add Risk</button>
                </div>
                <table class="oc-table" style="width:100%;font-size:.8rem;">
                    <thead>
                    <tr><th>Type</th><th>Severity</th><th>Source</th><th>Status</th><th></th></tr>
                    </thead>
                    <tbody id="md-risk-table-body">
                    <tr><td colspan="5" style="color:var(--slate);">No risk markers.</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- Escalations -->
            <div class="oc-card" style="padding:16px 20px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                    <span style="font-weight:700;color:var(--navy);font-size:.85rem;">Escalations</span>
                    <button class="oc-btn oc-btn--ghost oc-btn--sm" id="md-escalate-btn" style="display:none;">Escalate</button>
                </div>
                <div id="md-escalations-list" style="font-size:.8rem;color:var(--slate);">No open escalations.</div>
            </div>

            <!-- Moderation notes (internal) -->
            <div class="oc-card" style="padding:16px 20px;">
                <div style="font-weight:700;color:var(--navy);font-size:.85rem;margin-bottom:8px;">
                    Moderation Notes <span style="color:var(--slate);font-weight:400;">(internal only)</span>
                </div>
                <textarea class="oc-textarea" id="md-internal-notes" rows="3"
                          placeholder="Internal notes — not visible to the contributor…"></textarea>
            </div>

            <!-- Contributor feedback -->
            <div class="oc-card" style="padding:16px 20px;">
                <div style="font-weight:700;color:var(--navy);font-size:.85rem;margin-bottom:8px;">
                    Contributor Feedback
                </div>
                <textarea class="oc-textarea" id="md-contributor-feedback" rows="3"
                          placeholder="Feedback to send if requesting changes…"></textarea>
            </div>

            <!-- Action buttons -->
            <div class="oc-card" style="padding:16px 20px;display:flex;flex-direction:column;gap:8px;">
                <button class="oc-btn oc-btn--ghost" id="md-claim-btn" style="display:none;">Claim Review</button>
                <button class="oc-btn oc-btn--ghost" id="md-release-btn" style="display:none;">Release</button>
                <button class="oc-btn oc-btn--primary" id="md-approve-btn" style="display:none;background:var(--green, #16a34a);">
                    Approve
                </button>
                <button class="oc-btn oc-btn--ghost" id="md-request-changes-btn"
                        style="display:none;border-color:#fde68a;color:#92400e;">
                    Request Changes
                </button>
                <button class="oc-btn oc-btn--ghost" id="md-reject-btn"
                        style="display:none;border-color:#fecaca;color:var(--red);">
                    Reject
                </button>
                <div id="md-approve-disabled-reason" style="display:none;font-size:.75rem;color:var(--red);text-align:center;">
                    Resolve all blockers above to enable approval.
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Request changes modal -->
<div id="rc-modal" class="oc-modal-backdrop" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="rc-modal-title"
     onclick="if(event.target===this) document.dispatchEvent(new CustomEvent('oc:close-rc-modal'))">
    <div class="oc-modal" role="document">
        <h3 id="rc-modal-title" style="font-family:var(--font-display);color:var(--navy);margin-bottom:6px;">Request Changes</h3>
        <p style="font-size:.85rem;color:var(--slate);margin-bottom:16px;">
            The contributor will see this feedback and can resubmit once addressed.
        </p>
        <div class="oc-form-group">
            <label class="oc-label" for="rc-feedback">Contributor Feedback</label>
            <textarea class="oc-textarea" id="rc-feedback" rows="4" placeholder="What needs to change?" autofocus></textarea>
        </div>
        <div id="rc-errors" class="oc-form-errors" style="display:none;margin-bottom:12px;"></div>
        <div style="display:flex;gap:10px;">
            <button class="oc-btn oc-btn--ghost" style="flex:1;" id="rc-cancel-btn">Cancel</button>
            <button class="oc-btn oc-btn--primary" style="flex:1;" id="rc-submit-btn">Send to Contributor</button>
        </div>
    </div>
</div>

<!-- Reject modal -->
<div id="reject-modal" class="oc-modal-backdrop" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="reject-modal-title"
     onclick="if(event.target===this) document.dispatchEvent(new CustomEvent('oc:close-reject-modal'))">
    <div class="oc-modal" role="document">
        <h3 id="reject-modal-title" style="font-family:var(--font-display);color:var(--navy);margin-bottom:6px;">Reject Article</h3>
        <div class="oc-form-group">
            <label class="oc-label" for="reject-reason">Reason</label>
            <select class="oc-select" id="reject-reason" autofocus>
                <option value="">Select reason…</option>
                <option value="quality">Does not meet quality standards</option>
                <option value="off_topic">Off-topic or not a fit</option>
                <option value="plagiarism">Plagiarism detected</option>
                <option value="misinformation">Contains misinformation</option>
                <option value="policy_violation">Policy violation</option>
                <option value="other">Other</option>
            </select>
        </div>
        <div class="oc-form-group">
            <label class="oc-label oc-label--optional" for="reject-notes">Internal Notes</label>
            <textarea class="oc-textarea" id="reject-notes" rows="3" placeholder="Internal context (optional)…"></textarea>
        </div>
        <div id="reject-errors" class="oc-form-errors" style="display:none;margin-bottom:12px;"></div>
        <div style="display:flex;gap:10px;">
            <button class="oc-btn oc-btn--ghost" style="flex:1;" id="reject-cancel-btn">Cancel</button>
            <button class="oc-btn oc-btn--danger" style="flex:1;" id="reject-submit-btn">Reject Article</button>
        </div>
    </div>
</div>

<!-- Add risk modal -->
<div id="risk-modal" class="oc-modal-backdrop" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="risk-modal-title"
     onclick="if(event.target===this) document.dispatchEvent(new CustomEvent('oc:close-risk-modal'))">
    <div class="oc-modal" role="document">
        <h3 id="risk-modal-title" style="font-family:var(--font-display);color:var(--navy);margin-bottom:6px;">Add Risk Marker</h3>
        <div class="oc-form-group">
            <label class="oc-label" for="risk-type">Type</label>
            <select class="oc-select" id="risk-type" autofocus>
                <option value="copyright">Copyright</option>
                <option value="ai_generated">AI Generated</option>
                <option value="music_rights">Music Rights</option>
                <option value="brand_safety">Brand Safety</option>
                <option value="affiliate_link_abuse">Affiliate Link Abuse</option>
                <option value="sponsored_content">Sponsored Content</option>
                <option value="unclear_ownership">Unclear Ownership</option>
                <option value="missing_provenance">Missing Provenance</option>
                <option value="other">Other</option>
            </select>
        </div>
        <div class="oc-form-group">
            <label class="oc-label" for="risk-severity">Severity</label>
            <select class="oc-select" id="risk-severity">
                <option value="low">Low</option>
                <option value="medium">Medium</option>
                <option value="high">High</option>
                <option value="critical">Critical</option>
            </select>
        </div>
        <div class="oc-form-group">
            <label class="oc-label oc-label--optional" for="risk-notes">Notes</label>
            <textarea class="oc-textarea" id="risk-notes" rows="2"></textarea>
        </div>
        <div id="risk-errors" class="oc-form-errors" style="display:none;margin-bottom:12px;"></div>
        <div style="display:flex;gap:10px;">
            <button class="oc-btn oc-btn--ghost" style="flex:1;" id="risk-cancel-btn">Cancel</button>
            <button class="oc-btn oc-btn--primary" style="flex:1;" id="risk-submit-btn">Add Risk</button>
        </div>
    </div>
</div>

<!-- Resolve/dismiss risk modal (shared) -->
<div id="risk-resolve-modal" class="oc-modal-backdrop" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="risk-resolve-title"
     onclick="if(event.target===this) document.dispatchEvent(new CustomEvent('oc:close-risk-resolve-modal'))">
    <div class="oc-modal" role="document">
        <h3 id="risk-resolve-title" style="font-family:var(--font-display);color:var(--navy);margin-bottom:6px;"></h3>
        <div class="oc-form-group">
            <label class="oc-label" for="risk-resolve-notes" id="risk-resolve-notes-label">Notes</label>
            <textarea class="oc-textarea" id="risk-resolve-notes" rows="3" autofocus></textarea>
        </div>
        <div id="risk-resolve-errors" class="oc-form-errors" style="display:none;margin-bottom:12px;"></div>
        <div style="display:flex;gap:10px;">
            <button class="oc-btn oc-btn--ghost" style="flex:1;" id="risk-resolve-cancel-btn">Cancel</button>
            <button class="oc-btn oc-btn--primary" style="flex:1;" id="risk-resolve-submit-btn"></button>
        </div>
    </div>
</div>

<!-- Escalation modal -->
<div id="escalate-modal" class="oc-modal-backdrop" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="escalate-modal-title"
     onclick="if(event.target===this) document.dispatchEvent(new CustomEvent('oc:close-escalate-modal'))">
    <div class="oc-modal" role="document">
        <h3 id="escalate-modal-title" style="font-family:var(--font-display);color:var(--navy);margin-bottom:6px;">Escalate Content</h3>
        <div class="oc-form-group">
            <label class="oc-label" for="escalate-category">Category</label>
            <select class="oc-select" id="escalate-category" autofocus>
                <option value="copyright">Copyright</option>
                <option value="ai_generated">AI Generated</option>
                <option value="music_rights">Music Rights</option>
                <option value="brand_safety">Brand Safety</option>
                <option value="sponsored_content">Sponsored Content</option>
                <option value="affiliate_abuse">Affiliate Abuse</option>
                <option value="legal">Legal</option>
                <option value="other">Other</option>
            </select>
        </div>
        <div class="oc-form-group">
            <label class="oc-label" for="escalate-severity">Severity</label>
            <select class="oc-select" id="escalate-severity">
                <option value="low">Low</option>
                <option value="medium">Medium</option>
                <option value="high" selected>High</option>
                <option value="critical">Critical</option>
            </select>
        </div>
        <div class="oc-form-group">
            <label class="oc-label oc-label--optional" for="escalate-summary">Summary</label>
            <textarea class="oc-textarea" id="escalate-summary" rows="3" placeholder="Context for the specialist team…"></textarea>
        </div>
        <div id="escalate-errors" class="oc-form-errors" style="display:none;margin-bottom:12px;"></div>
        <div style="display:flex;gap:10px;">
            <button class="oc-btn oc-btn--ghost" style="flex:1;" id="escalate-cancel-btn">Cancel</button>
            <button class="oc-btn oc-btn--primary" style="flex:1;" id="escalate-submit-btn">Escalate</button>
        </div>
    </div>
</div>

<style>
    .oc-modal-backdrop {
        position:fixed; inset:0; background:rgba(15,25,41,.55); z-index:500;
        display:grid; place-items:center;
    }
    .oc-modal {
        background:#fff; border-radius:var(--radius-xl,12px); padding:28px 32px;
        max-width:480px; width:92%; box-shadow:0 20px 60px rgba(0,0,0,.2);
    }
    @media (max-width: 900px) {
        .md-grid { grid-template-columns: 1fr !important; }
    }
</style>

@endsection

@section('scripts')
<script>
    const SITE = '<?= htmlspecialchars($site ?? '') ?>';
    const QUEUE_ENTRY_ID = <?= (int)$queueEntryId ?>;
    const CURRENT_USER_ID = <?= (int)($currentUser->id ?? 0) ?>;

    class ModerationDetailManager {
        #site;
        #token;
        #entryId;
        #data = null;

        constructor(site, token, entryId) {
            this.#site = site;
            this.#token = token;
            this.#entryId = entryId;

            this.#bindStaticControls();
            this.load();
        }

        #bindStaticControls() {
            document.getElementById('md-retry-btn').addEventListener('click', () => this.load());

            document.getElementById('md-claim-btn').addEventListener('click', () => this.#claim());
            document.getElementById('md-release-btn').addEventListener('click', () => this.#release());
            document.getElementById('md-approve-btn').addEventListener('click', () => this.#approve());

            // Request changes modal
            document.getElementById('md-request-changes-btn').addEventListener('click', () => this.#openRcModal());
            document.getElementById('rc-cancel-btn').addEventListener('click', () => this.#closeRcModal());
            document.addEventListener('oc:close-rc-modal', () => this.#closeRcModal());
            document.getElementById('rc-submit-btn').addEventListener('click', () => this.#submitRequestChanges());

            // Reject modal
            document.getElementById('md-reject-btn').addEventListener('click', () => this.#openRejectModal());
            document.getElementById('reject-cancel-btn').addEventListener('click', () => this.#closeRejectModal());
            document.addEventListener('oc:close-reject-modal', () => this.#closeRejectModal());
            document.getElementById('reject-submit-btn').addEventListener('click', () => this.#submitReject());

            // Risk modal
            document.getElementById('md-add-risk-btn').addEventListener('click', () => this.#openRiskModal());
            document.getElementById('risk-cancel-btn').addEventListener('click', () => this.#closeRiskModal());
            document.addEventListener('oc:close-risk-modal', () => this.#closeRiskModal());
            document.getElementById('risk-submit-btn').addEventListener('click', () => this.#submitAddRisk());

            // Resolve/dismiss modal
            document.getElementById('risk-resolve-cancel-btn').addEventListener('click', () => this.#closeRiskResolveModal());
            document.addEventListener('oc:close-risk-resolve-modal', () => this.#closeRiskResolveModal());

            // Escalation modal
            document.getElementById('md-escalate-btn').addEventListener('click', () => this.#openEscalateModal());
            document.getElementById('escalate-cancel-btn').addEventListener('click', () => this.#closeEscalateModal());
            document.addEventListener('oc:close-escalate-modal', () => this.#closeEscalateModal());
            document.getElementById('escalate-submit-btn').addEventListener('click', () => this.#submitEscalate());

            // Basic focus-trap / Escape handling for all modals
            document.addEventListener('keydown', (e) => {
                if (e.key !== 'Escape') return;
                document.querySelectorAll('.oc-modal-backdrop').forEach(el => {
                    if (el.style.display !== 'none') el.style.display = 'none';
                });
            });
        }

        async load() {
            document.getElementById('md-loading').style.display = 'block';
            document.getElementById('md-error').style.display = 'none';
            document.getElementById('md-content').style.display = 'none';

            try {
                const res = await fetch(`/api/${this.#site}/open-collab/admin/moderation/${this.#entryId}`, {
                    headers: { Authorization: `Bearer ${this.#token()}`, Accept: 'application/json' },
                });

                if (!res.ok) throw new Error(`HTTP ${res.status}`);

                this.#data = await res.json();
                this.#render();

                document.getElementById('md-loading').style.display = 'none';
                document.getElementById('md-content').style.display = 'block';
            } catch {
                document.getElementById('md-loading').style.display = 'none';
                document.getElementById('md-error').style.display = 'block';
            }
        }

        #render() {
            const d = this.#data;
            const page = d.page ?? {};
            const status = d.status ?? 'queued';

            document.getElementById('md-title').textContent = page.title ?? `Page #${page.id}`;
            document.getElementById('md-contributor').textContent =
                `Contributor: ${d.contributor?.display_name ?? `#${d.contributor?.id ?? '—'}`}`;
            document.getElementById('md-submitted').textContent =
                d.submitted_at ? `Submitted ${this.#formatDate(d.submitted_at)}` : '';
            document.getElementById('md-assigned').textContent =
                d.assigned_to_display_name ? `Assigned: ${d.assigned_to_display_name}` : 'Unassigned';

            const statusBadge = document.getElementById('md-status-badge');
            statusBadge.textContent = this.#statusLabel(status);
            statusBadge.className = `oc-badge oc-badge--status-${status}`;

            document.getElementById('md-risk-summary').textContent =
                `Risk score: ${d.risk_score ?? 0} · Priority: ${d.priority_score ?? 0}`;

            // Preview
            document.getElementById('md-preview-headline').textContent = page.headline ?? page.title ?? '';
            document.getElementById('md-preview-deck').textContent = page.deck ?? '';
            document.getElementById('md-preview-body').innerHTML = page.content ?? page.body ?? '<em>No content</em>';
            this.#renderImages(page.images ?? []);

            // Status panel
            document.getElementById('md-panel-status').textContent = this.#statusLabel(status);
            document.getElementById('md-panel-assigned').textContent = d.assigned_to_display_name ?? '—';
            document.getElementById('md-panel-submitted').textContent = d.submitted_at ? this.#formatDate(d.submitted_at) : '—';
            document.getElementById('md-panel-reviewed').textContent = d.last_reviewed_at ? this.#formatDate(d.last_reviewed_at) : '—';

            // Governance summary
            document.getElementById('md-gov-contributor').textContent = d.contributor?.display_name ?? `#${d.contributor?.id ?? '—'}`;
            document.getElementById('md-gov-image-rights').textContent = d.image_rights_summary ?? '—';
            document.getElementById('md-gov-open-risks').textContent = `${(d.risk_markers ?? []).length}`;
            document.getElementById('md-gov-escalations').textContent = `${(d.escalations ?? []).length}`;

            // Notes
            document.getElementById('md-internal-notes').value = d.internal_notes ?? '';
            document.getElementById('md-contributor-feedback').value = '';

            this.#renderRiskMarkers(d.risk_markers ?? []);
            this.#renderEscalations(d.escalations ?? []);
            this.#renderGovernance(d.governance ?? { can_approve: true, blockers: [] });
            this.#renderActions(d);
        }

        #renderImages(images) {
            const container = document.getElementById('md-preview-images');
            if (!images.length) { container.innerHTML = ''; return; }

            container.innerHTML = images.map(img => `
                <figure style="margin:0;">
                    <img src="${this.#escapeAttr(img.url)}" alt="${this.#escapeAttr(img.alt ?? '')}"
                         style="width:100%;border-radius:8px;display:block;">
                    ${img.caption ? `<figcaption style="font-size:.8rem;color:var(--slate);margin-top:4px;">${this.#escape(img.caption)}</figcaption>` : ''}
                </figure>
            `).join('');
        }

        #renderRiskMarkers(markers) {
            const body = document.getElementById('md-risk-table-body');

            if (!markers.length) {
                body.innerHTML = `<tr><td colspan="5" style="color:var(--slate);">No risk markers.</td></tr>`;
                return;
            }

            body.innerHTML = markers.map(m => {
                const canResolve = (this.#data.available_actions ?? []).includes('resolve_risk');
                const isOutstanding = !['cleared', 'dismissed'].includes(m.status);

                return `
                    <tr>
                        <td>${this.#riskTypeLabel(m.risk_type)}</td>
                        <td><span class="oc-badge oc-badge--risk-${m.severity}">${this.#capitalise(m.severity)}</span></td>
                        <td>${this.#sourceLabel(m.source)}</td>
                        <td>${this.#capitalise(m.status)}</td>
                        <td style="text-align:right;white-space:nowrap;">
                            ${canResolve && isOutstanding ? `
                                <button class="oc-btn oc-btn--ghost oc-btn--sm" data-risk-resolve="${m.id}" data-severity="${m.severity}">Resolve</button>
                                <button class="oc-btn oc-btn--ghost oc-btn--sm" data-risk-dismiss="${m.id}">Dismiss</button>
                            ` : ''}
                        </td>
                    </tr>
                `;
            }).join('');

            body.querySelectorAll('[data-risk-resolve]').forEach(btn => {
                btn.addEventListener('click', () => this.#openRiskResolveModal(btn.dataset.riskResolve, 'resolve', btn.dataset.severity));
            });
            body.querySelectorAll('[data-risk-dismiss]').forEach(btn => {
                btn.addEventListener('click', () => this.#openRiskResolveModal(btn.dataset.riskDismiss, 'dismiss'));
            });
        }

        #renderEscalations(escalations) {
            const container = document.getElementById('md-escalations-list');

            if (!escalations.length) {
                container.innerHTML = 'No open escalations.';
                return;
            }

            container.innerHTML = escalations.map(e => `
                <div style="padding:8px 0;border-bottom:1px solid var(--border);">
                    <div style="font-weight:600;color:var(--navy);">${this.#categoryLabel(e.category)} — ${this.#capitalise(e.severity)}</div>
                    <div style="display:flex;justify-content:space-between;color:var(--slate);">
                        <span>${this.#escape(e.assigned_team ?? '—')}</span>
                        <span>Due ${e.due_at ? this.#formatDate(e.due_at) : '—'}</span>
                    </div>
                    <div style="margin-top:4px;">
                        <span class="oc-badge oc-badge--status-${e.status}">${this.#capitalise(e.status)}</span>
                        <a href="/${this.#site}/open-collab/admin/escalations/${e.id}" style="margin-left:8px;font-size:.75rem;">View</a>
                    </div>
                </div>
            `).join('');
        }

        #renderGovernance(governance) {
            const banner = document.getElementById('md-governance-banner');
            const list = document.getElementById('md-governance-list');

            if (governance.can_approve || !governance.blockers?.length) {
                banner.style.display = 'none';
                return;
            }

            banner.style.display = 'block';
            list.innerHTML = governance.blockers.map(b => `<li>${this.#escape(b.message)}</li>`).join('');
        }

        #renderActions(d) {
            const actions = d.available_actions ?? [];
            const status = d.status;
            const canApprove = (d.governance?.can_approve ?? true) && actions.includes('approve');

            this.#toggle('md-claim-btn', actions.includes('claim'));
            this.#toggle('md-release-btn', actions.includes('release'));
            this.#toggle('md-approve-btn', actions.includes('approve'));
            this.#toggle('md-request-changes-btn', actions.includes('request_changes'));
            this.#toggle('md-reject-btn', actions.includes('reject'));
            this.#toggle('md-add-risk-btn', actions.includes('add_risk') || actions.includes('review'));
            this.#toggle('md-escalate-btn', actions.includes('escalate'));

            const approveBtn = document.getElementById('md-approve-btn');
            const disabledReason = document.getElementById('md-approve-disabled-reason');

            if (actions.includes('approve')) {
                approveBtn.disabled = !canApprove;
                approveBtn.style.opacity = canApprove ? '1' : '.5';
                disabledReason.style.display = canApprove ? 'none' : 'block';
            }
        }

        #toggle(id, show) {
            document.getElementById(id).style.display = show ? '' : 'none';
        }

        // ---- Actions ----

        async #claim() {
            const btn = document.getElementById('md-claim-btn');
            btn.disabled = true;

            try {
                const res = await fetch(`/api/${this.#site}/open-collab/admin/moderation/${this.#entryId}/claim`, {
                    method: 'POST',
                    headers: { Authorization: `Bearer ${this.#token()}`, Accept: 'application/json' },
                });

                if (res.status === 409) {
                    this.#showToast('Already claimed by another moderator', false);
                    this.load();
                    return;
                }
                if (!res.ok) throw new Error('claim failed');

                this.#showToast('Claimed for review');
                this.load();
            } catch {
                this.#showToast('Could not claim', false);
                btn.disabled = false;
            }
        }

        async #release() {
            const btn = document.getElementById('md-release-btn');
            btn.disabled = true;

            try {
                const res = await fetch(`/api/${this.#site}/open-collab/admin/moderation/${this.#entryId}/release`, {
                    method: 'POST',
                    headers: { Authorization: `Bearer ${this.#token()}`, Accept: 'application/json' },
                });

                if (!res.ok) throw new Error('release failed');

                this.#showToast('Released');
                this.load();
            } catch {
                this.#showToast('Could not release', false);
                btn.disabled = false;
            }
        }

        async #approve() {
            if (!confirm('Approve and publish this article?')) return;

            const btn = document.getElementById('md-approve-btn');
            btn.disabled = true;
            const original = btn.textContent;
            btn.innerHTML = '<div class="oc-spinner"></div>';

            try {
                const res = await fetch(`/api/${this.#site}/open-collab/admin/articles/${this.#data.page.id}/approve`, {
                    method: 'POST',
                    headers: { Authorization: `Bearer ${this.#token()}`, Accept: 'application/json' },
                });

                const responseData = await res.json();

                if (res.ok) {
                    this.#showToast('Article approved and published');
                    setTimeout(() => { window.location.href = `/${this.#site}/open-collab/admin/moderation`; }, 1000);
                } else if (res.status === 422 && responseData.governance_failures) {
                    // Governance gate rejected at submit time — reflect it immediately.
                    this.#renderGovernance({ can_approve: false, blockers: responseData.governance_failures });
                    this.#showToast('Approval blocked — see governance warnings', false);
                    btn.disabled = false;
                    btn.textContent = original;
                } else {
                    this.#showToast(responseData.error || 'Approval failed', false);
                    btn.disabled = false;
                    btn.textContent = original;
                }
            } catch {
                this.#showToast('Network error', false);
                btn.disabled = false;
                btn.textContent = original;
            }
        }

        // ---- Request changes modal ----

        #openRcModal() {
            document.getElementById('rc-feedback').value = document.getElementById('md-contributor-feedback').value;
            document.getElementById('rc-errors').style.display = 'none';
            document.getElementById('rc-modal').style.display = 'grid';
            document.getElementById('rc-feedback').focus();
        }

        #closeRcModal() {
            document.getElementById('rc-modal').style.display = 'none';
        }

        async #submitRequestChanges() {
            const notes = document.getElementById('rc-feedback').value.trim();
            const errBox = document.getElementById('rc-errors');
            const btn = document.getElementById('rc-submit-btn');

            if (!notes) {
                errBox.textContent = 'Feedback is required.';
                errBox.style.display = 'block';
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<div class="oc-spinner"></div> Sending…';

            try {
                const res = await fetch(`/api/${this.#site}/open-collab/admin/articles/${this.#data.page.id}/request-changes`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Authorization: `Bearer ${this.#token()}`,
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({ notes }),
                });

                const data = await res.json();

                if (res.ok) {
                    this.#closeRcModal();
                    this.#showToast('Changes requested — contributor notified');
                    this.load();
                } else {
                    errBox.textContent = data.error || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Failed.');
                    errBox.style.display = 'block';
                }
            } catch {
                errBox.textContent = 'Network error. Please try again.';
                errBox.style.display = 'block';
            } finally {
                btn.disabled = false;
                btn.textContent = 'Send to Contributor';
            }
        }

        // ---- Reject modal ----

        #openRejectModal() {
            document.getElementById('reject-reason').value = '';
            document.getElementById('reject-notes').value = document.getElementById('md-internal-notes').value;
            document.getElementById('reject-errors').style.display = 'none';
            document.getElementById('reject-modal').style.display = 'grid';
            document.getElementById('reject-reason').focus();
        }

        #closeRejectModal() {
            document.getElementById('reject-modal').style.display = 'none';
        }

        async #submitReject() {
            const reason = document.getElementById('reject-reason').value;
            const notes = document.getElementById('reject-notes').value.trim();
            const errBox = document.getElementById('reject-errors');
            const btn = document.getElementById('reject-submit-btn');

            if (!reason) {
                errBox.textContent = 'Please select a rejection reason.';
                errBox.style.display = 'block';
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<div class="oc-spinner"></div> Rejecting…';

            try {
                const res = await fetch(`/api/${this.#site}/open-collab/admin/articles/${this.#data.page.id}/reject`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Authorization: `Bearer ${this.#token()}`,
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({ reason, notes: notes || undefined }),
                });

                const data = await res.json();

                if (res.ok) {
                    this.#closeRejectModal();
                    this.#showToast('Article rejected — contributor notified');
                    setTimeout(() => { window.location.href = `/${this.#site}/open-collab/admin/moderation`; }, 1000);
                } else {
                    errBox.textContent = data.error || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Rejection failed.');
                    errBox.style.display = 'block';
                    btn.disabled = false;
                    btn.textContent = 'Reject Article';
                }
            } catch {
                errBox.textContent = 'Network error. Please try again.';
                errBox.style.display = 'block';
                btn.disabled = false;
                btn.textContent = 'Reject Article';
            }
        }

        // ---- Add risk modal ----

        #openRiskModal() {
            document.getElementById('risk-type').value = 'copyright';
            document.getElementById('risk-severity').value = 'low';
            document.getElementById('risk-notes').value = '';
            document.getElementById('risk-errors').style.display = 'none';
            document.getElementById('risk-modal').style.display = 'grid';
            document.getElementById('risk-type').focus();
        }

        #closeRiskModal() {
            document.getElementById('risk-modal').style.display = 'none';
        }

        async #submitAddRisk() {
            const riskType = document.getElementById('risk-type').value;
            const severity = document.getElementById('risk-severity').value;
            const notes = document.getElementById('risk-notes').value.trim();
            const errBox = document.getElementById('risk-errors');
            const btn = document.getElementById('risk-submit-btn');

            btn.disabled = true;
            btn.innerHTML = '<div class="oc-spinner"></div>';

            try {
                const res = await fetch(`/api/${this.#site}/open-collab/admin/moderation/${this.#entryId}/risks`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Authorization: `Bearer ${this.#token()}`,
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({
                        risk_type: riskType,
                        severity,
                        details: notes ? { notes } : undefined,
                    }),
                });

                const data = await res.json();

                if (res.ok) {
                    this.#closeRiskModal();
                    this.#showToast('Risk marker added');
                    this.load();
                } else {
                    errBox.textContent = data.error || 'Could not add risk marker.';
                    errBox.style.display = 'block';
                }
            } catch {
                errBox.textContent = 'Network error. Please try again.';
                errBox.style.display = 'block';
            } finally {
                btn.disabled = false;
                btn.textContent = 'Add Risk';
            }
        }

        // ---- Resolve/dismiss risk modal ----

        #openRiskResolveModal(markerId, mode, severity = null) {
            this.#riskResolveContext = { markerId, mode };

            const title = document.getElementById('risk-resolve-title');
            const submit = document.getElementById('risk-resolve-submit-btn');
            const notesLabel = document.getElementById('risk-resolve-notes-label');

            const notesRequired = mode === 'resolve' && ['high', 'critical'].includes(severity);

            title.textContent = mode === 'resolve' ? 'Resolve Risk Marker' : 'Dismiss Risk Marker';
            submit.textContent = mode === 'resolve' ? 'Resolve' : 'Dismiss';
            notesLabel.textContent = notesRequired ? 'Resolution notes (required)' : 'Notes (optional)';

            document.getElementById('risk-resolve-notes').value = '';
            document.getElementById('risk-resolve-errors').style.display = 'none';
            document.getElementById('risk-resolve-modal').style.display = 'grid';
            document.getElementById('risk-resolve-notes').focus();

            // bind submit fresh each time to avoid stacking listeners
            const submitBtn = document.getElementById('risk-resolve-submit-btn');
            submitBtn.onclick = () => this.#submitRiskResolution(notesRequired);
        }

        #closeRiskResolveModal() {
            document.getElementById('risk-resolve-modal').style.display = 'none';
            this.#riskResolveContext = null;
        }

        async #submitRiskResolution(notesRequired) {
            const { markerId, mode } = this.#riskResolveContext;
            const notes = document.getElementById('risk-resolve-notes').value.trim();
            const errBox = document.getElementById('risk-resolve-errors');
            const btn = document.getElementById('risk-resolve-submit-btn');

            if (notesRequired && !notes) {
                errBox.textContent = 'Notes are required for high/critical risk markers.';
                errBox.style.display = 'block';
                return;
            }

            btn.disabled = true;
            const original = btn.textContent;
            btn.innerHTML = '<div class="oc-spinner"></div>';

            try {
                const res = await fetch(`/api/${this.#site}/open-collab/admin/risks/${markerId}/${mode}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Authorization: `Bearer ${this.#token()}`,
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({ notes: notes || undefined }),
                });

                const data = await res.json();

                if (res.ok) {
                    this.#closeRiskResolveModal();
                    this.#showToast(mode === 'resolve' ? 'Risk marker resolved' : 'Risk marker dismissed');
                    this.load();
                } else {
                    errBox.textContent = data.error || 'Could not update risk marker.';
                    errBox.style.display = 'block';
                    btn.disabled = false;
                    btn.textContent = original;
                }
            } catch {
                errBox.textContent = 'Network error. Please try again.';
                errBox.style.display = 'block';
                btn.disabled = false;
                btn.textContent = original;
            }
        }

        // ---- Escalation modal ----

        #openEscalateModal() {
            document.getElementById('escalate-category').value = 'copyright';
            document.getElementById('escalate-severity').value = 'high';
            document.getElementById('escalate-summary').value = '';
            document.getElementById('escalate-errors').style.display = 'none';
            document.getElementById('escalate-modal').style.display = 'grid';
            document.getElementById('escalate-category').focus();
        }

        #closeEscalateModal() {
            document.getElementById('escalate-modal').style.display = 'none';
        }

        async #submitEscalate() {
            const category = document.getElementById('escalate-category').value;
            const severity = document.getElementById('escalate-severity').value;
            const summary = document.getElementById('escalate-summary').value.trim();
            const errBox = document.getElementById('escalate-errors');
            const btn = document.getElementById('escalate-submit-btn');

            btn.disabled = true;
            btn.innerHTML = '<div class="oc-spinner"></div>';

            try {
                const res = await fetch(`/api/${this.#site}/open-collab/admin/moderation/${this.#entryId}/escalate`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Authorization: `Bearer ${this.#token()}`,
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({ category, severity, details: summary ? { summary } : undefined }),
                    // NOTE: EscalateContentRequest validates category/severity/risk_marker_id/cms_image_id.
                    // `details`/`summary` isn't in that request's rules — add it there if you want
                    // the summary persisted (e.g. into ModerationEscalation.resolution_notes-equivalent
                    // or a new `summary` column). Sending it is harmless if ignored server-side.
                });

                const data = await res.json();

                if (res.ok) {
                    this.#closeEscalateModal();
                    this.#showToast('Escalated to specialist team');
                    this.load();
                } else {
                    errBox.textContent = data.error || 'Could not escalate.';
                    errBox.style.display = 'block';
                }
            } catch {
                errBox.textContent = 'Network error. Please try again.';
                errBox.style.display = 'block';
            } finally {
                btn.disabled = false;
                btn.textContent = 'Escalate';
            }
        }

        // ---- Helpers ----

        #riskResolveContext = null;

        #statusLabel(status) {
            return {
                queued: 'Queued', claimed: 'Claimed', in_review: 'In Review',
                changes_requested: 'Changes Requested', escalated: 'Escalated',
                approved: 'Approved', rejected: 'Rejected', cancelled: 'Cancelled',
            }[status] ?? status;
        }

        #riskTypeLabel(type) {
            return {
                copyright: 'Copyright', ai_generated: 'AI Generated', music_rights: 'Music Rights',
                brand_safety: 'Brand Safety', affiliate_link_abuse: 'Affiliate Link Abuse',
                sponsored_content: 'Sponsored Content', unclear_ownership: 'Unclear Ownership',
                missing_provenance: 'Missing Provenance', other: 'Other',
            }[type] ?? type;
        }

        #sourceLabel(source) {
            return {
                creator_declaration: 'Creator Declaration', automated_check: 'Automated',
                moderator: 'Moderator', legal: 'Legal', brand_safety: 'Brand Safety',
            }[source] ?? source;
        }

        #categoryLabel(category) {
            return {
                copyright: 'Copyright', ai_generated: 'AI Generated', music_rights: 'Music Rights',
                brand_safety: 'Brand Safety', sponsored_content: 'Sponsored Content',
                affiliate_abuse: 'Affiliate Abuse', legal: 'Legal', other: 'Other',
            }[category] ?? category;
        }

        #capitalise(s) {
            return (s ?? '').replace('_', ' ').replace(/^\w/, c => c.toUpperCase());
        }

        #formatDate(iso) {
            const d = new Date(iso);
            return d.toLocaleString(undefined, { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        }

        #escape(str) {
            const div = document.createElement('div');
            div.textContent = str ?? '';
            return div.innerHTML;
        }

        #escapeAttr(str) {
            return (str ?? '').replace(/"/g, '&quot;');
        }

        #showToast(msg, ok = true) {
            const el = document.getElementById('status-toast');
            el.textContent = msg;
            el.style.background = ok ? 'var(--navy)' : 'var(--red)';
            el.style.opacity = '1';
            setTimeout(() => { el.style.opacity = '0'; }, 3000);
        }
    }

    new ModerationDetailManager(SITE, () => localStorage.getItem('oc_token') || '', QUEUE_ENTRY_ID);
</script>
@endsection