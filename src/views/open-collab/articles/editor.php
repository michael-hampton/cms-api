@section('logic')
<?php
/**
 * Template: open-collab/articles/editor.php
 *
 * Block-based editor. Three default blocks (heading, text, image) are always
 * present and cannot be removed. Additional blocks of any type can be added.
 * Text blocks use individual Quill instances. SEO and Comments live in the
 * sidebar exactly as in the original template.
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
        grid-template-columns: 1fr 300px;
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

    /* ── Block builder ──────────────────────────────────────── */
    .block-builder {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .block-card {
        background: #fff;
        border: 1.5px solid var(--border);
        border-radius: var(--radius);
        overflow: hidden;
    }

    /* Default blocks: no visible header chrome (header hidden) */
    .block-card--default > .block-card__header {
        display: none;
    }

    /* Default blocks still get the card chrome so they look like the original elements */
    .block-card--default {
        border: none;
        background: transparent;
    }

    .block-card__header {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        background: var(--cream-dark);
        border-bottom: 1px solid var(--border);
        user-select: none;
    }

    .block-card__drag {
        cursor: grab;
        color: var(--slate-light);
        font-size: .85rem;
        line-height: 1;
        padding: 2px 4px;
    }

    .block-card__drag:active {
        cursor: grabbing;
    }

    .block-card__type-badge {
        font-size: .65rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: var(--slate);
        background: var(--slate-pale, #f0f2f5);
        border-radius: 4px;
        padding: 2px 7px;
    }

    .block-card__spacer {
        flex: 1;
    }

    .block-card__move-btn,
    .block-card__remove-btn {
        background: none;
        border: none;
        cursor: pointer;
        color: var(--slate-light);
        padding: 3px 5px;
        border-radius: 4px;
        font-size: .78rem;
        line-height: 1;
        transition: background .12s, color .12s;
    }

    .block-card__move-btn:hover {
        background: var(--border);
        color: var(--navy);
    }

    .block-card__remove-btn:hover {
        background: #fee2e2;
        color: var(--red);
    }

    .block-card__body {
        padding: 0;
    }

    /* ── Quill inside a block ───────────────────────────────── */
    .block-quill-wrap {
        background: #fff;
        border-radius: var(--radius);
        overflow: hidden;
    }

    /* For non-default blocks, add the card border */
    .block-card:not(.block-card--default) .block-quill-wrap {
        border-top: none; /* header provides top border already */
    }

    .block-card--default .block-quill-wrap {
        border: 1.5px solid var(--border);
        border-radius: var(--radius);
    }

    .block-quill-wrap .ql-toolbar {
        border: none;
        border-bottom: 1px solid var(--border);
        padding: 10px 12px;
        background: var(--cream-dark);
    }

    .block-quill-wrap .ql-container {
        border: none;
        font-family: var(--font-body);
        font-size: 1rem;
    }

    .block-quill-wrap .ql-editor {
        padding: 20px 24px;
        min-height: 220px;
        line-height: 1.8;
        color: var(--navy);
    }

    .block-quill-wrap .ql-editor p {
        margin-bottom: .75em;
    }

    .block-quill-wrap--compact .ql-editor {
        min-height: 120px;
    }

    .ql-snow .ql-picker-label {
        color: var(--slate);
    }

    /* ── Block body field styles ────────────────────────────── */
    .block-card__body-inner {
        padding: 14px 16px;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .block-card--default .block-card__body-inner {
        padding: 0;
    }

    .block-field-label {
        font-size: .72rem;
        font-weight: 600;
        color: var(--slate);
        text-transform: uppercase;
        letter-spacing: .06em;
        margin-bottom: 4px;
        display: block;
    }

    .block-field-row {
        display: flex;
        gap: 10px;
    }

    .block-field-row > * {
        flex: 1;
    }

    /* Image block drop zone */
    .block-image-drop {
        border: 2px dashed var(--border);
        border-radius: var(--radius);
        padding: 24px;
        text-align: center;
        cursor: pointer;
        background: var(--cream-dark);
        transition: border-color .15s, background .15s;
    }

    .block-image-drop:hover,
    .block-image-drop.drag-over {
        border-color: var(--amber);
        background: rgba(245, 158, 11, .05);
    }

    .block-image-drop__hint {
        font-size: .78rem;
        color: var(--slate);
    }

    .block-image-preview {
        position: relative;
        display: inline-block;
    }

    .block-image-preview img {
        max-height: 160px;
        border-radius: 6px;
        border: 1.5px solid var(--border);
        display: block;
    }

    .block-image-preview__remove {
        position: absolute;
        top: -6px;
        right: -6px;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: var(--red);
        color: #fff;
        border: none;
        cursor: pointer;
        font-size: .7rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* Default image block — styled like the original image-upload-zone */
    .block-card--default .block-image-wrap {
        border: 2px dashed var(--border);
        border-radius: var(--radius);
        background: var(--cream-dark);
        padding: 20px 24px;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .block-card--default .block-image-wrap.drag-over {
        border-color: var(--amber);
        background: rgba(245, 158, 11, .06);
    }

    .block-image-wrap__header {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .block-image-wrap__title {
        font-size: .8rem;
        font-weight: 700;
        color: var(--navy);
        text-transform: uppercase;
        letter-spacing: .06em;
    }

    .block-image-wrap__hint {
        font-size: .72rem;
        color: var(--slate);
    }

    .block-image-thumbs {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        min-height: 36px;
    }

    .block-image-thumb {
        position: relative;
        width: 64px;
        height: 64px;
        border-radius: 6px;
        overflow: hidden;
        border: 1.5px solid var(--border);
        background: #f0f0f2;
        flex-shrink: 0;
    }

    .block-image-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .block-image-thumb__remove {
        position: absolute;
        top: 2px;
        right: 2px;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background: rgba(0, 0, 0, .55);
        color: #fff;
        font-size: .65rem;
        border: none;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        line-height: 1;
    }

    .block-image-empty {
        font-size: .75rem;
        color: var(--slate);
        text-align: center;
        padding: 4px 0;
    }

    /* List block */
    .list-items-wrap {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .list-item-row {
        display: flex;
        gap: 6px;
        align-items: flex-start;
    }

    .list-item-row textarea {
        flex: 1;
        min-height: 36px;
        resize: none;
    }

    .list-item-remove {
        background: none;
        border: none;
        cursor: pointer;
        color: var(--slate-light);
        font-size: .85rem;
        padding: 4px;
        border-radius: 4px;
        flex-shrink: 0;
        margin-top: 2px;
    }

    .list-item-remove:hover {
        background: #fee2e2;
        color: var(--red);
    }

    /* ── Shared input reset inside block bodies ─────────────── */
    .block-card__body-inner input[type="text"],
    .block-card__body-inner input[type="number"],
    .block-card__body-inner select,
    .block-card__body-inner textarea {
        width: 100%;
        box-sizing: border-box;
        border: 1.5px solid var(--border);
        border-radius: var(--radius);
        padding: 8px 12px;
        font-size: .88rem;
        font-family: var(--font-body);
        color: var(--navy);
        background: #fff;
        outline: none;
    }

    .block-card__body-inner textarea {
        resize: vertical;
    }

    .block-card__body-inner input[type="text"]:focus,
    .block-card__body-inner input[type="number"]:focus,
    .block-card__body-inner select:focus,
    .block-card__body-inner textarea:focus {
        border-color: var(--amber);
    }

    /* ── Add block toolbar ──────────────────────────────────── */
    .add-block-row {
        display: flex;
        align-items: center;
        gap: 0;
        border: 1.5px dashed var(--border);
        border-radius: var(--radius);
        overflow: hidden;
        background: var(--cream-dark);
    }

    .add-block-row__label {
        font-size: .72rem;
        font-weight: 600;
        color: var(--slate);
        text-transform: uppercase;
        letter-spacing: .07em;
        padding: 0 14px;
        white-space: nowrap;
    }

    .add-block-type-btn {
        background: none;
        border: none;
        border-left: 1px solid var(--border);
        cursor: pointer;
        padding: 9px 13px;
        font-size: .75rem;
        font-weight: 600;
        color: var(--navy);
        transition: background .12s;
        white-space: nowrap;
    }

    .add-block-type-btn:hover {
        background: #fff;
        color: var(--amber-dark, #b45309);
    }

    /* Preview button in add-block-row */
    .add-block-preview-btn {
        background: none;
        border: none;
        border-left: 1px solid var(--border);
        cursor: pointer;
        padding: 9px 13px;
        font-size: .75rem;
        font-weight: 600;
        color: var(--slate);
        transition: background .12s;
        white-space: nowrap;
        margin-left: auto;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .add-block-preview-btn:hover {
        background: #fff;
        color: var(--navy);
    }

    /* ── Drag state ─────────────────────────────────────────── */
    .block-card.dragging {
        opacity: .4;
    }

    .block-card.drag-over-top {
        border-top: 2.5px solid var(--amber);
    }

    .block-card.drag-over-bottom {
        border-bottom: 2.5px solid var(--amber);
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

    /* ── Version diff modal ─────────────────────────────────── */
    #diff-modal {
        position: fixed;
        inset: 0;
        z-index: 500;
        background: rgba(15, 25, 41, .65);
        display: none;
        place-items: center;
        padding: 20px;
    }

    #diff-modal.open {
        display: grid;
    }

    .diff-modal-panel {
        background: #fff;
        border-radius: 12px;
        width: 100%;
        max-width: 1100px;
        max-height: 90vh;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        box-shadow: 0 20px 60px rgba(0, 0, 0, .3);
    }

    .diff-modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 20px;
        border-bottom: 1px solid var(--border);
    }

    .diff-modal-header h3 {
        font-size: .95rem;
        font-weight: 700;
        color: var(--navy);
        margin: 0;
    }

    .diff-modal-body {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0;
        flex: 1;
        overflow: hidden;
    }

    .diff-col {
        overflow-y: auto;
        padding: 20px;
        border-right: 1px solid var(--border);
    }

    .diff-col:last-child {
        border-right: none;
    }

    .diff-col__title {
        font-size: .68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: var(--slate);
        margin-bottom: 12px;
    }

    .diff-col__content {
        font-size: .85rem;
        line-height: 1.7;
        color: var(--navy);
    }

    .diff-col__content h2 {
        font-size: 1.1rem;
        margin: .5em 0 .25em;
    }

    .diff-col__content h3 {
        font-size: .95rem;
        margin: .5em 0 .2em;
    }

    .diff-modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        padding: 14px 20px;
        border-top: 1px solid var(--border);
    }

    /* ── Restore banner ─────────────────────────────────────── */
    #restore-banner {
        background: var(--amber-pale, #fffbeb);
        border: 1.5px solid var(--amber-dark, #b45309);
        border-radius: var(--radius);
        padding: 12px 16px;
        display: none;
        align-items: center;
        gap: 12px;
        font-size: .82rem;
        color: var(--amber-dark, #b45309);
    }

    /* ── Comment styles ─────────────────────────────────────── */
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

    /* ── Heading block (default, styled like original title card) */
    .block-heading-wrap {
        background: #fff;
        border: 1.5px solid var(--border);
        border-radius: var(--radius);
        padding: 20px 24px 0;
    }

    .block-heading-wrap input.heading-title-input {
        border: none;
        outline: none;
        width: 100%;
        font-family: var(--font-display);
        font-size: 1.6rem;
        font-weight: 700;
        color: var(--navy);
        padding: 0 0 16px;
        background: transparent;
        box-sizing: border-box;
    }

    .block-heading-wrap input.heading-subtitle-input {
        border: none;
        outline: none;
        width: 100%;
        font-family: var(--font-body);
        font-size: 1rem;
        font-weight: 400;
        color: var(--slate);
        padding: 0 0 14px;
        background: transparent;
        box-sizing: border-box;
    }

    /* Non-default heading block */
    .block-heading-inner {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    /* ── Preview modal ──────────────────────────────────────── */
    #preview-modal {
        position: fixed;
        inset: 0;
        z-index: 600;
        background: rgba(15, 25, 41, .7);
        display: none;
        place-items: center;
        padding: 20px;
    }

    #preview-modal.open {
        display: grid;
    }

    .preview-modal-panel {
        background: #fff;
        border-radius: 12px;
        width: 100%;
        max-width: 760px;
        max-height: 90vh;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        box-shadow: 0 20px 60px rgba(0, 0, 0, .35);
    }

    .preview-modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 20px;
        border-bottom: 1px solid var(--border);
        flex-shrink: 0;
    }

    .preview-modal-header h3 {
        font-size: .9rem;
        font-weight: 700;
        color: var(--navy);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .preview-modal-body {
        overflow-y: auto;
        padding: 32px 40px;
        flex: 1;
    }

    /* Article preview typography */
    .preview-content {
        font-family: var(--font-body);
        color: var(--navy);
        line-height: 1.8;
    }

    .preview-content .preview-title {
        font-family: var(--font-display);
        font-size: 2rem;
        font-weight: 700;
        color: var(--navy);
        margin: 0 0 8px;
        line-height: 1.25;
    }

    .preview-content .preview-subtitle {
        font-size: 1.1rem;
        color: var(--slate);
        margin: 0 0 28px;
        font-weight: 400;
    }

    .preview-content h2 {
        font-size: 1.35rem;
        font-weight: 700;
        margin: 1.8em 0 .5em;
        color: var(--navy);
    }

    .preview-content h3 {
        font-size: 1.1rem;
        font-weight: 700;
        margin: 1.4em 0 .4em;
        color: var(--navy);
    }

    .preview-content p {
        margin: 0 0 .9em;
    }

    .preview-content blockquote {
        border-left: 3px solid var(--amber);
        margin: 1.2em 0;
        padding: 10px 18px;
        background: var(--cream-dark);
        border-radius: 0 var(--radius) var(--radius) 0;
        font-style: italic;
    }

    .preview-content .preview-quote-attr {
        font-style: normal;
        font-size: .8rem;
        color: var(--slate);
        margin-top: 6px;
    }

    .preview-content .preview-info-block {
        border-radius: var(--radius);
        padding: 12px 16px;
        margin: 1em 0;
        font-size: .9rem;
    }

    .preview-content .preview-info-block--disclaimer {
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        color: #1e40af;
    }

    .preview-content .preview-info-block--tip {
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        color: #166534;
    }

    .preview-content .preview-info-block--warning {
        background: #fffbeb;
        border: 1px solid #fde68a;
        color: #92400e;
    }

    .preview-content .preview-info-block--note {
        background: #faf5ff;
        border: 1px solid #e9d5ff;
        color: #6b21a8;
    }

    .preview-content .preview-section-heading {
        border-bottom: 2px solid var(--border);
        padding-bottom: 8px;
        margin: 2em 0 1em;
    }

    .preview-content .preview-image-block {
        margin: 1.5em 0;
        text-align: center;
    }

    .preview-content .preview-image-block img {
        max-width: 100%;
        border-radius: var(--radius);
        border: 1px solid var(--border);
    }

    .preview-content .preview-image-block figcaption {
        font-size: .78rem;
        color: var(--slate);
        margin-top: 6px;
    }

    .preview-content ul,
    .preview-content ol {
        padding-left: 1.4em;
        margin: .8em 0;
    }

    .preview-content li {
        margin-bottom: .35em;
    }

    .block-image-selected {
        display: inline-flex;
        flex-direction: column;
        align-items: flex-start;
    }

    .block-image-rights {
        margin-top: 6px;
        font-size: .72rem;
        color: var(--slate);
    }
</style>

<!-- Toast -->
<div id="save-status"
     style="position:fixed;bottom:24px;left:50%;transform:translateX(-50%);
            background:var(--navy);color:#fff;padding:8px 18px;border-radius:20px;
            font-size:.8rem;font-weight:500;opacity:0;transition:opacity .3s;
            z-index:300;pointer-events:none;"></div>

<!-- Upload overlay -->
<div id="upload-overlay">
    <div class="upload-card">
        <div class="oc-spinner" style="margin:0 auto 12px;width:28px;height:28px;border-width:3px;"></div>
        <div style="font-weight:600;color:var(--navy);">Uploading image…</div>
        <div id="upload-pct" style="font-size:.8rem;color:var(--slate);margin-top:4px;"></div>
    </div>
</div>

<!-- Restore banner -->
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

<!-- Version diff modal -->
<div id="diff-modal" role="dialog" aria-modal="true" aria-label="Version comparison">
    <div class="diff-modal-panel">
        <div class="diff-modal-header">
            <h3 id="diff-modal-title">Comparing versions</h3>
            <button onclick="closeDiffModal()" class="oc-btn oc-btn--ghost oc-btn--sm" aria-label="Close">✕</button>
        </div>
        <div class="diff-modal-body">
            <div class="diff-col" id="diff-old">
                <div class="diff-col__title">Previous version</div>
                <div class="diff-col__content" id="diff-old-content"></div>
            </div>
            <div class="diff-col" id="diff-new">
                <div class="diff-col__title">Current version</div>
                <div class="diff-col__content" id="diff-new-content"></div>
            </div>
        </div>
        <div class="diff-modal-footer">
            <button onclick="closeDiffModal()" class="oc-btn oc-btn--ghost">Close</button>
            <button onclick="restoreFromDiff()" class="oc-btn oc-btn--amber" id="diff-restore-btn">Restore this
                version
            </button>
        </div>
    </div>
</div>

<!-- Preview modal (FIX 4) -->
<div id="preview-modal" role="dialog" aria-modal="true" aria-label="Article preview">
    <div class="preview-modal-panel">
        <div class="preview-modal-header">
            <h3>
                <svg viewBox="0 0 20 20" fill="currentColor" width="16" style="color:var(--slate);">
                    <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                    <path fill-rule="evenodd"
                          d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z"
                          clip-rule="evenodd"/>
                </svg>
                Article preview
            </h3>
            <button onclick="closePreview()" class="oc-btn oc-btn--ghost oc-btn--sm" aria-label="Close">✕</button>
        </div>
        <div class="preview-modal-body">
            <div class="preview-content" id="preview-content"></div>
        </div>
    </div>
</div>

<!-- Validation errors -->
<div id="editor-errors" class="oc-form-errors" style="display:none;margin-bottom:16px;" role="alert"></div>

<!-- Hidden file input for block image uploads -->
<input type="file" id="block-image-file-input" accept="image/*" style="display:none;">
<!-- Hidden file input for default image block uploads (multiple) -->
<input type="file" id="default-image-file-input" accept="image/*" multiple style="display:none;">

<div class="editor-shell">

    <!-- ── Main column ─────────────────────────────────────────── -->
    <div class="editor-main">

        <!-- Block builder — JS populates this -->
        <div id="block-builder" class="block-builder"></div>

        <!-- Add block toolbar -->
        <div class="add-block-row">
            <span class="add-block-row__label">+ Add block</span>
            <button type="button" class="add-block-type-btn" onclick="addBlock('text')">Text</button>
            <button type="button" class="add-block-type-btn" onclick="addBlock('heading')">Heading</button>
            <button type="button" class="add-block-type-btn" onclick="addBlock('section')">Section</button>
            <button type="button" class="add-block-type-btn" onclick="addBlock('quote')">Quote</button>
            <button type="button" class="add-block-type-btn" onclick="addBlock('info')">Info</button>
            <button type="button" class="add-block-type-btn" onclick="addBlock('list')">List</button>
            <button type="button" class="add-block-type-btn" onclick="addBlock('image')">Image</button>
            <button type="button" class="add-block-preview-btn" onclick="openPreview()">
                <svg viewBox="0 0 20 20" fill="currentColor" width="13">
                    <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                    <path fill-rule="evenodd"
                          d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z"
                          clip-rule="evenodd"/>
                </svg>
                Preview
            </button>
        </div>

        <!-- Readability score -->
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

    <!-- ── Sidebar ──────────────────────────────────────────────── -->
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

        <!-- Monetisation -->
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

        <!-- Publish options -->
        <div class="oc-card">
            <div class="oc-card__header">
                <span class="oc-card__title" style="font-size:.95rem;">Publish options</span>
            </div>
            <div class="oc-card__body">
                <div class="oc-form-group" style="margin-bottom:12px;">
                    <label class="oc-label" for="article-visibility">Visibility</label>
                    <select class="oc-input" id="article-visibility">
                        <option value="public" <?= ($page?->visibility ?? 'public') === 'public' ? 'selected' : '' ?>>
                            Public
                        </option>
                        <option value="premium" <?= ($page?->visibility ?? '') === 'premium' ? 'selected' : '' ?>>
                            Premium
                        </option>
                        <option value="members" <?= ($page?->visibility ?? '') === 'members' ? 'selected' : '' ?>>
                            Members only
                        </option>
                        <option value="private" <?= ($page?->visibility ?? '') === 'private' ? 'selected' : '' ?>>
                            Private
                        </option>
                    </select>
                    <div class="oc-help" id="visibility-paid-note"
                         style="display:<?= ($page?->is_paid) ? 'block' : 'none' ?>;color:var(--amber-dark,#b45309);">
                        Paid articles must use Premium visibility.
                    </div>
                </div>
                <label class="oc-label oc-label--optional" for="scheduled-at">Schedule for later</label>
                <input class="oc-input" type="datetime-local" id="scheduled-at"
                       value="<?= $page && $page->scheduled_at ? date('Y-m-d\TH:i', strtotime($page->scheduled_at)) : '' ?>"
                       min="<?= date('Y-m-d\TH:i', strtotime('+5 minutes')) ?>">
                <div class="oc-help">Leave blank to publish immediately.</div>
            </div>
            <button onclick="saveArticle('scheduled')"
                    class="oc-btn oc-btn--ghost oc-btn--block oc-btn--sm" id="schedule-btn">
                <svg viewBox="0 0 20 20" fill="currentColor" width="14">
                    <path fill-rule="evenodd"
                          d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"
                          clip-rule="evenodd"/>
                </svg>
                <?= ($page && $page->status === 'scheduled') ? 'Update schedule' : 'Schedule' ?>
            </button>
        </div>

        <!-- SEO -->
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

            <!-- Version history -->
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

            <!-- Comments -->
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
                         id="comments-empty">No comments yet.
                    </div>
                </div>
                <div style="padding:12px 16px;border-top:1px solid var(--border);">
                    <textarea id="new-comment-input" placeholder="Add a comment…"
                              style="width:100%;resize:none;min-height:64px;padding:8px 10px;
                                     border:1.5px solid var(--border);border-radius:6px;
                                     font-size:.82rem;font-family:var(--font-body);
                                     color:var(--navy);background:#fff;box-sizing:border-box;"
                              onkeydown="if(event.ctrlKey&&event.key==='Enter'){postComment();}"></textarea>
                    <div style="display:flex;justify-content:flex-end;margin-top:6px;">
                        <button onclick="postComment()" class="oc-btn oc-btn--primary oc-btn--sm">
                            Post <span style="font-size:.7rem;color:rgba(255,255,255,.6);margin-left:4px;">Ctrl+↵</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Danger zone -->
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

@include('open-collab/articles/partials/image-library-modal')

@endsection

@section('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.min.js"></script>
<script>
    // =============================================================================
    // Constants
    // =============================================================================
    const SITE = '<?= htmlspecialchars($site ?? '') ?>';
    const PAGE_ID = <?= $isEdit ? (int)$page->id : 'null' ?>;
    const SITE_ID = <?= (int)($siteId ?? 1) ?>;
    const TOKEN = () => localStorage.getItem('oc_token') || '';

    // =============================================================================
    // Block state
    // =============================================================================
    /**
     * Default block IDs — these three blocks are always present and cannot be
     * removed. They are always first in the list, in order: heading, text, image.
     */
    const DEFAULT_BLOCK_IDS = {heading: '__default_heading__', text: '__default_text__', image: '__default_image__'};

    const IMAGE_RIGHTS_LABELS = {
        contributor_owned:    'Contributor-owned',
        staff_owned:          'Staff-owned',
        third_party_licensed: 'Licensed third-party',
        agency:                'Agency',
        editorial_use_only:   'Editorial use only',
        royalty_free:         'Royalty Free',
        public_domain:        'Public Domain',
        creative_commons:     'Creative Commons',
        all_rights_reserved:  'All Rights Reserved',
        attribution_required: 'Attribution Required',
        custom_license:       'Custom License',
        unknown:              'Rights not confirmed',
    };

    function getImageRightsLabel(rights) {
        return IMAGE_RIGHTS_LABELS[rights] ?? rights ?? 'Unknown';
    }

    function imageBlockDefaults() {
        return {
            cms_image_id: null,
            image_url: '',
            thumbnail_url: '',
            name: '',
            alt: '',
            credit: '',
            image_rights: '',
            source_alt: '',
            source_credit: '',
            caption: '',
            layout: 'full',
            alignment: 'center',
        };
    }

    function normaliseImageBlock(block) {
        return {
            ...imageBlockDefaults(),
            ...block,

            // Backwards compatibility for older saved/frontend block shapes.
            cms_image_id: block.cms_image_id ?? block.cmsImageId ?? block.imageId ?? null,
            image_url: block.image_url ?? block.src ?? '',
            thumbnail_url: block.thumbnail_url ?? '',
            image_rights: block.image_rights ?? block.imageRights ?? '',
            source_alt: block.source_alt ?? block.alt ?? '',
            source_credit: block.source_credit ?? block.credit ?? '',
        };
    }

    function getImageLibrary() {
        if (!window.imageLibrary) {
            showToast('Image library is not available yet.', false);
            return null;
        }

        window.imageLibrary.onSelect = function (blockId, image) {
            applyLibraryImageToBlock(blockId, image);
        };

        return window.imageLibrary;
    }

    function openImageLibraryForBlock(blockId) {
        const block = blocks.find(candidate => candidate.id === blockId);
        const imageLibrary = getImageLibrary();

        if (!block || !imageLibrary) {
            return;
        }

        imageLibrary.open(blockId, block.cms_image_id ?? null);
    }

    function registerImageLibrarySelectionHandler() {
        if (!window.imageLibrary) {
            return;
        }

        window.imageLibrary.onSelect = function (blockId, image) {
            applyLibraryImageToBlock(blockId, image);
        };
    }

    document.addEventListener('DOMContentLoaded', registerImageLibrarySelectionHandler);

    function applyLibraryImageToBlock(blockId, image) {
        const block = blocks.find(candidate => candidate.id === blockId);

        if (!block || block.type !== 'image') {
            return;
        }

        const imageUrl = image.preview_url ?? image.url ?? image.thumbnail_url ?? '';
        const thumbnailUrl = image.thumbnail_url ?? image.preview_url ?? image.url ?? '';
        const sourceAlt = image.alt_text ?? image.alt ?? '';
        const sourceCredit = image.credit ?? '';

        // The default image block is the legacy multi-image drop area.
        // It does not expose editable metadata fields, so selected library images are
        // added to the thumbnail collection instead of replacing the whole block UI.
        if (block.isDefault) {
            const itemId = String(image.id ?? uid());
            const alreadyAdded = defaultImageItems.some(item => String(item.id) === itemId);

            if (!alreadyAdded) {
                defaultImageItems.push({
                    id: itemId,
                    cms_image_id: image.id ?? null,
                    url: imageUrl,
                    thumbnail_url: thumbnailUrl,
                    name: image.name ?? '',
                    alt: sourceAlt,
                    credit: sourceCredit,
                    image_rights: image.image_rights ?? '',
                });
            }

            if (!block.cms_image_id) {
                block.cms_image_id = image.id ?? null;
                block.image_url = imageUrl;
                block.thumbnail_url = thumbnailUrl;
                block.name = image.name ?? '';
                block.alt = sourceAlt;
                block.credit = sourceCredit;
                block.image_rights = image.image_rights ?? '';
                block.source_alt = sourceAlt;
                block.source_credit = sourceCredit;
            }

            renderBlocks();
            scheduleAutosave();
            showToast(alreadyAdded ? 'Image already added' : 'Image added');
            return;
        }

        const previousImageId = block.cms_image_id ?? null;
        const nextImageId = image.id ?? null;

        const isSameImage =
            previousImageId !== null &&
            nextImageId !== null &&
            String(previousImageId) === String(nextImageId);

        block.cms_image_id = nextImageId;
        block.image_url = imageUrl;
        block.thumbnail_url = thumbnailUrl;
        block.name = image.name ?? '';
        block.image_rights = image.image_rights ?? '';

        // Keep the CMS metadata snapshot used by the explicit reset action.
        // This deliberately avoids refetching CMS metadata when editing an existing article.
        block.source_alt = sourceAlt;
        block.source_credit = sourceCredit;

        // Ticket 8 overwrite rules:
        // Empty/different image = use CMS metadata.
        // Same image = preserve contributor-edited alt/credit.
        if (!isSameImage) {
            block.alt = sourceAlt;
            block.credit = sourceCredit;
        }

        renderBlocks();
        scheduleAutosave();
        showToast(isSameImage ? 'Image re-selected' : 'Image selected');
    }

    function resetImageBlockMetadata(blockId) {
        const block = blocks.find(candidate => candidate.id === blockId);

        if (!block || block.type !== 'image') {
            return;
        }

        block.alt = block.source_alt ?? '';
        block.credit = block.source_credit ?? '';

        renderBlocks();
        scheduleAutosave();
        showToast('Image metadata reset');
    }

    /**
     * blocks[] — each entry:
     *   { id, type, isDefault, order, ...typeFields }
     *
     * Types: heading | section | text | quote | info | list | image
     */
    let blocks = [];

    // Quill instances keyed by block id.
    // These are cleared and remounted on every renderBlocks() call.
    const quillInstances = {};

    // Default image block uploaded images: [{ id, url, alt }]
    let defaultImageItems = [];

    // =============================================================================
    // Initialise block state
    // =============================================================================
    (function initBlocks() {
        <?php if ($isEdit && !empty($page->blocks)): ?>
        const raw = <?= json_encode($page->blocks) ?>;
        if (Array.isArray(raw) && raw.length) {
            raw.forEach((b, i) => {
                const block = {...b, id: b.id ?? uid(), order: b.order ?? i, isDefault: false};
                blocks.push(block.type === 'image' ? normaliseImageBlock(block) : block);
            });
            // Ensure we always have the three default blocks at positions 0-2
            ensureDefaultBlocks();
            reorder();
            return;
        }
        <?php endif; ?>
        // Fresh article — seed with three default blocks
        blocks = [
            {
                id: DEFAULT_BLOCK_IDS.heading, type: 'heading', isDefault: true, order: 0,
                level: 2, text: '<?= addslashes(htmlspecialchars($page->title ?? '')) ?>', subtitle: ''
            },
            {
                id: DEFAULT_BLOCK_IDS.text, type: 'text', isDefault: true, order: 1,
                content: '<?= addslashes($page->content ?? '') ?>'
            },
            {
                id: DEFAULT_BLOCK_IDS.image,
                type: 'image',
                isDefault: true,
                order: 2,
                ...imageBlockDefaults(),
            },
        ];
    })();

    function ensureDefaultBlocks() {
        const hasDefault = (type) => blocks.some(b => b.id === DEFAULT_BLOCK_IDS[type]);
        const defaultDefs = [
            {
                id: DEFAULT_BLOCK_IDS.heading, type: 'heading', isDefault: true, order: -3,
                level: 2, text: '', subtitle: ''
            },
            {
                id: DEFAULT_BLOCK_IDS.text, type: 'text', isDefault: true, order: -2,
                content: ''
            },
            {
                id: DEFAULT_BLOCK_IDS.image,
                type: 'image',
                isDefault: true,
                order: -1,
                ...imageBlockDefaults(),
            },
        ];
        defaultDefs.forEach(def => {
            if (!hasDefault(def.type)) blocks.unshift(def);
        });
        // Ensure defaults are pinned to the front
        const defaults = blocks.filter(b => b.isDefault);
        const extras = blocks.filter(b => !b.isDefault);
        // Sort defaults by canonical order
        const order = [DEFAULT_BLOCK_IDS.heading, DEFAULT_BLOCK_IDS.text, DEFAULT_BLOCK_IDS.image];
        defaults.sort((a, b) => order.indexOf(a.id) - order.indexOf(b.id));
        blocks = [...defaults, ...extras];
    }

    function uid() {
        return Math.random().toString(36).slice(2, 10);
    }

    function blockDefaults(type) {
        switch (type) {
            case 'text':
                return {content: ''};
            case 'heading':
                return {level: 2, text: '', subtitle: ''};
            case 'section':
                return {level: 2, title: ''};
            case 'quote':
                return {text: '', attribution: ''};
            case 'info':
                return {infoType: 'disclaimer', description: ''};
            case 'list':
                return {listType: 'ul', items: ['']};
            case 'image':
                return imageBlockDefaults();
            default:
                return {};
        }
    }

    function addBlock(type, afterId = null) {
        const block = {id: uid(), type, isDefault: false, order: 0, ...blockDefaults(type)};
        if (afterId) {
            const idx = blocks.findIndex(b => b.id === afterId);
            blocks.splice(idx + 1, 0, block);
        } else {
            blocks.push(block);
        }
        reorder();
        renderBlocks();
        scheduleAutosave();
        setTimeout(() => {
            const card = document.querySelector(`[data-block-id="${block.id}"]`);
            card?.querySelector('textarea, input, .ql-editor')?.focus();
        }, 80);
    }

    function removeBlock(id) {
        const b = blocks.find(b => b.id === id);
        if (!b || b.isDefault) return; // default blocks cannot be removed
        blocks = blocks.filter(b => b.id !== id);
        reorder();
        renderBlocks();
        scheduleAutosave();
    }

    function moveBlock(id, dir) {
        const b = blocks.find(b => b.id === id);
        if (!b || b.isDefault) return; // cannot move default blocks
        const nonDefaultStart = blocks.findIndex(b => !b.isDefault);
        const idx = blocks.findIndex(b => b.id === id);
        const swap = dir === 'up' ? idx - 1 : idx + 1;
        if (swap < nonDefaultStart || swap >= blocks.length) return;
        [blocks[idx], blocks[swap]] = [blocks[swap], blocks[idx]];
        reorder();
        renderBlocks();
        scheduleAutosave();
    }

    function reorder() {
        blocks.forEach((b, i) => {
            b.order = i;
        });
    }

    function patchBlock(id, field, value) {
        const b = blocks.find(b => b.id === id);
        if (!b) return;
        if (field.includes('.')) {
            const [arr, idx] = field.split('.');
            b[arr][parseInt(idx)] = value;
        } else {
            b[field] = value;
        }
        scheduleAutosave();
    }

    function addListItem(blockId) {
        const b = blocks.find(b => b.id === blockId);
        if (!b) return;
        b.items.push('');
        renderBlocks();
        scheduleAutosave();
        setTimeout(() => {
            const card = document.querySelector(`[data-block-id="${blockId}"]`);
            const inputs = card?.querySelectorAll('.list-item-row textarea');
            inputs?.[inputs.length - 1]?.focus();
        }, 30);
    }

    function removeListItem(blockId, idx) {
        const b = blocks.find(b => b.id === blockId);
        if (!b || b.items.length <= 1) return;
        b.items.splice(idx, 1);
        renderBlocks();
        scheduleAutosave();
    }

    // =============================================================================
    // Rendering
    // =============================================================================
    // In renderBlocks(), after initDragDrop():

    function renderBlocks() {
        const container = document.getElementById('block-builder');

        Object.keys(quillInstances).forEach(id => {
            const q = quillInstances[id];
            if (!q) return;
            const b = blocks.find(b => b.id === id);
            if (b) b.content = q.root.innerHTML;
            delete quillInstances[id];
        });

        container.innerHTML = '';
        blocks.forEach(b => container.appendChild(buildBlockCard(b)));

        blocks.forEach(b => {
            if (b.type === 'text') initQuillForBlock(b);
        });

        initDragDrop();

        // Re-populate default image thumbs — renderBlocks() replaces the entire DOM
        // so the #default-image-thumbs container is always fresh after this call.
        // Without this, uploaded images disappear whenever any block is added/removed.
        renderDefaultImageThumbs();
    }

    function buildBlockCard(b) {
        const card = document.createElement('div');
        card.className = 'block-card' + (b.isDefault ? ' block-card--default' : '');
        if (!b.isDefault) card.draggable = true;
        card.dataset.blockId = b.id;

        const labels = {
            text: 'Text', heading: 'Heading', section: 'Section',
            quote: 'Quote', info: 'Info', list: 'List', image: 'Image',
        };

        // Header (only for non-default blocks)
        if (!b.isDefault) {
            card.innerHTML = `
        <div class="block-card__header">
            <span class="block-card__drag" title="Drag to reorder">⠿</span>
            <span class="block-card__type-badge">${labels[b.type] ?? b.type}</span>
            <span class="block-card__spacer"></span>
            <button type="button" class="block-card__move-btn" title="Move up"   onclick="moveBlock('${b.id}','up')">↑</button>
            <button type="button" class="block-card__move-btn" title="Move down" onclick="moveBlock('${b.id}','down')">↓</button>
            <button type="button" class="block-card__remove-btn" title="Remove"  onclick="removeBlock('${b.id}')">✕</button>
        </div>`;
        }

        const body = document.createElement('div');
        body.className = 'block-card__body';
        body.innerHTML = blockBodyHTML(b);
        card.appendChild(body);
        return card;
    }

    function blockBodyHTML(b) {
        const id = b.id;

        switch (b.type) {
            // ── Heading ──────────────────────────────────────────────────
            case 'heading': {
                if (b.isDefault) {
                    // Styled like the original title card
                    return `<div class="block-heading-wrap">
                    <input type="text" class="heading-title-input"
                           id="article-title"
                           placeholder="Article title…"
                           value="${escAttr(b.text ?? '')}"
                           autocomplete="off"
                           oninput="patchBlock('${id}','text',this.value);autoSlug(this.value);"
                           onblur="autoSlug(this.value)">
                </div>`;
                }
                return `<div class="block-card__body-inner block-heading-inner">
                <div>
                    <label class="block-field-label">Level</label>
                    <select oninput="patchBlock('${id}','level',parseInt(this.value))">
                        <option value="2" ${b.level == 2 ? 'selected' : ''}>H2 — Heading</option>
                        <option value="3" ${b.level == 3 ? 'selected' : ''}>H3 — Sub-heading</option>
                    </select>
                </div>
                <div>
                    <label class="block-field-label">Heading text</label>
                    <textarea rows="2" placeholder="Heading…"
                        oninput="patchBlock('${id}','text',this.value)">${escHtml(b.text ?? '')}</textarea>
                </div>
                <div>
                    <label class="block-field-label">Subtitle <span style="font-weight:400;color:var(--slate-light);">(optional)</span></label>
                    <textarea rows="2" placeholder="Sub-heading or intro line…"
                        oninput="patchBlock('${id}','subtitle',this.value)">${escHtml(b.subtitle ?? '')}</textarea>
                </div>
            </div>`;
            }

            // ── Section ──────────────────────────────────────────────────
            case 'section': {
                return `<div class="block-card__body-inner">
                <div>
                    <label class="block-field-label">Heading type</label>
                    <select oninput="patchBlock('${id}','level',parseInt(this.value))">
                        <option value="2" ${(b.level ?? 2) == 2 ? 'selected' : ''}>H2 — Section heading</option>
                        <option value="3" ${(b.level ?? 2) == 3 ? 'selected' : ''}>H3 — Sub-section</option>
                    </select>
                </div>
                <div>
                    <label class="block-field-label">Title</label>
                    <textarea rows="2" placeholder="Section title…"
                        oninput="patchBlock('${id}','title',this.value)">${escHtml(b.title ?? '')}</textarea>
                </div>
            </div>`;
            }

            // ── Text (Quill) ─────────────────────────────────────────────
            case 'text': {
                const isDefault = b.isDefault;
                const wrapClass = 'block-quill-wrap' + (isDefault ? '' : ' block-quill-wrap--compact');
                // Quill is initialised after DOM insertion via initQuillForBlock()
                return `<div class="${wrapClass}" id="quill-wrap-${id}">
                <div id="quill-toolbar-${id}">
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
                    </span>
                    <span class="ql-formats">
                        <button class="ql-clean"></button>
                    </span>
                    ${isDefault ? `<span class="ql-formats" style="margin-left:auto;">
                        <span id="autosave-indicator">
                            <svg viewBox="0 0 20 20" fill="currentColor" width="12" id="autosave-icon">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <span id="autosave-text">All changes saved</span>
                        </span>
                    </span>` : ''}
                </div>
                <div id="quill-editor-${id}">${b.content ?? ''}</div>
            </div>`;
            }

            // ── Quote ────────────────────────────────────────────────────
            case 'quote':
                return `<div class="block-card__body-inner">
                <div>
                    <label class="block-field-label">Quote text</label>
                    <textarea rows="4" placeholder="Quote…"
                        oninput="patchBlock('${id}','text',this.value)">${escHtml(b.text ?? '')}</textarea>
                </div>
                <div>
                    <label class="block-field-label">Attribution <span style="font-weight:400;color:var(--slate-light);">(optional)</span></label>
                    <input type="text" placeholder="— Name or source"
                        value="${escAttr(b.attribution ?? '')}"
                        oninput="patchBlock('${id}','attribution',this.value)">
                </div>
            </div>`;

            // ── Info ─────────────────────────────────────────────────────
            case 'info':
                return `<div class="block-card__body-inner">
                <div>
                    <label class="block-field-label">Info type</label>
                    <select oninput="patchBlock('${id}','infoType',this.value)">
                        <option value="disclaimer" ${b.infoType === 'disclaimer' ? 'selected' : ''}>Disclaimer</option>
                        <option value="tip"        ${b.infoType === 'tip' ? 'selected' : ''}>Tip</option>
                        <option value="warning"    ${b.infoType === 'warning' ? 'selected' : ''}>Warning</option>
                        <option value="note"       ${b.infoType === 'note' ? 'selected' : ''}>Note</option>
                    </select>
                </div>
                <div>
                    <label class="block-field-label">Content</label>
                    <textarea rows="4" placeholder="Info block content…"
                        oninput="patchBlock('${id}','description',this.value)">${escHtml(b.description ?? '')}</textarea>
                </div>
            </div>`;

            // ── List ─────────────────────────────────────────────────────
            case 'list': {
                const items = b.items?.length ? b.items : [''];
                return `<div class="block-card__body-inner">
                <div>
                    <label class="block-field-label">List type</label>
                    <select oninput="patchBlock('${id}','listType',this.value)">
                        <option value="ul" ${b.listType === 'ul' ? 'selected' : ''}>Unordered (bullets)</option>
                        <option value="ol" ${b.listType === 'ol' ? 'selected' : ''}>Ordered (numbered)</option>
                    </select>
                </div>
                <div class="list-items-wrap">
                    <label class="block-field-label">Items</label>
                    ${items.map((item, i) => `
                    <div class="list-item-row">
                        <textarea rows="2" placeholder="Item ${i + 1}…"
                            oninput="patchBlock('${id}','items.${i}',this.value)">${escHtml(item)}</textarea>
                        <button type="button" class="list-item-remove" title="Remove item"
                                onclick="removeListItem('${id}',${i})">✕</button>
                    </div>`).join('')}
                </div>
                <button type="button" class="oc-btn oc-btn--ghost oc-btn--sm"
                        style="align-self:flex-start;"
                        onclick="addListItem('${id}')">+ Add item</button>
            </div>`;
            }

            // ── Image ─────────────────────────────────────────────────────
            case 'image': {
                if (b.isDefault) {
                    return `<div class="block-image-wrap" id="default-image-wrap">
            <div class="block-image-wrap__header">
                <span class="block-image-wrap__title">Images</span>

                <span class="block-image-wrap__hint">
                    Add images from your Open Collab image library
                </span>

                <button type="button"
                        onclick="openImageLibraryForBlock('${id}')"
                        class="oc-btn oc-btn--ghost oc-btn--sm"
                        style="font-size:.75rem;padding:4px 10px;">
                    + Choose image
                </button>
            </div>

            <div class="block-image-thumbs" id="default-image-thumbs">
                <span class="block-image-empty" id="default-image-empty">
                    No images added yet
                </span>
            </div>
        </div>`;
                }

                const imageBlock = normaliseImageBlock(b);
                const rightsLabel = imageBlock.image_rights
                    ? getImageRightsLabel(imageBlock.image_rights)
                    : '';

                return `<div class="block-card__body-inner">

        <div>
            <label class="block-field-label">Image</label>

            ${imageBlock.image_url
                    ? `<div class="block-image-selected">
                    <div class="block-image-preview">
                        <img src="${escAttr(imageBlock.image_url)}"
                             alt="${escAttr(b.alt ?? '')}">

                        <button type="button"
                                class="block-image-preview__remove"
                                onclick="clearBlockImage('${id}')"
                                title="Remove image">
                            ✕
                        </button>
                    </div>

                    ${rightsLabel
                        ? `<div class="block-image-rights">
                            Image rights: ${escHtml(rightsLabel)}
                        </div>`
                        : ''
                    }

                    <div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap;">
                        <button type="button"
                                class="oc-btn oc-btn--ghost oc-btn--sm"
                                onclick="openImageLibraryForBlock('${id}')">
                            Change image
                        </button>

                        <button type="button"
                                class="oc-btn oc-btn--ghost oc-btn--sm"
                                onclick="resetImageBlockMetadata('${id}')">
                            Reset from image metadata
                        </button>
                    </div>
                </div>`
                    : `<div class="block-image-drop"
                        id="img-drop-${id}"
                        role="button"
                        tabindex="0"
                        onclick="openImageLibraryForBlock('${id}')"
                        onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();openImageLibraryForBlock('${id}');}">

                    <div class="block-image-drop__hint">
                        <svg viewBox="0 0 20 20"
                             fill="currentColor"
                             width="20"
                             style="color:var(--slate-light);display:block;margin:0 auto 6px;">
                            <path fill-rule="evenodd"
                                  d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z"
                                  clip-rule="evenodd"/>
                        </svg>

                        Choose an image from your library
                    </div>
                </div>`
                }
        </div>

        <div>
            <label class="block-field-label">
                Image name
            </label>

            <input class="oc-input"
                   type="text"
                   maxlength="255"
                   placeholder="Image name…"
                   value="${escAttr(imageBlock.name ?? '')}"
                   oninput="patchBlock('${id}','name',this.value)">
        </div>

        <div>
            <label class="block-field-label">
                Alt text
            </label>

            <input class="oc-input"
                   type="text"
                   maxlength="500"
                   placeholder="Describe the image for accessibility…"
                   value="${escAttr(imageBlock.alt ?? '')}"
                   oninput="patchBlock('${id}','alt',this.value)">
        </div>

        <div>
            <label class="block-field-label">
                Credit
                <span style="font-weight:400;color:var(--slate-light);">
                    (optional override)
                </span>
            </label>

            <input class="oc-input"
                   type="text"
                   maxlength="255"
                   placeholder="Image credit…"
                   value="${escAttr(imageBlock.credit ?? '')}"
                   oninput="patchBlock('${id}','credit',this.value)">
        </div>

        <div>
            <label class="block-field-label">
                Caption
                <span style="font-weight:400;color:var(--slate-light);">
                    (optional)
                </span>
            </label>

            <input class="oc-input"
                   type="text"
                   placeholder="Image caption…"
                   value="${escAttr(b.caption ?? '')}"
                   oninput="patchBlock('${id}','caption',this.value)">
        </div>

        <div class="block-field-row">
            <div>
                <label class="block-field-label">Layout</label>

                <select class="oc-input"
                        oninput="patchBlock('${id}','layout',this.value)">
                    <option value="full"
                            ${b.layout === 'full' ? 'selected' : ''}>
                        Full width
                    </option>

                    <option value="wide"
                            ${b.layout === 'wide' ? 'selected' : ''}>
                        Wide
                    </option>

                    <option value="normal"
                            ${b.layout === 'normal' ? 'selected' : ''}>
                        Normal
                    </option>
                </select>
            </div>

            <div>
                <label class="block-field-label">Alignment</label>

                <select class="oc-input"
                        oninput="patchBlock('${id}','alignment',this.value)">
                    <option value="center"
                            ${b.alignment === 'center' ? 'selected' : ''}>
                        Centre
                    </option>

                    <option value="left"
                            ${b.alignment === 'left' ? 'selected' : ''}>
                        Left
                    </option>

                    <option value="right"
                            ${b.alignment === 'right' ? 'selected' : ''}>
                        Right
                    </option>
                </select>
            </div>
        </div>
    </div>`;
            }

            default:
                return `<div class="block-card__body-inner"><span style="color:var(--slate);font-size:.82rem;">Unknown block type: ${escHtml(b.type)}</span></div>`;
        }
    }

    // =============================================================================
    // Quill initialisation per text block
    // =============================================================================
    function initQuillForBlock(b) {
        const toolbarId = `quill-toolbar-${b.id}`;
        const editorId = `quill-editor-${b.id}`;
        const editorEl = document.getElementById(editorId);
        if (!editorEl) return;
        // FIX 1 — guard removed; quillInstances is cleared before every renderBlocks()
        // so we always remount on fresh DOM nodes. The old check
        // `if (quillInstances[b.id]) return` caused stale detached instances to
        // block remounting after any re-render.

        const q = new Quill(`#${editorId}`, {
            theme: 'snow',
            modules: {
                toolbar: `#${toolbarId}`,
                clipboard: {matchVisual: false},
            },
            placeholder: 'Start writing…',
        });

        quillInstances[b.id] = q;

        q.on('text-change', () => {
            b.content = q.root.innerHTML;
            scheduleAutosave();
        });
    }

    // =============================================================================
    // Default image block
    // =============================================================================
    function renderDefaultImageThumbs() {
        const container = document.getElementById('default-image-thumbs');
        if (!container) return;
        const empty = document.getElementById('default-image-empty');
        container.querySelectorAll('.block-image-thumb').forEach(el => el.remove());

        if (!defaultImageItems.length) {
            if (empty) empty.style.display = '';
            return;
        }
        if (empty) empty.style.display = 'none';
        defaultImageItems.forEach(img => {
            const wrap = document.createElement('div');
            wrap.className = 'block-image-thumb';
            wrap.innerHTML = `<img src="${escAttr(img.url)}" alt="${escAttr(img.alt)}">
            <button class="block-image-thumb__remove" title="Remove"
                    onclick="removeDefaultImage('${img.id}')">✕</button>`;
            container.appendChild(wrap);
        });
    }

    function removeDefaultImage(id) {
        defaultImageItems = defaultImageItems.filter(i => i.id !== id);
        // Update default image block src (use first image if any)
        const defImg = blocks.find(b => b.id === DEFAULT_BLOCK_IDS.image);
        if (defImg) defImg.image_url = defaultImageItems[0]?.url ?? '';
        renderDefaultImageThumbs();
        scheduleAutosave();
    }

    document.getElementById('default-image-file-input').addEventListener('change', function () {
        Array.from(this.files).forEach(f => uploadDefaultImage(f));
        this.value = '';
    });

    function handleDefaultImageDrop(event) {
        event.preventDefault();
        document.getElementById('default-image-wrap')?.classList.remove('drag-over');
        Array.from(event.dataTransfer?.files ?? [])
            .filter(f => f.type.startsWith('image/'))
            .forEach(f => uploadDefaultImage(f));
    }

    async function uploadDefaultImage(file) {
        if (!file.type.startsWith('image/')) {
            showToast('Only image files are supported', false);
            return;
        }
        const url = await uploadImageXHR(file);
        if (!url) return;
        const item = {
            id: uid(),
            cms_image_id: null,
            url,
            thumbnail_url: url,
            name: file.name.replace(/\.[^.]+$/, ''),
            alt: file.name.replace(/\.[^.]+$/, ''),
            credit: '',
            image_rights: '',
        };
        defaultImageItems.push(item);
        // Keep the default block's src pointing at the first uploaded image
        const defImg = blocks.find(b => b.id === DEFAULT_BLOCK_IDS.image);
        if (defImg && !defImg.image_url) defImg.image_url = url;
        renderDefaultImageThumbs();
        scheduleAutosave();
        showToast('Image added');
    }

    // =============================================================================
    // Non-default image block upload
    // FIX 5 — use a Map keyed by block id so concurrent uploads to different
    // blocks never overwrite each other via a shared _pendingImageBlockId.
    // =============================================================================
    const _pendingFileInputResolvers = new Map();

    function triggerBlockImagePick(blockId) {
        const fileInput = document.getElementById('block-image-file-input');
        // Store which block triggered this pick
        fileInput.dataset.targetBlockId = blockId;
        fileInput.click();
    }

    document.getElementById('block-image-file-input').addEventListener('change', function () {
        const blockId = this.dataset.targetBlockId;
        if (this.files[0] && blockId) uploadBlockImage(blockId, this.files[0]);
        this.value = '';
        delete this.dataset.targetBlockId;
    });

    function handleBlockImageDrop(event, blockId) {
        event.preventDefault();
        document.getElementById(`img-drop-${blockId}`)?.classList.remove('drag-over');
        const file = Array.from(event.dataTransfer?.files ?? []).find(f => f.type.startsWith('image/'));
        if (file) uploadBlockImage(blockId, file);
    }

    async function uploadBlockImage(blockId, file) {
        if (!file.type.startsWith('image/')) {
            showToast('Only image files are supported', false);
            return;
        }
        const url = await uploadImageXHR(file);
        if (!url) return;
        // FIX 5 — patch the specific block's src in state, then re-render.
        // Because patchBlock writes to blocks[] before renderBlocks() reads it,
        // every block retains its own src through the re-render.
        patchBlock(blockId, 'src', url);
        const b = blocks.find(b => b.id === blockId);
        if (b && !b.alt) patchBlock(blockId, 'alt', file.name.replace(/\.[^.]+$/, ''));
        renderBlocks();
        showToast('Image uploaded');
    }

    function clearBlockImage(blockId) {
        const block = blocks.find(candidate => candidate.id === blockId);

        if (!block || block.type !== 'image') {
            return;
        }

        block.cms_image_id = null;
        block.image_url = '';
        block.thumbnail_url = '';
        block.name = '';
        block.alt = '';
        block.credit = '';
        block.image_rights = '';
        block.source_alt = '';
        block.source_credit = '';

        renderBlocks();
        scheduleAutosave();
    }

    function serialiseImageBlock(block, order) {
        const b = normaliseImageBlock(block);

        return {
            type: 'image',
            order,
            cms_image_id: b.cms_image_id,
            image_url: b.image_url,
            thumbnail_url: b.thumbnail_url,
            name: b.name,
            alt: b.alt,
            credit: b.credit,
            image_rights: b.image_rights,
            source_alt: b.source_alt,
            source_credit: b.source_credit,
            caption: b.caption,
            layout: b.layout,
            alignment: b.alignment,
        };
    }

    // =============================================================================
    // XHR image upload helper
    // =============================================================================
    async function uploadImageXHR(file) {
        const overlay = document.getElementById('upload-overlay');
        const pctEl = document.getElementById('upload-pct');
        overlay.style.display = 'grid';
        pctEl.textContent = '';
        const formData = new FormData();
        formData.append('image', file);
        try {
            return await new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', `/api/${SITE}/images`);
                xhr.setRequestHeader('Authorization', `Bearer ${TOKEN()}`);
                xhr.setRequestHeader('Accept', 'application/json');
                xhr.upload.onprogress = e => {
                    if (e.lengthComputable) pctEl.textContent = `${Math.round(e.loaded / e.total * 100)}%`;
                };
                xhr.onload = () => {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        try {
                            const data = JSON.parse(xhr.responseText);
                            const imgUrl = data?.data?.image?.url || data?.image?.url || data?.url;
                            if (imgUrl) resolve(imgUrl); else reject(new Error('No URL in response'));
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
        } catch (err) {
            showToast(err.message || 'Upload failed', false);
            return null;
        } finally {
            overlay.style.display = 'none';
        }
    }

    // =============================================================================
    // Drag-and-drop reordering (non-default blocks only)
    // =============================================================================
    let dragSrcId = null;
    const nonDefaultStart = () => blocks.findIndex(b => !b.isDefault);

    function initDragDrop() {
        document.querySelectorAll('.block-card:not(.block-card--default)').forEach(card => {
            card.addEventListener('dragstart', e => {
                dragSrcId = card.dataset.blockId;
                card.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
            });
            card.addEventListener('dragend', () => {
                card.classList.remove('dragging');
                document.querySelectorAll('.block-card').forEach(c => {
                    c.classList.remove('drag-over-top', 'drag-over-bottom');
                });
                dragSrcId = null;
            });
            card.addEventListener('dragover', e => {
                if (!dragSrcId || card.dataset.blockId === dragSrcId) return;
                e.preventDefault();
                const rect = card.getBoundingClientRect();
                const mid = rect.top + rect.height / 2;
                card.classList.toggle('drag-over-top', e.clientY < mid);
                card.classList.toggle('drag-over-bottom', e.clientY >= mid);
            });
            card.addEventListener('dragleave', () => {
                card.classList.remove('drag-over-top', 'drag-over-bottom');
            });
            card.addEventListener('drop', e => {
                e.preventDefault();
                if (!dragSrcId || card.dataset.blockId === dragSrcId) return;
                const tgtBlock = blocks.find(b => b.id === card.dataset.blockId);
                if (tgtBlock?.isDefault) return; // cannot drop onto default blocks
                const rect = card.getBoundingClientRect();
                const mid = rect.top + rect.height / 2;
                const before = e.clientY < mid;
                const srcIdx = blocks.findIndex(b => b.id === dragSrcId);
                const tgtIdx = blocks.findIndex(b => b.id === card.dataset.blockId);
                const [moved] = blocks.splice(srcIdx, 1);
                const insert = before ? tgtIdx : tgtIdx + (srcIdx < tgtIdx ? 0 : 1);
                blocks.splice(Math.max(nonDefaultStart(), insert - (srcIdx < tgtIdx && !before ? 1 : 0)), 0, moved);
                reorder();
                renderBlocks();
                scheduleAutosave();
            });
        });
    }

    // =============================================================================
    // Initial render
    // =============================================================================
    renderBlocks();

    // =============================================================================
    // Autosave indicator helpers
    // =============================================================================
    function setAutosaveState(state, text) {
        const el = document.getElementById('autosave-indicator');
        const txt = document.getElementById('autosave-text');
        const icon = document.getElementById('autosave-icon');
        if (!el) return;
        el.className = state;
        if (txt) txt.textContent = text;
        const icons = {
            idle: '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>',
            saving: '<path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"/>',
            saved: '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>',
            error: '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>',
        };
        if (icon) icon.innerHTML = icons[state] ?? icons.idle;
    }

    // =============================================================================
    // Autosave
    // =============================================================================
    let autosaveTimer = null;
    let isDirty = false;
    let lastSavedHash = '';
    let isRestoreMode = false;

    function contentHash(str) {
        let h = 0;
        for (let i = 0; i < str.length; i++) h = (Math.imul(31, h) + str.charCodeAt(i)) | 0;
        return h;
    }

    function scheduleAutosave() {
        if (!PAGE_ID) return;
        isDirty = true;
        clearTimeout(autosaveTimer);
        setAutosaveState('saving', 'Saving…');
        autosaveTimer = setTimeout(doAutosave, 2000);
    }

    async function doAutosave() {
        if (!PAGE_ID || isRestoreMode) return;
        const hash = contentHash(JSON.stringify(blocks) + JSON.stringify(defaultImageItems));
        if (hash === lastSavedHash) {
            setAutosaveState('saved', 'All changes saved');
            return;
        }
        try {
            const ok = await persistArticle('draft', {silent: true});
            if (ok) {
                lastSavedHash = hash;
                setAutosaveState('saved', 'All changes saved');
            } else setAutosaveState('error', 'Save failed');
        } catch {
            setAutosaveState('error', 'Save failed');
        }
    }

    window.addEventListener('beforeunload', e => {
        if (isDirty) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    // =============================================================================
    // FIX 2 — Slug auto-generation
    // Track whether the user has manually edited the slug so we stop overwriting it.
    // Previously, autoSlug() returned early as soon as slugField.value was truthy,
    // meaning it stopped updating after writing the very first character.
    // =============================================================================
    let slugManuallyEdited = <?= ($page?->slug) ? 'true' : 'false' ?>;

    (function initSlugField() {
        const slugField = document.getElementById('article-slug');
        if (!slugField) return;
        // Mark as manually edited if the user types directly into the slug field.
        slugField.addEventListener('input', () => {
            slugManuallyEdited = true;
        });
        // If the user clears the slug field, re-enable auto-generation.
        slugField.addEventListener('change', () => {
            if (!slugField.value.trim()) slugManuallyEdited = false;
        });
    })();

    function autoSlug(titleVal) {
        if (slugManuallyEdited) return; // user owns the slug field — do not overwrite
        const slugField = document.getElementById('article-slug');
        if (!slugField) return;
        slugField.value = titleVal.toLowerCase()
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-')
            .slice(0, 100);
    }

    // =============================================================================
    // FIX 3 — Paid article forces Premium visibility
    // =============================================================================
    document.getElementById('is-paid').addEventListener('change', function () {
        const priceField = document.getElementById('price-field');
        const visibilitySelect = document.getElementById('article-visibility');
        const visibilityNote = document.getElementById('visibility-paid-note');

        priceField.style.display = this.checked ? 'block' : 'none';

        if (this.checked) {
            visibilitySelect.value = 'premium';
            if (visibilityNote) visibilityNote.style.display = 'block';
        } else {
            if (visibilityNote) visibilityNote.style.display = 'none';
        }
    });

    // Prevent the user from changing visibility away from Premium while paid is on.
    document.getElementById('article-visibility').addEventListener('change', function () {
        const isPaid = document.getElementById('is-paid').checked;
        if (isPaid && this.value !== 'premium') {
            this.value = 'premium';
            showToast('Paid articles must use Premium visibility', false);
        }
    });

    // =============================================================================
    // Build blocks payload for API
    // =============================================================================
    function buildBlocksPayload() {
        // Snapshot all Quill instances
        Object.keys(quillInstances).forEach(id => {
            const q = quillInstances[id];
            if (!q) return;
            const b = blocks.find(b => b.id === id);
            if (b) b.content = q.root.innerHTML;
        });

        const payload = [];
        let order = 0;

        blocks.forEach(b => {
            switch (b.type) {
                case 'heading':
                    payload.push({
                        type: 'heading', order: order++, level: b.level ?? 2,
                        text: b.text ?? '', subtitle: b.subtitle ?? ''
                    });
                    break;
                case 'section':
                    payload.push({
                        type: 'section', order: order++, level: b.level ?? 2,
                        title: b.title ?? ''
                    });
                    break;
                case 'text':
                    // Parse the Quill HTML into paragraph blocks
                    parseQuillHTML(b.content ?? '', payload, order);
                    order = payload.length;
                    break;
                case 'quote':
                    payload.push({
                        type: 'quote', order: order++, text: b.text ?? '',
                        attribution: b.attribution ?? ''
                    });
                    break;
                case 'info':
                    payload.push({
                        type: 'info', order: order++, infoType: b.infoType ?? 'disclaimer',
                        description: b.description ?? ''
                    });
                    break;
                case 'list':
                    payload.push({
                        type: 'list', order: order++, listType: b.listType ?? 'ul',
                        items: b.items ?? []
                    });
                    break;
                case 'image':
                    if (b.isDefault) {
                        // Expand the legacy default image collection into image blocks.
                        if (defaultImageItems.length) {
                            defaultImageItems.forEach(img => {
                                payload.push({
                                    type: 'image',
                                    order: order++,
                                    cms_image_id: img.cms_image_id ?? null,
                                    image_url: img.url ?? '',
                                    thumbnail_url: img.thumbnail_url ?? '',
                                    name: img.name ?? '',
                                    alt: img.alt ?? '',
                                    credit: img.credit ?? '',
                                    image_rights: img.image_rights ?? '',
                                    source_alt: img.alt ?? '',
                                    source_credit: img.credit ?? '',
                                    caption: '',
                                    layout: 'full',
                                    alignment: 'center',
                                });
                            });
                        }
                    } else if (normaliseImageBlock(b).image_url) {
                        payload.push(serialiseImageBlock(b, order++));
                    }
                    break;
            }
        });

        return payload;
    }

    /**
     * Minimal Quill HTML → structured blocks converter.
     * Pushes text/heading/list/quote/info blocks into the target array.
     */
    function parseQuillHTML(html, target, startOrder) {
        const tmp = document.createElement('div');
        tmp.innerHTML = html;
        let order = startOrder;
        const paraAccum = [];

        function flushParas() {
            if (!paraAccum.length) return;
            target.push({type: 'text', order: order++, paragraphs: [...paraAccum]});
            paraAccum.length = 0;
        }

        tmp.childNodes.forEach(node => {
            if (node.nodeType === 3) { // text node
                const t = node.textContent.trim();
                if (t) paraAccum.push(t);
                return;
            }
            const tag = node.tagName?.toLowerCase();
            if (!tag) return;

            if (tag === 'h2') {
                flushParas();
                target.push({type: 'heading', order: order++, level: 2, text: node.innerHTML, subtitle: ''});
            } else if (tag === 'h3') {
                flushParas();
                target.push({type: 'heading', order: order++, level: 3, text: node.innerHTML, subtitle: ''});
            } else if (tag === 'blockquote') {
                flushParas();
                target.push({type: 'quote', order: order++, text: node.innerHTML, attribution: ''});
            } else if (tag === 'pre') {
                flushParas();
                target.push({type: 'info', order: order++, infoType: 'disclaimer', description: node.innerHTML});
            } else if (tag === 'ol' || tag === 'ul') {
                flushParas();
                const items = Array.from(node.querySelectorAll('li')).map(li => li.innerHTML);
                target.push({type: 'list', order: order++, listType: tag, items});
            } else if (tag === 'p') {
                const t = node.innerHTML.trim();
                if (t && t !== '<br>') paraAccum.push(t);
                else if (paraAccum.length) flushParas();
            }
        });

        flushParas();
    }

    // =============================================================================
    // Save / publish
    // =============================================================================
    async function saveArticle(status) {
        const errBox = document.getElementById('editor-errors');
        errBox.style.display = 'none';

        // Title comes from the default heading block
        const titleBlock = blocks.find(b => b.id === DEFAULT_BLOCK_IDS.heading);
        const title = (titleBlock?.text ?? '').trim();
        if (!title) {
            errBox.textContent = 'Please give your article a title before saving.';
            errBox.style.display = 'block';
            document.querySelector('#article-title')?.focus();
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
        publishBtn.innerHTML = `<svg viewBox="0 0 20 20" fill="currentColor" width="15"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-8.707l-3-3a1 1 0 00-1.414 1.414L10.586 9H7a1 1 0 100 2h3.586l-1.293 1.293a1 1 0 101.414 1.414l3-3a1 1 0 000-1.414z" clip-rule="evenodd"/></svg> <?= $isEdit && in_array($page->status ?? '', ['published', 'scheduled']) ? 'Update' : 'Publish' ?>`;
        publishBtn.disabled = false;
    }

    async function persistArticle(status, opts = {}) {
        const titleBlock = blocks.find(b => b.id === DEFAULT_BLOCK_IDS.heading);
        const title = (titleBlock?.text ?? '').trim();

        // Primary content = first Quill block HTML
        const defaultTextBlock = blocks.find(b => b.id === DEFAULT_BLOCK_IDS.text);
        const q = defaultTextBlock ? quillInstances[defaultTextBlock.id] : null;
        const html = q ? q.root.innerHTML : (defaultTextBlock?.content ?? '');

        const isPaid = document.getElementById('is-paid').checked;
        const priceVal = document.getElementById('article-price').value;
        const slug = document.getElementById('article-slug').value.trim();
        const metaDesc = document.getElementById('meta-description').value.trim();
        const pricePence = isPaid ? Math.round(parseFloat(priceVal || '0') * 100) : 0;
        const scheduledAt = document.getElementById('scheduled-at')?.value ?? null;

        // FIX 3 — enforce premium visibility for paid articles at the API payload level
        const rawVisibility = document.getElementById('article-visibility')?.value ?? 'public';
        const visibility = isPaid ? 'premium' : rawVisibility;

        if (status === 'scheduled') {
            if (!scheduledAt) {
                const errBox = document.getElementById('editor-errors');
                errBox.textContent = 'Please select a date and time to schedule publication.';
                errBox.style.display = 'block';
                return false;
            }
            if (new Date(scheduledAt) <= new Date()) {
                const errBox = document.getElementById('editor-errors');
                errBox.textContent = 'Scheduled date must be in the future.';
                errBox.style.display = 'block';
                return false;
            }
        }

        const blocksPayload = buildBlocksPayload();

        const payload = {
            site_id: SITE_ID,
            is_paid: isPaid,
            price: pricePence,
            forms: {
                main: {title, content: html, subtitle: ''},
                meta: {
                    status,
                    slug: slug || undefined,
                    visibility,
                    ...(status === 'scheduled' && scheduledAt ? {publish_date: scheduledAt} : {}),
                },
                seo: {meta_description: metaDesc, meta_title: title},
            },
            blocks: blocksPayload,
            gallery_slides: [],
            ...(status === 'scheduled' && scheduledAt ? {scheduled_at: scheduledAt} : {}),
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
                if (!opts.silent) {
                    const msg = status === 'published' ? '✓ Published!'
                        : status === 'scheduled' ? '✓ Article scheduled'
                            : '✓ Draft saved';
                    showToast(msg);
                }
                const id = data?.data?.page?.id || PAGE_ID;
                if (!PAGE_ID && id) window.location.href = `/articles/${id}/edit`;
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

    // =============================================================================
    // Delete
    // =============================================================================
    async function confirmDelete() {
        if (!PAGE_ID) return;
        if (!confirm('Delete this article permanently? This cannot be undone.')) return;
        const res = await fetch(`/api/${SITE}/open-collab/pages/${PAGE_ID}`, {
            method: 'DELETE',
            headers: {'Authorization': `Bearer ${TOKEN()}`, 'Accept': 'application/json'},
        });
        if (res.ok || res.status === 204) window.location.href = '/articles';
        else showToast('Could not delete article. Please try again.', false);
    }

    // =============================================================================
    // Version history + diff modal
    // =============================================================================
    let historyLoaded = false;
    let previewingEntry = null;
    let originalBlocks = null;
    let diffEntry = null;

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
            <div style="flex:1;min-width:0;">
                <div class="history-item__summary">${escHtml(entry.action_label ?? entry.action ?? 'Updated')}</div>
                <div class="history-item__meta">${escHtml(entry.user_name ?? 'You')} · ${formatDate(entry.created_at)}</div>
                ${entry.change_summary ? `<div style="font-size:.7rem;color:var(--slate);margin-top:2px;">${escHtml(entry.change_summary)}</div>` : ''}
            </div>
            ${entry.snapshot ? `<button class="oc-btn oc-btn--ghost oc-btn--sm" style="font-size:.68rem;padding:3px 7px;flex-shrink:0;"
                onclick="openDiffModal(event,${JSON.stringify(entry).replace(/"/g, '&quot;')})">Compare</button>` : ''}`;
                el.addEventListener('click', e => {
                    if (!e.target.closest('button')) previewVersion(entry);
                });
                list.appendChild(el);
            });
            historyLoaded = true;
        } catch {
            list.innerHTML = '<div style="padding:16px;text-align:center;color:var(--red);font-size:.82rem;">Failed to load history.</div>';
        }
    }

    function openDiffModal(event, entry) {
        event.stopPropagation();
        if (!entry.snapshot) {
            showToast('No snapshot available', false);
            return;
        }
        diffEntry = entry;
        const snap = entry.snapshot;
        document.getElementById('diff-modal-title').textContent =
            `Comparing: ${entry.action_label ?? entry.action ?? 'version'} (${formatDate(entry.created_at)})`;
        const titleBlock = blocks.find(b => b.id === DEFAULT_BLOCK_IDS.heading);
        document.getElementById('diff-old-content').innerHTML =
            `<div style="margin-bottom:8px;font-size:.95rem;font-weight:700;color:var(--navy);">${escHtml(snap.title ?? '')}</div>` +
            (snap.content ?? '<em style="color:var(--slate)">No content snapshot</em>');
        document.getElementById('diff-new-content').innerHTML =
            `<div style="margin-bottom:8px;font-size:.95rem;font-weight:700;color:var(--navy);">${escHtml(titleBlock?.text ?? '')}</div>` +
            (quillInstances[DEFAULT_BLOCK_IDS.text]?.root.innerHTML ?? '');
        document.getElementById('diff-modal').classList.add('open');
    }

    function closeDiffModal() {
        document.getElementById('diff-modal').classList.remove('open');
        diffEntry = null;
    }

    document.getElementById('diff-modal').addEventListener('click', function (e) {
        if (e.target === this) closeDiffModal();
    });

    async function restoreFromDiff() {
        if (!diffEntry?.snapshot) {
            showToast('No snapshot to restore', false);
            return;
        }
        const snap = diffEntry.snapshot;
        // Restore title into default heading block
        const titleBlock = blocks.find(b => b.id === DEFAULT_BLOCK_IDS.heading);
        if (titleBlock && snap.title) titleBlock.text = snap.title;
        // Restore content into default text block Quill
        const q = quillInstances[DEFAULT_BLOCK_IDS.text];
        if (q && snap.content) q.root.innerHTML = snap.content;
        closeDiffModal();
        const ok = await persistArticle('draft');
        if (ok) {
            showToast('✓ Version restored');
            historyLoaded = false;
        }
    }

    function previewVersion(entry) {
        if (!entry.snapshot) {
            showToast('No snapshot available', false);
            return;
        }
        document.querySelectorAll('.history-item').forEach(el => el.classList.remove('active'));
        document.querySelector(`.history-item[data-id="${entry.id}"]`)?.classList.add('active');
        if (!isRestoreMode) {
            originalBlocks = JSON.parse(JSON.stringify(blocks));
        }
        isRestoreMode = true;
        previewingEntry = entry;
        const snap = entry.snapshot;
        const titleBlock = blocks.find(b => b.id === DEFAULT_BLOCK_IDS.heading);
        if (titleBlock && snap.title) {
            titleBlock.text = snap.title;
            const titleInput = document.getElementById('article-title');
            if (titleInput) titleInput.value = snap.title;
        }
        const q = quillInstances[DEFAULT_BLOCK_IDS.text];
        if (q && snap.content) q.root.innerHTML = snap.content;
        document.getElementById('restore-banner-text').textContent =
            `Previewing version from ${formatDate(entry.created_at)}.`;
        document.getElementById('restore-banner').style.display = 'flex';
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
                historyLoaded = false;
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
        if (originalBlocks) {
            blocks = originalBlocks;
            renderBlocks();
        }
        originalBlocks = null;
        isRestoreMode = false;
        previewingEntry = null;
        document.getElementById('restore-banner').style.display = 'none';
        document.querySelectorAll('.history-item').forEach(el => el.classList.remove('active'));
    }

    // =============================================================================
    // FIX 4 — Article preview
    // Renders all blocks as read-only HTML in a modal overlay.
    // Lives in the main column (add-block-row), sidebar is untouched.
    // =============================================================================
    function openPreview() {
        // Snapshot Quill content first
        Object.keys(quillInstances).forEach(id => {
            const q = quillInstances[id];
            if (!q) return;
            const b = blocks.find(b => b.id === id);
            if (b) b.content = q.root.innerHTML;
        });

        const container = document.getElementById('preview-content');
        container.innerHTML = renderBlocksAsHTML();
        document.getElementById('preview-modal').classList.add('open');
    }

    function closePreview() {
        document.getElementById('preview-modal').classList.remove('open');
    }

    document.getElementById('preview-modal').addEventListener('click', function (e) {
        if (e.target === this) closePreview();
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            closePreview();
            closeDiffModal();
        }
    });

    function renderBlocksAsHTML() {
        let html = '';

        blocks.forEach(b => {
            switch (b.type) {
                case 'heading': {
                    const tag = `h${b.level ?? 2}`;
                    if (b.isDefault) {
                        if (b.text) html += `<h1 class="preview-title">${escHtml(b.text)}</h1>`;
                        if (b.subtitle) html += `<p class="preview-subtitle">${escHtml(b.subtitle)}</p>`;
                    } else {
                        if (b.text) html += `<${tag}>${escHtml(b.text)}</${tag}>`;
                        if (b.subtitle) html += `<p style="color:var(--slate);margin-top:-.5em;">${escHtml(b.subtitle)}</p>`;
                    }
                    break;
                }
                case 'section': {
                    const tag = `h${b.level ?? 2}`;
                    if (b.title) html += `<${tag} class="preview-section-heading">${escHtml(b.title)}</${tag}>`;
                    break;
                }
                case 'text': {
                    // Render Quill HTML directly — it's already safe from the editor
                    html += `<div>${b.content ?? ''}</div>`;
                    break;
                }
                case 'quote': {
                    html += `<blockquote>
                        <div>${escHtml(b.text ?? '')}</div>
                        ${b.attribution ? `<div class="preview-quote-attr">${escHtml(b.attribution)}</div>` : ''}
                    </blockquote>`;
                    break;
                }
                case 'info': {
                    const cls = `preview-info-block preview-info-block--${b.infoType ?? 'disclaimer'}`;
                    html += `<div class="${cls}">${escHtml(b.description ?? '')}</div>`;
                    break;
                }
                case 'list': {
                    const tag = b.listType === 'ol' ? 'ol' : 'ul';
                    const items = (b.items ?? []).map(i => `<li>${escHtml(i)}</li>`).join('');
                    html += `<${tag}>${items}</${tag}>`;
                    break;
                }
                case 'image': {
                    if (b.isDefault) {
                        // Show all uploaded default images
                        defaultImageItems.forEach(img => {
                            html += `<figure class="preview-image-block">
                                <img src="${escAttr(img.url)}" alt="${escAttr(img.alt)}">
                                ${img.alt ? `<figcaption>${escHtml(img.alt)}</figcaption>` : ''}
                            </figure>`;
                        });
                    } else {
                        const imageBlock = normaliseImageBlock(b);
                        if (imageBlock.image_url) {
                            html += `<figure class="preview-image-block">
                                <img src="${escAttr(imageBlock.image_url)}" alt="${escAttr(imageBlock.alt ?? '')}">
                                ${imageBlock.caption ? `<figcaption>${escHtml(imageBlock.caption)}</figcaption>` : ''}
                            </figure>`;
                        }
                    }
                    break;
                }
            }
        });

        return html || '<p style="color:var(--slate);text-align:center;padding:40px 0;">Nothing to preview yet.</p>';
    }

    // =============================================================================
    // Comments
    // =============================================================================
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
        } catch {
        }
    })();
    <?php endif; ?>

    function renderComments(comments) {
        const body = document.getElementById('comments-body');
        const empty = document.getElementById('comments-empty');
        const badge = document.getElementById('comment-count-badge');
        const total = comments.reduce((a, c) => a + 1 + (c.replies?.length ?? 0), 0);
        badge.textContent = total;
        if (!comments.length) {
            empty.style.display = 'block';
            body.querySelectorAll('.comment-thread').forEach(el => el.remove());
            return;
        }
        empty.style.display = 'none';
        body.querySelectorAll('.comment-thread').forEach(el => el.remove());
        comments.forEach(c => body.insertBefore(buildCommentThread(c), empty));
    }

    function buildCommentThread(comment) {
        const wrap = document.createElement('div');
        wrap.className = 'comment-thread top-level';
        wrap.appendChild(buildCommentEl(comment, false));
        if (comment.replies?.length) {
            const r = document.createElement('div');
            r.className = 'replies';
            comment.replies.forEach(rep => r.appendChild(buildCommentEl(rep, true)));
            wrap.appendChild(r);
        }
        return wrap;
    }

    function buildCommentEl(comment, isReply) {
        const el = document.createElement('div');
        el.className = 'comment-item';
        el.dataset.commentId = comment.id;
        const isOwn = comment.user_id === <?= Auth::id() ?? 'null' ?>;
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
    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:6px;">
        <button class="oc-btn oc-btn--ghost oc-btn--sm"
                onclick="document.getElementById('reply-form-${parentId}').style.display='none'">Cancel</button>
        <button class="oc-btn oc-btn--primary oc-btn--sm" onclick="postReply(${parentId})">Post reply</button>
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
                headers: {'Content-Type': 'application/json', 'Authorization': `Bearer ${TOKEN()}`},
                body: JSON.stringify({content}),
            });
            const data = await res.json();
            if (res.ok) {
                input.value = '';
                commentsCache.push(data.data.comment);
                renderComments(commentsCache);
            } else showToast(data.message || 'Failed to post comment', false);
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
                headers: {'Content-Type': 'application/json', 'Authorization': `Bearer ${TOKEN()}`},
                body: JSON.stringify({content}),
            });
            const data = await res.json();
            if (res.ok) {
                const parent = commentsCache.find(c => c.id === parentId);
                if (parent) {
                    parent.replies = parent.replies ?? [];
                    parent.replies.push(data.data.comment);
                }
                renderComments(commentsCache);
            } else showToast(data.message || 'Failed to post reply', false);
        } catch {
            showToast('Network error', false);
        }
    }

    async function deleteComment(id) {
        if (!confirm('Delete this comment?')) return;
        try {
            const res = await fetch(`/api/${SITE}/open-collab/comments/${id}`, {
                method: 'DELETE', headers: {'Authorization': `Bearer ${TOKEN()}`},
            });
            if (res.ok) {
                commentsCache = commentsCache
                    .map(c => ({...c, replies: (c.replies ?? []).filter(r => r.id !== id)}))
                    .filter(c => c.id !== id);
                renderComments(commentsCache);
            } else showToast('Could not delete comment', false);
        } catch {
            showToast('Network error', false);
        }
    }

    // =============================================================================
    // Utilities
    // =============================================================================
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
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function escAttr(str) {
        return escHtml(str ?? '');
    }

    function formatDate(str) {
        if (!str) return '';
        return new Date(str).toLocaleDateString('en-GB', {day: 'numeric', month: 'short', year: 'numeric'});
    }
</script>
@endsection