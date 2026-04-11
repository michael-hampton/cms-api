@section('logic')
<?php
/**
 * Template: open-collab/dashboard/show.php
 * Variables: $articles, $earnings, $activity, $currentUser
 */

$publishedCount = 0;
$draftCount = 0;
foreach ($articles as $a) {
    if ($a->status === 'published') $publishedCount++;
    if ($a->status === 'draft') $draftCount++;
}

$totalPence = (int)($earnings['total'] ?? 0);

// Inject into layout
$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
$breadcrumbs = [['label' => 'Dashboard']];
$headerActions = '<a href="/articles/create" class="oc-btn oc-btn--amber">
  <svg viewBox="0 0 20 20" fill="currentColor" width="15"><path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/></svg>
  New article
</a>';
?>
@endsection

@extends('open-collab/layouts/opencollab')

@section('content')
<!-- Stats -->
<div class="oc-stats" style="animation:fadeSlideIn .4s ease;">
    <div class="oc-stat oc-stat--accent">
        <div class="oc-stat__label">Total Earnings</div>
        <div class="oc-stat__value">£<?= number_format($totalPence / 100, 2) ?></div>
        <div class="oc-stat__sub">Lifetime revenue</div>
        </div>
    <div class="oc-stat">
        <div class="oc-stat__label">Published</div>
        <div class="oc-stat__value"><?= $publishedCount ?></div>
        <div class="oc-stat__sub">Live articles</div>
        </div>
    <div class="oc-stat">
        <div class="oc-stat__label">Drafts</div>
        <div class="oc-stat__value"><?= $draftCount ?></div>
        <div class="oc-stat__sub">In progress</div>
        </div>
    <div class="oc-stat oc-stat--green">
        <div class="oc-stat__label">Paid articles</div>
        <div class="oc-stat__value"><?= count($earnings['breakdown'] ?? []) ?></div>
        <div class="oc-stat__sub">With revenue</div>
        </div>
    </div>

<!-- Main grid -->
<div class="oc-grid-sidebar">

    <!-- Articles table -->
    <div class="oc-card" style="animation:fadeSlideIn .45s ease;">
        <div class="oc-card__header">
            <span class="oc-card__title">Your Articles</span>
            <a href="/articles" class="oc-btn oc-btn--ghost oc-btn--sm">View all</a>
            </div>

        <?php if (empty($articles) || count($articles) === 0): ?>
            <div style="padding:48px 24px;text-align:center;color:var(--slate);">
                <svg viewBox="0 0 20 20" fill="currentColor" width="32"
                     style="opacity:.25;margin-bottom:12px;display:block;margin-left:auto;margin-right:auto;">
                    <path fill-rule="evenodd"
                          d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"
                          clip-rule="evenodd"/>
                </svg>
                <div style="font-weight:500;margin-bottom:6px;">No articles yet</div>
                <div style="font-size:.85rem;margin-bottom:16px;">Start writing your first piece to see it here.</div>
                <a href="/articles/create" class="oc-btn oc-btn--primary oc-btn--sm">Create article</a>
            </div>
        <?php else: ?>
            <table class="oc-table">
                <thead>
                <tr>
                    <th>Article</th>
                    <th>Status</th>
                    <th>Type</th>
                    <th style="text-align:right;">Revenue</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($articles as $article): ?>
                    <?php
                    $revenue = 0;
                    foreach ($earnings['breakdown'] ?? [] as $b) {
                        if ((int)$b['page_id'] === (int)$article->id) {
                            $revenue = (int)$b['total'];
                            break;
                        }
                    }
                    ?>
                    <tr>
                        <td>
                            <div class="oc-table__title"><?= htmlspecialchars($article->title ?: 'Untitled draft') ?></div>
                            <div class="oc-table__meta">
                                Updated <?= $article->updated_at ? $article->updated_at->format('d M Y') : '–' ?>
                            </div>
                        </td>
                        <td>
              <span class="oc-badge oc-badge--<?= htmlspecialchars($article->status) ?>">
                <?= ucfirst(htmlspecialchars($article->status)) ?>
              </span>
                        </td>
                        <td>
                            <?php if ($article->is_paid): ?>
                                <span class="oc-badge oc-badge--paid">PAID</span>
                            <?php else: ?>
                                <span class="oc-badge oc-badge--free">Free</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right;font-weight:600;color:var(--navy);">
                            <?= $revenue > 0 ? '£' . number_format($revenue / 100, 2) : '—' ?>
                        </td>
                        <td style="text-align:right;">
                            <a href="/articles/<?= (int)$article->id ?>/edit" class="oc-btn oc-btn--ghost oc-btn--sm">Edit</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Sidebar -->
    <div style="display:flex;flex-direction:column;gap:20px;">

        <!-- Activity feed -->
        <div class="oc-card" style="animation:fadeSlideIn .5s ease;">
            <div class="oc-card__header">
                <span class="oc-card__title">Recent Activity</span>
            </div>
            <div class="oc-card__body" style="padding:16px 20px;">
                <?php if (empty($activity) || count($activity) === 0): ?>
                    <p style="font-size:.85rem;color:var(--slate);text-align:center;padding:16px 0;">No recent
                        activity.</p>
                <?php else: ?>
                    <ul class="oc-activity">
                        <?php foreach ($activity as $event): ?>
                            <li class="oc-activity__item">
                                <div class="oc-activity__dot"></div>
                                <div class="oc-activity__text">
                                    <?= htmlspecialchars($event->type ? str_replace('_', ' ', ucfirst($event->type)) : 'Activity') ?>
                                </div>
                                <div class="oc-activity__time">
                                    <?= $event->created_at ? $event->created_at->format('d M') : '' ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick links -->
        <div class="oc-card" style="animation:fadeSlideIn .55s ease;">
            <div class="oc-card__body" style="padding:18px 20px;">
                <div style="font-size:.75rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--slate);margin-bottom:12px;">
                    Quick links
                </div>
                <div style="display:flex;flex-direction:column;gap:8px;">
                    <a href="/contributor/earnings"
                       style="font-size:.875rem;color:var(--navy);text-decoration:none;display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid #f5f2ee;">
                        <svg viewBox="0 0 20 20" fill="currentColor" width="14"
                             style="color:var(--amber);flex-shrink:0;">
                            <path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/>
                            <path fill-rule="evenodd"
                                  d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.077 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.077-2.354-1.253V5z"
                                  clip-rule="evenodd"/>
                        </svg>
                        Earnings &amp; payouts
                    </a>
                    <a href="/contributor/settings"
                       style="font-size:.875rem;color:var(--navy);text-decoration:none;display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid #f5f2ee;">
                        <svg viewBox="0 0 20 20" fill="currentColor" width="14"
                             style="color:var(--amber);flex-shrink:0;">
                            <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"
                                  clip-rule="evenodd"/>
                        </svg>
                        Profile settings
                    </a>
                    <a href="/contributor/settings#danger"
                       style="font-size:.875rem;color:var(--red);text-decoration:none;display:flex;align-items:center;gap:8px;padding:6px 0;">
                        <svg viewBox="0 0 20 20" fill="currentColor" width="14" style="flex-shrink:0;">
                            <path fill-rule="evenodd"
                                  d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z"
                                  clip-rule="evenodd"/>
                        </svg>
                        Close account
                    </a>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection
