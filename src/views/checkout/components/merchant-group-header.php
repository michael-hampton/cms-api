<?php if ($show ?? true): ?>
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