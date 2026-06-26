<?php
/**
 * PressStack account layout.
 */

use App\Framework\Authorization\MemberAuth;

if (!MemberAuth::check()) {
    $currentPath = $_SERVER['REQUEST_URI'] ?? '/press-stack/account';
    $loginUrl = '/member/login?redirect=' . urlencode($currentPath);
    header('Location: ' . $loginUrl, true, 302);
    exit;
}

if (!isset($member)) {
    $member = MemberAuth::getMember();
}

$t = $active_tab ?? 'overview';
$summary = $subscription_summary ?? $summary ?? [];
$activeCount = (int) ($summary['active'] ?? 0);
$previousCount = (int) ($summary['previous'] ?? $summary['expired'] ?? $summary['cancelled'] ?? 0);
$billingSection = $billing_section ?? null;
$showBillingHistory = $t === 'billing_history'
    || !empty($has_billing_history)
    || !empty($billing_history_rows)
    || $activeCount > 0
    || $previousCount > 0;
$accountNavItems = [
    [
        'key' => 'subscriptions',
        'label' => 'My Subscriptions',
        'href' => '/press-stack/account/subscriptions',
        'icon' => '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>',
        'badge' => $activeCount > 0 ? (string) $activeCount : null,
        'visible' => true,
    ],
    [
        'key' => 'payment_methods',
        'label' => 'Payment methods',
        'href' => '/press-stack/account/payment-methods',
        'icon' => '<rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/>',
        'visible' => true,
    ],
    [
        'key' => 'addresses',
        'label' => 'Manage Addresses',
        'href' => '/press-stack/account/addresses',
        'icon' => '<path d="M21 10c0 7-9 12-9 12S3 17 3 10a9 9 0 1118 0z"/><circle cx="12" cy="10" r="3"/>',
        'visible' => true,
    ],
    [
        'key' => 'billing_history',
        'label' => 'Billing history',
        'href' => '/press-stack/account/billing-history',
        'icon' => '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/>',
        'visible' => $showBillingHistory,
    ],
    [
        'key' => 'faqs',
        'label' => 'FAQs',
        'href' => '/press-stack/account/faqs',
        'icon' => '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 115.82 1c0 2-3 2-3 4"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        'visible' => true,
    ],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(\App\Framework\Security\Csrf::getToken()) ?>">
    <title><?= htmlspecialchars($page_title ?? 'My Account') ?> — PressStack</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,600&family=Instrument+Sans:wght@300;400;500;600&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/public/css/subscription-account.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --ink: #0d0d0f; --ink-soft: #3a3840; --ink-muted: #8a8795;
            --paper: #f8f6f1; --paper-dark: #f0ede6; --paper-light: #fbfaf7;
            --white: #ffffff; --border: #dedad2; --border-soft: #eae7e0;
            --gold: #b8860b; --gold-light: #fdf6e3; --gold-mid: #e8c96d;
            --navy: #12123a; --navy-light: #eeeef8;
            --green: #0b6e4f; --green-light: #d4f0e4;
            --amber: #b35c00; --amber-light: #fdecd3;
            --red: #c0392b; --red-light: #fde8e6;
            --blue: #1a4fa0; --blue-light: #dce8f8;
            --radius: 14px; --radius-sm: 9px; --radius-xs: 5px;
            --shadow-xs: 0 1px 2px rgba(13, 13, 15, .05);
            --shadow-sm: 0 2px 8px rgba(13, 13, 15, .07);
            --shadow: 0 4px 20px rgba(13, 13, 15, .09);
            --shadow-lg: 0 10px 40px rgba(13, 13, 15, .14);
            --font-display: 'Playfair Display', Georgia, serif;
            --font-serif: 'Instrument Serif', Georgia, serif;
            --font-body: 'Instrument Sans', system-ui, sans-serif;
            --transition: all .18s cubic-bezier(.4, 0, .2, 1);
            --nav-w: 256px;
        }
        html { scroll-behavior: smooth; }
        body { font-family: var(--font-body); background: var(--paper); color: var(--ink); line-height: 1.6; -webkit-font-smoothing: antialiased; min-height: 100vh; }
        .site-header { background: var(--ink); border-bottom: 1px solid rgba(255,255,255,.06); height: 64px; display: flex; align-items: center; padding: 0 28px; position: sticky; top: 0; z-index: 200; }
        .header-brand { display: flex; align-items: center; gap: 10px; text-decoration: none; flex-shrink: 0; }
        .header-brand__icon { width: 34px; height: 34px; background: var(--gold); border-radius: 7px; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden; }
        .header-brand__icon::before, .header-brand__icon::after, .header-brand__icon-inner { content: ''; position: absolute; left: 6px; height: 2px; background: var(--ink); border-radius: 2px; }
        .header-brand__icon::before { top: 10px; right: 10px; }
        .header-brand__icon::after { top: 16px; right: 6px; }
        .header-brand__icon-inner { bottom: 10px; right: 14px; }
        .header-brand__wordmark { display: flex; flex-direction: column; line-height: 1; }
        .header-brand__name { font-family: var(--font-display); font-size: 19px; font-weight: 700; color: #fff; letter-spacing: -.02em; }
        .header-brand__name em { font-style: italic; color: var(--gold-mid); }
        .header-brand__tagline { font-size: 9px; font-weight: 500; letter-spacing: .18em; text-transform: uppercase; color: rgba(255,255,255,.35); margin-top: 1px; }
        .header-sep { width: 1px; height: 22px; background: rgba(255,255,255,.12); margin: 0 20px; flex-shrink: 0; }
        .header-back { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 500; color: rgba(255,255,255,.4); text-decoration: none; letter-spacing: .04em; text-transform: uppercase; transition: color .16s; }
        .header-back:hover { color: rgba(255,255,255,.75); }
        .header-back svg { width: 12px; height: 12px; }
        .header-section-label { font-size: 13px; font-weight: 500; color: rgba(255,255,255,.45); letter-spacing: .01em; }
        .header-member { margin-left: auto; display: flex; align-items: center; gap: 12px; }
        .header-member__name { font-size: 13px; font-weight: 500; color: rgba(255,255,255,.6); max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .header-member__avatar { width: 34px; height: 34px; border-radius: 50%; background: linear-gradient(135deg, var(--gold) 0%, #8b6914 100%); color: var(--ink); display: flex; align-items: center; justify-content: center; font-family: var(--font-display); font-size: 14px; font-weight: 700; border: 2px solid rgba(255,255,255,.15); }
        .shell { display: grid; grid-template-columns: var(--nav-w) 1fr; min-height: calc(100vh - 64px); max-width: 1340px; margin: 0 auto; padding: 36px 28px 80px; gap: 36px; align-items: start; }
        .account-nav { position: sticky; top: 84px; background: var(--white); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; box-shadow: var(--shadow-xs); }
        .account-nav__header { padding: 22px 22px 18px; border-bottom: 1px solid var(--border-soft); background: linear-gradient(160deg, var(--ink) 0%, #1e1b3a 100%); }
        .account-nav__name { font-family: var(--font-display); font-size: 17px; color: #fff; margin-bottom: 3px; }
        .account-nav__email { font-size: 12px; color: rgba(255,255,255,.45); font-weight: 400; }
        .account-nav__links { padding: 8px 0; }
        .nav-link { display: flex; align-items: center; gap: 11px; padding: 11px 22px; font-size: 13.5px; font-weight: 500; color: var(--ink-soft); text-decoration: none; transition: var(--transition); border-left: 2.5px solid transparent; position: relative; }
        .nav-link:hover { color: var(--ink); background: var(--paper); }
        .nav-link.active { color: var(--ink); font-weight: 600; border-left-color: var(--gold); background: var(--gold-light); }
        .nav-link svg { width: 15px; height: 15px; flex-shrink: 0; opacity: .55; }
        .nav-link.active svg { opacity: 1; }
        .nav-link__badge { margin-left: auto; background: var(--gold); color: var(--ink); font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 100px; }
        .account-nav__footer { padding: 14px 22px; border-top: 1px solid var(--border-soft); background: var(--paper); }
        .account-nav__footer-label { font-size: 10px; font-weight: 600; letter-spacing: .12em; text-transform: uppercase; color: var(--ink-muted); display: flex; align-items: center; gap: 6px; }
        .account-nav__footer-label::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: var(--gold); }
        .mobile-nav { display: none; gap: 6px; overflow-x: auto; padding: 16px 16px 0; scrollbar-width: none; max-width: 1340px; margin: 0 auto; }
        .mobile-nav::-webkit-scrollbar { display: none; }
        .mobile-nav-link { flex: 0 0 auto; display: flex; align-items: center; gap: 6px; padding: 8px 15px; border-radius: 100px; border: 1px solid var(--border); background: var(--white); font-size: 12.5px; font-weight: 500; color: var(--ink-soft); text-decoration: none; white-space: nowrap; transition: var(--transition); }
        .mobile-nav-link svg { width: 13px; height: 13px; }
        .mobile-nav-link.active { background: var(--ink); color: #fff; border-color: var(--ink); }
        .page-content { min-width: 0; }
        .card { background: var(--white); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; box-shadow: var(--shadow-xs); }
        .card__header { padding: 18px 24px 16px; border-bottom: 1px solid var(--border-soft); display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        .card__title { font-family: var(--font-display); font-size: 18px; color: var(--ink); }
        .card__body { padding: 24px; }
        .card__footer { padding: 12px 24px; border-top: 1px solid var(--border-soft); background: var(--paper); }
        .page-heading { margin-bottom: 28px; }
        .page-heading__eyebrow { font-size: 10.5px; font-weight: 600; letter-spacing: .14em; text-transform: uppercase; color: var(--gold); margin-bottom: 6px; display: flex; align-items: center; gap: 8px; }
        .page-heading__eyebrow::before { content: ''; width: 20px; height: 1.5px; background: var(--gold); border-radius: 2px; }
        .page-heading__title { font-family: var(--font-display); font-size: clamp(26px, 3.5vw, 36px); line-height: 1.1; margin-bottom: 5px; letter-spacing: -.02em; }
        .page-heading__sub { font-size: 14px; color: var(--ink-muted); font-weight: 400; }
        .empty-state { text-align: center; padding: 64px 24px; }
        .empty-state__icon { font-size: 44px; margin-bottom: 16px; opacity: .45; }
        .empty-state__title { font-family: var(--font-display); font-size: 22px; color: var(--ink); margin-bottom: 6px; }
        .empty-state__sub, .muted { font-size: 14px; color: var(--ink-muted); }
        .btn { display: inline-flex; align-items: center; gap: 7px; padding: 9px 18px; border-radius: var(--radius-sm); font-family: var(--font-body); font-size: 13.5px; font-weight: 600; cursor: pointer; text-decoration: none; transition: var(--transition); border: none; line-height: 1; letter-spacing: .01em; }
        .btn--primary { background: var(--ink); color: #fff; }
        .btn--ghost { background: transparent; color: var(--ink-soft); border: 1px solid var(--border); }
        .btn--danger { background: var(--red-light); color: var(--red); }
        .btn--gold { background: var(--gold); color: var(--ink); }
        .btn--sm { padding: 6px 13px; font-size: 12px; }
        .badge { display: inline-flex; align-items: center; gap: 5px; font-size: 10.5px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; padding: 3px 10px; border-radius: 100px; }
        .badge::before { content: ''; width: 5px; height: 5px; border-radius: 50%; background: currentColor; opacity: .7; }
        .badge--active, .badge--paid, .badge--completed { background: var(--green-light); color: var(--green); }
        .badge--expired { background: var(--border-soft); color: var(--ink-muted); }
        .badge--cancelled, .badge--failed { background: var(--red-light); color: var(--red); }
        .badge--past_due, .badge--refunded { background: var(--amber-light); color: var(--amber); }
        .badge--pending { background: var(--blue-light); color: var(--blue); }
        .badge--processing { background: var(--navy-light); color: var(--navy); }
        .modal-overlay { position: fixed; inset: 0; background: rgba(13,13,15,.55); backdrop-filter: blur(4px); z-index: 300; display: flex; align-items: center; justify-content: center; padding: 24px; opacity: 0; pointer-events: none; transition: opacity .22s ease; }
        .modal-overlay.open { opacity: 1; pointer-events: auto; }
        .modal { background: var(--white); border-radius: var(--radius); box-shadow: var(--shadow-lg); width: 100%; max-width: 492px; overflow: hidden; transform: translateY(14px); transition: transform .24s cubic-bezier(.32,.72,0,1); }
        .modal-overlay.open .modal { transform: translateY(0); }
        .modal__header { padding: 24px 24px 16px; border-bottom: 1px solid var(--border-soft); display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; background: linear-gradient(160deg, var(--ink) 0%, #1e1b3a 100%); }
        .modal__title { font-family: var(--font-display); font-size: 21px; line-height: 1.2; color: #fff; }
        .modal__close { background: rgba(255,255,255,.1); border: none; cursor: pointer; color: rgba(255,255,255,.6); padding: 5px 7px; border-radius: 6px; line-height: 1; font-size: 16px; }
        .modal__body { padding: 24px; }
        .modal__footer { padding: 16px 24px; border-top: 1px solid var(--border-soft); display: flex; gap: 10px; justify-content: flex-end; background: var(--paper); }
        .faq-list { display: grid; gap: 12px; }
        .faq-item { border: 1px solid var(--border-soft); border-radius: var(--radius-sm); background: var(--paper-light); padding: 0; overflow: hidden; }
        .faq-item summary { cursor: pointer; padding: 16px 18px; font-weight: 650; color: var(--ink); }
        .faq-item p { padding: 0 18px 18px; color: var(--ink-soft); font-size: 14px; line-height: 1.65; }
        .table-wrap { width: 100%; overflow-x: auto; }
        .account-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .account-table th, .account-table td { padding: 13px 14px; border-bottom: 1px solid var(--border-soft); text-align: left; }
        .account-table th { color: var(--ink-muted); font-size: 11px; letter-spacing: .08em; text-transform: uppercase; }
        <?php if ($billingSection === 'payment_methods'): ?>
        .billing-grid > .card:nth-of-type(2) { display: none; }
        <?php elseif ($billingSection === 'addresses'): ?>
        .billing-grid > .card:nth-of-type(1), .billing-grid > div:nth-of-type(3) { display: none; }
        <?php endif; ?>
        @media (max-width: 860px) { .shell { grid-template-columns: 1fr; padding: 20px 16px 60px; gap: 0; } .account-nav { display: none; } .mobile-nav { display: flex; } .header-member__name, .header-section-label { display: none; } }
    </style>
</head>
<body>
<header class="site-header">
    <a href="/press-stack" class="header-brand">
        <div class="header-brand__icon" aria-hidden="true"><span class="header-brand__icon-inner"></span></div>
        <div class="header-brand__wordmark">
            <span class="header-brand__name">Press<em>Stack</em></span>
            <span class="header-brand__tagline">Publishing Platform</span>
        </div>
    </a>
    <div class="header-sep"></div>
    <a href="/press-stack" class="header-back">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="15 18 9 12 15 6"/></svg>
        Back to Shop
    </a>
    <div class="header-sep"></div>
    <span class="header-section-label">My Account</span>
    <div class="header-member">
        <span class="header-member__name"><?= htmlspecialchars($member->name ?? $member->email ?? '') ?></span>
        <div class="header-member__avatar" title="<?= htmlspecialchars($member->email ?? '') ?>"><?= strtoupper(substr($member->name ?? $member->email ?? 'M', 0, 1)) ?></div>
    </div>
</header>

<div style="background:var(--white); border-bottom:1px solid var(--border-soft);">
    <nav class="mobile-nav" aria-label="Subscription account navigation">
        <?php foreach ($accountNavItems as $item): ?>
            <?php if (!$item['visible']) { continue; } ?>
            <a href="<?= htmlspecialchars($item['href']) ?>" class="mobile-nav-link <?= $t === $item['key'] ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?= $item['icon'] ?></svg>
                <?= htmlspecialchars($item['label']) ?>
            </a>
        <?php endforeach; ?>
    </nav>
</div>

<div class="shell">
    <aside class="account-nav">
        <div class="account-nav__header">
            <div class="account-nav__name"><?= htmlspecialchars($member->name ?? 'Account') ?></div>
            <div class="account-nav__email"><?= htmlspecialchars($member->email ?? '') ?></div>
        </div>
        <nav class="account-nav__links" aria-label="Subscription account navigation">
            <?php foreach ($accountNavItems as $item): ?>
                <?php if (!$item['visible']) { continue; } ?>
                <a href="<?= htmlspecialchars($item['href']) ?>" class="nav-link <?= $t === $item['key'] ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?= $item['icon'] ?></svg>
                    <?= htmlspecialchars($item['label']) ?>
                    <?php if (!empty($item['badge'])): ?>
                        <span class="nav-link__badge"><?= htmlspecialchars($item['badge']) ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="account-nav__footer">
            <span class="account-nav__footer-label">PressStack Member</span>
        </div>
    </aside>

<?php if ($billingSection === 'payment_methods' || $billingSection === 'addresses'): ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const title = document.querySelector('.page-heading__title');
        const sub = document.querySelector('.page-heading__sub');
        if (!title || !sub) {
            return;
        }

        <?php if ($billingSection === 'payment_methods'): ?>
        title.textContent = 'Payment methods';
        sub.textContent = 'Manage saved cards for your PressStack subscription payments.';
        <?php elseif ($billingSection === 'addresses'): ?>
        title.textContent = 'Manage Addresses';
        sub.textContent = 'Manage saved billing and delivery addresses for your subscriptions.';
        <?php endif; ?>
    });
</script>
<?php endif; ?>
