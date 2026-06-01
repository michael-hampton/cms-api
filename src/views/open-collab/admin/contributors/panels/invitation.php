<?php
$invitations = $invitations ?? collect([]);
$canSendInvitation = (bool) ($canSendInvitation ?? false);
?>

<?php if ($invitations && count($invitations) > 0): ?>
    <div class="oc-card">
        <div class="oc-card__header">
            <span class="oc-card__title">Invitation History</span>
            <span style="font-size:.72rem;background:var(--slate-pale);color:var(--slate);
                     padding:2px 8px;border-radius:10px;font-weight:600;">
                <?= count($invitations) ?>
            </span>
        </div>
        <table class="oc-table">
            <thead>
            <tr>
                <th>Sent</th>
                <th>Status</th>
                <th>Expires</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($invitations as $inv): ?>
                <?php
                $status = $inv->resolveStatus()->value;
                $statusBadge = match ($status) {
                    'pending' => 'oc-badge--waiting-approval',
                    'used' => 'oc-badge--published',
                    'expired' => 'oc-badge--draft',
                    'revoked' => 'oc-badge--revoked',
                    default => 'oc-badge--draft',
                };
                ?>
                <tr data-invitation-id="<?= (int) $inv->id ?>">
                    <td style="font-size:.78rem;color:var(--slate);">
                        <?= $inv->created_at ? $inv->created_at->format('d M Y') : '–' ?>
                    </td>
                    <td data-role="invitation-status">
                        <span class="oc-badge <?= $statusBadge ?>"><?= ucfirst($status) ?></span>
                    </td>
                    <td style="font-size:.78rem;color:var(--slate);">
                        <?= $inv->expires_at ? $inv->expires_at->format('d M Y') : '–' ?>
                    </td>
                    <td style="text-align:right;" data-role="invitation-action">
                        <?php if ($status === 'pending' && $canSendInvitation): ?>
                            <button data-action="revoke-invitation" data-invitation-id="<?= (int) $inv->id ?>"
                                    class="oc-btn oc-btn--ghost oc-btn--sm"
                                    style="font-size:.7rem;border-color:#fecaca;color:var(--red);">
                                Revoke
                            </button>
                        <?php elseif (in_array($status, ['expired', 'revoked'], true) && $canSendInvitation): ?>
                            <button data-action="resend-invitation" data-invitation-id="<?= (int) $inv->id ?>"
                                    class="oc-btn oc-btn--ghost oc-btn--sm" style="font-size:.7rem;">
                                Resend
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
