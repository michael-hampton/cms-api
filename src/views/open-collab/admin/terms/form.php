<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($terms) ? 'Edit Terms' : 'Create Terms' ?> — OpenCollab</title>
    @css('open-collab.css')
</head>
<body style="background:var(--cream);min-height:100vh;">
<main style="max-width:980px;margin:0 auto;padding:32px 20px;">
    <?php
    $isEdit = isset($terms) && $terms;
    $status = $isEdit ? (is_object($terms->status) ? $terms->status->value : (string)$terms->status) : 'draft';
    $isLocked = $isEdit && $status !== 'draft';
    ?>

    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:20px;margin-bottom:24px;">
        <div>
            <a href="<?= url('/open-collab/admin/terms') ?>" class="oc-link">&larr; Terms versions</a>
            <h1 style="margin:10px 0 8px;"><?= $isEdit ? 'Edit terms draft' : 'Create terms draft' ?></h1>
            <p class="oc-muted">Published versions are immutable. Any later wording change must be created as a new version.</p>
        </div>
        <?php if ($isEdit): ?>
            <span class="oc-badge <?= $status === 'published' ? 'oc-badge--success' : ($status === 'archived' ? 'oc-badge--muted' : 'oc-badge--warning') ?>">
                <?= ucfirst(htmlspecialchars($status)) ?>
            </span>
        <?php endif; ?>
    </div>

    <?php if ($isLocked): ?>
        <div class="oc-alert oc-alert--info" style="margin-bottom:20px;">
            This version is read-only because it has already been <?= htmlspecialchars($status) ?>.
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= $isEdit ? url('/api/' . $siteSlug . '/open-collab/admin/terms/' . $terms->id) : url('/api/' . $siteSlug . '/open-collab/admin/terms') ?>" class="oc-card">
        <input type="hidden" name="_token" value="<?= csrf_token() ?>">
        <?php if ($isEdit): ?><input type="hidden" name="_method" value="PUT"><?php endif; ?>

        <div class="oc-card__header">
            <div class="oc-card__title">Version details</div>
        </div>
        <div class="oc-card__body" style="padding:28px;">
            <div style="display:grid;grid-template-columns:180px 1fr;gap:16px;">
                <div class="oc-form-group">
                    <label class="oc-label" for="semantic_version">Semantic version</label>
                    <input class="oc-input" id="semantic_version" name="semantic_version" required
                           value="<?= htmlspecialchars($terms->semantic_version ?? '') ?>" <?= $isEdit ? 'readonly' : '' ?>
                           placeholder="1.0.0">
                    <div class="oc-help">Use MAJOR.MINOR.PATCH.</div>
                </div>
                <div class="oc-form-group">
                    <label class="oc-label" for="title">Title</label>
                    <input class="oc-input" id="title" name="title" required
                           value="<?= htmlspecialchars($terms->title ?? '') ?>" <?= $isLocked ? 'readonly' : '' ?>
                           placeholder="OpenCollab Contributor Terms & Conditions">
                </div>
            </div>

            <div class="oc-form-group">
                <label class="oc-label" for="change_summary">Change summary</label>
                <textarea class="oc-textarea" id="change_summary" name="change_summary" rows="3" <?= $isLocked ? 'readonly' : '' ?>
                          placeholder="Explain what changed and why."><?= htmlspecialchars($terms->change_summary ?? '') ?></textarea>
            </div>

            <label style="display:flex;gap:12px;align-items:flex-start;padding:14px;border:1px solid var(--border);border-radius:var(--radius);margin-bottom:20px;">
                <input type="checkbox" name="is_material_change" value="1" style="margin-top:3px;" <?= !empty($terms->is_material_change) ? 'checked' : '' ?> <?= $isLocked ? 'disabled' : '' ?>>
                <span>
                    <strong>Material change requiring creator re-acceptance</strong>
                    <span class="oc-muted" style="display:block;font-size:.8rem;margin-top:3px;">
                        Enable for changes affecting rights, ownership, licensing, revenue share, set-off, moderation or termination.
                    </span>
                </span>
            </label>

            <div class="oc-form-group">
                <label class="oc-label" for="source_content">Terms content</label>
                <textarea class="oc-textarea" id="source_content" name="source_content" rows="22" required <?= $isLocked ? 'readonly' : '' ?>
                          style="font-family:ui-monospace,SFMono-Regular,Menlo,monospace;line-height:1.6;"><?= htmlspecialchars($terms->source_content ?? '') ?></textarea>
            </div>

            <?php if (!$isLocked): ?>
                <div style="display:flex;justify-content:flex-end;gap:12px;border-top:1px solid var(--border);padding-top:20px;">
                    <a class="oc-btn oc-btn--ghost" href="<?= url('/open-collab/admin/terms') ?>">Cancel</a>
                    <button class="oc-btn oc-btn--amber" type="submit">Save draft</button>
                </div>
            <?php endif; ?>
        </div>
    </form>

    <?php if (!$isEdit): ?>
        <div class="oc-card" style="margin-top:20px;">
            <div class="oc-card__header"><div class="oc-card__title">Import from document</div></div>
            <div class="oc-card__body" style="padding:28px;">
                <form method="POST" enctype="multipart/form-data" action="<?= url('/api/' . $siteSlug . '/open-collab/admin/terms/from-document') ?>">
                    <input type="hidden" name="_token" value="<?= csrf_token() ?>">
                    <div style="display:grid;grid-template-columns:180px 1fr;gap:16px;">
                        <div class="oc-form-group">
                            <label class="oc-label" for="upload_semantic_version">Semantic version</label>
                            <input class="oc-input" id="upload_semantic_version" name="semantic_version" required placeholder="1.0.0">
                        </div>
                        <div class="oc-form-group">
                            <label class="oc-label" for="upload_title">Title</label>
                            <input class="oc-input" id="upload_title" name="title" required>
                        </div>
                    </div>
                    <div class="oc-form-group">
                        <label class="oc-label" for="terms_document">Document</label>
                        <input class="oc-input" id="terms_document" type="file" name="document" required accept=".pdf,.docx,.txt,.md">
                        <div class="oc-help">The original file is retained in the OpenCollab documents table and linked to the terms version.</div>
                    </div>
                    <button class="oc-btn oc-btn--ghost" type="submit">Import document as draft</button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</main>
</body>
</html>
