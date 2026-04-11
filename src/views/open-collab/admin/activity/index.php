@section('logic')
<?php
/**
 * Template: open-collab/admin/activity/index.php
 * Variables:
 *   $events      — Collection of ActivityEvent models
 *   $site        — string
 *   $currentUser — AuthenticatedUser
 */

$pageTitle = 'Activity Feed';
$activeNav = 'activity';
$breadcrumbs = [['label' => 'Activity Feed']];

$typeIcons = [
    'article_created' => ['color' => '#3b82f6', 'path' => 'M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z'],
    'article_updated' => ['color' => '#8b5cf6', 'path' => 'M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z'],
    'article_published' => ['color' => '#10b981', 'path' => 'M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z'],
    'comment_added' => ['color' => '#f59e0b', 'path' => 'M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7z'],
    'invitation_sent' => ['color' => '#6366f1', 'path' => 'M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884zM18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z'],
    'invitation_accepted' => ['color' => '#10b981', 'path' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
    'payment_received' => ['color' => '#f59e0b', 'path' => 'M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267zM10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.077 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.077-2.354-1.253V5z'],
];
$defaultIcon = ['color' => '#64748b', 'path' => 'M3 3a1 1 0 000 2v8a2 2 0 002 2h2.586l-1.293 1.293a1 1 0 101.414 1.414L10 15.414l2.293 2.293a1 1 0 001.414-1.414L12.414 15H15a2 2 0 002-2V5a1 1 0 100-2H3z'];
?>
@endsection

@extends('open-collab/layouts/admin')

@section('content')

<?php if (empty($events) || count($events) === 0): ?>
    <div class="oc-card" style="padding:64px 24px;text-align:center;">
        <svg viewBox="0 0 20 20" fill="currentColor" width="36"
             style="opacity:.15;display:block;margin:0 auto 16px;color:var(--navy);">
            <path fill-rule="evenodd"
                  d="M3 3a1 1 0 000 2v8a2 2 0 002 2h2.586l-1.293 1.293a1 1 0 101.414 1.414L10 15.414l2.293 2.293a1 1 0 001.414-1.414L12.414 15H15a2 2 0 002-2V5a1 1 0 100-2H3z"
                  clip-rule="evenodd"/>
        </svg>
        <div style="font-size:1.05rem;font-weight:600;color:var(--navy);margin-bottom:6px;">No activity yet</div>
        <div style="font-size:.875rem;color:var(--slate);">Events will appear here as contributors interact with the
            platform.
        </div>
    </div>
<?php else: ?>

    <div class="oc-card" style="overflow:hidden;">
        <div class="oc-card__header" style="border-bottom:1px solid var(--border);">
            <span class="oc-card__title">Recent Activity</span>
            <span style="font-size:.75rem;color:var(--slate);">
        <?= count($events) ?> events
      </span>
        </div>

        <div style="display:flex;flex-direction:column;">
            <?php foreach ($events as $i => $event):
                $type = $event->type ?? 'unknown';
                $icon = $typeIcons[$type] ?? $defaultIcon;
                $payload = is_string($event->payload ?? null)
                    ? (json_decode($event->payload, true) ?? [])
                    : ($event->payload ?? []);
                $label = match ($type) {
                    'article_created' => 'Created an article',
                    'article_updated' => 'Updated an article',
                    'article_published' => 'Published an article',
                    'comment_added' => 'Added a comment',
                    'invitation_sent' => 'Invitation sent',
                    'invitation_accepted' => 'Invitation accepted',
                    'payment_received' => 'Payment received',
                    default => ucfirst(str_replace('_', ' ', $type)),
                };
                $isLast = $i === count($events) - 1;
                ?>
                <div style="display:flex;gap:14px;padding:16px 20px;
                <?= !$isLast ? 'border-bottom:1px solid var(--border);' : '' ?>
                        align-items:flex-start;">

                    <!-- Icon dot -->
                    <div style="width:34px;height:34px;border-radius:50%;background:<?= $icon['color'] ?>1a;
                            display:grid;place-items:center;flex-shrink:0;margin-top:1px;">
                        <svg viewBox="0 0 20 20" fill="<?= $icon['color'] ?>" width="15">
                            <path fill-rule="evenodd" d="<?= $icon['path'] ?>" clip-rule="evenodd"/>
                        </svg>
                    </div>

                    <!-- Content -->
                    <div style="flex:1;min-width:0;">
                        <div style="display:flex;justify-content:space-between;align-items:baseline;
                        gap:12px;margin-bottom:2px;flex-wrap:wrap;">
                            <div style="font-size:.875rem;font-weight:500;color:var(--navy);">
                                <?= htmlspecialchars($label) ?>
                            </div>
                            <div style="font-size:.72rem;color:var(--slate-light);white-space:nowrap;flex-shrink:0;">
                                <?= $event->created_at ? date('d M Y, H:i', strtotime($event->created_at)) : '' ?>
                            </div>
                        </div>
                        <div style="font-size:.78rem;color:var(--slate);display:flex;gap:12px;flex-wrap:wrap;">
                            <?php if ($event->user_id): ?>
                                <span>User #<?= (int)$event->user_id ?></span>
                            <?php endif; ?>
                            <?php if (!empty($payload['page_id'])): ?>
                                <span>Article #<?= (int)$payload['page_id'] ?></span>
                            <?php endif; ?>
                            <?php if (!empty($payload['action'])): ?>
                                <span>· <?= htmlspecialchars($payload['action']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>
    </div>

<?php endif; ?>

@endsection