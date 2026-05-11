<?php
/**
 * Gift fields component.
 *
 * Rendered when the user checks "This is a gift".
 * Which sub-fields appear depends on basket type:
 *
 *   print-only       → first/last name + optional mobile
 *   digital-only     → first/last name + required recipient email
 *   print+digital    → first/last name + required recipient email + optional mobile
 *
 * @var string $basketType 'print_only' | 'digital_only' | 'print_and_digital'
 * @var bool $isGift Whether the gift section should start expanded.
 */

$basketType = $basketType ?? 'print_only';
$isGift = $isGift ?? false;

$includesDigital = in_array($basketType, ['digital_only', 'print_and_digital'], true);
$includesPrint = in_array($basketType, ['print_only', 'print_and_digital'], true);

$emailHint = $includesPrint
        ? 'Used to send gift access instructions and subscription updates to the recipient.'
        : 'Used to send gift access instructions to the recipient.';
?>

<style>
    .gift-toggle-row {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 1.5rem;
    }

    .gift-toggle-row input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
        flex-shrink: 0;
        accent-color: var(--primary-color);
    }

    .gift-toggle-row label {
        font-size: 0.9375rem;
        font-weight: 500;
        cursor: pointer;
        color: var(--text-primary);
    }

    #gift-fields-section {
        background: #f8fafc;
        border: 1px solid var(--border-color);
        border-radius: 0.5rem;
        padding: 1.25rem;
        margin-top: 0.5rem;
        margin-bottom: 1.5rem;
    }

    #gift-fields-section .section-title {
        font-size: 1rem;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
    }

    .gift-hint {
        font-size: 0.8125rem;
        color: var(--text-secondary);
        margin-top: 0.25rem;
        line-height: 1.5;
    }

    .gift-age-block {
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid var(--border-color);
    }
</style>

<!-- Gift toggle -->
@include('checkout/components/form/form-section', ['title' => 'Gift Options'])

<div class="gift-toggle-row">
    <input
            type="checkbox"
            id="is-gift-checkbox"
            name="is_gift"
            value="1"
            <?= $isGift ? 'checked' : '' ?>
            onchange="giftFields.toggle(this.checked)"
    >
    <label for="is-gift-checkbox">This order is a gift</label>
</div>

<div id="gift-fields-section" style="<?= $isGift ? '' : 'display:none;' ?>">
    <h3 class="section-title">Recipient details</h3>

    <!-- Recipient name -->
    @include('checkout/components/form/form-row')
    @include('checkout/components/form/form-group', [
    'name' => 'recipient_first_name',
    'label' => 'Recipient first name',
    'required' => true,
    'value' => '',
    ])
    @include('checkout/components/form/form-group', [
    'name' => 'recipient_last_name',
    'label' => 'Recipient last name',
    'required' => true,
    'value' => '',
    ])
    @include('checkout/components/form/form-row', ['close' => true])

    <?php if ($includesDigital): ?>
        <!-- Recipient email — required when basket includes a digital item -->
        @include('checkout/components/form/form-group', [
        'name'     => 'recipient_email',
        'label'    => 'Recipient email address',
        'type'     => 'email',
        'required' => true,
        'value'    => '',
        'class'    => 'full-width',
        'attrs'    => ['autocomplete' => 'off'],
        ])
        <p class="gift-hint" id="recipient-email-hint"><?= htmlspecialchars($emailHint) ?></p>
    <?php endif; ?>

    <?php if ($includesPrint): ?>
        <!-- Optional mobile — print delivery only -->
        @include('checkout/components/form/form-group', [
        'name'     => 'recipient_mobile',
        'label'    => 'Recipient mobile number (optional)',
        'type'     => 'tel',
        'required' => false,
        'value'    => '',
        'class'    => 'full-width',
        ])
        <p class="gift-hint">We may use this to notify the recipient about their subscription delivery.</p>
    <?php endif; ?>

    <!-- Recipient age -->
    <div class="gift-age-block">
        @include('checkout/components/form/checkbox-control', [
        'name' => 'recipient_under_13',
        'id' => 'recipient-under-13',
        'label' => 'The recipient is 13 years old or younger',
        'checked' => false,
        ])
    </div>
</div>

@include('checkout/components/form/form-section', ['close' => true])

<script>
    const giftFields = (() => {
        const section = document.getElementById('gift-fields-section');

        /**
         * Required gift fields — scoped to the section so we don't accidentally
         * capture unrelated inputs outside of it.
         */
        function setRequired(enabled) {
            section.querySelectorAll('[data-gift-required]').forEach(el => {
                el.required = enabled;
            });
        }

        function toggle(show) {
            if (!section) return;
            section.style.display = show ? 'block' : 'none';

            // When hidden, disable required so form validation passes
            section.querySelectorAll('input, select, textarea').forEach(el => {
                if (show) {
                    if (el.dataset.giftRequired !== undefined) el.required = true;
                } else {
                    el.required = false;
                }
            });
        }

        // On first load, mark the fields that should be required when gift is active.
        // We do this by reading the PHP-rendered `required` attribute and caching it.
        function init() {
            if (!section) return;
            section.querySelectorAll('[required]').forEach(el => {
                el.dataset.giftRequired = '1';
            });

            // If gift is already checked on load (e.g. back-navigation), ensure
            // the required attributes are live.
            const checkbox = document.getElementById('is-gift-checkbox');
            if (checkbox && !checkbox.checked) {
                toggle(false);
            }
        }

        init();
        return {toggle};
    })();
</script>