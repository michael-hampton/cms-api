<?php
/**
 * @var Member $member
 * @var Site $site
 * @var string|null $gift_token — set when landing on /{site}/gift/{token} while logged in
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gifted Articles - <?= htmlspecialchars($site->name) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #667eea;
            --primary-dark: #5568d3;
            --secondary-color: #764ba2;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --border-color: #e5e7eb;
            --bg-light: #f5f7fa;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --shadow: 0 1px 3px rgba(0, 0, 0, .1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, .1);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: var(--bg-light);
            min-height: 100vh;
            color: var(--text-primary);
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem;
            margin-top: 2rem;
        }

        /* ── Loader ── */
        .page-loader {
            text-align: center;
            padding: 5rem 2rem;
        }

        .page-loader p {
            color: var(--text-secondary);
            font-size: 1rem;
        }

        /* ── Allowance card ── */
        .allowance-card {
            background: white;
            border-radius: 1rem;
            padding: 1.75rem 2rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .allowance-label {
            font-size: .875rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: .05em;
            margin-bottom: .35rem;
        }

        .allowance-value {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .allowance-sub {
            font-size: .875rem;
            color: var(--text-secondary);
            margin-top: .25rem;
        }

        .allowance-warning {
            background: #fff3cd;
            border: 1px solid #fbbf24;
            border-radius: .5rem;
            padding: .75rem 1rem;
            font-size: .875rem;
            color: #92400e;
        }

        .allowance-danger {
            background: #fee2e2;
            border: 1px solid #fca5a5;
            border-radius: .5rem;
            padding: .75rem 1rem;
            font-size: .875rem;
            color: #991b1b;
        }

        /* ── Tabs ── */
        .tabs {
            display: flex;
            gap: 0;
            border-bottom: 2px solid var(--border-color);
            margin-bottom: 2rem;
        }

        .tab-btn {
            padding: .875rem 1.5rem;
            background: none;
            border: none;
            font-weight: 600;
            font-size: .9375rem;
            cursor: pointer;
            color: var(--text-secondary);
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: all .2s;
        }

        .tab-btn:hover {
            color: var(--primary-color);
        }

        .tab-btn.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }

        .tab-panel {
            display: none;
        }

        .tab-panel.active {
            display: block;
        }

        /* ── Gift cards ── */
        .gifts-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .gift-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            transition: all .2s;
            border: 2px solid transparent;
        }

        .gift-card:hover {
            border-color: var(--border-color);
            box-shadow: var(--shadow-lg);
        }

        .gift-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: .875rem;
            gap: 1rem;
        }

        .gift-card-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            flex: 1;
            line-height: 1.5;
        }

        .gift-status {
            padding: .25rem .75rem;
            border-radius: 9999px;
            font-size: .75rem;
            font-weight: 700;
            text-transform: uppercase;
            flex-shrink: 0;
        }

        .gift-status.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .gift-status.claimed {
            background: #d1fae5;
            color: #065f46;
        }

        .gift-status.expired {
            background: #fee2e2;
            color: #991b1b;
        }

        .gift-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            font-size: .8125rem;
            color: var(--text-secondary);
            margin-bottom: .75rem;
        }

        .gift-message {
            font-size: .875rem;
            color: var(--text-secondary);
            font-style: italic;
            margin-bottom: .875rem;
            padding-left: 1rem;
            border-left: 3px solid var(--border-color);
            line-height: 1.6;
        }

        .gift-actions {
            display: flex;
            gap: .75rem;
            flex-wrap: wrap;
        }

        /* ── Empty state ── */
        .empty-state {
            text-align: center;
            padding: 3.5rem 2rem;
        }

        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: .5;
        }

        .empty-state h3 {
            font-size: 1.125rem;
            font-weight: 600;
            color: #4b5563;
            margin-bottom: .5rem;
        }

        .empty-state p {
            color: var(--text-secondary);
            font-size: .9375rem;
        }

        /* ── Claim result panel ── */
        .result-panel {
            background: white;
            border-radius: 1rem;
            padding: 2.5rem 2rem;
            text-align: center;
            box-shadow: var(--shadow);
            max-width: 560px;
            margin: 0 auto;
        }

        .result-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }

        .result-icon.success {
            background: #4CAF50;
            animation: scaleIn .5s ease-out;
        }

        .result-icon.error {
            background: #dc3545;
            animation: shake .5s ease-out;
        }

        .result-icon svg {
            width: 50px;
            height: 50px;
            stroke: white;
            stroke-width: 3;
            stroke-linecap: round;
            stroke-linejoin: round;
            fill: none;
        }

        .result-icon.success svg {
            animation: checkmark .8s ease-out .3s forwards;
            stroke-dasharray: 100;
            stroke-dashoffset: 100;
        }

        @keyframes scaleIn {
            0% {
                transform: scale(0);
                opacity: 0
            }
            50% {
                transform: scale(1.1)
            }
            100% {
                transform: scale(1);
                opacity: 1
            }
        }

        @keyframes checkmark {
            to {
                stroke-dashoffset: 0;
            }
        }

        @keyframes shake {
            0%, 100% {
                transform: translateX(0)
            }
            10%, 30%, 50%, 70%, 90% {
                transform: translateX(-10px)
            }
            20%, 40%, 60%, 80% {
                transform: translateX(10px)
            }
        }

        .result-panel h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1rem;
        }

        .result-message {
            font-size: 1rem;
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
        }

        .result-details {
            background: var(--bg-light);
            border-radius: .75rem;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            text-align: left;
        }

        .result-detail-row {
            display: flex;
            justify-content: space-between;
            padding: .625rem 0;
            border-bottom: 1px solid var(--border-color);
            font-size: .875rem;
        }

        .result-detail-row:last-child {
            border-bottom: none;
        }

        .result-detail-label {
            font-weight: 600;
            color: var(--text-secondary);
        }

        .result-detail-value {
            color: var(--text-primary);
            font-weight: 500;
            text-align: right;
            max-width: 60%;
        }

        .personal-message-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 1rem 1.25rem;
            border-radius: .5rem;
            margin-bottom: 1.5rem;
            text-align: left;
        }

        .personal-message-label {
            font-size: .75rem;
            text-transform: uppercase;
            color: #856404;
            font-weight: 700;
            margin-bottom: .5rem;
        }

        .personal-message-text {
            color: #856404;
            font-style: italic;
            line-height: 1.6;
        }

        .error-reasons {
            background: var(--bg-light);
            border-radius: .75rem;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            text-align: left;
        }

        .error-reasons h3 {
            font-size: .9375rem;
            font-weight: 600;
            margin-bottom: .875rem;
        }

        .error-reason {
            display: flex;
            gap: .75rem;
            padding: .625rem 0;
            border-bottom: 1px solid var(--border-color);
            font-size: .875rem;
        }

        .error-reason:last-child {
            border-bottom: none;
        }

        .error-reason-icon {
            font-size: 1.125rem;
            flex-shrink: 0;
        }

        .error-reason-text strong {
            display: block;
            margin-bottom: .2rem;
        }

        .error-reason-text {
            color: var(--text-secondary);
            line-height: 1.5;
        }

        /* ── Gift form ── */
        .gift-form-wrap {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: var(--shadow);
            max-width: 580px;
            margin: 0 auto;
        }

        .gift-form-wrap h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: .5rem;
        }

        .gift-form-wrap .subtitle {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
        }

        .article-info {
            background: var(--bg-light);
            border-radius: .75rem;
            padding: 1rem 1.25rem;
            margin-bottom: 1.25rem;
            border-left: 4px solid var(--primary-color);
        }

        .article-info-title {
            font-weight: 600;
            font-size: .9375rem;
            margin-bottom: .2rem;
        }

        .article-info-sub {
            font-size: .8125rem;
            color: var(--text-secondary);
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-label {
            display: block;
            font-weight: 600;
            font-size: .875rem;
            margin-bottom: .5rem;
            color: var(--text-primary);
        }

        .form-input, .form-textarea {
            width: 100%;
            padding: .75rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: .5rem;
            font-size: .9375rem;
            transition: border-color .2s;
            font-family: inherit;
        }

        .form-input:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, .1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        .char-count {
            font-size: .75rem;
            color: var(--text-secondary);
            text-align: right;
            margin-top: .25rem;
        }

        .share-link-box {
            background: var(--bg-light);
            border-radius: .75rem;
            padding: 1.25rem;
            margin-top: 1.5rem;
        }

        .share-link-box h3 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: .5rem;
        }

        .share-link-row {
            display: flex;
            gap: .75rem;
            align-items: center;
            margin-top: .75rem;
        }

        .share-link-input {
            flex: 1;
            padding: .625rem .875rem;
            border: 1px solid var(--border-color);
            border-radius: .5rem;
            font-size: .875rem;
            background: white;
        }

        /* ── Buttons ── */
        .btn {
            padding: .75rem 1.5rem;
            border: none;
            border-radius: .5rem;
            font-weight: 600;
            font-size: .9375rem;
            cursor: pointer;
            transition: all .2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            justify-content: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, .4);
        }

        .btn-primary:disabled {
            opacity: .6;
            cursor: not-allowed;
            transform: none;
        }

        .btn-secondary {
            background: white;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }

        .btn-secondary:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
        }

        .btn-danger {
            background: white;
            color: var(--danger-color);
            border: 2px solid var(--danger-color);
        }

        .btn-danger:hover {
            background: var(--danger-color);
            color: white;
        }

        .btn-sm {
            padding: .5rem 1rem;
            font-size: .875rem;
        }

        .btn-copy {
            padding: .5rem 1rem;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: .5rem;
            cursor: pointer;
            font-size: .875rem;
            font-weight: 600;
            transition: all .2s;
        }

        .btn-copy:hover {
            background: var(--primary-dark);
        }

        /* ── Given gifts table ── */
        .given-table {
            width: 100%;
            border-collapse: collapse;
        }

        .given-table th {
            padding: .75rem 1rem;
            text-align: left;
            font-size: .8125rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: .05em;
            background: var(--bg-light);
            border-bottom: 2px solid var(--border-color);
        }

        .given-table td {
            padding: .875rem 1rem;
            border-bottom: 1px solid var(--border-color);
            font-size: .875rem;
        }

        .given-table tr:last-child td {
            border-bottom: none;
        }

        .given-table-wrap {
            background: white;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        /* ── Toast ── */
        .toast-container {
            position: fixed;
            top: 1.5rem;
            right: 1.5rem;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: .75rem;
            pointer-events: none;
        }

        .toast {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: 1rem 1.25rem;
            border-radius: .75rem;
            font-size: .9375rem;
            font-weight: 500;
            box-shadow: var(--shadow-lg);
            pointer-events: all;
            animation: slideIn .3s ease;
            max-width: 360px;
        }

        .toast.success {
            background: #ecfdf5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .toast.error {
            background: #fef2f2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        .toast.info {
            background: #eff6ff;
            color: #1e40af;
            border-left: 4px solid #3b82f6;
        }

        .toast-icon {
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .toast-close {
            margin-left: auto;
            background: none;
            border: none;
            cursor: pointer;
            color: inherit;
            opacity: .6;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(100%)
            }
            to {
                opacity: 1;
                transform: translateX(0)
            }
        }

        @keyframes slideOut {
            from {
                opacity: 1
            }
            to {
                opacity: 0;
                transform: translateX(100%)
            }
        }

        @media (max-width: 640px) {
            .container {
                padding: 1rem;
            }

            .allowance-card {
                flex-direction: column;
            }

            .gift-card-header {
                flex-direction: column;
                gap: .5rem;
            }

            .result-panel {
                padding: 2rem 1.25rem;
            }

            .result-detail-row {
                flex-direction: column;
                gap: .25rem;
            }

            .result-detail-value {
                max-width: 100%;
                text-align: left;
            }

            .share-link-row {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
@include('member._header')

<div class="container" id="app-root">
    <div class="page-loader" id="page-loader">
        <p>Loading…</p>
    </div>
    <div id="app-content" style="display:none;"></div>
</div>

<script>
    /* ── PHP → JS bootstrap ── */
    const API_BASE = '/api/' + SITE_SLUG;
    const GIFT_TOKEN = <?= isset($gift_token) ? json_encode($gift_token) : 'null' ?>;

    /* ─────────────────────────────────────────────────────────────
       Component: AllowanceBar
    ───────────────────────────────────────────────────────────── */
    class AllowanceBar {
        constructor(allowance) {
            this.a = allowance;
        }

        render() {
            const a = this.a;
            const valueEl = UI.el('div', {className: 'allowance-value'}, [`${a.remaining_gifts} / ${a.annual_limit}`]);
            const labelEl = UI.el('div', {className: 'allowance-label'}, ['Remaining Gifts This Year']);
            const subEl = UI.el('div', {className: 'allowance-sub'}, [`${a.gifts_used ?? 0} of ${a.annual_limit} used`]);

            let statusEl = null;
            if (!a.can_gift) {
                statusEl = UI.el('div', {className: 'allowance-danger'},
                    ['🚫 Gift limit reached for this year. Your allowance resets annually.']);
            } else if (a.remaining_gifts <= 2) {
                statusEl = UI.el('div', {className: 'allowance-warning'},
                    [`⚠️ Only ${a.remaining_gifts} gift${a.remaining_gifts !== 1 ? 's' : ''} remaining this year.`]);
            }

            return UI.el('div', {className: 'allowance-card'}, [
                UI.el('div', {}, [labelEl, valueEl, subEl]),
                statusEl,
            ]);
        }
    }

    /* ─────────────────────────────────────────────────────────────
       Component: ReceivedGiftCard
    ───────────────────────────────────────────────────────────── */
    class ReceivedGiftCard {
        constructor(gift) {
            this.gift = gift;
        }

        render() {
            const g = this.gift;
            const status = (g.status ?? '').toLowerCase();

            const giftedByName = g.giftedBy?.first_name
                ? `${g.giftedBy.first_name} ${g.giftedBy.last_name ?? ''}`.trim()
                : (g.giftedBy?.email ?? 'A friend');

            const actionBtn = status === 'pending'
                ? UI.el('a', {
                    className: 'btn btn-primary btn-sm',
                    href: `/${SITE_SLUG}/gift/${g.gift_token}`
                }, ['🎁 Claim & Read'])
                : UI.el('a', {
                    className: 'btn btn-secondary btn-sm',
                    href: `/${SITE_SLUG}/${g.page?.slug}`
                }, ['Read Article →']);

            return UI.el('div', {className: 'gift-card'}, [
                UI.el('div', {className: 'gift-card-header'}, [
                    UI.el('div', {className: 'gift-card-title'}, [g.page?.title ?? '']),
                    UI.el('span', {className: `gift-status ${status}`}, [g.status ?? '']),
                ]),
                UI.el('div', {className: 'gift-meta'}, [
                    UI.el('span', {}, [`👤 From: ${giftedByName}`]),
                    UI.el('span', {}, [`📅 ${UI.formatDate(g.gifted_at)}`]),
                    g.claimed_at ? UI.el('span', {style: {color: 'var(--success-color)'}}, [`✓ Claimed ${UI.formatDate(g.claimed_at)}`]) : null,
                ]),
                g.personal_message
                    ? UI.el('div', {className: 'gift-message'}, [`"${g.personal_message}"`])
                    : null,
                UI.el('div', {className: 'gift-actions'}, [actionBtn]),
            ]);
        }
    }

    /* ─────────────────────────────────────────────────────────────
       Component: GivenGiftsTable
    ───────────────────────────────────────────────────────────── */
    class GivenGiftsTable {
        constructor(gifts) {
            this.gifts = gifts;
        }

        render() {
            if (!this.gifts.length) {
                return UI.emptyState({
                    icon: '🎁',
                    title: "You haven't gifted any articles yet",
                    body: 'Find an article you love and share it with a friend.',
                });
            }

            const rows = this.gifts.map(g =>
                UI.el('tr', {}, [
                    UI.el('td', {}, [g.page?.title ?? '—']),
                    UI.el('td', {}, [g.recipient_email ?? '']),
                    UI.el('td', {}, [UI.statusBadge(g.status)]),
                    UI.el('td', {}, [UI.formatDate(g.gifted_at)]),
                ])
            );

            return UI.el('div', {className: 'given-table-wrap'}, [
                UI.el('table', {className: 'given-table'}, [
                    UI.el('thead', {}, [UI.el('tr', {}, [
                        UI.el('th', {}, ['Article']),
                        UI.el('th', {}, ['Recipient']),
                        UI.el('th', {}, ['Status']),
                        UI.el('th', {}, ['Date']),
                    ])]),
                    UI.el('tbody', {}, rows),
                ]),
            ]);
        }
    }

    /* ─────────────────────────────────────────────────────────────
       Component: GiftForm (for gifting a page from a URL param)
    ───────────────────────────────────────────────────────────── */
    class GiftForm {
        constructor(page, allowance) {
            this.page = page;
            this.allowance = allowance;
        }

        render() {
            const a = this.allowance;
            const p = this.page;

            let allowanceNotice = null;
            if (!a.can_gift) {
                allowanceNotice = UI.el('div', {className: 'allowance-danger', style: {marginBottom: '1.25rem'}},
                    [`🚫 Gift limit reached. You've used all ${a.annual_limit} annual article gifts.`]);
            } else if (a.remaining_gifts <= 2) {
                allowanceNotice = UI.el('div', {className: 'allowance-warning', style: {marginBottom: '1.25rem'}},
                    [`⚠️ ${a.remaining_gifts} gift${a.remaining_gifts !== 1 ? 's' : ''} remaining this year.`]);
            }

            const emailInput = UI.el('input', {
                type: 'email',
                id: 'gift_email',
                className: 'form-input',
                required: 'true',
                placeholder: 'friend@example.com'
            });
            const charCountEl = UI.el('span', {id: 'gift_char_count'}, ['0']);
            const messageInput = UI.el('textarea', {
                id: 'gift_message',
                className: 'form-textarea',
                maxlength: '500',
                placeholder: 'Add a personal note…'
            });
            messageInput.addEventListener('input', () => UI.text(charCountEl, String(messageInput.value.length)));

            const shareBox = UI.el('div', {
                className: 'share-link-box',
                id: 'share_link_box',
                style: {display: 'none'}
            }, [
                UI.el('h3', {}, ['🎁 Gift Sent Successfully!']),
                UI.el('p', {
                    style: {
                        color: 'var(--text-secondary)',
                        fontSize: '.875rem'
                    }
                }, ['Share this link with your recipient, or an email has been sent.']),
                UI.el('div', {className: 'share-link-row'}, [
                    UI.el('input', {
                        type: 'text',
                        id: 'share_link_input',
                        className: 'share-link-input',
                        readonly: 'true'
                    }),
                    UI.el('button', {className: 'btn-copy', onclick: () => this.copyLink()}, ['Copy']),
                ]),
            ]);

            const submitBtn = UI.el('button', {
                type: 'button', className: 'btn btn-primary', id: 'gift_submit_btn',
                disabled: a.can_gift ? null : 'true'
            }, ['Send Gift 🎁']);
            submitBtn.addEventListener('click', () => this.submit(emailInput, messageInput, submitBtn, shareBox));

            return UI.el('div', {className: 'gift-form-wrap'}, [
                UI.el('h1', {}, ['🎁 Gift This Article']),
                UI.el('p', {className: 'subtitle'}, ['Send a friend access to this article.']),
                UI.el('div', {className: 'article-info'}, [
                    UI.el('div', {className: 'article-info-title'}, [p.title]),
                    UI.el('div', {className: 'article-info-sub'}, ['The recipient will receive a link to read this article for free.']),
                ]),
                allowanceNotice,
                a.can_gift ? UI.el('div', {}, [
                    UI.el('div', {className: 'form-group'}, [
                        UI.el('label', {className: 'form-label', for: 'gift_email'}, ['Recipient\'s Email *']),
                        emailInput,
                    ]),
                    UI.el('div', {className: 'form-group'}, [
                        UI.el('label', {className: 'form-label', for: 'gift_message'}, ['Personal Message (optional)']),
                        messageInput,
                        UI.el('div', {className: 'char-count'}, [charCountEl, '/500']),
                    ]),
                    UI.el('div', {style: {display: 'flex', gap: '1rem', marginTop: '1.5rem'}}, [
                        submitBtn,
                        UI.el('a', {
                            className: 'btn btn-secondary',
                            href: `/${SITE_SLUG}/member/gifted-articles`
                        }, ['My Gifts']),
                    ]),
                    shareBox,
                ]) : UI.el('a', {
                    className: 'btn btn-secondary',
                    href: `/${SITE_SLUG}/member/gifted-articles`
                }, ['← Back to My Gifts']),
            ]);
        }

        async submit(emailInput, messageInput, btn, shareBox) {
            const email = emailInput.value.trim();
            const message = messageInput.value.trim();

            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                UI.toast('Please enter a valid email address.', 'error');
                return;
            }

            btn.disabled = true;
            UI.text(btn, 'Sending…');

            try {
                const data = await api(`${API_BASE}/gift-article/${this.page.slug}`, {
                    method: 'POST',
                    body: JSON.stringify({recipient_email: email, personal_message: message}),
                });

                shareBox.style.display = 'block';
                const input = shareBox.querySelector('#share_link_input');
                if (input) input.value = data.share_link ?? '';

                UI.toast(data.message ?? 'Gift sent successfully!', 'success');
            } catch (err) {
                UI.toast(err.message ?? 'Failed to send gift. Please try again.', 'error');
                btn.disabled = false;
                UI.text(btn, 'Send Gift 🎁');
            }
        }

        copyLink() {
            const input = document.getElementById('share_link_input');
            if (!input) return;
            input.select();
            document.execCommand('copy');
            UI.toast('Link copied to clipboard!', 'success');
        }
    }

    /* ─────────────────────────────────────────────────────────────
       Component: ClaimResult (success or error)
    ───────────────────────────────────────────────────────────── */
    class ClaimResult {
        constructor(result) {
            this.result = result;
        }

        renderSuccess() {
            const r = this.result;
            const gift = r.gift;

            const details = [
                ['Article', gift.page?.title ?? 'Article'],
                ['Gifted By', gift.gifted_by?.name ?? 'A friend'],
                ['Gifted On', UI.formatDate(gift.gifted_at)],
                ['Claimed On', UI.formatDate(gift.claimed_at, true)],
            ];

            return UI.el('div', {className: 'result-panel'}, [
                UI.el('div', {className: 'result-icon success'}, [
                    UI.rawEl('<svg viewBox="0 0 52 52"><polyline points="14 27 22 35 38 19"/></svg>'),
                ]),
                UI.el('h1', {}, ['🎁 Gift Claimed!']),
                UI.el('p', {className: 'result-message'}, [r.message]),
                UI.el('div', {className: 'result-details'},
                    details.map(([label, value]) =>
                        UI.el('div', {className: 'result-detail-row'}, [
                            UI.el('span', {className: 'result-detail-label'}, [label]),
                            UI.el('span', {className: 'result-detail-value'}, [value ?? '—']),
                        ])
                    )
                ),
                gift.personal_message ? UI.el('div', {className: 'personal-message-box'}, [
                    UI.el('div', {className: 'personal-message-label'}, ['Personal Message']),
                    UI.el('div', {className: 'personal-message-text'}, [`"${gift.personal_message}"`]),
                ]) : null,
                UI.el('div', {
                    style: {
                        display: 'flex',
                        gap: '1rem',
                        justifyContent: 'center',
                        flexWrap: 'wrap',
                        marginTop: '1rem'
                    }
                }, [
                    UI.el('a', {
                        className: 'btn btn-primary',
                        href: `/${SITE_SLUG}/${gift.page?.slug ?? ''}`
                    }, ['Read Article Now']),
                    UI.el('a', {
                        className: 'btn btn-secondary',
                        href: `/${SITE_SLUG}/member/gifted-articles`
                    }, ['View All Gifts']),
                ]),
            ]);
        }

        renderError() {
            const reasons = [
                {icon: '⏰', title: 'Gift Link Expired', body: 'Gift links expire after a set period for security.'},
                {
                    icon: '✉️',
                    title: 'Wrong Email Address',
                    body: 'You may be logged in with a different email than the one the gift was sent to.'
                },
                {icon: '👥', title: 'Already Claimed', body: 'Each gift can only be claimed once.'},
                {
                    icon: '🔗',
                    title: 'Invalid Link',
                    body: 'Make sure you\'re using the full link that was shared with you.'
                },
            ];

            return UI.el('div', {className: 'result-panel'}, [
                UI.el('div', {className: 'result-icon error'}, [
                    UI.rawEl('<svg viewBox="0 0 52 52"><line x1="16" y1="16" x2="36" y2="36"/><line x1="36" y1="16" x2="16" y2="36"/></svg>'),
                ]),
                UI.el('h1', {}, ['Unable to Claim Gift']),
                UI.el('p', {className: 'result-message'}, [this.result.message ?? 'This gift could not be claimed.']),
                UI.el('div', {className: 'error-reasons'}, [
                    UI.el('h3', {}, ['Common reasons this might happen:']),
                    ...reasons.map(r => UI.el('div', {className: 'error-reason'}, [
                        UI.el('span', {className: 'error-reason-icon'}, [r.icon]),
                        UI.el('div', {className: 'error-reason-text'}, [
                            UI.el('strong', {}, [r.title]),
                            r.body,
                        ]),
                    ])),
                ]),
                UI.el('div', {style: {display: 'flex', gap: '1rem', justifyContent: 'center', flexWrap: 'wrap'}}, [
                    UI.el('a', {className: 'btn btn-primary', href: `/member/login`}, ['Try Different Account']),
                    UI.el('a', {
                        className: 'btn btn-secondary',
                        href: `/${SITE_SLUG}/member/gifted-articles`
                    }, ['My Gifts']),
                ]),
            ]);
        }

        render() {
            return this.result.success ? this.renderSuccess() : this.renderError();
        }
    }

    /* ─────────────────────────────────────────────────────────────
       App orchestration
    ───────────────────────────────────────────────────────────── */
    class GiftedArticlesStore {
        constructor() {
            this.state = {
                mode: 'loading',
                data: null,
                error: null,
            };
            this.listeners = [];
        }

        subscribe(listener) {
            this.listeners.push(listener);
            listener(this.state);
        }

        setState(patch) {
            this.state = {
                ...this.state,
                ...patch,
            };

            this.listeners.forEach(listener => listener(this.state));
        }
    }

    class GiftedArticlesApp {
        constructor() {
            this.root = document.getElementById('app-content');
            this.loader = document.getElementById('page-loader');
            this.store = new GiftedArticlesStore();
            this.store.subscribe(state => this.render(state));
        }

        async init() {
            // If we landed here via /gift/{token}, attempt the claim flow first.
            if (GIFT_TOKEN) {
                await this.runClaimFlow(GIFT_TOKEN);
                return;
            }

            // Normal index flow — check URL params for a "gift" action (e.g., ?gift=slug).
            const params = new URLSearchParams(window.location.search);
            const giftSlug = params.get('gift');

            if (giftSlug) {
                await this.runGiftFormFlow(giftSlug);
                return;
            }

            await this.runIndexFlow();
        }

        async runIndexFlow() {
            try {
                const data = await api(`${API_BASE}/member/gifted-articles`);
                this.store.setState({
                    mode: 'index',
                    data: data.data,
                    error: null,
                });
            } catch (err) {
                this.store.setState({
                    mode: 'error',
                    error: 'Failed to load gifted articles. Please refresh.',
                });
            }
        }

        async runGiftFormFlow(slug) {
            try {
                const data = await api(`${API_BASE}/member/gift-modal/${slug}`);
                this.store.setState({
                    mode: 'gift-form',
                    data: data.data,
                    error: null,
                });
            } catch (err) {
                this.store.setState({
                    mode: 'error',
                    error: err.message ?? 'Article not found.',
                });
            }
        }

        async runClaimFlow(token) {
            try {
                const data = await api(`${API_BASE}/member/gift/${token}/claim`, {method: 'POST'});
                // If already claimed, just redirect to the article.
                if (data.already_claimed && data.gift?.page?.slug) {
                    window.location.href = `/${SITE_SLUG}/${data.gift.page.slug}`;
                    return;
                }
                this.store.setState({
                    mode: 'claim-result',
                    data,
                    error: null,
                });
            } catch (err) {
                this.store.setState({
                    mode: 'claim-result',
                    data: {success: false, message: err.message},
                    error: null,
                });
            }
        }

        render(state) {
            if (state.mode === 'loading') {
                return;
            }

            if (state.mode === 'index' && state.data) {
                this.showIndex(state.data);
                return;
            }

            if (state.mode === 'gift-form' && state.data) {
                this.show([new GiftForm(state.data.page, state.data.allowance).render()]);
                return;
            }

            if (state.mode === 'claim-result' && state.data) {
                this.show([new ClaimResult(state.data).render()]);
                return;
            }

            if (state.mode === 'error') {
                this.showError(state.error);
            }
        }

        showIndex({received, given, allowance}) {
            const receivedCards = (received ?? []).map(g => new ReceivedGiftCard(g).render());
            const givenPanel = new GivenGiftsTable(given ?? []).render();

            const receivedContent = receivedCards.length
                ? UI.el('div', {className: 'gifts-list'}, receivedCards)
                : UI.emptyState({
                    icon: '📭',
                    title: "No gifts received yet",
                    body: "When someone gifts you an article, it will appear here."
                });

            const tabReceived = UI.el('button', {className: 'tab-btn active'}, [`Received (${(received ?? []).length})`]);
            const tabGiven = UI.el('button', {className: 'tab-btn'}, [`Given (${(given ?? []).length})`]);
            const panelRec = UI.el('div', {className: 'tab-panel active', id: 'panel-received'});
            const panelGiven = UI.el('div', {className: 'tab-panel', id: 'panel-given'});

            UI.render(panelRec, [receivedContent]);
            UI.render(panelGiven, [givenPanel]);

            tabReceived.addEventListener('click', () => {
                tabReceived.classList.add('active');
                tabGiven.classList.remove('active');
                panelRec.classList.add('active');
                panelGiven.classList.remove('active');
            });
            tabGiven.addEventListener('click', () => {
                tabGiven.classList.add('active');
                tabReceived.classList.remove('active');
                panelGiven.classList.add('active');
                panelRec.classList.remove('active');
            });

            this.show([
                new AllowanceBar(allowance).render(),
                UI.el('div', {className: 'tabs'}, [tabReceived, tabGiven]),
                panelRec,
                panelGiven,
            ]);
        }

        show(nodes) {
            UI.render(this.root, nodes);
            this.loader.style.display = 'none';
            this.root.style.display = 'block';
        }

        showError(message) {
            this.show([
                UI.el('div', {
                    style: {
                        textAlign: 'center',
                        padding: '4rem 2rem',
                        color: 'var(--danger-color)'
                    }
                }, [message]),
            ]);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const app = new GiftedArticlesApp();
        app.init();
    });
</script>
</body>
</html>
