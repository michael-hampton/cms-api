<?php
/** @var array|null $document */
?>
<?php if (!empty($document)): ?>
    <?php if (($document['mode'] ?? 'html') === 'html'): ?>
        <div class="legal-scroll" style="height:260px;overflow-y:scroll;border:1.5px solid var(--border);border-radius:var(--radius);padding:18px 20px;font-size:.875rem;line-height:1.75;color:var(--navy);background:#fff;margin-bottom:20px;">
            <?= $document['content'] ?>
        </div>
    <?php else: ?>
        <div style="border:1.5px solid var(--border);border-radius:var(--radius);padding:18px 20px;background:#fff;margin-bottom:20px;">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:14px;">
                <div>
                    <div style="font-weight:700;color:var(--navy);"><?= htmlspecialchars($document['title'] ?? 'Document', ENT_QUOTES, 'UTF-8') ?></div>
                    <div style="font-size:.82rem;color:var(--slate);">
                        Version <?= (int)($document['version'] ?? 1) ?>
                        <?php if (!empty($document['filename'])): ?>
                            · <?= htmlspecialchars($document['filename'], ENT_QUOTES, 'UTF-8') ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if (($document['mimeType'] ?? null) === 'application/pdf' && !empty($document['documentUrl'])): ?>
                <iframe src="<?= htmlspecialchars($document['documentUrl'], ENT_QUOTES, 'UTF-8') ?>"
                        style="width:100%;height:360px;border:1px solid var(--border);border-radius:var(--radius);background:#f8fafc;margin-bottom:14px;"></iframe>
            <?php else: ?>
                <p style="margin:0 0 14px;color:var(--slate);">This document must be opened to review.</p>
            <?php endif; ?>

            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <?php if (!empty($document['documentUrl'])): ?>
                    <a class="oc-btn oc-btn--ghost" href="<?= htmlspecialchars($document['documentUrl'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Open document</a>
                <?php endif; ?>
                <?php if (!empty($document['downloadUrl'])): ?>
                    <a class="oc-btn oc-btn--ghost" href="<?= htmlspecialchars($document['downloadUrl'], ENT_QUOTES, 'UTF-8') ?>">Download document</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>
