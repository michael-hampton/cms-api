@section('logic')
<?php
$pageTitle = 'Invitations';
$activeNav = 'invitations';
$breadcrumbs = [['label' => 'Invitations']];
$allowedComponentKeys = $allowedComponentKeys ?? [];
$canCreateInvitation = in_array('invitations.create_action', $allowedComponentKeys, true);
?>
@endsection

@extends('open-collab/layouts/admin')

@section('content')

<div class="oc-grid-sidebar" style="align-items:start;gap:24px;">
    <div>
        <div class="oc-card">
            <div class="oc-card__header">
                <span class="oc-card__title">Invitation History</span>
                <span style="font-size:.72rem;background:var(--slate-pale);color:var(--slate);padding:2px 8px;border-radius:10px;font-weight:600;">
                    <?= count($invitations ?? []) ?>
                </span>
            </div>

            <?php if (empty($invitations) || count($invitations) === 0): ?>
                <div style="padding:48px 24px;text-align:center;color:var(--slate);">
                    No invitations sent yet.
                </div>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="oc-table">
                        <thead>
                        <tr>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Sent</th>
                            <th>Expires</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($invitations as $invitation): ?>
                            <?php
                            $status = $invitation->resolveStatus()->value;
                            $statusBadge = match ($status) {
                                'pending' => 'oc-badge--waiting-approval',
                                'used' => 'oc-badge--published',
                                'expired' => 'oc-badge--draft',
                                'revoked' => 'oc-badge--revoked',
                                default => 'oc-badge--draft',
                            };
                            ?>
                            <tr>
                                <td style="font-weight:500;color:var(--navy);"><?= htmlspecialchars($invitation->email) ?></td>
                                <td><span class="oc-badge <?= $statusBadge ?>"><?= ucfirst($status) ?></span></td>
                                <td style="font-size:.78rem;color:var(--slate);"><?= $invitation->created_at ? $invitation->created_at->format('d M Y, H:i') : '—' ?></td>
                                <td style="font-size:.78rem;color:var(--slate);"><?= $invitation->expires_at ? $invitation->expires_at->format('d M Y, H:i') : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($canCreateInvitation): ?>
        <div style="position:sticky;top:84px;">
            <div class="oc-card">
                <div class="oc-card__header"><span class="oc-card__title">Send Invitation</span></div>
                <div class="oc-card__body">
                    <div class="oc-form-group">
                        <label class="oc-label" for="invite-email">Email address</label>
                        <input id="invite-email" type="email" class="oc-input" placeholder="writer@example.com">
                    </div>
                    <div id="invite-errors" class="oc-form-errors" style="display:none;margin-bottom:12px;"></div>
                    <button type="button" id="invite-submit-btn" class="oc-btn oc-btn--primary oc-btn--block" onclick="sendInvitation()">
                        Send Invitation Link
                    </button>
                    <div id="invite-success" style="display:none;margin-top:12px;font-size:.82rem;color:var(--green);">
                        Invitation sent successfully.
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

@endsection

@section('scripts')
<?php if ($canCreateInvitation): ?>
<script>
    const SITE = '<?= htmlspecialchars($site ?? '') ?>';

    async function sendInvitation() {
        const input = document.getElementById('invite-email');
        const btn = document.getElementById('invite-submit-btn');
        const err = document.getElementById('invite-errors');
        const success = document.getElementById('invite-success');
        const email = input.value.trim();

        err.style.display = 'none';
        success.style.display = 'none';

        if (!email) {
            err.textContent = 'Email is required.';
            err.style.display = 'block';
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<div class="oc-spinner"></div> Sending...';

        try {
            const res = await fetch(`/api/${SITE}/open-collab/invitations`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${localStorage.getItem('oc_token') || ''}`
                },
                body: JSON.stringify({email})
            });

            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                err.textContent = data.error || data.message || 'Failed to send invitation.';
                err.style.display = 'block';
                return;
            }

            input.value = '';
            success.style.display = 'block';
        } finally {
            btn.disabled = false;
            btn.textContent = 'Send Invitation Link';
        }
    }
</script>
<?php endif; ?>
@endsection
