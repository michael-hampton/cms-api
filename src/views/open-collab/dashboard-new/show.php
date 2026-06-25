@section('logic')
<?php
/**
 * Template: open-collab/dashboard/show.php
 *
 * Variables:
 *   $widgets     — array of ['key' => string, 'title' => string]
 *   $currentUser — authenticated User model
 *   $site        — site slug string
 *   $ocToken     — SSE auth token (optional)
 */

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
$breadcrumbs = [['label' => 'Dashboard']];
$openCollabBase = '/' . trim((string) $site, '/') . '/open-collab';
$headerActions = '
<button onclick="DashboardWidgetManager.openManager()"
        class="oc-btn oc-btn--ghost oc-btn--sm"
        style="display:inline-flex;align-items:center;gap:6px;">
  <svg viewBox="0 0 20 20" fill="currentColor" width="14">
    <path d="M5 4a1 1 0 00-2 0v7.268a2 2 0 000 3.464V16a1 1 0 102 0v-1.268a2 2 0 000-3.464V4zM11 4a1 1 0 10-2 0v1.268a2 2 0 000 3.464V16a1 1 0 102 0V8.732a2 2 0 000-3.464V4zM16 3a1 1 0 011 1v7.268a2 2 0 010 3.464V16a1 1 0 11-2 0v-1.268a2 2 0 010-3.464V4a1 1 0 011-1z"/>
  </svg>
  Customise
</button>
<a href="' . $openCollabBase . '/articles/create" class="oc-btn oc-btn--amber" style="display:inline-flex;align-items:center;gap:6px;">
  <svg viewBox="0 0 20 20" fill="currentColor" width="14">
    <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
  </svg>
  New article
</a>';
$extraHead = ($extraHead ?? '') . "\n"
        . '<link rel="stylesheet" href="' . asset('open-collab-dashboard-sections.css', 'css') . '">';

$extraScripts = ($extraScripts ?? '') . "\n"
        . '<script src="' . asset('open-collab-dashboard-sections.js', 'js') . '"></script>';
?>
@endsection

@extends('open-collab/layouts/opencollab')

@section('content')

<script>
    window.DASHBOARD_WIDGETS = {!! json_encode($widgets) !!};
    window.DASHBOARD_SITE    = {!! json_encode($site) !!};
    window.DASHBOARD_TOKEN   = {!! json_encode($ocToken ?? '') !!};
    window.DASHBOARD_BASE_PATH = {!! json_encode($openCollabBase) !!};
</script>

<!-- Widget management panel (hidden until Customise is clicked) -->
<div id="oc-widget-manager"
     role="dialog"
     aria-modal="true"
     aria-label="Customise dashboard"
     style="display:none;position:fixed;inset:0;z-index:1000;">

    <!-- Backdrop -->
    <div id="oc-wm-backdrop"
         onclick="DashboardWidgetManager.closeManager()"
         style="position:absolute;inset:0;background:rgba(0,0,0,.45);backdrop-filter:blur(2px);"></div>

    <!-- Panel -->
    <div style="position:absolute;top:0;right:0;bottom:0;width:min(420px,100vw);
                background:#fff;box-shadow:-4px 0 24px rgba(0,0,0,.12);
                display:flex;flex-direction:column;overflow:hidden;">

        <!-- Header -->
        <div style="display:flex;align-items:center;justify-content:space-between;
                    padding:20px 24px;border-bottom:1px solid var(--border);flex-shrink:0;">
            <div>
                <div style="font-family:var(--font-display);font-size:1.05rem;font-weight:700;color:var(--navy);">
                    Customise dashboard
                </div>
                <div style="font-size:.78rem;color:var(--slate);margin-top:2px;">
                    Drag to reorder · toggle to show or hide
                </div>
            </div>
            <button onclick="DashboardWidgetManager.closeManager()"
                    style="background:none;border:none;cursor:pointer;padding:6px;color:var(--slate);border-radius:6px;"
                    aria-label="Close">
                <svg viewBox="0 0 20 20" fill="currentColor" width="18">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                </svg>
            </button>
        </div>

        <!-- Loading state -->
        <div id="oc-wm-loading" style="padding:48px 24px;text-align:center;color:var(--slate);flex:1;">
            <div style="font-size:.875rem;">Loading widgets…</div>
        </div>

        <!-- Widget list (populated by JS) -->
        <ul id="oc-wm-list"
            style="display:none;flex:1;overflow-y:auto;padding:12px 16px;margin:0;list-style:none;"
            aria-label="Widget list">
        </ul>

        <!-- Error state -->
        <div id="oc-wm-error"
             style="display:none;padding:32px 24px;text-align:center;color:var(--slate);flex:1;">
            <div style="font-weight:500;margin-bottom:6px;color:var(--red);">Could not load widgets</div>
            <button onclick="DashboardWidgetManager.openManager()"
                    class="oc-btn oc-btn--ghost oc-btn--sm" style="margin-top:8px;">Retry</button>
        </div>

        <!-- Footer -->
        <div style="padding:16px 24px;border-top:1px solid var(--border);flex-shrink:0;
                    display:flex;justify-content:flex-end;gap:8px;">
            <button onclick="DashboardWidgetManager.closeManager()"
                    class="oc-btn oc-btn--ghost oc-btn--sm">Done</button>
        </div>
    </div>
</div>

<!-- Dashboard widget grid -->
<div id="dashboard-widget-grid" class="oc-widget-grid">
    <?php $loopIndex = 0; ?>
    <?php foreach ($widgets as $widget): ?>
        <div
                id="widget-<?= htmlspecialchars($widget['key'], ENT_QUOTES, 'UTF-8') ?>"
                class="oc-widget"
                data-widget-key="<?= htmlspecialchars($widget['key'], ENT_QUOTES, 'UTF-8') ?>"
                aria-label="<?= htmlspecialchars($widget['title'], ENT_QUOTES, 'UTF-8') ?>"
                style="animation:fadeSlideIn .4s ease both;animation-delay:<?= $loopIndex * 0.05 ?>s;"
        >
            <div class="oc-widget__skeleton">
                <div class="oc-card">
                    <div class="oc-card__header">
                        <span class="oc-card__title"><?= htmlspecialchars($widget['title'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="oc-card__body oc-widget__loading">
                        <div class="oc-skeleton-line"></div>
                        <div class="oc-skeleton-line oc-skeleton-line--short"></div>
                    </div>
                </div>
            </div>
        </div>
        <?php $loopIndex++; ?>
    <?php endforeach; ?>

    <?php if (empty($widgets)): ?>
        <div class="oc-card" style="padding:48px 24px;text-align:center;color:var(--slate);">
            <div style="font-weight:500;margin-bottom:6px;">No widgets configured</div>
            <div style="font-size:.85rem;">
                <button onclick="DashboardWidgetManager.openManager()"
                        style="background:none;border:none;cursor:pointer;color:var(--amber);font-weight:600;font-size:.85rem;padding:0;">
                    Open customise panel
                </button>
                to add widgets to your dashboard.
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    let DashboardWidgetManager = (() => {

        // ── State ─────────────────────────────────────────────────────────────

        const site = '<?= \App\Framework\Support\SiteContext::slug() ?>';

        const state = {
            widgets:   {},
            site,
            baseUrl:   `/api/${site}/open-collab/dashboard/widgets`,
            sseSource: null,
            // Management panel state
            manager: {
                open:      false,
                manifest:  [],   // [{key, title, enabled, position}] as returned by API
                dragging:  null, // key of item being dragged
                dirty:     false,
            },
        };

        // ── Widget Renderers ──────────────────────────────────────────────────

        const widgetRenderers = {

            earnings(data) {
                const fmt          = (pence) => `£${((pence ?? 0) / 100).toFixed(2)}`;
                const breakdown    = data.breakdown    ?? [];
                const transactions = data.transactions ?? [];
                const payment      = data.payment_details;

                const breakdownRows = breakdown.length
                    ? breakdown.map(item => `
                        <tr>
                          <td>
                            <a href="${window.DASHBOARD_BASE_PATH}/articles/edit/${item.page_id}"
                               style="font-weight:500;color:var(--navy);text-decoration:none;">
                              ${escHtml(item.title ?? 'Untitled')}
                            </a>
                          </td>
                          <td style="text-align:right;font-family:var(--font-display);font-weight:700;font-size:1rem;color:var(--navy);">
                            ${fmt(item.total)}
                          </td>
                        </tr>`).join('')
                    : `<tr><td colspan="2" style="padding:48px 24px;text-align:center;color:var(--slate);">
                         <div style="font-weight:500;margin-bottom:6px;">No earnings yet</div>
                         <div style="font-size:.85rem;">Publish a paid article to start earning.</div>
                       </td></tr>`;

                const txSection = transactions.length ? `
                    <div class="oc-card" style="animation:fadeSlideIn .5s ease;">
                      <div class="oc-card__header">
                        <span class="oc-card__title">Transaction History</span>
                      </div>
                      <table class="oc-table">
                        <thead>
                          <tr><th>Date</th><th>Article</th><th>Type</th><th style="text-align:right;">Amount</th></tr>
                        </thead>
                        <tbody>
                          ${transactions.map(tx => {
                    const isRefund = (tx.status ?? '') === 'refunded';
                    return `<tr>
                                <td style="white-space:nowrap;color:var(--slate);">${tx.created_at ? fmtDate(tx.created_at) : '–'}</td>
                                <td>${escHtml(tx.page_title ?? '–')}</td>
                                <td>
                                  <span class="oc-badge oc-badge--${isRefund ? 'revoked' : 'published'}">
                                    ${ucFirst(tx.status ?? 'sale')}
                                  </span>
                                </td>
                                <td style="text-align:right;font-weight:600;color:${isRefund ? 'var(--red)' : 'var(--green)'};">
                                  ${isRefund ? '-' : ''}${fmt(tx.amount ?? 0)}
                                </td>
                              </tr>`;
                }).join('')}
                        </tbody>
                      </table>
                    </div>` : '';

                const payoutSidebar = payment
                    ? `<div style="background:var(--slate-pale);border:1px solid var(--border);border-radius:var(--radius);padding:14px 16px;margin-bottom:16px;">
                         <div style="font-size:.72rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--slate);margin-bottom:4px;">Connected account</div>
                         <div style="font-weight:500;font-size:.9rem;color:var(--navy);margin-bottom:4px;">${escHtml(payment.email ?? 'Stripe account')}</div>
                         <div style="font-size:.75rem;color:var(--green);font-weight:600;">● Active via Stripe</div>
                       </div>
                       <a href="${window.DASHBOARD_BASE_PATH}/settings#payment" class="oc-btn oc-btn--ghost oc-btn--sm oc-btn--block">Update payout details</a>`
                    : `<div style="padding:16px;text-align:center;border:1.5px dashed var(--border);border-radius:var(--radius);margin-bottom:16px;">
                         <svg viewBox="0 0 20 20" fill="currentColor" width="28" style="color:var(--slate-light);display:block;margin:0 auto 8px;">
                           <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/>
                           <path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/>
                         </svg>
                         <div style="font-size:.85rem;font-weight:500;margin-bottom:4px;">No payout method</div>
                         <div style="font-size:.78rem;color:var(--slate);margin-bottom:12px;">Set up to receive payments</div>
                         <a href="${window.DASHBOARD_BASE_PATH}/onboarding" class="oc-btn oc-btn--amber oc-btn--sm">Set up now</a>
                       </div>`;

                return `
                    <div style="display:flex;flex-direction:column;gap:20px;">
                      <div class="oc-stats" style="animation:fadeSlideIn .4s ease;">
                        <div class="oc-stat oc-stat--accent">
                          <div class="oc-stat__label">Lifetime Earnings</div>
                          <div class="oc-stat__value">${fmt(data.total)}</div>
                          <div class="oc-stat__sub">All time gross revenue</div>
                        </div>
                        <div class="oc-stat oc-stat--green">
                          <div class="oc-stat__label">Pending Payout</div>
                          <div class="oc-stat__value">${fmt(data.pending)}</div>
                          <div class="oc-stat__sub">Awaiting transfer</div>
                        </div>
                        <div class="oc-stat">
                          <div class="oc-stat__label">Revenue Sources</div>
                          <div class="oc-stat__value">${breakdown.length}</div>
                          <div class="oc-stat__sub">Paid articles earning</div>
                        </div>
                      </div>
                      <div class="oc-grid-sidebar" style="align-items:start;">
                        <div style="display:flex;flex-direction:column;gap:20px;">
                          <div class="oc-card" style="animation:fadeSlideIn .45s ease;">
                            <div class="oc-card__header">
                              <span class="oc-card__title">Revenue by Article</span>
                            </div>
                            <table class="oc-table">
                              <thead><tr><th>Article</th><th style="text-align:right;">Revenue</th></tr></thead>
                              <tbody>${breakdownRows}</tbody>
                            </table>
                          </div>
                          ${txSection}
                        </div>
                        <div style="position:sticky;top:calc(var(--header-h) + 20px);">
                          <div class="oc-card" style="animation:fadeSlideIn .5s ease;">
                            <div class="oc-card__header">
                              <span class="oc-card__title" style="font-size:.95rem;">Payout Method</span>
                            </div>
                            <div class="oc-card__body">
                              ${payoutSidebar}
                              <div style="font-size:.75rem;color:var(--slate);line-height:1.6;padding-top:12px;border-top:1px solid var(--border);">
                                Payouts are processed automatically when your balance exceeds <strong>£50.00</strong>.
                                Funds typically arrive within 2–5 business days.
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>`;
            },

            quick_links(data) {
                const icons = {
                    earnings: `<svg viewBox="0 0 20 20" fill="currentColor" width="14" style="color:var(--amber);flex-shrink:0;"><path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.077 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.077-2.354-1.253V5z" clip-rule="evenodd"/></svg>`,
                    profile:  `<svg viewBox="0 0 20 20" fill="currentColor" width="14" style="color:var(--amber);flex-shrink:0;"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/></svg>`,
                    danger:   `<svg viewBox="0 0 20 20" fill="currentColor" width="14" style="flex-shrink:0;"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>`,
                };
                const linkIcon = (link) => {
                    if (link.variant === 'danger')      return icons.danger;
                    if (link.href.includes('earnings')) return icons.earnings;
                    return icons.profile;
                };
                const linksHtml = (data.links ?? []).map((link, i) => {
                    const isLast = i === (data.links.length - 1);
                    const color  = link.variant === 'danger' ? 'var(--red)' : 'var(--navy)';
                    const border = isLast ? '' : 'border-bottom:1px solid #f5f2ee;';
                    return `<a href="${escHtml(link.href)}" style="font-size:.875rem;color:${color};text-decoration:none;display:flex;align-items:center;gap:8px;padding:6px 0;${border}">
                        ${linkIcon(link)}${escHtml(link.label)}
                    </a>`;
                }).join('');
                return `
                    <div class="oc-card" style="animation:fadeSlideIn .55s ease;">
                      <div class="oc-card__body" style="padding:18px 20px;">
                        <div style="font-size:.75rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--slate);margin-bottom:12px;">Quick links</div>
                        <div style="display:flex;flex-direction:column;gap:8px;">${linksHtml}</div>
                      </div>
                    </div>`;
            },

            review_queue(data) {
                const count = data.pending_count ?? 0;
                const rows  = (data.items ?? []).map(item => `
                    <a href="${window.DASHBOARD_BASE_PATH}/articles/edit/${item.id}"
                       style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #f5f2ee;text-decoration:none;color:var(--navy);font-size:.875rem;">
                        <span>${escHtml(item.title ?? 'Untitled')}</span>
                        <span style="font-size:.75rem;color:var(--slate);">${item.updated_at ? fmtDate(item.updated_at) : ''}</span>
                    </a>`).join('');
                return `
                    <div class="oc-card" style="animation:fadeSlideIn .55s ease;">
                      <div class="oc-card__body" style="padding:18px 20px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                          <div style="font-size:.75rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--slate);">Review Queue</div>
                          <span style="background:var(--amber);color:#fff;font-size:.7rem;font-weight:700;padding:2px 8px;border-radius:99px;">${count}</span>
                        </div>
                        ${rows || '<p style="font-size:.875rem;color:var(--slate);margin:0;">Nothing awaiting review.</p>'}
                      </div>
                    </div>`;
            },

            approvals(data) {
                const rows = (data.items ?? []).map(item => `
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #f5f2ee;">
                        <a href="${window.DASHBOARD_BASE_PATH}/articles/edit/${item.id}"
                           style="font-size:.875rem;color:var(--navy);text-decoration:none;">${escHtml(item.title ?? 'Untitled')}</a>
                        <a href="${window.DASHBOARD_BASE_PATH}/articles/${item.id}/approve"
                           style="font-size:.75rem;font-weight:600;color:var(--amber);text-decoration:none;">Approve →</a>
                    </div>`).join('');
                return `
                    <div class="oc-card" style="animation:fadeSlideIn .55s ease;">
                      <div class="oc-card__body" style="padding:18px 20px;">
                        <div style="font-size:.75rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--slate);margin-bottom:12px;">Pending Approvals</div>
                        ${rows || '<p style="font-size:.875rem;color:var(--slate);margin:0;">No pending approvals.</p>'}
                      </div>
                    </div>`;
            },

            drafts(data) {
                const articles = data.articles ?? [];
                const rows = articles.length
                    ? articles.map(a => `
                        <tr>
                          <td>
                            <div class="oc-table__title">${escHtml(a.title || 'Untitled draft')}</div>
                            <div class="oc-table__meta">Updated ${a.updated_at ? fmtDate(a.updated_at) : '–'}</div>
                          </td>
                          <td><span class="oc-badge oc-badge--${escHtml(a.status)}">${ucFirst(a.status)}</span></td>
                          <td>${a.is_paid ? '<span class="oc-badge oc-badge--paid">PAID</span>' : '<span class="oc-badge oc-badge--free">Free</span>'}</td>
                          <td style="text-align:right;">
                            <a href="${window.DASHBOARD_BASE_PATH}/articles/edit/${a.id}" class="oc-btn oc-btn--ghost oc-btn--sm">Edit</a>
                          </td>
                        </tr>`).join('')
                    : `<tr><td colspan="4" style="text-align:center;padding:40px;color:var(--slate);">
                         No articles yet.
                         <a href="${window.DASHBOARD_BASE_PATH}/articles/create" class="oc-btn oc-btn--primary oc-btn--sm" style="margin-left:8px;">Create one</a>
                       </td></tr>`;
                return `
                    <div class="oc-card">
                      <div class="oc-card__header">
                        <span class="oc-card__title">Your Articles</span>
                        <div style="display:flex;gap:8px;align-items:center;">
                          <span style="font-size:.8rem;color:var(--slate);">${data.published_count} published · ${data.draft_count} drafts</span>
                          <a href="${window.DASHBOARD_BASE_PATH}/articles" class="oc-btn oc-btn--ghost oc-btn--sm">View all</a>
                        </div>
                      </div>
                      <table class="oc-table">
                        <thead><tr><th>Article</th><th>Status</th><th>Type</th><th></th></tr></thead>
                        <tbody>${rows}</tbody>
                      </table>
                    </div>`;
            },

            activity(data) {
                const items = data.items ?? [];
                const list  = items.length
                    ? items.map(e => `
                        <li class="oc-activity__item">
                          <div class="oc-activity__dot"></div>
                          <div class="oc-activity__text">
                            ${escHtml(e.type ? e.type.replace(/_/g, ' ').replace(/^\w/, c => c.toUpperCase()) : 'Activity')}
                          </div>
                          <div class="oc-activity__time">${e.created_at ? fmtDate(e.created_at) : ''}</div>
                        </li>`).join('')
                    : `<li style="font-size:.85rem;color:var(--slate);text-align:center;padding:16px 0;">No recent activity.</li>`;
                return `
                    <div class="oc-card">
                      <div class="oc-card__header"><span class="oc-card__title">Recent Activity</span></div>
                      <div class="oc-card__body" style="padding:16px 20px;">
                        <ul class="oc-activity">${list}</ul>
                      </div>
                    </div>`;
            },

            onboarding(data) {
                const STEP_META = {
                    profile: {
                        title: 'Complete your profile',
                        description: 'Add a bio so readers know who you are.',
                        action: { label: 'Complete profile', href: `${window.DASHBOARD_BASE_PATH}/settings#profile` },
                        icon: `<svg viewBox="0 0 20 20" fill="currentColor" width="18"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/></svg>`,
                    },
                    payment: {
                        title: 'Set up payouts',
                        description: 'Connect Stripe to receive your earnings.',
                        action: { label: 'Set up payouts', href: `${window.DASHBOARD_BASE_PATH}/settings#stripe-connect` },
                        icon: `<svg viewBox="0 0 20 20" fill="currentColor" width="18"><path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/></svg>`,
                    },
                    contract: {
                        title: 'Sign contributor agreement',
                        description: 'Review and sign the platform contributor contract.',
                        action: { label: 'Review contract', href: `${window.DASHBOARD_BASE_PATH}/onboarding/contract` },
                        icon: `<svg viewBox="0 0 20 20" fill="currentColor" width="18"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/></svg>`,
                    },
                    guidelines: {
                        title: 'Acknowledge brand guidelines',
                        description: 'Confirm you have read the editorial standards.',
                        action: { label: 'Read guidelines', href: `${window.DASHBOARD_BASE_PATH}/onboarding/guidelines` },
                        icon: `<svg viewBox="0 0 20 20" fill="currentColor" width="18"><path d="M9 4.804A7.968 7.968 0 005.5 4c-1.255 0-2.443.29-3.5.804v10A7.969 7.969 0 015.5 14c1.669 0 3.218.51 4.5 1.385A7.962 7.962 0 0114.5 14c1.255 0 2.443.29 3.5.804v-10A7.968 7.968 0 0014.5 4c-1.255 0-2.443.29-3.5.804V12a1 1 0 11-2 0V4.804z"/></svg>`,
                    },
                    age_verification: {
                        title: 'Verify your age',
                        description: 'Confirm you meet the minimum contributor age requirement.',
                        action: { label: 'Verify age', href: `${window.DASHBOARD_BASE_PATH}/settings#profile` },
                        icon: `<svg viewBox="0 0 20 20" fill="currentColor" width="18"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>`,
                    },
                };
                const statusBadge = (status) => {
                    const cfg = {
                        completed:   { label: '✓ Complete',  bg: '#dcfce7', color: '#15803d', border: '#bbf7d0' },
                        pending:     { label: 'Pending',      bg: '#fef9c3', color: '#854d0e', border: '#fde68a' },
                        in_progress: { label: 'In progress',  bg: '#dbeafe', color: '#1e40af', border: '#bfdbfe' },
                        locked:      { label: 'Locked',       bg: '#f1f5f9', color: '#64748b', border: '#e2e8f0' },
                    }[status] || { label: 'Pending', bg: '#fef9c3', color: '#854d0e', border: '#fde68a' };
                    return `<span style="display:inline-flex;align-items:center;padding:3px 10px;border-radius:99px;font-size:.75rem;font-weight:600;background:${cfg.bg};color:${cfg.color};border:1px solid ${cfg.border};white-space:nowrap;">${cfg.label}</span>`;
                };
                const stepCard = (stepKey, status, reason, index) => {
                    const meta = STEP_META[stepKey] ?? {
                        title: stepKey.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()),
                        description: reason ?? '', action: null,
                        icon: `<svg viewBox="0 0 20 20" fill="currentColor" width="18"><circle cx="10" cy="10" r="8"/></svg>`,
                    };
                    const isComplete = status === 'completed';
                    const actionBtn  = (!isComplete && meta.action)
                        ? `<a href="${meta.action.href}" class="oc-btn oc-btn--primary oc-btn--sm" style="white-space:nowrap;">${meta.action.label}</a>`
                        : '';
                    return `
                        <div class="oc-card" style="animation:fadeSlideIn .4s ease ${(index * 0.05).toFixed(2)}s both;opacity:${isComplete ? '.7' : '1'};border-left:3px solid ${isComplete ? 'var(--green)' : 'var(--amber)'};">
                          <div class="oc-card__body" style="display:flex;align-items:flex-start;gap:14px;padding:16px 20px;">
                            <div style="width:36px;height:36px;border-radius:8px;flex-shrink:0;background:${isComplete ? '#dcfce7' : 'var(--slate-pale)'};color:${isComplete ? '#15803d' : 'var(--slate)'};display:grid;place-items:center;margin-top:1px;">${meta.icon}</div>
                            <div style="flex:1;min-width:0;">
                              <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:3px;">
                                <span style="font-weight:600;font-size:.9rem;color:var(--navy);">${escHtml(meta.title)}</span>
                                ${statusBadge(status)}
                              </div>
                              <div style="font-size:.8rem;color:var(--slate);line-height:1.5;">${escHtml(reason ?? meta.description)}</div>
                            </div>
                            <div style="flex-shrink:0;margin-top:2px;">${actionBtn}</div>
                          </div>
                        </div>`;
                };
                const pct     = data.progress_percent ?? 0;
                const total   = data.total_steps      ?? 0;
                const done    = data.completed_count  ?? 0;
                const allSteps = [
                    ...(data.completed_steps ?? []).map(key => ({ key, status: 'completed', reason: null })),
                    ...(data.pending_steps   ?? []).map(p   => ({ key: p.step, status: 'pending', reason: p.reason ?? null })),
                ];
                const completionState = `
                    <div style="text-align:center;padding:48px 24px;animation:fadeSlideIn .3s ease;">
                      <div style="width:60px;height:60px;background:#dcfce7;border-radius:50%;display:grid;place-items:center;margin:0 auto 16px;">
                        <svg viewBox="0 0 20 20" fill="#16a34a" width="28">
                          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                      </div>
                      <h2 style="font-family:var(--font-display);font-size:1.35rem;color:var(--navy);margin-bottom:8px;">You're ready to create content</h2>
                      <p style="font-size:.9rem;color:var(--slate);line-height:1.6;max-width:380px;margin:0 auto 20px;">All onboarding requirements are complete.</p>
                      <a href="${window.DASHBOARD_BASE_PATH}/dashboard" class="oc-btn oc-btn--primary">Go to dashboard</a>
                    </div>`;
                return `
                    <div style="max-width:700px;">
                      <div style="margin-bottom:28px;animation:fadeSlideIn .3s ease;">
                        <h1 style="font-family:var(--font-display);font-size:1.75rem;color:var(--navy);margin-bottom:6px;">Get set up as a contributor</h1>
                        <p style="font-size:.9rem;color:var(--slate);line-height:1.6;">Complete the steps below before you can publish content or request payouts.</p>
                      </div>
                      <div class="oc-card" style="margin-bottom:24px;animation:fadeSlideIn .35s ease;">
                        <div class="oc-card__body" style="padding:20px 24px;">
                          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                            <div>
                              <span style="font-weight:600;font-size:.95rem;color:var(--navy);">${done} of ${total} steps complete</span>
                              <span style="font-size:.8rem;color:var(--slate);margin-left:8px;">${pct}%</span>
                            </div>
                            ${data.is_complete ? `<span class="oc-badge oc-badge--published">✓ All done</span>` : ''}
                          </div>
                          <div style="height:8px;background:var(--slate-pale);border-radius:99px;overflow:hidden;">
                            <div style="height:100%;width:0%;background:linear-gradient(90deg,var(--green),#34d399);border-radius:99px;transition:width .6s cubic-bezier(.4,0,.2,1);" data-progress-bar></div>
                          </div>
                        </div>
                      </div>
                      ${data.is_complete
                    ? completionState
                    : `<div style="display:flex;flex-direction:column;gap:12px;animation:fadeSlideIn .4s ease;">${allSteps.map(({ key, status, reason }, i) => stepCard(key, status, reason, i)).join('')}</div>`}
                    </div>`;
            },

        };

        // ── Post-render hooks ─────────────────────────────────────────────────

        function runPostRenderHooks(key, container, data) {
            if (key !== 'onboarding') return;

            requestAnimationFrame(() => {
                const bar = container.querySelector('[data-progress-bar]');
                if (bar) bar.style.width = `${data.progress_percent ?? 0}%`;
            });

            if (state.sseSource) {
                state.sseSource.close();
                state.sseSource = null;
            }

            const sseUrl = data.sse_url ?? '';
            const token  = window.DASHBOARD_TOKEN ?? '';
            if (!sseUrl || !token || !window.EventSource) return;

            try {
                state.sseSource = new EventSource(`${sseUrl}?token=${encodeURIComponent(token)}`);
                const events = ['contract.signed','guidelines.acknowledged','payment.enabled','profile.updated','age.verified'];
                events.forEach(ev => state.sseSource.addEventListener(ev, () => refresh('onboarding')));
                state.sseSource.addEventListener('error', () => {
                    if (state.sseSource?.readyState === EventSource.CLOSED) {
                        setTimeout(() => runPostRenderHooks(key, container, data), 5000);
                    }
                });
            } catch (e) {
                console.error('SSE connection failed:', e);
            }
        }

        // ── Core ──────────────────────────────────────────────────────────────

        function init() {
            (window.DASHBOARD_WIDGETS ?? []).forEach(({ key, title }) => {
                const el = document.getElementById(`widget-${key}`);
                if (!el) return;
                state.widgets[key] = { key, title, el, status: 'idle' };
            });
        }

        async function load() {
            await Promise.allSettled(Object.keys(state.widgets).map(loadWidget));
        }

        async function refresh(key = null) {
            key ? await loadWidget(key) : await load();
        }

        function destroy() {
            state.sseSource?.close();
            state.sseSource = null;
            Object.keys(state.widgets).forEach(k => { state.widgets[k].status = 'idle'; });
            state.widgets = {};
        }

        // ── Widget management panel ───────────────────────────────────────────

        async function openManager() {
            const panel = document.getElementById('oc-widget-manager');
            panel.style.display = 'block';
            document.body.style.overflow = 'hidden';
            state.manager.open = true;

            _managerSetView('loading');

            try {
                const res = await fetch(state.baseUrl, {
                    headers: {
                        'Content-Type': 'application/json',
                        Authorization: `Bearer ${localStorage.getItem('oc_token') || ''}`,
                        Accept: 'application/json',
                    },
                    credentials: 'same-origin',
                });
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                const payload = await res.json();
                const widgets = Array.isArray(payload.widgets)
                    ? payload.widgets
                    : (Array.isArray(payload.data?.widgets) ? payload.data.widgets : []);
                state.manager.manifest = widgets;
                state.manager.dirty    = false;
                _managerRender();
                _managerSetView('list');
            } catch (e) {
                console.error('Widget manager load failed:', e);
                _managerSetView('error');
            }
        }

        function closeManager() {
            document.getElementById('oc-widget-manager').style.display = 'none';
            document.body.style.overflow = '';
            state.manager.open     = false;
            state.manager.dragging = null;
        }

        // ── Management panel internals ────────────────────────────────────────

        function _managerSetView(view) {
            document.getElementById('oc-wm-loading').style.display = view === 'loading' ? 'flex'  : 'none';
            document.getElementById('oc-wm-list').style.display    = view === 'list'    ? 'block' : 'none';
            document.getElementById('oc-wm-error').style.display   = view === 'error'   ? 'flex'  : 'none';
        }

        function _managerRender() {
            const list = document.getElementById('oc-wm-list');
            list.innerHTML = state.manager.manifest.map(item => `
                <li data-key="${escHtml(item.key)}"
                    draggable="true"
                    style="display:flex;align-items:center;gap:12px;padding:10px 8px;border-radius:8px;
                           margin-bottom:4px;background:#fff;border:1px solid var(--border);
                           cursor:grab;transition:opacity .15s,box-shadow .15s;"
                    aria-label="${escHtml(item.title)}">

                    <!-- Drag handle -->
                    <span style="color:var(--slate-light);flex-shrink:0;cursor:grab;line-height:0;" aria-hidden="true">
                        <svg viewBox="0 0 20 20" fill="currentColor" width="16">
                            <path d="M7 2a2 2 0 110 4 2 2 0 010-4zM7 8a2 2 0 110 4 2 2 0 010-4zM7 14a2 2 0 110 4 2 2 0 010-4zM13 2a2 2 0 110 4 2 2 0 010-4zM13 8a2 2 0 110 4 2 2 0 010-4zM13 14a2 2 0 110 4 2 2 0 010-4z"/>
                        </svg>
                    </span>

                    <!-- Title -->
                    <span style="flex:1;font-size:.875rem;font-weight:500;color:${item.enabled ? 'var(--navy)' : 'var(--slate)'};">
                        ${escHtml(item.title)}
                    </span>

                    <!-- Toggle -->
                    <button onclick="DashboardWidgetManager._managerToggle('${escHtml(item.key)}')"
                            role="switch"
                            aria-checked="${item.enabled}"
                            aria-label="${item.enabled ? 'Hide' : 'Show'} ${escHtml(item.title)}"
                            style="width:36px;height:20px;border-radius:99px;border:none;cursor:pointer;flex-shrink:0;
                                   background:${item.enabled ? 'var(--green)' : 'var(--slate-light)'};
                                   position:relative;transition:background .2s;">
                        <span style="position:absolute;top:2px;left:${item.enabled ? '18px' : '2px'};
                                     width:16px;height:16px;border-radius:50%;background:#fff;
                                     transition:left .2s;box-shadow:0 1px 3px rgba(0,0,0,.2);"></span>
                    </button>
                </li>`).join('');

            _managerBindDrag(list);
        }

        function _managerToggle(key) {
            const item = state.manager.manifest.find(w => w.key === key);
            if (!item) return;

            item.enabled = !item.enabled;
            state.manager.dirty = true;

            // Persist immediately — toggle is a single-widget operation
            saveWidgetConfig(key, item.enabled, item.position)
                .then(() => {
                    // Reflect on the live dashboard without full reload:
                    // if enabling, load the widget slot; if disabling, blank it
                    if (item.enabled) {
                        _dashboardAddWidgetSlot(item);
                        loadWidget(key);
                    } else {
                        _dashboardRemoveWidgetSlot(key);
                    }
                })
                .catch(e => console.error('Failed to save widget toggle:', e));

            _managerRender();
        }

        function _managerBindDrag(list) {
            let dragKey  = null;
            let dragOver = null;

            list.querySelectorAll('li[data-key]').forEach(li => {
                li.addEventListener('dragstart', e => {
                    dragKey = li.dataset.key;
                    e.dataTransfer.effectAllowed = 'move';
                    li.style.opacity = '.4';
                });

                li.addEventListener('dragend', () => {
                    li.style.opacity = '1';
                    list.querySelectorAll('li').forEach(el => el.style.boxShadow = '');

                    if (dragKey && dragOver && dragKey !== dragOver) {
                        _managerCommitReorder(dragKey, dragOver);
                    }
                    dragKey  = null;
                    dragOver = null;
                });

                li.addEventListener('dragover', e => {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';
                    if (li.dataset.key !== dragKey) {
                        dragOver = li.dataset.key;
                        list.querySelectorAll('li').forEach(el => el.style.boxShadow = '');
                        li.style.boxShadow = '0 -2px 0 var(--amber)';
                    }
                });
            });
        }

        function _managerCommitReorder(draggedKey, targetKey) {
            const manifest = state.manager.manifest;
            const fromIdx  = manifest.findIndex(w => w.key === draggedKey);
            const toIdx    = manifest.findIndex(w => w.key === targetKey);
            if (fromIdx === -1 || toIdx === -1) return;

            // Splice dragged item to new position
            const [moved] = manifest.splice(fromIdx, 1);
            manifest.splice(toIdx, 0, moved);

            // Reassign positions sequentially
            manifest.forEach((w, i) => { w.position = i; });
            state.manager.dirty = true;

            // Persist and re-render the dashboard grid to match
            const positions = manifest.map(w => ({ widget_key: w.key, position: w.position }));
            savePositions(positions)
                .then(() => _dashboardReorder(manifest))
                .catch(e => console.error('Failed to save widget order:', e));

            _managerRender();
        }

        // ── Live dashboard DOM helpers ────────────────────────────────────────

        function _dashboardAddWidgetSlot(item) {
            if (document.getElementById(`widget-${item.key}`)) return; // already exists

            const grid = document.getElementById('dashboard-widget-grid');
            const slot = document.createElement('div');
            slot.id               = `widget-${item.key}`;
            slot.className        = 'oc-widget';
            slot.dataset.widgetKey= item.key;
            slot.innerHTML        = `<div class="oc-widget__skeleton"><div class="oc-card">
                                       <div class="oc-card__header"><span class="oc-card__title">${escHtml(item.title)}</span></div>
                                       <div class="oc-card__body oc-widget__loading">
                                         <div class="oc-skeleton-line"></div>
                                         <div class="oc-skeleton-line oc-skeleton-line--short"></div>
                                       </div></div></div>`;
            grid.appendChild(slot);
            state.widgets[item.key] = { key: item.key, title: item.title, el: slot, status: 'idle' };
        }

        function _dashboardRemoveWidgetSlot(key) {
            const el = document.getElementById(`widget-${key}`);
            if (el) el.remove();
            delete state.widgets[key];
        }

        function _dashboardReorder(manifest) {
            const grid    = document.getElementById('dashboard-widget-grid');
            const enabled = manifest.filter(w => w.enabled);
            enabled.forEach(item => {
                const el = document.getElementById(`widget-${item.key}`);
                if (el) grid.appendChild(el); // re-append in order (moves to end, sequentially = correct)
            });
        }

        // ── User override API ─────────────────────────────────────────────────

        async function saveWidgetConfig(key, enabled, position, settings = {}) {
            const res = await fetch(`${state.baseUrl}/${key}/settings`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    Authorization: `Bearer ${localStorage.getItem('oc_token') || ''}`,
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ enabled, position, settings }),
            });
            if (!res.ok) {
                const err = await res.json().catch(() => ({}));
                throw new Error(err.error ?? `HTTP ${res.status}`);
            }
            return res.json();
        }

        async function savePositions(positions) {
            const res = await fetch(`${state.baseUrl}/positions`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    Authorization: `Bearer ${localStorage.getItem('oc_token') || ''}`,
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ positions }),
            });
            if (!res.ok) {
                const err = await res.json().catch(() => ({}));
                throw new Error(err.error ?? `HTTP ${res.status}`);
            }
            return res.json();
        }

        // ── Private ───────────────────────────────────────────────────────────

        async function loadWidget(key) {
            const entry = state.widgets[key];
            if (!entry) return;
            setStatus(key, 'loading');
            try {
                const res = await fetch(`${state.baseUrl}/${key}`, {
                    headers: {
                        'Content-Type': 'application/json',
                        Authorization: `Bearer ${localStorage.getItem('oc_token') || ''}`,
                        Accept: 'application/json',
                    },
                    credentials: 'same-origin',
                });
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                const envelope    = await res.json();
                const dataPayload = envelope.data ?? {};
                renderWidget(key, dataPayload);
                runPostRenderHooks(key, entry.el, dataPayload);
                setStatus(key, 'loaded');
            } catch (err) {
                renderError(key, err);
                setStatus(key, 'error');
            }
        }

        function renderWidget(key, dataPayload) {
            const entry    = state.widgets[key];
            const renderer = widgetRenderers[key];
            if (!renderer) {
                entry.el.innerHTML = `<div class="oc-card"><div class="oc-card__body" style="padding:20px;color:var(--slate);font-size:.875rem;">
                    Widget <strong>${escHtml(key)}</strong> has no renderer.</div></div>`;
                return;
            }
            entry.el.innerHTML = renderer(dataPayload);
        }

        function renderError(key, err) {
            state.widgets[key].el.innerHTML = `
                <div class="oc-card">
                  <div class="oc-card__body" style="padding:20px;text-align:center;color:var(--slate);">
                    <div style="font-weight:500;margin-bottom:4px;">Could not load widget</div>
                    <div style="font-size:.78rem;">${escHtml(err.message)}</div>
                    <button onclick="DashboardWidgetManager.refresh('${escHtml(key)}')"
                            class="oc-btn oc-btn--ghost oc-btn--sm" style="margin-top:12px;">Retry</button>
                  </div>
                </div>`;
        }

        function setStatus(key, status) {
            if (state.widgets[key]) state.widgets[key].status = status;
        }

        // ── Helpers ───────────────────────────────────────────────────────────

        function escHtml(str) {
            return String(str ?? '')
                .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
                .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
        }
        function ucFirst(str) { return String(str ?? '').replace(/^\w/, c => c.toUpperCase()); }
        function fmtDate(str) {
            try { return new Date(str).toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' }); }
            catch { return str; }
        }

        // ── Public API ────────────────────────────────────────────────────────

        return {
            init, load, refresh, destroy,
            openManager, closeManager,
            saveWidgetConfig, savePositions,
            _managerToggle, // exposed for inline onclick handlers in the panel
        };

    })();

    window.DashboardWidgetManager = DashboardWidgetManager;

    document.addEventListener('DOMContentLoaded', async () => {
        DashboardWidgetManager.init();
        await DashboardWidgetManager.load();
    });
</script>

@endsection
