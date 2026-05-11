<?php
/**
 * Billing form — Contact Information + Shipping Address sections.
 *
 * Handles both the saved-addresses flow (logged-in users) and
 * the address-lookup / manual address form (guests / "use different address").
 *
 * Dynamically adjusts which sections and fields are shown based on basket type:
 *
 *   print_only        → delivery address required, no email required here
 *   print_and_digital → delivery address required + email required
 *   digital_only      → address section hidden entirely, email required
 *
 * @var object|null $member Authenticated member object (nullable for guests).
 * @var bool $requiresShipping Whether the order needs a shipping address.
 * @var string $checkoutMode 'steps' | 'single-page'.
 * @var string $basketType 'print_only' | 'digital_only' | 'print_and_digital'.
 *                                   Defaults to 'print_only' for backward compatibility.
 */
$member = $member ?? null;
$requiresShipping = $requiresShipping ?? true;
$checkoutMode = $checkoutMode ?? 'single-page';
$basketType = $basketType ?? 'print_only';

$includesDigital = in_array($basketType, ['digital_only', 'print_and_digital'], true);
$includesPrint = in_array($basketType, ['print_only', 'print_and_digital'], true);
$isDigitalOnly = $basketType === 'digital_only';

// Copy for the email hint changes per scenario (AC2 vs AC3)
$emailHintText = $isDigitalOnly
        ? 'Your email is required to access your digital subscription.'
        : 'Your email is used for subscription access and updates.';
?>
    <style>
        .saved-address-card {
            border: 2px solid var(--border-color);
            border-radius: 0.5rem;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .saved-address-card:hover {
            border-color: var(--primary-color);
            background: rgba(37, 99, 235, 0.05);
        }

        .saved-address-card input[type="radio"] {
            flex-shrink: 0;
        }

        .saved-address-card .address-details {
            flex: 1;
        }

        .saved-address-card .address-details strong {
            display: block;
            margin-bottom: 0.25rem;
        }

        .field-hint {
            font-size: 0.8125rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
            line-height: 1.5;
        }
    </style>

    <!-- ── Contact Information ──────────────────────────────────────────── -->
    @include('checkout/components/form/form-section', ['title' => 'Contact Information'])

    @include('checkout/components/form/form-row')
    @include('checkout/components/form/form-group', [
    'name'     => 'first_name',
    'label'    => 'First Name',
    'required' => true,
    'value'    => $member?->first_name ?? '',
    ])
    @include('checkout/components/form/form-group', [
    'name'     => 'last_name',
    'label'    => 'Last Name',
    'required' => true,
    'value'    => $member?->last_name ?? '',
    ])
    @include('checkout/components/form/form-row', ['close' => true])

    @include('checkout/components/form/form-row')

<?php if ($includesDigital): ?>
    <!--
        Email is a required field for digital and print+digital baskets.
        It is rendered in full-width mode so the phone field drops to its own row.
        For print+digital gift flows, AC2 states email validation is NOT enforced
        before proceeding (gift recipient email is separate) — that is handled in JS.
    -->
    <div class="form-group full-width" style="margin-bottom: 1rem;">
        <label class="form-label" for="field-email">
            Email address <span class="required">*</span>
        </label>
        @include('checkout/components/form/input', [
        'name' => 'email',
        'id' => 'field-email',
        'type' => 'email',
        'required' => true,
        'value' => $member?->email ?? '',
        'attrs' => ['autocomplete' => 'email'],
        ])
        <p class="field-hint" id="email-field-hint"><?= htmlspecialchars($emailHintText) ?></p>
        <span class="form-error" id="error-email"></span>
    </div>
    @include('checkout/components/form/form-row', ['close' => true])

    @include('checkout/components/form/form-row')
    @include('checkout/components/form/form-group', [
    'name'  => 'phone',
    'label' => 'Phone',
    'type'  => 'tel',
    ])
    @include('checkout/components/form/form-row', ['close' => true])

<?php else: ?>
    <!--
        Print-only: email is present but not required at checkout level.
        Phone sits beside it in the standard two-column row.
    -->
    @include('checkout/components/form/form-group', [
    'name'     => 'email',
    'label'    => 'Email',
    'type'     => 'email',
    'required' => false,
    'value'    => $member?->email ?? '',
    ])
    @include('checkout/components/form/form-group', [
    'name'  => 'phone',
    'label' => 'Phone',
    'type'  => 'tel',
    ])
    @include('checkout/components/form/form-row', ['close' => true])
<?php endif; ?>

    @include('checkout/components/form/form-section', ['close' => true])

<?php if (!$isDigitalOnly): ?>

    <!-- ── Saved addresses (logged-in users) ────────────────────────────── -->
    @include('checkout/components/form/form-section', [
    'title' => 'Saved Addresses',
    'id'    => 'saved-addresses-section',
    'style' => 'display: none;',
    ])
    <div id="saved-addresses-list"></div>

    <?= $this->partial('checkout/components/form/button', [
            'label' => 'Use Different Address',
            'variant' => 'secondary',
            'type' => 'button',
            'onclick' => 'showNewAddressForm()',
    ]) ?>

    @include('checkout/components/form/form-section', ['close' => true])

    <?php
    $backBtn = $this->partial('checkout/components/form/button', [
            'label' => ' ← Back to Saved Addresses',
            'variant' => 'secondary',
            'type' => 'button',
            'onclick' => 'showSavedAddresses()',
            'style' => 'display: none; width: auto; padding: 0.5rem 1rem;',
            'id' => 'back-to-saved-btn',
    ]);
    ?>

    <!-- ── Shipping / Delivery Address ──────────────────────────────────── -->
    @include('checkout/components/form/form-section', [
    'title'         => 'Delivery Address',
    'id'            => 'shipping-address-form',
    'close'         => false,
    'headerContent' => $backBtn,
    ])

    @include('checkout/components/form/address-lookup', [
    'member'           => $member,
    'requiresShipping' => $requiresShipping,
    ])

    <!-- Billing same as delivery — shown for print baskets only -->
    <div style="margin-top: 1rem;">
        @include('checkout/components/form/checkbox-control', [
        'name' => 'billing_same_as_delivery',
        'id' => 'billing-same-as-delivery',
        'label' => 'Billing address is the same as delivery address',
        'checked' => true,
        ])
    </div>

    @include('checkout/components/form/form-section', ['close' => true])

<?php else: ?>

    <!--
        Digital-only: address section is intentionally absent from the DOM.
        No address fields are rendered or validated.
        A hidden input signals basket type to the server for downstream processing.
    -->
    <input type="hidden" name="basket_type" value="digital_only">

<?php endif; ?>

<?php if (!$isDigitalOnly && $includesPrint): ?>
    <!-- Hidden basket type flag for print scenarios -->
    <input type="hidden" name="basket_type" value="<?= htmlspecialchars($basketType) ?>">
<?php endif; ?>