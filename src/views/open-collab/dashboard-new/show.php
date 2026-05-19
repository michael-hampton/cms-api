@section('logic')
<?php
/**
 * Template: open-collab/dashboard/show.blade.php
 *
 * Variables:
 *   $widgets     — array of ['key' => string, 'title' => string]
 *   $currentUser — authenticated User model
 *   $site        — site slug string
 *
 * The page shell and nav are rendered here.
 * Each widget's content is loaded asynchronously by the JS widget manager
 * using the DASHBOARD_WIDGETS bootstrap config injected below.
 *
 * To add a widget: register it in DashboardServiceProvider.
 * No template changes required.
 */

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
$breadcrumbs = [['label' => 'Dashboard']];
$headerActions = '<a href="/articles/create" class="oc-btn oc-btn--amber">
  <svg viewBox="0 0 20 20" fill="currentColor" width="15">
    <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"/>
  </svg>
  New article
</a>';
?>
@endsection

@extends('open-collab/layouts/opencollab')

@section('content')

<script>
    window.DASHBOARD_WIDGETS = {!! json_encode($widgets) !!};
    window.DASHBOARD_SITE =  {!! json_encode($site) !!};;
    window.DASHBOARD_TOKEN =  {!! json_encode($ocToken ?? '') !!};;
</script>

<div id="dashboard-widget-grid" class="oc-widget-grid">
    <?php $loopIndex = 0; ?>
    <?php foreach ($widgets as $widget): ?>
        <div
                id="widget-<?= htmlspecialchars($widget['key'], ENT_QUOTES, 'UTF-8') ?>"
                class="oc-widget"
                data-widget-key="<?= htmlspecialchars($widget['key'], ENT_QUOTES, 'UTF-8') ?>"
                aria-label="<?= htmlspecialchars($widget['title'], ENT_QUOTES, 'UTF-8') ?>"
                style="animation: fadeSlideIn .4s ease both; animation-delay: <?= $loopIndex * 0.05 ?>s;"
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
        <div class="oc-card" style="padding: 48px 24px; text-align: center; color: var(--slate);">
            <div style="font-weight: 500; margin-bottom: 6px;">No widgets configured</div>
            <div style="font-size: .85rem;">Contact your administrator to configure your dashboard.</div>
        </div>
    <?php endif; ?>
</div>

<?php foreach ($widgets as $widget): ?>
    <?php
    // Replicating @includeIf by converting dot notation to a file path and checking existence.
    // Note: Adjust the base directory path (__DIR__ . '/views/') to match your environment.
    $widgetPath = __DIR__ . '/views/open-collab/dashboard/widgets/' . $widget['key'] . '.php';
    if (file_exists($widgetPath)) {
        include $widgetPath;
    }
    ?>
<?php endforeach; ?>

@endsection

<script>
    /**
     * Dashboard Widget Manager
     *
     * Consumes window.DASHBOARD_WIDGETS (set by the Blade template) and
     * orchestrates the full widget lifecycle:
     *
     *   init()     → register all widgets from the bootstrap config
     *   load()     → fetch all widget data via individual API calls
     *   refresh()  → reload a single widget (or all)
     *   destroy()  → tear down (used on SPA navigation)
     *
     * Adding a new widget requires:
     *   1. Register it server-side (DashboardServiceProvider)
     *   2. Create a renderer function here: widgetRenderers[key] = (data) => htmlString
     *   3. No other changes needed
     *
     * All API responses follow the WidgetResponse envelope:
     *   { key, title, data, meta: { loaded_at } }
     */

    let DashboardWidgetManager = (() => {

        // ── State ──────────────────────────────────────────────────────────────────

        const site = '<?= \App\Framework\Support\SiteContext::slug() ?>'

        const state = {
            widgets:    {},   // key → { key, title, el, status: 'idle'|'loading'|'loaded'|'error' }
            site:       site,
            baseUrl:    `/api/${site ?? ''}/open-collab/dashboard/widgets`,
            sseSource:  null, // Track active SSE stream to prevent memory leaks
        };

        // ── Widget Renderers ───────────────────────────────────────────────────────

        const widgetRenderers = {

            earnings(data) {
                const fmt      = (pence) => `£${((pence ?? 0) / 100).toFixed(2)}`;
                const breakdown    = data.breakdown    ?? [];
                const transactions = data.transactions ?? [];
                const payment      = data.payment_details;

                const breakdownRows = breakdown.length
                    ? breakdown.map(item => `
            <tr>
              <td>
                <a href="/articles/${item.page_id}/edit"
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
                  <td style="white-space:nowrap;color:var(--slate);">
                    ${tx.created_at ? fmtDate(tx.created_at) : '–'}
                  </td>
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
           <a href="/contributor/settings#payment" class="oc-btn oc-btn--ghost oc-btn--sm oc-btn--block">Update payout details</a>`
                    : `<div style="padding:16px;text-align:center;border:1.5px dashed var(--border);border-radius:var(--radius);margin-bottom:16px;">
             <svg viewBox="0 0 20 20" fill="currentColor" width="28" style="color:var(--slate-light);display:block;margin:0 auto 8px;">
               <path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/>
               <path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/>
             </svg>
             <div style="font-size:.85rem;font-weight:500;margin-bottom:4px;">No payout method</div>
             <div style="font-size:.78rem;color:var(--slate);margin-bottom:12px;">Set up to receive payments</div>
             <a href="/onboarding" class="oc-btn oc-btn--amber oc-btn--sm">Set up now</a>
           </div>`;

                return `
        <div style="display:flex;flex-direction:column;gap:20px;">
          <!-- Stats -->
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

          <!-- Main grid -->
          <div class="oc-grid-sidebar" style="align-items:start;">
            <!-- Left: article breakdown + transactions -->
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

            <!-- Right: payout sidebar -->
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

            // In your widget manager's render/handler map:
            'quick_links': (data) => {
                const icons = {
                    earnings: `<svg viewBox="0 0 20 20" fill="currentColor" width="14" style="color:var(--amber);flex-shrink:0;"><path d="M8.433 7.418c.155-.103.346-.196.567-.267v1.698a2.305 2.305 0 01-.567-.267C8.07 8.34 8 8.114 8 8c0-.114.07-.34.433-.582zM11 12.849v-1.698c.22.071.412.164.567.267.364.243.433.468.433.582 0 .114-.07.34-.433.582a2.305 2.305 0 01-.567.267z"/><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-13a1 1 0 10-2 0v.092a4.535 4.535 0 00-1.676.662C6.602 6.234 6 7.009 6 8c0 .99.602 1.765 1.324 2.246.48.32 1.054.545 1.676.662v1.941c-.391-.127-.68-.317-.843-.504a1 1 0 10-1.51 1.31c.562.649 1.413 1.077 2.353 1.253V15a1 1 0 102 0v-.092a4.535 4.535 0 001.676-.662C13.398 13.766 14 12.991 14 12c0-.99-.602-1.765-1.324-2.246A4.535 4.535 0 0011 9.092V7.151c.391.127.68.317.843.504a1 1 0 101.511-1.31c-.563-.649-1.413-1.077-2.354-1.253V5z" clip-rule="evenodd"/></svg>`,
                    profile:  `<svg viewBox="0 0 20 20" fill="currentColor" width="14" style="color:var(--amber);flex-shrink:0;"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/></svg>`,
                    danger:   `<svg viewBox="0 0 20 20" fill="currentColor" width="14" style="flex-shrink:0;"><path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>`,
                };

                const linkIcon = (link) => {
                    if (link.variant === 'danger')              return icons.danger;
                    if (link.href.includes('earnings'))         return icons.earnings;
                    return icons.profile;
                };

                const linksHtml = data.links.map((link, i) => {
                    const isLast    = i === data.links.length - 1;
                    const color     = link.variant === 'danger' ? 'var(--red)' : 'var(--navy)';
                    const border    = isLast ? '' : 'border-bottom:1px solid #f5f2ee;';
                    return `<a href="${link.href}" style="font-size:.875rem;color:${color};text-decoration:none;display:flex;align-items:center;gap:8px;padding:6px 0;${border}">
            ${linkIcon(link)}${link.label}
        </a>`;
                }).join('');

                return `<div class="oc-card" style="animation:fadeSlideIn .55s ease;">
        <div class="oc-card__body" style="padding:18px 20px;">
            <div style="font-size:.75rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--slate);margin-bottom:12px;">Quick links</div>
            <div style="display:flex;flex-direction:column;gap:8px;">${linksHtml}</div>
        </div>
    </div>`;
            },

            'review_queue': (data) => {
                const count = data.pending_count ?? 0;
                const rows = (data.items ?? []).map(item => `
        <a href="/contributor/pages/${item.id}/edit"
           style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #f5f2ee;text-decoration:none;color:var(--navy);font-size:.875rem;">
            <span>${item.title ?? 'Untitled'}</span>
            <span style="font-size:.75rem;color:var(--slate);">${item.updated_at ?? ''}</span>
        </a>`).join('');

                return `<div class="oc-card" style="animation:fadeSlideIn .55s ease;">
        <div class="oc-card__body" style="padding:18px 20px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                <div style="font-size:.75rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--slate);">Review Queue</div>
                <span style="background:var(--amber);color:#fff;font-size:.7rem;font-weight:700;padding:2px 8px;border-radius:99px;">${count}</span>
            </div>
            ${rows || '<p style="font-size:.875rem;color:var(--slate);margin:0;">Nothing awaiting review.</p>'}
        </div>
    </div>`;
            },

            'approvals': (data) => {
                const rows = (data.items ?? []).map(item => `
        <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #f5f2ee;">
            <a href="/contributor/pages/${item.id}/edit"
               style="font-size:.875rem;color:var(--navy);text-decoration:none;">${item.title ?? 'Untitled'}</a>
            <a href="/contributor/pages/${item.id}/approve"
               style="font-size:.75rem;font-weight:600;color:var(--amber);text-decoration:none;">Approve →</a>
        </div>`).join('');

                return `<div class="oc-card" style="animation:fadeSlideIn .55s ease;">
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
              <td>${a.is_paid
                        ? '<span class="oc-badge oc-badge--paid">PAID</span>'
                        : '<span class="oc-badge oc-badge--free">Free</span>'}</td>
              <td style="text-align:right;">
                <a href="/articles/${a.id}/edit" class="oc-btn oc-btn--ghost oc-btn--sm">Edit</a>
              </td>
            </tr>`).join('')
                    : `<tr><td colspan="4" style="text-align:center;padding:40px;color:var(--slate);">
             No articles yet. <a href="/articles/create" class="oc-btn oc-btn--primary oc-btn--sm" style="margin-left:8px;">Create one</a>
           </td></tr>`;

                return `
        <div class="oc-card">
          <div class="oc-card__header">
            <span class="oc-card__title">Your Articles</span>
            <div style="display:flex;gap:8px;align-items:center;">
              <span style="font-size:.8rem;color:var(--slate);">
                ${data.published_count} published · ${data.draft_count} drafts
              </span>
              <a href="/articles" class="oc-btn oc-btn--ghost oc-btn--sm">View all</a>
            </div>
          </div>
          <table class="oc-table">
            <thead>
              <tr><th>Article</th><th>Status</th><th>Type</th><th></th></tr>
            </thead>
            <tbody>${rows}</tbody>
          </table>
        </div>`;
            },

            activity(data) {
                const items = data.items ?? [];

                const list = items.length
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
          <div class="oc-card__header">
            <span class="oc-card__title">Recent Activity</span>
          </div>
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
                        action: { label: 'Complete profile', href: '/contributor/settings#profile' },
                        icon: `<svg viewBox="0 0 20 20" fill="currentColor" width="18"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/></svg>`,
                    },
                    payment: {
                        title: 'Set up payouts',
                        description: 'Connect Stripe to receive your earnings.',
                        action: { label: 'Set up payouts', href: '/contributor/settings#stripe-connect' },
                        icon: `<svg viewBox="0 0 20 20" fill="currentColor" width="18"><path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z"/><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd"/></svg>`,
                    },
                    contract: {
                        title: 'Sign contributor agreement',
                        description: 'Review and sign the platform contributor contract.',
                        action: { label: 'Review contract', href: '/contributor/onboarding/contract' },
                        icon: `<svg viewBox="0 0 20 20" fill="currentColor" width="18"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/></svg>`,
                    },
                    guidelines: {
                        title: 'Acknowledge brand guidelines',
                        description: 'Confirm you have read the editorial standards.',
                        action: { label: 'Read guidelines', href: '/contributor/onboarding/guidelines' },
                        icon: `<svg viewBox="0 0 20 20" fill="currentColor" width="18"><path d="M9 4.804A7.968 7.968 0 005.5 4c-1.255 0-2.443.29-3.5.804v10A7.969 7.969 0 015.5 14c1.669 0 3.218.51 4.5 1.385A7.962 7.962 0 0114.5 14c1.255 0 2.443.29-3.5.804v-10A7.968 7.968 0 0014.5 4c-1.255 0-2.443.29-3.5.804V12a1 1 0 11-2 0V4.804z"/></svg>`,
                    },
                    age_verification: {
                        title: 'Verify your age',
                        description: 'Confirm you meet the minimum contributor age requirement.',
                        action: { label: 'Verify age', href: '/contributor/settings#profile' },
                        icon: `<svg viewBox="0 0 20 20" fill="currentColor" width="18"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>`,
                    },
                };

                const statusBadge = (status) => {
                    const cfg = {
                        completed:   { label: '✓ Complete',   bg: '#dcfce7', color: '#15803d', border: '#bbf7d0' },
                        pending:     { label: 'Pending',       bg: '#fef9c3', color: '#854d0e', border: '#fde68a' },
                        in_progress: { label: 'In progress',   bg: '#dbeafe', color: '#1e40af', border: '#bfdbfe' },
                        locked:      { label: 'Locked',        bg: '#f1f5f9', color: '#64748b', border: '#e2e8f0' },
                    }[status] || { label: 'Pending', bg: '#fef9c3', color: '#854d0e', border: '#fde68a' };
                    return `<span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:99px;font-size:.75rem;font-weight:600;background:${cfg.bg};color:${cfg.color};border:1px solid ${cfg.border};white-space:nowrap;">${cfg.label}</span>`;
                };

                const stepCard = (stepKey, status, reason, index) => {
                    const meta = STEP_META[stepKey] ?? {
                        title: stepKey.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()),
                        description: reason ?? '',
                        action: null,
                        icon: `<svg viewBox="0 0 20 20" fill="currentColor" width="18"><circle cx="10" cy="10" r="8"/></svg>`,
                    };
                    const isComplete = status === 'completed';
                    const delay      = (index * 0.05).toFixed(2);
                    const actionBtn  = (!isComplete && meta.action)
                        ? `<a href="${meta.action.href}" class="oc-btn oc-btn--primary oc-btn--sm" style="white-space:nowrap;">${meta.action.label}</a>`
                        : '';

                    return `
          <div class="oc-card" style="animation:fadeSlideIn .4s ease ${delay}s both;opacity:${isComplete ? '.7' : '1'};border-left:3px solid ${isComplete ? 'var(--green)' : 'var(--amber)'};">
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

                const pct      = data.progress_percent ?? 0;
                const total    = data.total_steps      ?? 0;
                const done     = data.completed_count  ?? 0;
                const pending  = data.pending_steps    ?? [];
                const complete = data.completed_steps  ?? [];

                const allSteps = [
                    ...complete.map(key   => ({ key, status: 'completed', reason: null })),
                    ...pending.map(p      => ({ key: p.step, status: 'pending', reason: p.reason ?? null })),
                ];

                const stepCards = allSteps.map(({ key, status, reason }, i) => stepCard(key, status, reason, i)).join('');

                const completionState = data.is_complete ? `
        <div style="text-align:center;padding:48px 24px;animation:fadeSlideIn .3s ease;">
          <div style="width:60px;height:60px;background:#dcfce7;border-radius:50%;display:grid;place-items:center;margin:0 auto 16px;">
            <svg viewBox="0 0 20 20" fill="#16a34a" width="28">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
          </div>
          <h2 style="font-family:var(--font-display);font-size:1.35rem;color:var(--navy);margin-bottom:8px;">You're ready to create content</h2>
          <p style="font-size:.9rem;color:var(--slate);line-height:1.6;max-width:380px;margin:0 auto 20px;">All onboarding requirements are complete. Head to your dashboard to start submitting content.</p>
          <a href="/contributor/dashboard" class="oc-btn oc-btn--primary">Go to dashboard</a>
        </div>` : '';

                return `
        <div style="max-width:700px;">
          <div style="margin-bottom:28px;animation:fadeSlideIn .3s ease;">
            <h1 style="font-family:var(--font-display);font-size:1.75rem;color:var(--navy);margin-bottom:6px;">Get set up as a contributor</h1>
            <p style="font-size:.9rem;color:var(--slate);line-height:1.6;">Complete the steps below before you can publish content or request payouts.</p>
          </div>

          <!-- Progress Bar -->
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

          <!-- Steps or completion state -->
          ${data.is_complete ? completionState : `<div style="display:flex;flex-direction:column;gap:12px;animation:fadeSlideIn .4s ease;">${stepCards}</div>`}
        </div>`;
            },

        };

        // ── Native Lifecycle Handlers ──────────────────────────────────────────────

        function runPostRenderHooks(key, container, data) {
            if (key !== 'onboarding') return;

            // 1. Animate progress bar safely in runtime JS environment
            requestAnimationFrame(() => {
                const bar = container.querySelector('[data-progress-bar]');
                if (bar) bar.style.width = `${data.progress_percent ?? 0}%`;
            });

            // 2. Wire Server-Sent Events stream safely without connection leaks
            if (state.sseSource) {
                state.sseSource.close();
                state.sseSource = null;
            }

            const sseUrl = data.sse_url ?? '';
            const token  = window.DASHBOARD_TOKEN ?? '';

            if (!sseUrl || !token || !window.EventSource) return;

            try {
                state.sseSource = new EventSource(`${sseUrl}?token=${encodeURIComponent(token)}`);

                const refreshEvents = ['contract.signed', 'guidelines.acknowledged', 'payment.enabled', 'profile.updated', 'age.verified'];
                refreshEvents.forEach(ev => {
                    state.sseSource.addEventListener(ev, () => DashboardWidgetManager.refresh('onboarding'));
                });

                state.sseSource.addEventListener('error', () => {
                    if (state.sseSource && state.sseSource.readyState === EventSource.CLOSED) {
                        setTimeout(() => runPostRenderHooks(key, container, data), 5000);
                    }
                });
            } catch (e) {
                console.error("SSE Connection Failed:", e);
            }
        }

        // ── Core ───────────────────────────────────────────────────────────────────

        function init() {
            const manifest = window.DASHBOARD_WIDGETS ?? [];

            manifest.forEach(({ key, title }) => {
                const el = document.getElementById(`widget-${key}`);
                if (!el) return;

                state.widgets[key] = { key, title, el, status: 'idle' };
            });
        }

        async function load() {
            const keys = Object.keys(state.widgets);
            await Promise.allSettled(keys.map(key => loadWidget(key)));
        }

        async function refresh(key = null) {
            if (key) {
                await loadWidget(key);
            } else {
                await load();
            }
        }

        function destroy() {
            if (state.sseSource) {
                state.sseSource.close();
                state.sseSource = null;
            }
            Object.keys(state.widgets).forEach(key => {
                state.widgets[key].status = 'idle';
            });
            state.widgets = {};
        }

        // ── Private ────────────────────────────────────────────────────────────────

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

                const envelope = await res.json();
                renderWidget(key, envelope);
                setStatus(key, 'loaded');

            } catch (err) {
                renderError(key, err);
                setStatus(key, 'error');
            }
        }

        function renderWidget(key, envelope) {
            const entry    = state.widgets[key];
            const renderer = widgetRenderers[key];
            const dataPayload = envelope.data ?? {};

            if (!renderer) {
                entry.el.innerHTML = `
        <div class="oc-card">
          <div class="oc-card__body" style="padding:20px;color:var(--slate);font-size:.875rem;">
            Widget <strong>${escHtml(key)}</strong> has no renderer. Register one in widget-manager.js.
          </div>
        </div>`;
                return;
            }

            entry.el.innerHTML = renderer(dataPayload);
            runPostRenderHooks(key, entry.el, dataPayload);
        }

        function renderError(key, err) {
            const entry = state.widgets[key];

            entry.el.innerHTML = `
      <div class="oc-card">
        <div class="oc-card__body" style="padding:20px;text-align:center;color:var(--slate);">
          <div style="font-weight:500;margin-bottom:4px;">Could not load widget</div>
          <div style="font-size:.78rem;">${escHtml(err.message)}</div>
          <button onclick="DashboardWidgetManager.refresh('${escHtml(key)}')"
                  class="oc-btn oc-btn--ghost oc-btn--sm" style="margin-top:12px;">
            Retry
          </button>
        </div>
      </div>`;
        }

        function setStatus(key, status) {
            if (state.widgets[key]) {
                state.widgets[key].status = status;
            }
        }

        // ── Helpers ────────────────────────────────────────────────────────────────

        function escHtml(str) {
            return String(str ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function ucFirst(str) {
            return String(str ?? '').replace(/^\w/, c => c.toUpperCase());
        }

        function fmtDate(str) {
            try {
                return new Date(str).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
            } catch {
                return str;
            }
        }

        // ── Public API ─────────────────────────────────────────────────────────────

        return { init, load, refresh, destroy };

    })();

    // Globally map to window to guarantee inline execution strings work everywhere
    window.DashboardWidgetManager = DashboardWidgetManager;

    // Boot on DOMContentLoaded
    document.addEventListener('DOMContentLoaded', async () => {
        DashboardWidgetManager.init();
        await DashboardWidgetManager.load();
    });
</script>