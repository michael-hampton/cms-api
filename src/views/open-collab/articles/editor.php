@section('logic')
<?php
/**
 * Template: open-collab/articles/editor.php
 * Works for both create (?page=null) and edit (?page=Article)
 * Variables: $page (null|Page), $site, $currentUser, $readabilityScore (optional)
 */

$isEdit = $page !== null;
$pageTitle = $isEdit ? 'Edit Article' : 'New Article';
$activeNav = $isEdit ? 'articles' : 'create';
$breadcrumbs = [
        ['label' => 'Articles', 'url' => '/articles'],
        ['label' => $pageTitle],
];

$headerActions = '
<button onclick="submitArticle(\'draft\')" class="oc-btn oc-btn--ghost" id="save-draft-btn">Save draft</button>
<button onclick="submitArticle(\'published\')" class="oc-btn oc-btn--amber" id="publish-btn">
  <svg viewBox="0 0 20 20" fill="currentColor" width="15"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-8.707l-3-3a1 1 0 00-1.414 1.414L10.586 9H7a1 1 0 100 2h3.586l-1.293 1.293a1 1 0 101.414 1.414l3-3a1 1 0 000-1.414z" clip-rule="evenodd"/></svg>
  ' . ($isEdit && $page->status === 'published' ? 'Update' : 'Publish') . '
</button>';

$pageClass = 'oc-page--wide';

// Readability data
$rScore = $readabilityScore ?? null;
$rGrade = $rScore ? ($rScore >= 80 ? 'A' : ($rScore >= 65 ? 'B' : ($rScore >= 50 ? 'C' : ($rScore >= 35 ? 'D' : 'F')))) : null;
$rLabel = $rScore ? ($rScore >= 80 ? 'Very easy' : ($rScore >= 65 ? 'Easy to read' : ($rScore >= 50 ? 'Fairly easy' : ($rScore >= 35 ? 'Difficult' : 'Very difficult')))) : null;
?>
@endsection

@extends('open-collab/layouts/opencollab')

@section('content')
<div id="save-status"
     style="position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:var(--navy);color:#fff;padding:8px 18px;border-radius:20px;font-size:.8rem;font-weight:500;opacity:0;transition:opacity .3s;z-index:300;pointer-events:none;"></div>

<div class="oc-grid-sidebar" style="align-items:flex-start;gap:24px;">

    <!-- Main editor pane -->
    <div>
        <div class="oc-card" style="padding:32px;">
            <div id="editor-errors" class="oc-form-errors" style="display:none;" role="alert"></div>

            <input
                    type="text"
                    id="article-title"
                    class="oc-editor__title"
                    placeholder="Article title…"
                    value="<?= htmlspecialchars($page->title ?? '') ?>"
                    autocomplete="off"
            >

            <textarea
                    id="article-content"
                    class="oc-editor__body"
                    placeholder="Start writing your article…"
            ><?= htmlspecialchars($page->content ?? '') ?></textarea>
        </div>

        <!-- Readability indicator -->
        <?php if ($rScore !== null): ?>
            <div style="margin-top:12px;padding:12px 16px;background:#fff;border:1px solid var(--border);border-radius:var(--radius);display:flex;align-items:center;gap:12px;">
                <span style="font-size:.75rem;font-weight:600;color:var(--slate);letter-spacing:.06em;text-transform:uppercase;">Readability</span>
                <span class="oc-readability oc-readability--<?= $rGrade ?>">
        <strong><?= $rGrade ?>:</strong> <?= htmlspecialchars($rLabel) ?>
        <div class="oc-readability__bar" style="margin-left:4px;">
          <div class="oc-readability__fill"
               style="width:<?= (int)$rScore ?>%;background:currentColor;opacity:.6;"></div>
        </div>
      </span>
                <span style="margin-left:auto;font-size:.75rem;color:var(--slate-light);"><?= $rScore ?>/100</span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar settings -->
    <div style="display:flex;flex-direction:column;gap:16px;position:sticky;top:calc(var(--header-h) + 20px);">

        <!-- Status badge -->
        <?php if ($isEdit): ?>
            <div style="padding:12px 16px;background:#fff;border:1px solid var(--border);border-radius:var(--radius);display:flex;align-items:center;justify-content:space-between;">
                <span style="font-size:.78rem;font-weight:600;color:var(--slate);text-transform:uppercase;letter-spacing:.06em;">Status</span>
                <span class="oc-badge oc-badge--<?= htmlspecialchars($page->status) ?>"><?= ucfirst(htmlspecialchars($page->status)) ?></span>
            </div>
        <?php endif; ?>

        <!-- Monetisation card -->
        <div class="oc-card">
            <div class="oc-card__header">
                <span class="oc-card__title" style="font-size:.95rem;">Monetisation</span>
            </div>
            <div class="oc-card__body">
                <div class="oc-toggle-row">
                    <label class="oc-toggle">
                        <input type="checkbox" id="is-paid" <?= ($page?->is_paid) ? 'checked' : '' ?>>
                        <span class="oc-toggle__slider"></span>
                    </label>
                    <div class="oc-toggle-label">
                        <strong>Paid article</strong>
                        <span>Readers pay to access</span>
                    </div>
                </div>

                <div id="price-field" style="display:<?= ($page?->is_paid) ? 'block' : 'none' ?>;margin-top:14px;">
                    <label class="oc-label" for="article-price">Price</label>
                    <div style="display:flex;align-items:center;border:1.5px solid var(--border);border-radius:var(--radius);overflow:hidden;background:#fff;">
                        <span style="padding:10px 12px;background:var(--slate-pale);border-right:1px solid var(--border);font-size:.9rem;color:var(--slate);">£</span>
                        <input
                                type="number"
                                id="article-price"
                                step="0.50"
                                min="0.50"
                                value="<?= $page?->price ? number_format($page->price / 100, 2) : '2.99' ?>"
                                style="border:none;outline:none;padding:10px 12px;font-size:.9rem;width:100%;font-family:var(--font-body);"
                        >
                    </div>
                    <div class="oc-help">Minimum £0.50</div>
                </div>
            </div>
        </div>

        <!-- SEO / Slug -->
        <div class="oc-card">
            <div class="oc-card__header">
                <span class="oc-card__title" style="font-size:.95rem;">SEO</span>
            </div>
            <div class="oc-card__body">
                <div class="oc-form-group">
                    <label class="oc-label oc-label--optional" for="article-slug">URL slug</label>
                    <input class="oc-input" type="text" id="article-slug"
                           value="<?= htmlspecialchars($page?->slug ?? '') ?>"
                           placeholder="auto-generated-from-title">
                    <div class="oc-help">Leave blank to auto-generate.</div>
                </div>
                <div class="oc-form-group" style="margin-bottom:0;">
                    <label class="oc-label oc-label--optional" for="meta-description">Meta description</label>
                    <textarea class="oc-textarea" id="meta-description"
                              rows="3" style="min-height:72px;"
                              placeholder="Brief description for search engines…"><?= htmlspecialchars($page?->meta_description ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <?php if ($isEdit): ?>
            <!-- Danger zone for published articles -->
            <div class="oc-card" style="border-color:#fecaca;">
                <div class="oc-card__body" style="padding:16px 18px;">
                    <div style="font-size:.72rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--red);margin-bottom:10px;">
                        Danger zone
                    </div>
                    <?php if ($page->status === 'published'): ?>
                        <button onclick="submitArticle('draft')" class="oc-btn oc-btn--ghost oc-btn--sm oc-btn--block"
                                style="margin-bottom:8px;border-color:#fecaca;color:var(--red);">
                            Unpublish article
                        </button>
                    <?php endif; ?>
                    <button onclick="confirmDelete()" class="oc-btn oc-btn--danger oc-btn--sm oc-btn--block">
                        Delete article
                    </button>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>
@endsection

@section('scripts')
<script>
    const SITE = '<?= htmlspecialchars($site ?? '') ?>';
    const PAGE_ID = <?= $isEdit ? (int)$page->id : 'null' ?>;
    //const TOKEN    = localStorage.getItem('oc_token') || '';
    const TOKEN = '25ff6fc98de5ce11ea726753b21b2f045d3d5b82013e28ecaf927dc2d90d9804'; //todo

    // Paid toggle
    document.getElementById('is-paid').addEventListener('change', function () {
        document.getElementById('price-field').style.display = this.checked ? 'block' : 'none';
    });

    // Auto-generate slug from title
    document.getElementById('article-title').addEventListener('blur', function () {
        const slugField = document.getElementById('article-slug');
        if (!slugField.value && this.value) {
            slugField.value = this.value.toLowerCase()
                .replace(/[^a-z0-9\s-]/g, '')
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-')
                .slice(0, 100);
        }
    });

    // Dirty state warning
    let isDirty = false;
    document.querySelectorAll('#article-title, #article-content').forEach(el => {
        el.addEventListener('input', () => {
            isDirty = true;
        });
    });
    window.addEventListener('beforeunload', e => {
        if (isDirty) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    function showStatus(msg, ok = true) {
        const el = document.getElementById('save-status');
        el.textContent = msg;
        el.style.background = ok ? 'var(--navy)' : 'var(--red)';
        el.style.opacity = '1';
        setTimeout(() => el.style.opacity = '0', 2500);
    }

    async function submitArticle(status) {
        const errBox = document.getElementById('editor-errors');
        errBox.style.display = 'none';

        const title = document.getElementById('article-title').value.trim();
        const content = document.getElementById('article-content').value;
        const isPaid = document.getElementById('is-paid').checked;
        const priceVal = document.getElementById('article-price').value;
        const slug = document.getElementById('article-slug').value.trim();
        const metaDesc = document.getElementById('meta-description').value.trim();
        const subtitle = document.getElementById('article-content').value.trim();

        if (!title) {
            errBox.textContent = 'Please give your article a title before saving.';
            errBox.style.display = 'block';
            return;
        }

        const pricePence = isPaid ? Math.round(parseFloat(priceVal || '0') * 100) : 0;
        if (isPaid && pricePence < 50) {
            errBox.textContent = 'Minimum price is £0.50.';
            errBox.style.display = 'block';
            return;
        }

        // Articles always start as draft unless explicitly published
        const effectiveStatus = status === 'published' ? 'published' : 'draft';

        const payload = {
            site_id: parseInt('<?= $siteId ?? 1 ?>'),
            is_paid: isPaid,
            price: pricePence,
            forms: {
                main: {title, subtitle: subtitle, content: subtitle},
                meta: {status: effectiveStatus, slug: slug || undefined},
                seo: {meta_description: metaDesc, meta_title: title}
            },
            blocks: [],
            gallery_slides: []
        };

        const draftBtn = document.getElementById('save-draft-btn');
        const publishBtn = document.getElementById('publish-btn');
        if (status === 'draft') {
            draftBtn.innerHTML = '<div class="oc-spinner oc-spinner--dark"></div> Saving…';
            draftBtn.disabled = true;
        } else {
            publishBtn.innerHTML = '<div class="oc-spinner"></div> Publishing…';
            publishBtn.disabled = true;
        }

        const url = PAGE_ID
            ? `/api/${SITE}/open-collab/pages/${PAGE_ID}`
            : `/api/${SITE}/open-collab/pages`;
        const method = PAGE_ID ? 'PUT' : 'POST';

        try {
            const res = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${TOKEN}`
                },
                body: JSON.stringify(payload)
            });

            const data = await res.json();
            isDirty = false;

            if (res.ok) {
                showStatus(status === 'published' ? '✓ Published!' : '✓ Draft saved');
                const id = data?.page?.id || PAGE_ID;
                if (!PAGE_ID && id) {
                    window.location.href = `/articles/${id}/edit`;
                }
            } else {
                let msg = data.message || 'Save failed.';
                if (data.errors) msg = Object.values(data.errors).flat().join(' ');
                errBox.textContent = msg;
                errBox.style.display = 'block';
                showStatus('Save failed', false);
            }
        } catch {
            showStatus('Network error', false);
        } finally {
            draftBtn.innerHTML = 'Save draft';
            draftBtn.disabled = false;
            publishBtn.innerHTML = '<svg viewBox="0 0 20 20" fill="currentColor" width="15"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-8.707l-3-3a1 1 0 00-1.414 1.414L10.586 9H7a1 1 0 100 2h3.586l-1.293 1.293a1 1 0 101.414 1.414l3-3a1 1 0 000-1.414z" clip-rule="evenodd"/></svg> <?= $isEdit && $page->status === 'published' ? 'Update' : 'Publish' ?>';
            publishBtn.disabled = false;
        }
    }

    async function confirmDelete() {
        if (!PAGE_ID) return;
        if (!confirm('Delete this article permanently? This cannot be undone.')) return;

        const res = await fetch(`/api/${SITE}/open-collab/pages/${PAGE_ID}`, {
            method: 'DELETE',
            headers: {'Authorization': `Bearer ${TOKEN}`, 'Accept': 'application/json'}
        });

        if (res.ok || res.status === 204) {
            window.location.href = '/articles';
        } else {
            alert('Could not delete article. Please try again.');
        }
    }
</script>
@endsection