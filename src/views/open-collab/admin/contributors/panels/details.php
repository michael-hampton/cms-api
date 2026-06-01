<?php
$contributor = $contributor ?? [];
$site = $site ?? '';
$isActive = (bool) ($contributor['is_active'] ?? true);
$canManageRole = (bool) ($canManageRole ?? false);
$canManageSiteAccess = (bool) ($canManageSiteAccess ?? false);

$profile = $contributor['profile'] ?? [];
$sampleLinks = $profile['sample_links'] ?? $contributor['sample_links'] ?? [];
if (is_string($sampleLinks)) {
    $decodedSampleLinks = json_decode($sampleLinks, true);
    $sampleLinks = is_array($decodedSampleLinks) ? $decodedSampleLinks : [];
}
?>

<div class="oc-card">
    <div class="oc-card__header">
        <div style="display:flex;align-items:center;gap:14px;">
            <div style="width:48px;height:48px;border-radius:50%;background:var(--navy);
                        display:grid;place-items:center;font-weight:700;font-size:1.2rem;
                        color:var(--amber);flex-shrink:0;">
                <?= strtoupper(substr($contributor['name'] ?? 'C', 0, 1)) ?>
            </div>
            <div>
                <div style="font-size:1.1rem;font-weight:700;color:var(--navy);">
                    <?= htmlspecialchars($contributor['name'] ?? 'Unknown') ?>
                </div>
                <div style="font-size:.82rem;color:var(--slate);">
                    <?= htmlspecialchars($contributor['email'] ?? '') ?>
                </div>
            </div>
            <span data-role="status-badge" class="oc-badge <?= $isActive ? 'oc-badge--published' : 'oc-badge--revoked' ?>" style="margin-left:auto;">
                <?= $isActive ? 'Active' : 'Inactive' ?>
            </span>
        </div>
    </div>
    <div class="oc-card__body">
        <dl style="display:grid;grid-template-columns:140px 1fr;gap:10px 16px;font-size:.875rem;">
            <dt style="color:var(--slate);font-weight:500;">ID</dt>
            <dd style="color:var(--navy);font-family:monospace;">#<?= (int) ($contributor['id'] ?? 0) ?></dd>
            <dt style="color:var(--slate);font-weight:500;">Role</dt>
            <dd>
                <?php if ($canManageRole): ?>
                    <select id="role-select" class="oc-select" style="font-size:.8rem;padding:4px 8px;">
                        <?php foreach (['contributor', 'editor', 'admin'] as $role): ?>
                            <option value="<?= $role ?>" <?= ($contributor['role'] ?? 'contributor') === $role ? 'selected' : '' ?>>
                                <?= ucfirst($role) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <span style="color:var(--navy);"><?= htmlspecialchars(ucfirst($contributor['role'] ?? 'contributor')) ?></span>
                <?php endif; ?>
            </dd>
            <dt style="color:var(--slate);font-weight:500;">Joined</dt>
            <dd style="color:var(--navy);">
                <?= !empty($contributor['created_at']) ? $contributor['created_at'] : '–' ?>
            </dd>
            <dt style="color:var(--slate);font-weight:500;">Is contributor</dt>
            <dd><?= ($contributor['is_contributor'] ?? false) ? '<span class="oc-badge oc-badge--published">Yes</span>' : '<span class="oc-badge oc-badge--draft">No</span>' ?></dd>
        </dl>
    </div>
</div>

<div class="oc-card">
    <div class="oc-card__header">
        <span class="oc-card__title">Writing Samples</span>
    </div>
    <div class="oc-card__body">
        <?php if (empty($sampleLinks)): ?>
            <div style="font-size:.85rem;color:var(--slate);">No writing sample links saved.</div>
        <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:12px;">
                <?php foreach ($sampleLinks as $link): ?>
                    <div style="border:1px solid var(--border);border-radius:var(--radius);padding:12px;">
                        <a href="<?= htmlspecialchars($link['url'] ?? '#') ?>"
                           target="_blank"
                           rel="noopener noreferrer"
                           style="font-weight:700;color:var(--navy);text-decoration:none;">
                            <?= htmlspecialchars($link['title'] ?? $link['url'] ?? 'Writing sample') ?>
                        </a>
                        <?php if (!empty($link['description'])): ?>
                            <div style="font-size:.82rem;color:var(--slate);line-height:1.5;margin-top:4px;">
                                <?= htmlspecialchars($link['description']) ?>
                            </div>
                        <?php endif; ?>
                        <div style="font-size:.75rem;color:var(--slate);margin-top:6px;word-break:break-all;">
                            <?= htmlspecialchars($link['url'] ?? '') ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="oc-card">
    <div class="oc-card__header">
        <span class="oc-card__title">Site Access</span>
    </div>
    <div class="oc-card__body">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 0;">
            <div>
                <div style="font-weight:500;font-size:.875rem;color:var(--navy);">
                    <?= htmlspecialchars($site) ?>
                </div>
                <div style="font-size:.75rem;color:var(--slate);">Current site</div>
            </div>
            <?php if ($canManageSiteAccess): ?>
                <div style="display:flex;gap:8px;">
                    <button data-action="grant-access"
                            class="oc-btn oc-btn--ghost oc-btn--sm"
                            style="border-color:#bbf7d0;color:var(--green);">Grant
                    </button>
                    <button data-action="revoke-access"
                            class="oc-btn oc-btn--ghost oc-btn--sm"
                            style="border-color:#fecaca;color:var(--red);">Revoke
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
