<?php
$vouchers = $vouchers ?? [];

if (method_exists($vouchers, 'toArray')) {
    $vouchers = $vouchers->toArray();
}

$readVoucherValue = static function (array|object $voucher, string $key, mixed $default = null): mixed {
    if (is_array($voucher)) {
        return $voucher[$key] ?? $default;
    }

    return $voucher->{$key} ?? $default;
};

$formatValue = static function (array|object $voucher) use ($readVoucherValue): string {
    $type = (string) $readVoucherValue($voucher, 'type', 'percentage');
    $value = (float) $readVoucherValue($voucher, 'value', 0);

    if ($type === 'fixed') {
        return '£' . number_format($value, 0) . ' off';
    }

    return number_format($value, 0) . '% off';
};
?>

<?php if (!empty($vouchers)): ?>
    <section class="public-voucher-carousel" aria-labelledby="public-voucher-carousel-title">
        <div class="public-voucher-carousel__header">
            <div>
                <p class="public-voucher-carousel__eyebrow">Latest offers</p>
                <h2 id="public-voucher-carousel-title" class="public-voucher-carousel__title">Voucher codes picked for you</h2>
            </div>
            <p class="public-voucher-carousel__summary">Tap a voucher to reveal the code and offer details.</p>
        </div>

        <div class="public-voucher-carousel__track" role="list">
            <?php foreach ($vouchers as $voucher): ?>
                <?php
                    $id = (int) $readVoucherValue($voucher, 'id');
                    $code = (string) $readVoucherValue($voucher, 'code');
                    $name = (string) $readVoucherValue($voucher, 'name', 'Voucher code');
                    $description = (string) $readVoucherValue($voucher, 'description', 'Use this voucher at checkout.');
                    $minimumOrderValue = $readVoucherValue($voucher, 'minimum_order_value');
                    $maximumDiscount = $readVoucherValue($voucher, 'maximum_discount');
                    $expiresAt = $readVoucherValue($voucher, 'expires_at');
                    $expiresLabel = $expiresAt instanceof DateTimeInterface
                        ? $expiresAt->format('j M Y')
                        : ($expiresAt ? date('j M Y', strtotime((string) $expiresAt)) : 'Limited time');
                ?>
                <article class="public-voucher-card" role="listitem">
                    <div class="public-voucher-card__saving"><?= htmlspecialchars($formatValue($voucher), ENT_QUOTES, 'UTF-8') ?></div>
                    <h3 class="public-voucher-card__title"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></h3>
                    <p class="public-voucher-card__description"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></p>
                    <dl class="public-voucher-card__meta">
                        <?php if ($minimumOrderValue): ?>
                            <div><dt>Min spend</dt><dd>£<?= htmlspecialchars(number_format((float) $minimumOrderValue, 2), ENT_QUOTES, 'UTF-8') ?></dd></div>
                        <?php endif; ?>
                        <?php if ($maximumDiscount): ?>
                            <div><dt>Max saving</dt><dd>£<?= htmlspecialchars(number_format((float) $maximumDiscount, 2), ENT_QUOTES, 'UTF-8') ?></dd></div>
                        <?php endif; ?>
                        <div><dt>Expires</dt><dd><?= htmlspecialchars($expiresLabel, ENT_QUOTES, 'UTF-8') ?></dd></div>
                    </dl>
                    <button
                        type="button"
                        class="public-voucher-card__button"
                        data-voucher-modal-trigger
                        data-voucher-id="<?= $id ?>"
                        data-voucher-title="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"
                        data-voucher-description="<?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?>"
                        data-voucher-code="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>"
                        data-voucher-saving="<?= htmlspecialchars($formatValue($voucher), ENT_QUOTES, 'UTF-8') ?>"
                        data-voucher-expires="<?= htmlspecialchars($expiresLabel, ENT_QUOTES, 'UTF-8') ?>"
                    >
                        Get code
                    </button>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
