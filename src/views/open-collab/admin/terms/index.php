@section('logic')
<?php
$allowedComponentKeys = $allowedComponentKeys ?? [];
$canCreateTerms = in_array('terms.create_action', $allowedComponentKeys, true);
$canEditTerms = in_array('terms.edit_action', $allowedComponentKeys, true);
$canPublishTerms = in_array('terms.publish_action', $allowedComponentKeys, true);
$pageTitle = 'Terms & Conditions';
$activeNav = 'terms';
$breadcrumbs = [['label' => 'Terms & Conditions']];

$selectedTerms = $selectedTerms ?? null;
$selectedStatus = $selectedTerms
    ? (is_object($selectedTerms->status) ? $selectedTerms->status->value : (string)$selectedTerms->status)
    : null;
$isLocked = $selectedTerms && $selectedStatus !== 'draft';
?>
@endsection

@extends('open-collab/layouts/admin')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:20px;margin-bottom:24px;">
    <div>
        <div class="oc-eyebrow">OpenCollab legal</div>
        <h1 style="margin:4px 0 8px;">Terms &amp; Conditions</h1>
        <p class="oc-muted" style="max-width:720px;">
            Create, review and publish immutable terms versions. Published versions remain available for audit and creator acceptance evidence.
        </p>
    </div>
    <?php if ($canCreateTerms): ?>
        <button class="oc-btn oc-btn--amber" type="button" data-terms-panel="create">Create draft</button>
    <?php endif; ?>
</div>

<div style="display:grid;grid-template-columns:minmax(0,1.25fr) minmax(360px,.75fr);gap:20px;align-items:start;">
    <div class="oc-card">
        <div class="oc-card__header">
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
                <table style="width:100%;border-collapse:collapse;min-width:760px;">
                    <thead>
                    <tr style="text-align:left;border-bottom:1px solid var(--border);">
                        <th style="padding:14px 18px;">Version</th>
                        <th style="padding:14px 18px;">Title</th>
                        <th style="padding:14px 18px;">Change</th>
                        <th style="padding:14px 18px;">Status</th>
                        <th style="padding:14px 18px;text-align:right;">Action</th>
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
                                    <div class="oc-muted" style="font-size:.78rem;margin-top:3px;max-width:300px;">
                                        <?= htmlspecialchars($terms->change_summary) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="padding:16px 18px;">
                                <span class="oc-badge <?= $terms->is_material_change ? 'oc-badge--danger' : 'oc-badge--muted' ?>">
                                    <?= $terms->is_material_change ? 'Material' : 'Editorial' ?>
                                </span>
                            </td>
                            <td style="padding:16px 18px;"><span class="oc-badge <?= $badgeClass ?>"><?= ucfirst($status) ?></span></td>
                            <td style="padding:16px 18px;text-align:right;">
                                <a class="oc-btn oc-btn--ghost oc-btn--sm" href="?terms_id=<?= (int)$terms->id ?>">
                                    <?= $status === 'draft' && $canEditTerms ? 'Edit' : 'View' ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div>
        <div class="oc-card" id="terms-editor-card">
            <div class="oc-card__header">
                <div>
                    <div class="oc-card__title"><?= $selectedTerms ? ($isLocked ? 'Version details' : 'Edit draft') : 'Create draft' ?></div>
                    <?php if ($selectedTerms): ?>
                        <div class="oc-muted" style="font-size:.82rem;">Version <?= htmlspecialchars($selectedTerms->semantic_version) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="oc-card__body" style="padding:24px;">
                <?php if ($isLocked): ?>
                    <div class="oc-alert oc-alert--info" style="margin-bottom:16px;">
                        Published and archived versions are immutable.
                    </div>
                <?php endif; ?>

                <?php if ($selectedTerms || $canCreateTerms): ?>
                    <form method="POST"
                          action="<?= $selectedTerms ? url('/api/' . $siteSlug . '/open-collab/admin/terms/' . $selectedTerms->id) : url('/api/' . $siteSlug . '/open-collab/admin/terms') ?>">
                        <input type="hidden" name="_token" value="<?= csrf_token() ?>">
                        <?php if ($selectedTerms): ?><input type="hidden" name="_method" value="PUT"><?php endif; ?>

                        <div class="oc-form-group">
                            <label class="oc-label">Semantic version</label>
                            <input class="oc-input" name="semantic_version" required
                                   value="<?= htmlspecialchars($selectedTerms->semantic_version ?? '') ?>"
                                   <?= $selectedTerms ? 'readonly' : '' ?> placeholder="1.0.0">
                        </div>

                        <div class="oc-form-group">
                            <label class="oc-label">Title</label>
                            <input class="oc-input" name="title" required
                                   value="<?= htmlspecialchars($selectedTerms->title ?? '') ?>"
                                   <?= $isLocked ? 'readonly' : '' ?>>
                        </div>

                        <div class="oc-form-group">
                            <label class="oc-label">Change summary</label>
                            <textarea class="oc-textarea" name="change_summary" rows="3" <?= $isLocked ? 'readonly' : '' ?>><?= htmlspecialchars($selectedTerms->change_summary ?? '') ?></textarea>
                        </div>

                        <label style="display:flex;gap:10px;align-items:flex-start;margin-bottom:18px;">
                            <input type="checkbox" name="is_material_change" value="1" style="margin-top:3px;"
                                <?= !empty($selectedTerms->is_material_change) ? 'checked' : '' ?> <?= $isLocked ? 'disabled' : '' ?>>
                            <span>
                                <strong>Material change</strong>
                                <span class="oc-muted" style="display:block;font-size:.78rem;margin-top:2px;">Requires creator re-acceptance.</span>
                            </span>
                        </label>

                        <div class="oc-form-group">
                            <label class="oc-label">Terms content</label>
                            <textarea class="oc-textarea" name="source_content" rows="18" required <?= $isLocked ? 'readonly' : '' ?>
                                      style="font-family:ui-monospace,SFMono-Regular,Menlo,monospace;line-height:1.55;"><?= htmlspecialchars($selectedTerms->source_content ?? '') ?></textarea>
                        </div>

                        <?php if (!$isLocked && ($selectedTerms ? $canEditTerms : $canCreateTerms)): ?>
                            <div style="display:flex;justify-content:flex-end;gap:10px;">
                                <button class="oc-btn oc-btn--amber" type="submit">Save draft</button>
                            </div>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>

                <?php if ($selectedTerms && $selectedStatus === 'draft' && $canPublishTerms): ?>
                    <form method="POST" action="<?= url('/api/' . $siteSlug . '/open-collab/admin/terms/' . $selectedTerms->id . '/publish') ?>" style="margin-top:12px;">
                        <input type="hidden" name="_token" value="<?= csrf_token() ?>">
                        <button class="oc-btn oc-btn--ghost" type="submit" style="width:100%;">Publish version</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!$selectedTerms && $canCreateTerms): ?>
            <div class="oc-card" style="margin-top:20px;">
                <div class="oc-card__header"><div class="oc-card__title">Import document</div></div>
                <div class="oc-card__body" style="padding:24px;">
                    <form method="POST" enctype="multipart/form-data" action="<?= url('/api/' . $siteSlug . '/open-collab/admin/terms/from-document') ?>">
                        <input type="hidden" name="_token" value="<?= csrf_token() ?>">
                        <div class="oc-form-group">
                            <label class="oc-label">Semantic version</label>
                            <input class="oc-input" name="semantic_version" required placeholder="1.0.0">
                        </div>
                        <div class="oc-form-group">
                            <label class="oc-label">Title</label>
                            <input class="oc-input" name="title" required>
                        </div>
                        <div class="oc-form-group">
                            <label class="oc-label">Document</label>
                            <input class="oc-input" type="file" name="document" required accept=".pdf,.docx,.txt,.md">
                        </div>
                        <button class="oc-btn oc-btn--ghost" type="submit" style="width:100%;">Import as draft</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
@endsection
