<?php
/**
 * Voucher section — input, apply button, applied voucher display, discount row.
 *
 * @var string|null $sessionVoucherCode Pre-filled code from session.
 * @var string|null $sessionVoucherDiscount Pre-filled discount from session.
 * @var string|null $applyOnClick JS for apply button. Defaults to 'applyVoucher()'.
 * @var string|null $removeOnClick JS for remove button. Defaults to 'removeVoucher()'.
 * @var string $currency Currency symbol. Defaults to '$'.
 */
$sessionVoucherCode = $sessionVoucherCode ?? ($_SESSION['applied_voucher_code']['code'] ?? '');
$sessionVoucherDiscount = $sessionVoucherDiscount ?? ($_SESSION['applied_voucher_code']['discount'] ?? '');
$hasApplied = !empty($_SESSION['applied_voucher_code']);
$applyOnClick = $applyOnClick ?? 'applyVoucher()';
$removeOnClick = $removeOnClick ?? 'removeVoucher()';
$currency = $currency ?? '$';
?>
<div class="voucher-section"
     style="margin: 1.5rem 0; padding: 1.5rem 0;
            border-top: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);">

    <h4 style="font-size: 1rem; font-weight: 600; margin-bottom: 1rem;">Voucher Code</h4>

    <div style="display: flex; gap: 0.5rem;">
        <input type="text"
               id="voucher-input"
               placeholder="Enter code"
               value="<?= htmlspecialchars($sessionVoucherCode) ?>"
               style="flex: 1; padding: 0.75rem; border: 1px solid var(--border-color);
                      border-radius: 0.5rem; font-size: 0.875rem;">
        <button onclick="<?= htmlspecialchars($applyOnClick) ?>"
                class="btn btn-secondary"
                style="width: auto; padding: 0.75rem 1.5rem; font-size: 0.875rem;">
            Apply
        </button>
    </div>

    <div id="voucher-message" style="margin-top: 0.5rem; font-size: 0.875rem;"></div>

    <div id="applied-voucher"
         style="<?= $hasApplied ? 'display: block;' : 'display: none;' ?>
                 margin-top: 1rem; padding: 1rem; background: #d1fae5;
                 border-radius: 0.5rem; border: 1px solid #10b981;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <strong id="voucher-code-display" style="color: #065f46;">
                    <?= htmlspecialchars($sessionVoucherCode) ?>
                </strong>
                <p style="font-size: 0.875rem; color: #065f46; margin: 0.25rem 0 0 0;">
                    Discount: <span id="voucher-discount-display">
                          <?= $currency ?><?= number_format((float)$sessionVoucherDiscount, 2) ?>
                    </span>
                </p>
            </div>
            <button onclick="<?= htmlspecialchars($removeOnClick) ?>"
                    style="background: none; border: none; color: #065f46; cursor: pointer; padding: 0.5rem;"
                    aria-label="Remove voucher">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
    </div>
</div>

<!-- Discount summary row (hidden until voucher applied) -->
<div class="summary-row" id="discount-row"
     style="display: <?= $hasApplied ? 'flex' : 'none' ?>; color: var(--success-color);">
    <span>Discount:</span>
    <span id="discount-amount">
         -<?= $currency ?><?= number_format((float)$sessionVoucherDiscount, 2) ?>
    </span>
</div>