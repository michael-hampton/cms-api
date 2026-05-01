@section('logic')
<?php
/**
 * Template: open-collab/articles/index.php
 * Variables:
 *   $articles    — Collection of Page models
 *   $site        — string (site slug)
 *   $currentUser — AuthenticatedUser
 */

$pageTitle = 'My Articles';
$activeNav = 'articles';
$breadcrumbs = [['label' => 'Dashboard', 'url' => "/{$site}/open-collab/dashboard"], ['label' => 'My Articles']];
$headerActions = '
<a href="/' . $site . '/open-collab/articles/create" class="oc-btn oc-btn--amber">
  <svg viewBox="0 0 20 20" fill="currentColor" width="15">
    <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
  </svg>
  New article
</a>';

$statusOrder = ['on_hold' => 0, 'draft' => 1, 'waiting_approval' => 2, 'published' => 3, 'archived' => 4];
?>
@endsection

@extends('open-collab/layouts/opencollab')

@section('content')

<div id="status-toast"
     style="position:fixed;bottom:24px;left:50%;transform:translateX(-50%);
            background:var(--navy);color:#fff;padding:9px 20px;border-radius:20px;
            font-size:.8rem;font-weight:500;opacity:0;transition:opacity .3s;
            z-index:300;pointer-events:none;"></div>

<!-- Filter bar -->
<div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
    <span style="font-size:.78rem;font-weight:600;color:var(--slate);text-transform:uppercase;letter-spacing:.06em;">Filter:</span>
    <?php
    $filters = ['all' => 'All', 'draft' => 'Drafts', 'waiting_approval' => 'In Review',
            'on_hold' => 'Rejected', 'published' => 'Published'];
    foreach ($filters as $val => $label):
        ?>
        <button class="filter-btn oc-btn oc-btn--ghost oc-btn--sm <?= $val === 'all' ? 'filter-btn--active' : '' ?>"
                data-filter="<?= $val ?>"
                onclick="setFilter('<?= $val ?>', this)"
                style="<?= $val === 'all' ? 'background:var(--navy);color:#fff;border-color:var(--navy);' : '' ?>">
            <?= $label ?>
        </button>
    <?php endforeach; ?>
    <div style="margin-left:auto;font-size:.82rem;color:var(--slate);">
        <span id="article-count"><?= count($articles) ?></span> articles
    </div>
</div>

<?php if (empty($articles) || count($articles) === 0): ?>
    <div class="oc-card" style="padding:64px 24px;text-align:center;">
        <svg viewBox="0 0 20 20" fill="currentColor" width="40"
             style="opacity:.15;display:block;margin:0 auto 16px;color:var(--navy);">
            <path fill-rule="evenodd"
                  d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"
                  clip-rule="evenodd"/>
        </svg>
        <div style="font-size:1.1rem;font-weight:600;color:var(--navy);margin-bottom:8px;">No articles yet</div>
        <div style="font-size:.875rem;color:var(--slate);margin-bottom:20px;">
            Start writing your first article and build an audience.
        </div>
        <a href="/<?= $site ?>/open-collab/articles/create" class="oc-btn oc-btn--amber">
            Write your first article
        </a>
    </div>
<?php else: ?>

    <div id="articles-grid" style="display:flex;flex-direction:column;gap:12px;">

        <?php foreach ($articles as $article): ?>
            <?php
            $status = $article->status ?? 'draft';
            $isPaid = (bool)($article->is_paid ?? false);
            $price = (int)($article->price ?? 0);
            $rejReason = $article->rejection_reason ?? null;
            $rejNotes = $article->rejection_notes ?? null;
            $resubCount = (int)($article->resubmission_count ?? 0);
            $canSubmit = in_array($status, ['draft', 'on_hold'], true);
            $canResubmit = $status === 'on_hold';
            $canEdit = !in_array($status, ['published', 'archived'], true);
            ?>
            <div class="article-row oc-card"
                 data-status="<?= htmlspecialchars($status) ?>"
                 style="padding:0;overflow:hidden;transition:box-shadow .15s;">

                <!-- Main row -->
                <div style="display:grid;grid-template-columns:1fr auto;gap:16px;align-items:center;padding:18px 20px;">
                    <div style="min-width:0;">
                        <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px;flex-wrap:wrap;">
                            <a href="/<?= $site ?>/open-collab/articles/edit/<?= (int)$article->id ?>"
                               style="font-weight:600;font-size:.95rem;color:var(--navy);text-decoration:none;
                        white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:400px;"
                               title="<?= htmlspecialchars($article->title ?: 'Untitled') ?>">
                                <?= htmlspecialchars($article->title ?: 'Untitled draft') ?>
                            </a>
                            <span class="oc-badge oc-badge--<?= htmlspecialchars($status) ?>">
                <?= str_replace('_', ' ', ucfirst($status)) ?>
              </span>
                            <?php if ($isPaid): ?>
                                <span class="oc-badge oc-badge--paid" style="font-size:.65rem;">
                  PAID · £<?= number_format($price / 100, 2) ?>
                </span>
                            <?php else: ?>
                                <span class="oc-badge oc-badge--free" style="font-size:.65rem;">FREE</span>
                            <?php endif; ?>
                            <?php if ($resubCount > 0): ?>
                                <span style="font-size:.68rem;color:var(--slate);background:var(--cream-dark);
                             border:1px solid var(--border);border-radius:10px;padding:1px 7px;">
                  Resubmitted ×<?= $resubCount ?>
                </span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:.78rem;color:var(--slate);">
                            <?php if ($article->updated_at): ?>
                                Updated <?= $article->updated_at->format('d M Y') ?>
                            <?php endif; ?>
                            <?php if ($status === 'waiting_approval' && $article->submitted_at): ?>
                                · Submitted <?= $article->submitted_at->format('d M Y') ?>
                            <?php endif; ?>
                            <?php if ($status === 'published' && $article->published_at): ?>
                                · Published <?= $article->published_at->format('d M Y') ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;">
                        <?php if ($canEdit): ?>
                            <a href="/<?= $site ?>/open-collab/articles/edit/<?= (int)$article->id ?>"
                               class="oc-btn oc-btn--ghost oc-btn--sm">Edit</a>
                        <?php else: ?>
                            <a href="/<?= $site ?>/open-collab/articles/edit/<?= (int)$article->id ?>"
                               class="oc-btn oc-btn--ghost oc-btn--sm">View</a>
                        <?php endif; ?>

                        <?php if ($canResubmit): ?>
                            <button onclick="resubmitArticle(<?= (int)$article->id ?>, this)"
                                    class="oc-btn oc-btn--amber oc-btn--sm">Resubmit
                            </button>
                        <?php elseif ($canSubmit && $status === 'draft'): ?>
                            <button onclick="submitArticle(<?= (int)$article->id ?>, this)"
                                    class="oc-btn oc-btn--primary oc-btn--sm">Submit for review
                            </button>
                        <?php endif; ?>

                        <button onclick="toggleExpand(<?= (int)$article->id ?>)"
                                id="expand-btn-<?= (int)$article->id ?>"
                                style="background:none;border:none;cursor:pointer;padding:4px;color:var(--slate);
                                        display:<?= ($rejReason || $status === 'waiting_approval') ? 'flex' : 'none' ?>;
                                        align-items:center;"
                                aria-label="Details">
                            <svg viewBox="0 0 20 20" fill="currentColor" width="16"
                                 id="expand-chevron-<?= (int)$article->id ?>"
                                 style="transition:transform .2s;">
                                <path fill-rule="evenodd"
                                      d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                      clip-rule="evenodd"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Rejection detail panel (collapsed by default) -->
                <?php if ($rejReason || $status === 'waiting_approval'): ?>
                    <div id="expand-panel-<?= (int)$article->id ?>"
                         style="display:none;border-top:1px solid var(--border);padding:14px 20px;
                                 background:<?= $rejReason ? '#fff9f9' : 'var(--cream-dark)' ?>;">

                        <?php if ($rejReason): ?>
                            <div style="display:flex;gap:10px;align-items:flex-start;">
                                <svg viewBox="0 0 20 20" fill="var(--red)" width="16"
                                     style="flex-shrink:0;margin-top:2px;">
                                    <path fill-rule="evenodd"
                                          d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                          clip-rule="evenodd"/>
                                </svg>
                                <div>
                                    <div style="font-size:.8rem;font-weight:700;color:var(--red);margin-bottom:3px;">
                                        Rejected · <?= htmlspecialchars(str_replace('_', ' ', ucfirst($rejReason))) ?>
                                    </div>
                                    <?php if ($rejNotes): ?>
                                        <div style="font-size:.82rem;color:var(--navy);line-height:1.5;">
                                            <?= htmlspecialchars($rejNotes) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($canResubmit): ?>
                                        <div style="margin-top:10px;">
                                            <button onclick="resubmitArticle(<?= (int)$article->id ?>, this)"
                                                    class="oc-btn oc-btn--amber oc-btn--sm">
                                                Address feedback &amp; resubmit
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php elseif ($status === 'waiting_approval'): ?>
                            <div style="display:flex;gap:10px;align-items:center;">
                                <svg viewBox="0 0 20 20" fill="var(--amber-dark,#b45309)" width="16"
                                     style="flex-shrink:0;">
                                    <path fill-rule="evenodd"
                                          d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"
                                          clip-rule="evenodd"/>
                                </svg>
                                <div style="font-size:.82rem;color:var(--navy);">
                                    This article is under review. Our team will respond shortly.
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            </div>
        <?php endforeach; ?>

    </div>

<?php endif; ?>

@endsection

@section('scripts')
<script>
    const SITE = '<?= htmlspecialchars($site ?? '') ?>';

    class ArticlesManager {
        #site;
        #token;

        constructor(site, token) {
            this.#site = site;
            this.#token = token;
        }

        setFilter(filter, btn) {
            document.querySelectorAll('.filter-btn').forEach(b => {
                b.style.background = b.style.color = b.style.borderColor = '';
            });
            btn.style.background = 'var(--navy)';
            btn.style.color = '#fff';
            btn.style.borderColor = 'var(--navy)';

            let shown = 0;
            document.querySelectorAll('.article-row').forEach(row => {
                const match = filter === 'all' || row.dataset.status === filter;
                row.style.display = match ? '' : 'none';
                if (match) shown++;
            });
            document.getElementById('article-count').textContent = shown;
        }

        toggleExpand(id) {
            const panel = document.getElementById(`expand-panel-${id}`);
            const chevron = document.getElementById(`expand-chevron-${id}`);
            if (!panel) return;
            const open = panel.style.display === 'none';
            panel.style.display = open ? 'block' : 'none';
            chevron.style.transform = open ? 'rotate(180deg)' : '';
        }

        async submitArticle(id, btn) {
            if (!confirm('Submit this article for editorial review?')) return;
            btn.disabled = true;
            btn.innerHTML = '<div class="oc-spinner oc-spinner--dark"></div>';

            try {
                const res = await fetch(`/api/${this.#site}/open-collab/pages/${id}/submit`, {
                    method: 'POST',
                    headers: {Authorization: `Bearer ${this.#token()}`, Accept: 'application/json'},
                });
                const data = await res.json();
                if (res.ok) {
                    this.#showToast('✓ Article submitted for review');
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    this.#showToast(data.error || 'Submit failed', false);
                    btn.disabled = false;
                    btn.textContent = 'Submit for review';
                }
            } catch {
                this.#showToast('Network error', false);
                btn.disabled = false;
                btn.textContent = 'Submit for review';
            }
        }

        async resubmitArticle(id, btn) {
            if (!confirm('Resubmit this article for review?')) return;
            btn.disabled = true;
            btn.innerHTML = '<div class="oc-spinner oc-spinner--dark"></div>';

            try {
                const res = await fetch(`/api/${this.#site}/open-collab/pages/${id}/resubmit`, {
                    method: 'POST',
                    headers: {Authorization: `Bearer ${this.#token()}`, Accept: 'application/json'},
                });
                const data = await res.json();
                if (res.ok) {
                    this.#showToast('✓ Article resubmitted');
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    this.#showToast(data.error || 'Resubmit failed', false);
                    btn.disabled = false;
                    btn.textContent = 'Resubmit';
                }
            } catch {
                this.#showToast('Network error', false);
                btn.disabled = false;
                btn.textContent = 'Resubmit';
            }
        }

        #showToast(msg, ok = true) {
            const el = document.getElementById('status-toast');
            el.textContent = msg;
            el.style.background = ok ? 'var(--navy)' : 'var(--red)';
            el.style.opacity = '1';
            setTimeout(() => {
                el.style.opacity = '0';
            }, 2800);
        }
    }

    const manager = new ArticlesManager(SITE, () => localStorage.getItem('oc_token') || '');

    const setFilter = (f, btn) => manager.setFilter(f, btn);
    const toggleExpand = (id) => manager.toggleExpand(id);
    const submitArticle = (id, b) => manager.submitArticle(id, b);
    const resubmitArticle = (id, b) => manager.resubmitArticle(id, b);
</script>
@endsection