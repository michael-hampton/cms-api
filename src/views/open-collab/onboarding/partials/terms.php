<?php
$displayTerms = $terms ?? null;
?>

<?php if (!$displayTerms): ?>
    <div class="oc-alert oc-alert--warning">
        Terms &amp; Conditions are not currently available. Please contact support before continuing.
    </div>
<?php else: ?>
    <?php
    $termsId = is_array($displayTerms)
        ? (int)($displayTerms['id'] ?? 0)
        : (int)$displayTerms->id;
    $version = is_array($displayTerms)
        ? (string)($displayTerms['version'] ?? '')
        : (string)$displayTerms->semantic_version;
    $mode = is_array($displayTerms)
        ? (string)($displayTerms['mode'] ?? 'html')
        : 'html';
    $content = is_array($displayTerms)
        ? (string)($displayTerms['content'] ?? '')
        : (string)($displayTerms->rendered_content ?: $displayTerms->source_content);
    $documentUrl = is_array($displayTerms)
        ? ($displayTerms['documentUrl'] ?? null)
        : null;
    $downloadUrl = is_array($displayTerms)
        ? ($displayTerms['downloadUrl'] ?? null)
        : null;
    $filename = is_array($displayTerms)
        ? ($displayTerms['filename'] ?? 'Terms document')
        : 'Terms document';
    $mimeType = is_array($displayTerms)
        ? (string)($displayTerms['mimeType'] ?? '')
        : '';
    $isMaterialChange = is_array($displayTerms)
        ? (bool)($displayTerms['isMaterialChange'] ?? false)
        : (bool)$displayTerms->is_material_change;
    $changeSummary = is_array($displayTerms)
        ? ($displayTerms['changeSummary'] ?? null)
        : $displayTerms->change_summary;
    ?>

    <form id="onboarding-form" method="POST" action="<?= url('/api/' . $siteSlug . '/open-collab/onboarding/terms') ?>" novalidate>
        <input type="hidden" name="_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="terms_version_id" value="<?= $termsId ?>">

        <div class="oc-alert oc-alert--info" style="margin-bottom:20px;">
            <strong>Please review the OpenCollab Terms &amp; Conditions.</strong>
            <div style="margin-top:6px;">
                You must explicitly accept the current required version before continuing your creator onboarding.
            </div>
        </div>

        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:16px;">
            <span class="oc-badge oc-badge--muted">Version <?= htmlspecialchars($version) ?></span>
            <?php if ($isMaterialChange): ?>
                <span class="oc-badge oc-badge--danger">Material update</span>
            <?php endif; ?>
        </div>

        <?php if ($changeSummary): ?>
            <div style="border:1px solid var(--border);border-radius:var(--radius);padding:14px 16px;margin-bottom:16px;background:var(--cream);">
                <strong style="display:block;margin-bottom:5px;">What changed</strong>
                <div class="oc-muted" style="line-height:1.55;"><?= nl2br(htmlspecialchars((string)$changeSummary)) ?></div>
            </div>
        <?php endif; ?>

        <?php if ($mode === 'html' && trim($content) !== ''): ?>
            <div tabindex="0"
                 aria-label="Terms and Conditions content"
                 style="height:380px;overflow:auto;border:1px solid var(--border);border-radius:var(--radius);background:#fff;padding:24px;line-height:1.7;margin-bottom:18px;">
                <?= $content ?>
            </div>
        <?php elseif ($documentUrl): ?>
            <div style="border:1px solid var(--border);border-radius:var(--radius);background:#fff;padding:18px;margin-bottom:18px;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;margin-bottom:14px;">
                    <div>
                        <strong><?= htmlspecialchars((string)$filename) ?></strong>
                        <?php if ($mimeType): ?>
                            <div class="oc-muted" style="font-size:.78rem;margin-top:4px;">
                                <?= htmlspecialchars($mimeType) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <a class="oc-btn oc-btn--ghost oc-btn--sm"
                           href="<?= htmlspecialchars((string)$documentUrl) ?>"
                           target="_blank"
                           rel="noopener">
                            Open document
                        </a>

                        <?php if ($downloadUrl): ?>
                            <a class="oc-btn oc-btn--ghost oc-btn--sm"
                               href="<?= htmlspecialchars((string)$downloadUrl) ?>">
                                Download
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($mimeType === 'application/pdf'): ?>
                    <iframe
                        src="<?= htmlspecialchars((string)$documentUrl) ?>"
                        title="Terms and Conditions document"
                        style="width:100%;height:520px;border:1px solid var(--border);border-radius:var(--radius);background:#f8fafc;">
                    </iframe>
                <?php else: ?>
                    <div class="oc-alert oc-alert--info">
                        Open the uploaded document to review the full Terms and Conditions before accepting.
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="oc-alert oc-alert--warning" style="margin-bottom:18px;">
                The Terms and Conditions content could not be displayed. Please contact support before accepting.
            </div>
        <?php endif; ?>

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
