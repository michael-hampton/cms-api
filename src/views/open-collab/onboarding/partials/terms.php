<?php
$displayTerms = $terms ?? null;
?>

<?php if (!$displayTerms): ?>
    <div class="oc-alert oc-alert--warning">
        Terms &amp; Conditions are not currently available. Please contact support before continuing.
    </div>
<?php else: ?>
    <form id="onboarding-form" method="POST" action="<?= url('/api/' . $siteSlug . '/open-collab/onboarding/terms') ?>" novalidate>
        <input type="hidden" name="_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="terms_version_id" value="<?= (int)$displayTerms->id ?>">

        <div class="oc-alert oc-alert--info" style="margin-bottom:20px;">
            <strong>Please review the OpenCollab Terms &amp; Conditions.</strong>
            <div style="margin-top:6px;">
                You must explicitly accept the current required version before continuing your creator onboarding.
            </div>
        </div>

        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:16px;">
            <span class="oc-badge oc-badge--muted">Version <?= htmlspecialchars($displayTerms->semantic_version) ?></span>
            <?php if ($displayTerms->is_material_change): ?>
                <span class="oc-badge oc-badge--danger">Material update</span>
            <?php endif; ?>
        </div>

        <?php if ($displayTerms->change_summary): ?>
            <div style="border:1px solid var(--border);border-radius:var(--radius);padding:14px 16px;margin-bottom:16px;background:var(--cream);">
                <strong style="display:block;margin-bottom:5px;">What changed</strong>
                <div class="oc-muted" style="line-height:1.55;"><?= nl2br(htmlspecialchars($displayTerms->change_summary)) ?></div>
            </div>
        <?php endif; ?>

        <div tabindex="0"
             aria-label="Terms and Conditions content"
             style="height:380px;overflow:auto;border:1px solid var(--border);border-radius:var(--radius);background:#fff;padding:24px;line-height:1.7;margin-bottom:18px;">
            <?= $displayTerms->rendered_content ?>
        </div>

        <label style="display:flex;gap:12px;align-items:flex-start;border:1px solid var(--border);border-radius:var(--radius);padding:16px;background:#fff;margin-bottom:20px;cursor:pointer;">
            <input type="checkbox" name="agreed" value="1" required style="margin-top:3px;">
            <span>
                <strong>I agree to the OpenCollab Terms &amp; Conditions.</strong>
                <span class="oc-muted" style="display:block;font-size:.8rem;margin-top:4px;line-height:1.45;">
                    I understand these terms govern ownership, licence rights, revenue share, set-off, moderation and termination.
                </span>
            </span>
        </label>

        <div style="display:flex;justify-content:flex-end;border-top:1px solid var(--border);padding-top:20px;">
            <button type="submit" class="oc-btn oc-btn--amber" id="submit-btn" style="min-width:210px;display:flex;justify-content:center;align-items:center;gap:8px;">
                Accept &amp; continue
                <svg viewBox="0 0 20 20" fill="currentColor" width="16">
                    <path fill-rule="evenodd" d="M10.293 5.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L12.586 11H5a1 1 0 110-2h7.586l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd"/>
                </svg>
            </button>
        </div>
    </form>
<?php endif; ?>
