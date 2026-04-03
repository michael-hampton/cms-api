<?php if ($show ?? true): ?>
    <style>
        .cs-merchant-header {
            display: flex;
            align-items: center;
            gap: 0.625rem;
            padding: 0.625rem 0.875rem;
            background: linear-gradient(135deg, #f1f5f9 0%, #e8eef6 100%);
            border-radius: 0.5rem;
            margin-bottom: 0.75rem;
            border-left: 3px solid #2563eb;
        }

        .cs-merchant-avatar {
            width: 30px;
            height: 30px;
            border-radius: 6px;
            background: #2563eb;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .cs-merchant-meta {
            flex: 1;
            min-width: 0;
        }

        .cs-merchant-name {
            font-size: 0.8rem;
            font-weight: 700;
            color: #1e293b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .cs-merchant-pill {
            display: inline-block;
            font-size: 0.68rem;
            color: #64748b;
            background: #fff;
            border: 1px solid #e2e8f0;
            padding: 0.05rem 0.4rem;
            border-radius: 99px;
            margin-top: 0.1rem;
        }

        .cs-merchant-subtotal {
            font-size: 0.8rem;
            font-weight: 700;
            color: #1e293b;
            flex-shrink: 0;
        }
    </style>
    <div class="cs-merchant-header">
        <div class="cs-merchant-avatar" aria-hidden="true"><?= htmlspecialchars($initials ?? '?') ?></div>
        <div class="cs-merchant-meta">
            <div class="cs-merchant-name"><?= htmlspecialchars($name ?? '') ?></div>
            <span class="cs-merchant-pill">
            <?= (int)($itemCount ?? 0) ?> item<?= ($itemCount ?? 0) !== 1 ? 's' : '' ?>
        </span>
        </div>
        <div class="cs-merchant-subtotal">
            <?= htmlspecialchars($currency ?? '£') ?><?= number_format((float)($subtotal ?? 0), 2) ?>
        </div>
    </div>
<?php endif; ?>