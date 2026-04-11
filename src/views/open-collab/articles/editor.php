@section('logic')
<?php
/**
 * Template: open-collab/articles/editor.php
 * Works for both create ($page === null) and edit ($page instanceof Page)
 *
 * Variables:
 *   $page             — null|Page
 *   $site             — string  (site slug)
 *   $siteId           — int
 *   $currentUser      — AuthenticatedUser
 *   $readabilityScore — float|null
 */

use App\Framework\Authorization\Auth;

$isEdit = $page !== null;
$pageTitle = $isEdit ? 'Edit Article' : 'New Article';
$activeNav = $isEdit ? 'articles' : 'create';
$breadcrumbs = [
        ['label' => 'Articles', 'url' => '/articles'],
        ['label' => $pageTitle],
];
$pageClass = 'oc-page--wide';

$rScore = $readabilityScore ?? null;
$rGrade = match (true) {
    $rScore === null => null,
    $rScore >= 80 => 'A',
    $rScore >= 65 => 'B',
    $rScore >= 50 => 'C',
    $rScore >= 35 => 'D',
    default => 'F',
};
$rLabel = match (true) {
    $rScore === null => null,
    $rScore >= 80 => 'Very easy',
    $rScore >= 65 => 'Easy to read',
    $rScore >= 50 => 'Fairly easy',
    $rScore >= 35 => 'Difficult',
    default => 'Very difficult',
};

$headerActions = '
<button onclick="saveArticle(\'draft\')" class="oc-btn oc-btn--ghost" id="save-draft-btn">Save draft</button>
<button onclick="saveArticle(\'published\')" class="oc-btn oc-btn--amber" id="publish-btn">
  <svg viewBox="0 0 20 20" fill="currentColor" width="15"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-8.707l-3-3a1 1 0 00-1.414 1.414L10.586 9H7a1 1 0 100 2h3.586l-1.293 1.293a1 1 0 101.414 1.414l3-3a1 1 0 000-1.414z" clip-rule="evenodd"/></svg>
  ' . ($isEdit && ($page->status ?? '') === 'published' ? 'Update' : 'Publish') . '
</button>';
?>
@endsection

@extends('open-collab/layouts/opencollab')

@section('content')

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.snow.min.css">
<style>
    /* ── Editor layout ─────────────────────────────────────── */
    .editor-shell {
        display: grid;
        grid-template-columns:1fr 300px;
        gap: 20px;
        align-items: start;
    }

    .editor-main {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .editor-sidebar {
        display: flex;
        flex-direction: column;
        gap: 16px;
        position: sticky;
        top: calc(var(--header-h) + 20px);
    }

    /* ── Quill overrides ────────────────────────────────────── */
    .oc-quill-wrap {
        background: #fff;
        border: 1.5px solid var(--border);
        border-radius: var(--radius);
        overflow: hidden;
    }

    .oc-quill-wrap .ql-toolbar {
        border: none;
        border-bottom: 1px solid var(--border);
        padding: 10px 12px;
        background: var(--cream-dark);
    }

    .oc-quill-wrap .ql-container {
        border: none;
        font-family: var(--font-body);
        font-size: 1rem;
        min-height: 420px;
    }

    .oc-quill-wrap .ql-editor {
        padding: 20px 24px;
        min-height: 420px;
        line-height: 1.8;
        color: var(--navy);
    }

    .oc-quill-wrap .ql-editor p {
        margin-bottom: .75em;
    }

    .ql-snow .ql-picker-label {
        color: var(--slate);
    }

    /* ── Drag-over state ────────────────────────────────────── */
    .oc-quill-wrap.drag-over {
        border-color: var(--amber);
        background: var(--amber-pale,.fffbeb);
    }

    /* ── Autosave indicator ─────────────────────────────────── */
    #autosave-indicator {
        font-size: .72rem;
        color: var(--slate-light);
        display: flex;
        align-items: center;
        gap: 5px;
        transition: opacity .3s;
    }

    #autosave-indicator.saving {
        color: var(--amber-dark, #b45309);
    }

    #autosave-indicator.saved {
        color: var(--green);
    }

    #autosave-indicator.error {
        color: var(--red);
    }

    /* ── History panel ──────────────────────────────────────── */
    .history-panel {
        max-height: 320px;
        overflow-y: auto;
    }

    .history-item {
        padding: 10px 14px;
        cursor: pointer;
        border-radius: 6px;
        transition: background .15s;
        display: flex;
        align-items: flex-start;
        gap: 10px;
    }

    .history-item:hover {
        background: var(--cream-dark);
    }

    .history-item__dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--amber);
        flex-shrink: 0;
        margin-top: 5px;
    }

    .history-item__meta {
        font-size: .72rem;
        color: var(--slate);
        margin-top: 2px;
    }

    .history-item__summary {
        font-size: .8rem;
        font-weight: 500;
        color: var(--navy);
    }

    .history-item.active {
        background: var(--navy);
    }

    .history-item.active .history-item__summary,
    .history-item.active .history-item__meta {
        color: #fff;
    }

    .history-item.active .history-item__dot {
        background: var(--amber);
    }

    /* ── Comments panel ─────────────────────────────────────── */
    .comment-thread {
        border-left: 2px solid var(--border);
        padding-left: 12px;
        margin-bottom: 12px;
    }

    .comment-thread.top-level {
        border-left: none;
        padding-left: 0;
    }

    .comment-item {
        background: var(--cream-dark);
        border-radius: 8px;
        padding: 10px 14px;
        margin-bottom: 8px;
    }

    .comment-item__header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 4px;
    }

    .comment-item__author {
        font-size: .75rem;
        font-weight: 600;
        color: var(--navy);
    }

    .comment-item__time {
        font-size: .68rem;
        color: var(--slate-light);
    }

    .comment-item__body {
        font-size: .82rem;
        color: var(--navy);
        line-height: 1.5;
    }

    .comment-item__actions {
        display: flex;
        gap: 8px;
        margin-top: 6px;
    }

    .comment-action-btn {
        background: none;
        border: none;
        cursor: pointer;
        font-size: .72rem;
        color: var(--slate);
        padding: 0;
        text-decoration: underline;
    }

    .comment-action-btn:hover {
        color: var(--navy);
    }

    .replies {
        margin-top: 8px;
        padding-left: 14px;
        border-left: 2px solid var(--border);
    }

    /* ── Reply / new comment form ───────────────────────────── */
    .comment-form {
        margin-top: 8px;
    }

    .comment-form textarea {
        width: 100%;
        resize: vertical;
        min-height: 60px;
        padding: 8px 10px;
        border: 1.5px solid var(--border);
        border-radius: 6px;
        font-size: .82rem;
        font-family: var(--font-body);
        color: var(--navy);
        background: #fff;
        box-sizing: border-box;
    }

    .comment-form textarea:focus {
        outline: none;
        border-color: var(--navy);
    }

    .comment-form__actions {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
        margin-top: 6px;
    }

    /* ── Upload progress overlay ────────────────────────────── */
    #upload-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 25, 41, .55);
        z-index: 900;
        display: none;
        place-items: center;
    }

    #upload-overlay .upload-card {
        background: #fff;
        border-radius: 12px;
        padding: 32px 40px;
        text-align: center;
        min-width: 220px;
    }

    /* ── Restore banner ─────────────────────────────────────── */
    #restore-banner {
        background: var(--amber-pale,.fffbeb);
        border: 1.5px solid var(--amber-dark, #b45309);
        border-radius: var(--radius);
        padding: 12px 16px;
        display: none;
        align-items: center;
        gap: 12px;
        font-size: .82rem;
        color: var(--amber-dark, #b45309);
    }
</style>

<div id="save-status"
     style="position:fixed;bottom:24px;left:50%;transform:translateX(-50%);
            background:var(--navy);color:#fff;padding:8px 18px;border-radius:20px;
            font-size:.8rem;font-weight:500;opacity:0;transition:opacity .3s;
            z-index:300;pointer-events:none;"></div>

<div id="upload-overlay">
    <div class="upload-card">
        <div class="oc-spinner" style="margin:0 auto 12px;width:28px;height:28px;border-width:3px;"></div>
        <div style="font-weight:600;color:var(--navy);">Uploading image…</div>
        <div id="upload-pct" style="font-size:.8rem;color:var(--slate);margin-top:4px;"></div>
    </div>
</div>

<div id="restore-banner">
    <svg viewBox="0 0 20 20" fill="currentColor" width="16" style="flex-shrink:0;">
        <path fill-rule="evenodd"
              d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
              clip-rule="evenodd"/>
    </svg>
    <span id="restore-banner-text">Viewing a previous version.</span>
    <button onclick="confirmRestore()" class="oc-btn oc-btn--amber oc-btn--sm" id="restore-confirm-btn">Restore this
        version
    </button>
    <button onclick="cancelRestore()" class="oc-btn oc-btn--ghost oc-btn--sm">Cancel</button>
</div>

<div id="editor-errors" class="oc-form-errors" style="display:none;margin-bottom:16px;" role="alert"></div>

<div class="editor-shell">

    <div class="editor-main">

        <div class="oc-card" style="padding:20px 24px 0;">
            <input
                    type="text"
                    id="article-title"
                    class="oc-editor__title"
                    placeholder="Article title…"
                    value="<?= htmlspecialchars($page->title ?? '') ?>"
                    autocomplete="off"
                    style="border:none;outline:none;width:100%;font-family:var(--font-display);
                       font-size:1.6rem;font-weight:700;color:var(--navy);
                       padding:0 0 16px;background:transparent;"
            >
        </div>

        <div class="oc-quill-wrap" id="quill-wrap">
            <div id="quill-toolbar">
                <span class="ql-formats">
                    <select class="ql-header">
                        <option value="2">Heading</option>
                        <option value="3">Sub-heading</option>
                        <option selected></option>
                    </select>
                </span>
                <span class="ql-formats">
                    <button class="ql-bold"></button>
                    <button class="ql-italic"></button>
                    <button class="ql-underline"></button>
                    <button class="ql-strike"></button>
                </span>
                <span class="ql-formats">
                    <button class="ql-blockquote"></button>
                    <button class="ql-code-block"></button>
                </span>
                <span class="ql-formats">
                    <button class="ql-list" value="ordered"></button>
                    <button class="ql-list" value="bullet"></button>
                </span>
                <span class="ql-formats">
                    <button class="ql-link"></button>
                    <button class="ql-image" id="custom-image-btn" title="Upload image"></button>
                </span>
                <span class="ql-formats">
                    <button class="ql-clean"></button>
                </span>
                <span class="ql-formats" style="margin-left:auto;">
                    <span id="autosave-indicator">
                        <svg viewBox="0 0 20 20" fill="currentColor" width="12" id="autosave-icon">
                            <path fill-rule="evenodd"
                                  d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                  clip-rule="evenodd"/>
                        </svg>
                        <span id="autosave-text">All changes saved</span>
                    </span>
                </span>
            </div>
            <div id="quill-editor"><?= $page->content ?? '' ?></div>
        </div>

        <div style="font-size:.72rem;color:var(--slate-light);text-align:center;padding:4px 0;">
            Drag &amp; drop images into the editor, or use the image button in the toolbar
        </div>

        <?php if ($rScore !== null): ?>
            <div style="padding:12px 16px;background:#fff;border:1px solid var(--border);
                    border-radius:var(--radius);display:flex;align-items:center;gap:12px;">
            <span style="font-size:.75rem;font-weight:600;color:var(--slate);
                         letter-spacing:.06em;text-transform:uppercase;">Readability</span>
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

    <div class="editor-sidebar">

        <?php if ($isEdit): ?>
            <div style="padding:12px 16px;background:#fff;border:1px solid var(--border);
                    border-radius:var(--radius);display:flex;align-items:center;
                    justify-content:space-between;">
            <span style="font-size:.78rem;font-weight:600;color:var(--slate);
                         text-transform:uppercase;letter-spacing:.06em;">Status</span>
                <span class="oc-badge oc-badge--<?= htmlspecialchars($page->status) ?>">
                <?= ucfirst(htmlspecialchars($page->status)) ?>
            </span>
            </div>
        <?php endif; ?>

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
                    <div style="display:flex;align-items:center;border:1.5px solid var(--border);
                                border-radius:var(--radius);overflow:hidden;background:#fff;">
                        <span style="padding:10px 12px;background:var(--slate-pale);
                                     border-right:1px solid var(--border);font-size:.9rem;
                                     color:var(--slate);">£</span>
                        <input type="number" id="article-price" step="0.50" min="0.50"
                               value="<?= $page?->price ? number_format($page->price / 100, 2) : '2.99' ?>"
                               style="border:none;outline:none;padding:10px 12px;
                                      font-size:.9rem;width:100%;font-family:var(--font-body);">
                    </div>
                    <div class="oc-help">Minimum £0.50</div>
                </div>
            </div>
        </div>

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
                    <textarea class="oc-textarea" id="meta-description" rows="3"
                              style="min-height:72px;"
                              placeholder="Brief description for search engines…"><?= htmlspecialchars($page?->meta_description ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <?php if ($isEdit): ?>
            <div class="oc-card" id="history-card">
                <div class="oc-card__header" style="cursor:pointer;" onclick="toggleHistory()">
                    <span class="oc-card__title" style="font-size:.95rem;">Version history</span>
                    <svg id="history-chevron" viewBox="0 0 20 20" fill="currentColor" width="14"
                         style="color:var(--slate);transition:transform .2s;">
                        <path fill-rule="evenodd"
                              d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                              clip-rule="evenodd"/>
                    </svg>
                </div>
                <div id="history-body" style="display:none;">
                    <div class="history-panel" id="history-list">
                        <div style="padding:16px;text-align:center;color:var(--slate);font-size:.82rem;">
                            Loading history…
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($isEdit): ?>
            <div class="oc-card" id="comments-card">
                <div class="oc-card__header">
                    <span class="oc-card__title" style="font-size:.95rem;">Comments</span>
                    <span id="comment-count-badge"
                          style="font-size:.72rem;background:var(--slate-pale);color:var(--slate);
                             padding:2px 7px;border-radius:10px;font-weight:600;">0</span>
                </div>
                <div class="oc-card__body" id="comments-body"
                     style="padding:12px 16px;max-height:360px;overflow-y:auto;">
                    <div style="text-align:center;color:var(--slate);font-size:.82rem;padding:16px 0;"
                         id="comments-empty">
                        No comments yet.
                    </div>
                </div>
                <div style="padding:12px 16px;border-top:1px solid var(--border);">
                <textarea id="new-comment-input" placeholder="Add a comment…"
                          style="width:100%;resize:none;min-height:64px;padding:8px 10px;
                                 border:1.5px solid var(--border);border-radius:6px;
                                 font-size:.82rem;font-family:var(--font-body);
                                 color:var(--navy);background:#fff;box-sizing:border-box;"
                          onkeydown="if(event.ctrlKey&&event.key==='Enter'){postComment();}">
                </textarea>
                    <div style="display:flex;justify-content:flex-end;margin-top:6px;">
                        <button onclick="postComment()" class="oc-btn oc-btn--primary oc-btn--sm">
                            Post <span style="font-size:.7rem;color:rgba(255,255,255,.6);margin-left:4px;">Ctrl+↵</span>
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($isEdit): ?>
            <div class="oc-card" style="border-color:#fecaca;">
                <div class="oc-card__body" style="padding:16px 18px;">
                    <div style="font-size:.72rem;font-weight:700;letter-spacing:.1em;
                             text-transform:uppercase;color:var(--red);margin-bottom:10px;">
                        Danger zone
                    </div>
                    <?php if ($page->status === 'published'): ?>
                        <button onclick="saveArticle('draft')"
                                class="oc-btn oc-btn--ghost oc-btn--sm oc-btn--block"
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

<input type="file" id="image-file-input" accept="image/*" style="display:none;">

@endsection

@section('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.min.js"></script>
<script>
    // ─────────────────────────────────────────────────────────────────────────────
    // Constants
    // ─────────────────────────────────────────────────────────────────────────────
    const SITE = '<?= htmlspecialchars($site ?? '') ?>';
    const PAGE_ID = <?= $isEdit ? (int)$page->id : 'null' ?>;
    const SITE_ID = <?= (int)($siteId ?? 1) ?>;
    const TOKEN = () => localStorage.getItem('oc_token') || '';

    // ─────────────────────────────────────────────────────────────────────────────
    // Quill initialisation
    // ─────────────────────────────────────────────────────────────────────────────
    const quill = new Quill('#quill-editor', {
        theme: 'snow',
        modules: {
            toolbar: '#quill-toolbar',
            clipboard: {matchVisual: false},
        },
        placeholder: 'Start writing your article…',
    });

    // Remove default image handler; replace with our upload handler
    const toolbar = quill.getModule('toolbar');
    toolbar.addHandler('image', () => document.getElementById('image-file-input').click());

    // ─────────────────────────────────────────────────────────────────────────────
    // Autosave
    // ─────────────────────────────────────────────────────────────────────────────
    let autosaveTimer = null;
    let isDirty = false;
    let lastSavedHash = '';
    let isRestoreMode = false;
    let pendingSnapshot = null; // holds snapshot content during restore preview

    function contentHash(str) {
        // Cheap hash to avoid saving when content hasn't changed
        let h = 0;
        for (let i = 0; i < str.length; i++) {
            h = (Math.imul(31, h) + str.charCodeAt(i)) | 0;
        }
        return h;
    }

    function setAutosaveState(state, text) {
        const el = document.getElementById('autosave-indicator');
        const txt = document.getElementById('autosave-text');
        const icon = document.getElementById('autosave-icon');

        el.className = state;
        txt.textContent = text;

        const icons = {
            idle: '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>',
            saving: '<path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"/>',
            saved: '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>',
            error: '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>',
        };
        icon.innerHTML = icons[state] ?? icons.idle;
    }

    function scheduleAutosave() {
        if (!PAGE_ID) return; // only autosave on existing articles
        isDirty = true;
        clearTimeout(autosaveTimer);
        setAutosaveState('saving', 'Saving…');
        autosaveTimer = setTimeout(doAutosave, 2000);
    }

    async function doAutosave() {
        if (!PAGE_ID || isRestoreMode) return;

        const html = quill.root.innerHTML;
        const title = document.getElementById('article-title').value.trim();
        const hash = contentHash(html + title);

        if (hash === lastSavedHash) {
            setAutosaveState('saved', 'All changes saved');
            return;
        }

        try {
            const ok = await persistArticle('draft', {silent: true});
            if (ok) {
                lastSavedHash = hash;
                setAutosaveState('saved', 'All changes saved');
            } else {
                setAutosaveState('error', 'Save failed');
            }
        } catch {
            setAutosaveState('error', 'Save failed');
        }
    }

    // Wire Quill content changes to autosave
    quill.on('text-change', scheduleAutosave);

    // Wire title changes
    document.getElementById('article-title').addEventListener('input', scheduleAutosave);

    // Dirty-state browser warning (only for manual navigation)
    window.addEventListener('beforeunload', e => {
        if (isDirty) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    // ─────────────────────────────────────────────────────────────────────────────
    // Save / publish
    // ─────────────────────────────────────────────────────────────────────────────
    async function saveArticle(status) {
        const errBox = document.getElementById('editor-errors');
        errBox.style.display = 'none';

        const title = document.getElementById('article-title').value.trim();
        if (!title) {
            errBox.textContent = 'Please give your article a title before saving.';
            errBox.style.display = 'block';
            document.getElementById('article-title').focus();
            return;
        }

        const isPaid = document.getElementById('is-paid').checked;
        const priceVal = document.getElementById('article-price').value;
        const pricePence = isPaid ? Math.round(parseFloat(priceVal || '0') * 100) : 0;

        if (isPaid && pricePence < 50) {
            errBox.textContent = 'Minimum price is £0.50.';
            errBox.style.display = 'block';
            return;
        }

        const draftBtn = document.getElementById('save-draft-btn');
        const publishBtn = document.getElementById('publish-btn');

        if (status === 'draft') {
            draftBtn.innerHTML = '<div class="oc-spinner oc-spinner--dark"></div> Saving…';
            draftBtn.disabled = true;
        } else {
            publishBtn.innerHTML = '<div class="oc-spinner"></div> Publishing…';
            publishBtn.disabled = true;
        }

        const ok = await persistArticle(status);

        draftBtn.innerHTML = 'Save draft';
        draftBtn.disabled = false;
        publishBtn.innerHTML = `<svg viewBox="0 0 20 20" fill="currentColor" width="15"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-8.707l-3-3a1 1 0 00-1.414 1.414L10.586 9H7a1 1 0 100 2h3.586l-1.293 1.293a1 1 0 101.414 1.414l3-3a1 1 0 000-1.414z" clip-rule="evenodd"/></svg> <?= $isEdit && ($page->status ?? '') === 'published' ? 'Update' : 'Publish' ?>`;
        publishBtn.disabled = false;
    }

    async function persistArticle(status, opts = {}) {
        const title = document.getElementById('article-title').value.trim();
        const html = quill.root.innerHTML;
        const isPaid = document.getElementById('is-paid').checked;
        const priceVal = document.getElementById('article-price').value;
        const slug = document.getElementById('article-slug').value.trim();
        const metaDesc = document.getElementById('meta-description').value.trim();
        const pricePence = isPaid ? Math.round(parseFloat(priceVal || '0') * 100) : 0;

        const payload = {
            site_id: SITE_ID,
            is_paid: isPaid,
            price: pricePence,
            forms: {
                main: {title, content: html, subtitle: ''},
                meta: {status, slug: slug || undefined},
                seo: {meta_description: metaDesc, meta_title: title},
            },
            blocks: [],
            gallery_slides: [],
        };

        const url = PAGE_ID ? `/api/${SITE}/open-collab/pages/${PAGE_ID}` : `/api/${SITE}/open-collab/pages`;
        const method = PAGE_ID ? 'PUT' : 'POST';

        try {
            const res = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${TOKEN()}`,
                },
                body: JSON.stringify(payload),
            });

            const data = await res.json();
            isDirty = false;

            if (res.ok) {
                if (!opts.silent) showToast(status === 'published' ? '✓ Published!' : '✓ Draft saved');
                const id = data?.data?.page?.id || PAGE_ID;
                if (!PAGE_ID && id) {
                    window.location.href = `/articles/${id}/edit`;
                }
                return true;
            } else {
                if (!opts.silent) {
                    let msg = data.message || 'Save failed.';
                    if (data.errors) msg = Object.values(data.errors).flat().join(' ');
                    document.getElementById('editor-errors').textContent = msg;
                    document.getElementById('editor-errors').style.display = 'block';
                    showToast('Save failed', false);
                }
                return false;
            }
        } catch {
            if (!opts.silent) showToast('Network error', false);
            return false;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Image upload — toolbar button + drag-and-drop
    // ─────────────────────────────────────────────────────────────────────────────
    async function uploadImageFile(file) {
        if (!file || !file.type.startsWith('image/')) {
            showToast('Only image files are supported', false);
            return;
        }

        const overlay = document.getElementById('upload-overlay');
        const pctEl = document.getElementById('upload-pct');
        overlay.style.display = 'grid';
        pctEl.textContent = '';

        const formData = new FormData();
        formData.append('image', file);

        try {
            // Use XMLHttpRequest to get upload progress
            const url = await new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', `/api/${SITE}/images`);
                xhr.setRequestHeader('Authorization', `Bearer ${TOKEN()}`);
                xhr.setRequestHeader('Accept', 'application/json');

                xhr.upload.onprogress = e => {
                    if (e.lengthComputable) {
                        pctEl.textContent = `${Math.round(e.loaded / e.total * 100)}%`;
                    }
                };

                xhr.onload = () => {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        try {
                            const data = JSON.parse(xhr.responseText);
                            // ImageController returns data.data.image.url
                            const imgUrl = data?.data?.image?.url
                                || data?.image?.url
                                || data?.url;
                            if (imgUrl) resolve(imgUrl);
                            else reject(new Error('No URL in response'));
                        } catch {
                            reject(new Error('Invalid response'));
                        }
                    } else {
                        reject(new Error(`Upload failed: ${xhr.status}`));
                    }
                };
                xhr.onerror = () => reject(new Error('Network error'));
                xhr.send(formData);
            });

            // Insert image at current cursor position
            const range = quill.getSelection(true);
            quill.insertEmbed(range.index, 'image', url, 'user');
            quill.setSelection(range.index + 1);
            scheduleAutosave();
        } catch (err) {
            showToast(err.message || 'Image upload failed', false);
        } finally {
            overlay.style.display = 'none';
        }
    }

    // Toolbar file input
    document.getElementById('image-file-input').addEventListener('change', function () {
        if (this.files[0]) uploadImageFile(this.files[0]);
        this.value = '';
    });

    // Drag-and-drop onto the editor wrapper
    const quillWrap = document.getElementById('quill-wrap');

    quillWrap.addEventListener('dragover', e => {
        e.preventDefault();
        quillWrap.classList.add('drag-over');
    });
    quillWrap.addEventListener('dragleave', () => quillWrap.classList.remove('drag-over'));
    quillWrap.addEventListener('drop', e => {
        e.preventDefault();
        quillWrap.classList.remove('drag-over');
        const file = e.dataTransfer?.files?.[0];
        if (file) uploadImageFile(file);
    });

    // ─────────────────────────────────────────────────────────────────────────────
    // Auto-generate slug from title on blur
    // ─────────────────────────────────────────────────────────────────────────────
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

    // ─────────────────────────────────────────────────────────────────────────────
    // Paid toggle
    // ─────────────────────────────────────────────────────────────────────────────
    document.getElementById('is-paid').addEventListener('change', function () {
        document.getElementById('price-field').style.display = this.checked ? 'block' : 'none';
    });

    // ─────────────────────────────────────────────────────────────────────────────
    // Delete
    // ─────────────────────────────────────────────────────────────────────────────
    async function confirmDelete() {
        if (!PAGE_ID) return;
        if (!confirm('Delete this article permanently? This cannot be undone.')) return;

        const res = await fetch(`/api/${SITE}/open-collab/pages/${PAGE_ID}`, {
            method: 'DELETE',
            headers: {'Authorization': `Bearer ${TOKEN()}`, 'Accept': 'application/json'},
        });

        if (res.ok || res.status === 204) {
            window.location.href = '/articles';
        } else {
            showToast('Could not delete article. Please try again.', false);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Version history
    // ─────────────────────────────────────────────────────────────────────────────
    let historyLoaded = false;
    let previewingEntry = null; // { id, snapshot }
    let originalContent = null; // content before preview
    let originalTitle = null;

    function toggleHistory() {
        const body = document.getElementById('history-body');
        const chevron = document.getElementById('history-chevron');
        const open = body.style.display === 'none';
        body.style.display = open ? 'block' : 'none';
        chevron.style.transform = open ? 'rotate(180deg)' : '';

        if (open && !historyLoaded && PAGE_ID) loadHistory();
    }

    async function loadHistory() {
        const list = document.getElementById('history-list');
        list.innerHTML = '<div style="padding:16px;text-align:center;color:var(--slate);font-size:.82rem;">Loading…</div>';

        try {
            const res = await fetch(`/api/${SITE}/open-collab/pages/${PAGE_ID}/history`, {
                headers: {'Authorization': `Bearer ${TOKEN()}`},
            });
            const data = await res.json();
            const items = data?.data?.history ?? data?.history ?? [];

            console.log('items', data)

            if (!items.length) {
                list.innerHTML = '<div style="padding:16px;text-align:center;color:var(--slate);font-size:.82rem;">No version history yet.</div>';
                return;
            }

            list.innerHTML = '';
            items.forEach(entry => {
                const el = document.createElement('div');
                el.className = 'history-item';
                el.dataset.id = entry.id;
                el.innerHTML = `
                <div class="history-item__dot"></div>
                <div>
                    <div class="history-item__summary">${escHtml(entry.action_label ?? entry.action ?? 'Updated')}</div>
                    <div class="history-item__meta">
                        ${escHtml(entry.user_name ?? 'You')} · ${formatDate(entry.created_at)}
                    </div>
                    ${entry.change_summary ? `<div style="font-size:.7rem;color:var(--slate);margin-top:2px;">${escHtml(entry.change_summary)}</div>` : ''}
                </div>`;
                el.addEventListener('click', () => previewVersion(entry));
                list.appendChild(el);
            });

            historyLoaded = true;
        } catch {
            list.innerHTML = '<div style="padding:16px;text-align:center;color:var(--red);font-size:.82rem;">Failed to load history.</div>';
        }
    }

    function previewVersion(entry) {
        if (!entry.snapshot) {
            showToast('No snapshot available for this version', false);
            return;
        }

        // Highlight selected row
        document.querySelectorAll('.history-item').forEach(el => el.classList.remove('active'));
        document.querySelector(`.history-item[data-id="${entry.id}"]`)?.classList.add('active');

        // Stash current content on first preview
        if (!isRestoreMode) {
            originalContent = quill.root.innerHTML;
            originalTitle = document.getElementById('article-title').value;
        }

        isRestoreMode = true;
        previewingEntry = entry;

        // Load snapshot into editor (read-only visual)
        const snap = entry.snapshot;
        quill.root.innerHTML = snap.content ?? '';
        if (snap.title) document.getElementById('article-title').value = snap.title;

        // Show banner
        const banner = document.getElementById('restore-banner');
        document.getElementById('restore-banner-text').textContent =
            `Previewing version from ${formatDate(entry.created_at)}.`;
        banner.style.display = 'flex';
    }

    async function confirmRestore() {
        if (!previewingEntry || !PAGE_ID) return;

        const btn = document.getElementById('restore-confirm-btn');
        btn.disabled = true;
        btn.innerHTML = '<div class="oc-spinner"></div> Restoring…';

        try {
            const res = await fetch(`/api/${SITE}/open-collab/pages/${PAGE_ID}/history/${previewingEntry.id}/restore`, {
                method: 'POST',
                headers: {'Authorization': `Bearer ${TOKEN()}`, 'Accept': 'application/json'},
            });

            if (res.ok) {
                isDirty = false;
                showToast('✓ Version restored and saved');
                cancelRestore();
                historyLoaded = false; // force reload of history list
            } else {
                showToast('Restore failed. Please try again.', false);
            }
        } catch {
            showToast('Network error during restore.', false);
        } finally {
            btn.disabled = false;
            btn.textContent = 'Restore this version';
        }
    }

    function cancelRestore() {
        if (originalContent !== null) {
            quill.root.innerHTML = originalContent;
        }
        if (originalTitle !== null) {
            document.getElementById('article-title').value = originalTitle;
        }
        originalContent = null;
        originalTitle = null;
        isRestoreMode = false;
        previewingEntry = null;

        document.getElementById('restore-banner').style.display = 'none';
        document.querySelectorAll('.history-item').forEach(el => el.classList.remove('active'));
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Inline comments
    // ─────────────────────────────────────────────────────────────────────────────
    let commentsCache = [];

    <?php if ($isEdit): ?>
    (async function loadComments() {
        try {
            const res = await fetch(`/api/${SITE}/open-collab/pages/${PAGE_ID}/comments`, {
                headers: {'Authorization': `Bearer ${TOKEN()}`},
            });
            const data = await res.json();
            commentsCache = data?.data ?? [];
            renderComments(commentsCache);
        } catch { /* non-critical — fail silently */
        }
    })();
    <?php endif; ?>

    function renderComments(comments) {
        const body = document.getElementById('comments-body');
        const empty = document.getElementById('comments-empty');
        const badge = document.getElementById('comment-count-badge');

        const total = countAllComments(comments);

        badge.textContent = total;

        if (!comments.length) {
            empty.style.display = 'block';
            body.querySelectorAll('.comment-thread').forEach(el => el.remove());
            return;
        }

        empty.style.display = 'none';
        body.querySelectorAll('.comment-thread').forEach(el => el.remove());

        comments.forEach(comment => {
            body.insertBefore(buildCommentThread(comment), empty);
        });
    }

    function countAllComments(comments) {
        return comments.reduce((acc, c) => acc + 1 + (c.replies?.length ?? 0), 0);
    }

    function buildCommentThread(comment) {
        const wrap = document.createElement('div');
        wrap.className = 'comment-thread top-level';
        wrap.appendChild(buildCommentEl(comment, false));

        if (comment.replies?.length) {
            const repliesWrap = document.createElement('div');
            repliesWrap.className = 'replies';
            comment.replies.forEach(r => repliesWrap.appendChild(buildCommentEl(r, true)));
            wrap.appendChild(repliesWrap);
        }

        return wrap;
    }

    function buildCommentEl(comment, isReply) {
        const el = document.createElement('div');
        el.className = 'comment-item';
        el.dataset.commentId = comment.id;

        const currentUserId = <?= Auth::id() ?? 'null' ?>;
        const isOwn = comment.user_id === currentUserId;

        el.innerHTML = `
        <div class="comment-item__header">
            <span class="comment-item__author">${escHtml(comment.user_name ?? 'Unknown')}</span>
            <span class="comment-item__time">${formatDate(comment.created_at)}</span>
        </div>
        <div class="comment-item__body">${escHtml(comment.content)}</div>
        <div class="comment-item__actions">
            ${!isReply ? `<button class="comment-action-btn" onclick="showReplyForm(${comment.id}, this)">Reply</button>` : ''}
            ${isOwn ? `<button class="comment-action-btn" style="color:var(--red);" onclick="deleteComment(${comment.id})">Delete</button>` : ''}
        </div>
        ${!isReply ? `<div id="reply-form-${comment.id}" class="comment-form" style="display:none;"></div>` : ''}`;

        return el;
    }

    function showReplyForm(parentId, btn) {
        const formEl = document.getElementById(`reply-form-${parentId}`);
        if (!formEl) return;

        if (formEl.style.display !== 'none') {
            formEl.style.display = 'none';
            return;
        }

        formEl.innerHTML = `
        <textarea placeholder="Write a reply…"
                  style="width:100%;resize:none;min-height:56px;padding:8px 10px;
                         border:1.5px solid var(--border);border-radius:6px;
                         font-size:.82rem;font-family:var(--font-body);
                         color:var(--navy);background:#fff;box-sizing:border-box;"
                  id="reply-input-${parentId}"></textarea>
        <div class="comment-form__actions">
            <button class="oc-btn oc-btn--ghost oc-btn--sm"
                    onclick="document.getElementById('reply-form-${parentId}').style.display='none'">
                Cancel
            </button>
            <button class="oc-btn oc-btn--primary oc-btn--sm"
                    onclick="postReply(${parentId})">Post reply</button>
        </div>`;
        formEl.style.display = 'block';
        document.getElementById(`reply-input-${parentId}`)?.focus();
    }

    async function postComment() {
        const input = document.getElementById('new-comment-input');
        const content = input.value.trim();
        if (!content) return;

        try {
            const res = await fetch(`/api/${SITE}/open-collab/pages/${PAGE_ID}/comments`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${TOKEN()}`,
                },
                body: JSON.stringify({content}),
            });
            const data = await res.json();

            if (res.ok) {
                input.value = '';
                commentsCache.push(data.data.comment);
                renderComments(commentsCache);
            } else {
                showToast(data.message || 'Failed to post comment', false);
            }
        } catch {
            showToast('Network error', false);
        }
    }

    async function postReply(parentId) {
        const input = document.getElementById(`reply-input-${parentId}`);
        const content = input?.value.trim();
        if (!content) return;

        try {
            const res = await fetch(`/api/${SITE}/open-collab/pages/${PAGE_ID}/comments/${parentId}/reply`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${TOKEN()}`,
                },
                body: JSON.stringify({content}),
            });
            const data = await res.json();

            if (res.ok) {
                // Inject reply into cache
                const parent = commentsCache.find(c => c.id === parentId);
                if (parent) {
                    parent.replies = parent.replies ?? [];
                    parent.replies.push(data.data.comment);
                }
                renderComments(commentsCache);
            } else {
                showToast(data.message || 'Failed to post reply', false);
            }
        } catch {
            showToast('Network error', false);
        }
    }

    async function deleteComment(id) {
        if (!confirm('Delete this comment?')) return;

        try {
            const res = await fetch(`/api/${SITE}/open-collab/comments/${id}`, {
                method: 'DELETE',
                headers: {'Authorization': `Bearer ${TOKEN()}`},
            });

            if (res.ok) {
                // Remove from cache (top-level or reply)
                commentsCache = commentsCache
                    .map(c => ({...c, replies: (c.replies ?? []).filter(r => r.id !== id)}))
                    .filter(c => c.id !== id);
                renderComments(commentsCache);
            } else {
                showToast('Could not delete comment', false);
            }
        } catch {
            showToast('Network error', false);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Utilities
    // ─────────────────────────────────────────────────────────────────────────────
    function showToast(msg, ok = true) {
        const el = document.getElementById('save-status');
        el.textContent = msg;
        el.style.background = ok ? 'var(--navy)' : 'var(--red)';
        el.style.opacity = '1';
        setTimeout(() => {
            el.style.opacity = '0';
        }, 2500);
    }

    function escHtml(str) {
        if (str == null) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatDate(str) {
        if (!str) return '';
        const d = new Date(str);
        return d.toLocaleDateString('en-GB', {day: 'numeric', month: 'short', year: 'numeric'});
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // History API route wired through ArticlePageController
    // GET  /api/{site}/open-collab/pages/{id}/history
    // POST /api/{site}/open-collab/pages/{id}/history/{histId}/restore
    // These delegate to PageHistoryService which already exists in the CMS layer.
    // ─────────────────────────────────────────────────────────────────────────────
</script>
@endsection