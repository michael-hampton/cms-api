<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($terms->title) ?> — OpenCollab</title>
    @css('open-collab.css')
</head>
<body style="background:var(--cream);min-height:100vh;">
<main style="max-width:980px;margin:0 auto;padding:32px 20px;">
    <?php $status = is_object($terms->status) ? $terms->status->value : (string)$terms->status; ?>

    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:20px;margin-bottom:24px;">
        <div>
            <a href="<?= url('/open-collab/admin/terms') ?>" class="oc-link">&larr; Terms versions</a>
            <h1 style="margin:10px 0 8px;"><?= htmlspecialchars($terms->title) ?></h1>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <span class="oc-badge oc-badge--muted">v<?= htmlspecialchars($terms->semantic_version) ?></span>
                <span class="oc-badge <?= $status === 'published' ? 'oc-badge--success' : ($status === 'archived' ? 'oc-badge--muted' : 'oc-badge--warning') ?>"><?= ucfirst(htmlspecialchars($status)) ?></span>
                <?php if ($terms->is_material_change): ?>
                    <span class="oc-badge oc-badge--danger">Material change</span>
                <?php else: ?>
                    <span class="oc-badge oc-badge--muted">Editorial change</span>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($status === 'draft'): ?>
            <div style="display:flex;gap:10px;">
                <a class="oc-btn oc-btn--ghost" href="<?= url('/open-collab/admin/terms/' . $terms->id . '/edit') ?>">Edit draft</a>
                <form method="POST" action="<?= url('/api/' . $siteSlug . '/open-collab/admin/terms/' . $terms->id . '/publish') ?>">
                    <input type="hidden" name="_token" value="<?= csrf_token() ?>">
                    <button class="oc-btn oc-btn--amber" type="submit">Publish version</button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($terms->change_summary): ?>
        <div class="oc-alert oc-alert--info" style="margin-bottom:20px;">
            <strong>Change summary</strong>
            <div style="margin-top:6px;"><?= nl2br(htmlspecialchars($terms->change_summary)) ?></div>
        </div>
    <?php endif; ?>

    <div class="oc-card" style="margin-bottom:20px;">
        <div class="oc-card__header"><div class="oc-card__title">Version metadata</div></div>
        <div class="oc-card__body" style="padding:24px;">
            <dl style="display:grid;grid-template-columns:180px 1fr;gap:12px 18px;margin:0;">
                <dt class="oc-muted">Source</dt><dd style="margin:0;"><?= htmlspecialchars($terms->source_type ?? 'manual') ?></dd>
                <dt class="oc-muted">Published at</dt><dd style="margin:0;"><?= $terms->published_at ? htmlspecialchars((string)$terms->published_at) : 'Not published' ?></dd>
                <dt class="oc-muted">Rendered hash</dt>
                <dd style="margin:0;word-break:break-all;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;">
                    <?= $terms->rendered_hash ? htmlspecialchars($terms->rendered_hash) : 'Generated on publication' ?>
                </dd>
                <?php if ($terms->source_document_id): ?>
                    <dt class="oc-muted">Source document</dt>
                    <dd style="margin:0;"><a class="oc-link" href="<?= url('/api/' . $siteSlug . '/open-collab/documents/' . $terms->source_document_id . '/download') ?>">Download original document</a></dd>
                <?php endif; ?>
            </dl>
        </div>
    </div>

    <div class="oc-card">
        <div class="oc-card__header">
            <div>
                <div class="oc-card__title"><?= $status === 'draft' ? 'Draft content' : 'Immutable rendered snapshot' ?></div>
                <div class="oc-muted" style="font-size:.82rem;">This is the content used for creator display and acceptance evidence.</div>
            </div>
        </div>
        <div class="oc-card__body" style="padding:32px;line-height:1.72;background:#fff;">
            <?php if ($status === 'draft'): ?>
                <?= $terms->source_format === 'html' ? $terms->source_content : nl2br(htmlspecialchars($terms->source_content)) ?>
            <?php else: ?>
                <?= $terms->rendered_content ?>
            <?php endif; ?>
        </div>
    </div>
</main>
</body>
</html>
