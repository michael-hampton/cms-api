<?php
$vouchers = is_array($vouchers ?? null) ? $vouchers : [];
?>

<?php if (!empty($vouchers)): ?>
    <section class="public-voucher-carousel" aria-labelledby="public-voucher-carousel-title">
        <div class="public-voucher-carousel__header">
            <div>
                <p class="public-voucher-carousel__eyebrow">Reader offers</p>
                <h2 id="public-voucher-carousel-title" class="public-voucher-carousel__title">Latest voucher codes</h2>
                <p class="public-voucher-carousel__intro">Hand-picked active codes you can reveal before checkout.</p>
            </div>

            <div class="public-voucher-carousel__controls" aria-label="Voucher carousel controls">
                <button type="button" class="public-voucher-carousel__nav" data-voucher-carousel-prev aria-label="Previous vouchers">‹</button>
                <button type="button" class="public-voucher-carousel__nav" data-voucher-carousel-next aria-label="Next vouchers">›</button>
            </div>
        </div>

        <div class="public-voucher-carousel__track" data-voucher-carousel-track tabindex="0" aria-label="Voucher codes">
            <?php foreach ($vouchers as $voucher): ?>
                <?php
                $code = (string) ($voucher['code'] ?? '');
                $title = (string) ($voucher['title'] ?? 'Voucher code');
                $description = trim((string) ($voucher['description'] ?? ''));
                $discountLabel = (string) ($voucher['discount_label'] ?? 'Offer');
                $expiresAt = (string) ($voucher['expires_at'] ?? '');
                $minimumOrderValue = $voucher['minimum_order_value'] ?? null;
                $maximumDiscount = $voucher['maximum_discount'] ?? null;
                $terms = trim((string) ($voucher['terms_and_conditions'] ?? ''));
                $expiresTimestamp = $expiresAt !== '' ? strtotime($expiresAt) : false;
                ?>
                <article
                    class="public-voucher-card"
                    data-voucher-card
                    data-code="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>"
                    data-title="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>"
                    data-description="<?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?>"
                    data-discount-label="<?= htmlspecialchars($discountLabel, ENT_QUOTES, 'UTF-8') ?>"
                    data-expires-at="<?= htmlspecialchars($expiresAt, ENT_QUOTES, 'UTF-8') ?>"
                    data-minimum-order-value="<?= htmlspecialchars((string) ($minimumOrderValue ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    data-maximum-discount="<?= htmlspecialchars((string) ($maximumDiscount ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    data-terms="<?= htmlspecialchars($terms, ENT_QUOTES, 'UTF-8') ?>"
                >
                    <div class="public-voucher-card__badge"><?= htmlspecialchars($discountLabel, ENT_QUOTES, 'UTF-8') ?></div>
                    <h3 class="public-voucher-card__title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h3>

                    <?php if ($description !== ''): ?>
                        <p class="public-voucher-card__description"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>

                    <div class="public-voucher-card__meta">
                        <?php if ($minimumOrderValue !== null): ?>
                            <span>Min spend £<?= htmlspecialchars(number_format((float) $minimumOrderValue, 2), ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>

                        <?php if ($maximumDiscount !== null): ?>
                            <span>Max saving £<?= htmlspecialchars(number_format((float) $maximumDiscount, 2), ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>

                        <?php if ($expiresTimestamp): ?>
                            <span>Expires <?= htmlspecialchars(date('j M Y', $expiresTimestamp), ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </div>

                    <button type="button" class="public-voucher-card__button" data-voucher-open>
                        Get code
                    </button>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
