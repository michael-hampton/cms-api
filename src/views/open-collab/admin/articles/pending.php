@section('logic')
<?php
/**
 * Template: open-collab/admin/articles/pending.php
 * Variables:
 *   $articles     — Collection of Page models (status = waiting_approval)
 *   $pendingCount — int
 *   $site         — string
 *   $currentUser  — AuthenticatedUser
 */

$pageTitle = 'Approval Queue';
$activeNav = 'articles';
$breadcrumbs = [['label' => 'Approval Queue']];
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
    <div style="background:#fff;border-radius:var(--radius-xl,12px);padding:28px 32px;
              max-width:460px;width:92%;box-shadow:0 20px 60px rgba(0,0,0,.2);">
        <h3 style="font-family:var(--font-display);font-size:1.2rem;color:var(--navy);margin-bottom:6px;">
            Reject article
        </h3>
        <p style="font-size:.85rem;color:var(--slate);margin-bottom:20px;">
            Select a reason and add optional notes for the contributor.
        </p>
        <input type="hidden" id="reject-page-id">
        <div class="oc-form-group">
            <label class="oc-label" for="reject-reason">Reason</label>
            <select class="oc-select" id="reject-reason">
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
            <label class="oc-label oc-label--optional" for="reject-notes">Notes for contributor</label>
            <textarea class="oc-textarea" id="reject-notes" rows="3"
                      placeholder="Optional feedback to help the contributor improve…"
                      style="min-height:80px;"></textarea>
        </div>
        <div id="reject-modal-errors" class="oc-form-errors" style="display:none;margin-bottom:12px;"></div>
        <div style="display:flex;gap:10px;">
            <button onclick="closeRejectModal()" class="oc-btn oc-btn--ghost" style="flex:1;">Cancel</button>
            <button onclick="submitRejection()" class="oc-btn oc-btn--danger" style="flex:1;" id="reject-confirm-btn">
                Reject article
            </button>
        </div>
    </div>
</div>

<?php if (empty($articles) || count($articles) === 0): ?>
    <div class="oc-card" style="padding:64px 24px;text-align:center;">
        <svg viewBox="0 0 20 20" fill="currentColor" width="40"
             style="opacity:.15;display:block;margin:0 auto 16px;color:var(--green);">
            <path fill-rule="evenodd"
                  d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                  clip-rule="evenodd"/>
        </svg>
        <div style="font-size:1.1rem;font-weight:600;color:var(--navy);margin-bottom:6px;">Queue is clear</div>
        <div style="font-size:.875rem;color:var(--slate);">No articles awaiting review.</div>
    </div>
<?php else: ?>

    <div style="display:flex;flex-direction:column;gap:14px;">

        <?php foreach ($articles as $article):
            $resubCount = (int)($article->resubmission_count ?? 0);
            $prevRej = $article->rejection_reason ?? null;
            $prevNotes = $article->rejection_notes ?? null;
            $submittedAt = $article->submitted_at ?? null;
            ?>
            <div class="oc-card" id="article-card-<?= (int)$article->id ?>" style="overflow:hidden;">

                <!-- Header row -->
                <div style="display:grid;grid-template-columns:1fr auto;gap:16px;
                    align-items:start;padding:18px 20px;">
                    <div>
                        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:5px;">
              <span style="font-weight:700;font-size:.95rem;color:var(--navy);">
                <?= htmlspecialchars($article->title ?: 'Untitled') ?>
              </span>
                            <?php if ($resubCount > 0): ?>
                                <span style="font-size:.68rem;background:#fef3c7;color:#92400e;
                             border:1px solid #fde68a;border-radius:10px;padding:2px 8px;font-weight:600;">
                  Resubmission #<?= $resubCount ?>
                </span>
                            <?php endif; ?>
                            <?php if (!empty($article->is_paid)): ?>
                                <span class="oc-badge oc-badge--paid" style="font-size:.65rem;">
                  PAID · £<?= number_format((int)($article->price ?? 0) / 100, 2) ?>
                </span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:.78rem;color:var(--slate);display:flex;gap:14px;flex-wrap:wrap;">
                            <?php if ($article->contributor_id): ?>
                                <span>
                  <svg viewBox="0 0 20 20" fill="currentColor" width="12"
                       style="vertical-align:middle;margin-right:3px;opacity:.6;">
                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"
                          clip-rule="evenodd"/>
                  </svg>
                  Contributor #<?= (int)$article->contributor_id ?>
                </span>
                            <?php endif; ?>
                            <?php if ($submittedAt): ?>
                                <span>Submitted <?= $submittedAt->format('d M Y, H:i') ?></span>
                            <?php endif; ?>
                            <?php if ($article->updated_at): ?>
                                <span>Last updated <?= $article->updated_at->format('d M Y') ?></span>
                            <?php endif; ?>
                        </div>

                        <!-- Previous rejection notice -->
                        <?php if ($prevRej && $resubCount > 0): ?>
                            <div style="margin-top:10px;padding:10px 12px;background:#fff9f9;
                          border:1px solid #fecaca;border-radius:6px;font-size:.8rem;">
                <span style="font-weight:600;color:var(--red);">
                  Previously rejected:
                </span>
                                <span style="color:var(--navy);margin-left:4px;">
                  <?= htmlspecialchars(str_replace('_', ' ', ucfirst($prevRej))) ?>
                </span>
                                <?php if ($prevNotes): ?>
                                    — <span style="color:var(--slate);"><?= htmlspecialchars($prevNotes) ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Action buttons -->
                    <div style="display:flex;flex-direction:column;gap:8px;align-items:flex-end;">
                        <div style="display:flex;gap:8px;">
                            <button onclick="approveArticle(<?= (int)$article->id ?>, this)"
                                    class="oc-btn oc-btn--primary oc-btn--sm"
                                    id="approve-btn-<?= (int)$article->id ?>">
                                <svg viewBox="0 0 20 20" fill="currentColor" width="13">
                                    <path fill-rule="evenodd"
                                          d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                          clip-rule="evenodd"/>
                                </svg>
                                Approve
                            </button>
                            <button onclick="openRejectModal(<?= (int)$article->id ?>)"
                                    class="oc-btn oc-btn--ghost oc-btn--sm"
                                    style="border-color:#fecaca;color:var(--red);">
                                Reject
                            </button>
                        </div>
                        <button onclick="togglePreview(<?= (int)$article->id ?>)"
                                class="oc-btn oc-btn--ghost oc-btn--sm"
                                id="preview-toggle-<?= (int)$article->id ?>">
                            Preview content
                        </button>
                    </div>
                </div>

                <!-- Content preview (collapsed) -->
                <div id="preview-panel-<?= (int)$article->id ?>"
                     style="display:none;border-top:1px solid var(--border);padding:20px 24px;
                    background:var(--cream-dark);max-height:400px;overflow-y:auto;">
                    <div style="font-size:.875rem;line-height:1.75;color:var(--navy);">
                        <?= $article->content ?? '<em style="color:var(--slate)">No content</em>' ?>
                    </div>
                </div>

            </div>
        <?php endforeach; ?>

    </div>

<?php endif; ?>

@endsection

@section('scripts')
<script>
    const SITE = '<?= htmlspecialchars($site ?? '') ?>';
    const TOKEN = () => localStorage.getItem('oc_token') || '';

    function togglePreview(id) {
        const panel = document.getElementById('preview-panel-' + id);
        const btn = document.getElementById('preview-toggle-' + id);
        const open = panel.style.display === 'none';
        panel.style.display = open ? 'block' : 'none';
        btn.textContent = open ? 'Hide content' : 'Preview content';
    }

    async function approveArticle(id, btn) {
        if (!confirm('Approve and publish this article?')) return;
        btn.disabled = true;
        btn.innerHTML = '<div class="oc-spinner"></div>';

        try {
            const res = await fetch(`/api/${SITE}/open-collab/admin/articles/${id}/approve`, {
                method: 'POST',
                headers: {'Authorization': `Bearer ${TOKEN()}`, 'Accept': 'application/json'},
            });
            const data = await res.json();
            if (res.ok) {
                showToast('✓ Article approved and published');
                const card = document.getElementById('article-card-' + id);
                if (card) {
                    card.style.opacity = '.4';
                    card.style.pointerEvents = 'none';
                }
                setTimeout(() => card?.remove(), 1200);
            } else {
                showToast(data.error || 'Approval failed', false);
                btn.disabled = false;
                btn.innerHTML = '✓ Approve';
            }
        } catch {
            showToast('Network error', false);
            btn.disabled = false;
            btn.innerHTML = '✓ Approve';
        }
    }

    function openRejectModal(id) {
        document.getElementById('reject-page-id').value = id;
        document.getElementById('reject-reason').value = '';
        document.getElementById('reject-notes').value = '';
        document.getElementById('reject-modal-errors').style.display = 'none';
        document.getElementById('reject-modal').style.display = 'grid';
        document.getElementById('reject-reason').focus();
    }

    function closeRejectModal() {
        document.getElementById('reject-modal').style.display = 'none';
    }

    async function submitRejection() {
        const id = document.getElementById('reject-page-id').value;
        const reason = document.getElementById('reject-reason').value;
        const notes = document.getElementById('reject-notes').value.trim();
        const errBox = document.getElementById('reject-modal-errors');
        const btn = document.getElementById('reject-confirm-btn');

        errBox.style.display = 'none';
        if (!reason) {
            errBox.textContent = 'Please select a rejection reason.';
            errBox.style.display = 'block';
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<div class="oc-spinner"></div> Rejecting…';

        try {
            const res = await fetch(`/api/${SITE}/open-collab/admin/articles/${id}/reject`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${TOKEN()}`,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({reason, notes: notes || undefined}),
            });
            const data = await res.json();
            if (res.ok) {
                closeRejectModal();
                showToast('Article rejected — contributor notified');
                const card = document.getElementById('article-card-' + id);
                if (card) {
                    card.style.opacity = '.4';
                    card.style.pointerEvents = 'none';
                }
                setTimeout(() => card?.remove(), 1200);
            } else {
                errBox.textContent = data.error || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Rejection failed.');
                errBox.style.display = 'block';
                btn.disabled = false;
                btn.textContent = 'Reject article';
            }
        } catch {
            errBox.textContent = 'Network error. Please try again.';
            errBox.style.display = 'block';
            btn.disabled = false;
            btn.textContent = 'Reject article';
        }
    }

    function showToast(msg, ok = true) {
        const el = document.getElementById('status-toast');
        el.textContent = msg;
        el.style.background = ok ? 'var(--navy)' : 'var(--red)';
        el.style.opacity = '1';
        setTimeout(() => {
            el.style.opacity = '0';
        }, 3000);
    }
</script>
@endsection