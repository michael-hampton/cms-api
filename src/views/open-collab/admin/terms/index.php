<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms &amp; Conditions — OpenCollab</title>
    @css('open-collab.css')
</head>
<body style="background:var(--cream);min-height:100vh;">
<main class="oc-page" style="max-width:1180px;margin:0 auto;padding:32px 20px;">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:20px;margin-bottom:24px;">
        <div>
            <div class="oc-eyebrow">OpenCollab legal</div>
            <h1 style="margin:4px 0 8px;">Terms &amp; Conditions</h1>
            <p class="oc-muted" style="max-width:720px;">
                Create, review and publish immutable terms versions. Published versions remain available for audit and creator acceptance evidence.
            </p>
        </div>
        <a class="oc-btn oc-btn--amber" href="<?= url('/open-collab/admin/terms/create') ?>">Create draft</a>
    </div>

    <div class="oc-card">
        <div class="oc-card__header" style="display:flex;justify-content:space-between;align-items:center;">
            <div>
                <div class="oc-card__title">Version history</div>
                <div class="oc-muted" style="font-size:.82rem;">Newest publication first</div>
            </div>
        </div>

        <div class="oc-card__body" style="padding:0;overflow-x:auto;">
            <?php if (empty($termsVersions)): ?>
                <div style="padding:40px;text-align:center;">
                    <h3 style="margin-bottom:8px;">No terms versions yet</h3>
                    <p class="oc-muted">Create a draft manually or import a document.</p>
                </div>
            <?php else: ?>
                <table style="width:100%;border-collapse:collapse;min-width:880px;">
                    <thead>
                    <tr style="text-align:left;border-bottom:1px solid var(--border);">
                        <th style="padding:14px 18px;">Version</th>
                        <th style="padding:14px 18px;">Title</th>
                        <th style="padding:14px 18px;">Change</th>
                        <th style="padding:14px 18px;">Status</th>
                        <th style="padding:14px 18px;">Published</th>
                        <th style="padding:14px 18px;text-align:right;">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($termsVersions as $terms): ?>
                        <?php
                        $status = is_object($terms->status) ? $terms->status->value : (string)$terms->status;
                        $badgeClass = match ($status) {
                            'published' => 'oc-badge--success',
                            'archived' => 'oc-badge--muted',
                            default => 'oc-badge--warning',
                        };
                        ?>
                        <tr style="border-bottom:1px solid var(--border);">
                            <td style="padding:16px 18px;font-weight:700;">v<?= htmlspecialchars($terms->semantic_version) ?></td>
                            <td style="padding:16px 18px;">
                                <div style="font-weight:600;"><?= htmlspecialchars($terms->title) ?></div>
                                <?php if ($terms->change_summary): ?>
                                    <div class="oc-muted" style="font-size:.78rem;margin-top:3px;max-width:360px;">
                                        <?= htmlspecialchars($terms->change_summary) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="padding:16px 18px;">
                                <?php if ($terms->is_material_change): ?>
                                    <span class="oc-badge oc-badge--danger">Material</span>
                                <?php else: ?>
                                    <span class="oc-badge oc-badge--muted">Editorial</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding:16px 18px;"><span class="oc-badge <?= $badgeClass ?>"><?= ucfirst($status) ?></span></td>
                            <td style="padding:16px 18px;" class="oc-muted">
                                <?= $terms->published_at ? htmlspecialchars((string)$terms->published_at) : 'Not published' ?>
                            </td>
                            <td style="padding:16px 18px;text-align:right;white-space:nowrap;">
                                <a class="oc-btn oc-btn--ghost oc-btn--sm" href="<?= url('/open-collab/admin/terms/' . $terms->id) ?>">View</a>
                                <?php if ($status === 'draft'): ?>
                                    <a class="oc-btn oc-btn--ghost oc-btn--sm" href="<?= url('/open-collab/admin/terms/' . $terms->id . '/edit') ?>">Edit</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</main>
</body>
</html>
