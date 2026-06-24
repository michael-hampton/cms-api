<?php
/**
 * Billing form — Contact Information + Shipping Address sections.
 *
 * Handles both the saved-addresses flow (logged-in users) and
 * the address-lookup / manual address form (guests / "add new address").
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
        .subscription-address-ui {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .subscription-address-ui .form-section {
            border: 1px solid var(--sub-border, var(--border-color, #e2e8f0));
            border-radius: 16px;
            background: #fff;
            padding: 1.1rem;
            box-shadow: 0 10px 24px rgba(15, 23, 42, .04);
        }

        .subscription-address-ui .section-header {
            margin-bottom: 1rem !important;
            padding-bottom: .85rem;
            border-bottom: 1px solid var(--sub-border, var(--border-color, #e2e8f0));
        }

        .subscription-address-ui .section-title {
            font-size: .95rem !important;
            font-weight: 800;
            color: var(--sub-text, var(--text-primary, #0f172a));
            letter-spacing: -.01em;
        }

        .sub-address-mode-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .4rem;
            min-height: 36px;
            padding: .5rem .85rem;
            border: 1px solid var(--sub-border, var(--border-color, #e2e8f0));
            border-radius: 999px;
            background: #fff;
            color: var(--sub-text, var(--text-primary, #0f172a));
            cursor: pointer;
            font-size: .8125rem;
            font-weight: 800;
            line-height: 1;
            transition: border-color .18s ease, background .18s ease, color .18s ease, box-shadow .18s ease;
            white-space: nowrap;
        }

        .sub-address-mode-btn:hover,
        .sub-address-mode-btn.is-active {
            border-color: var(--sub-primary, var(--primary-color, #2563eb));
            background: rgba(99, 102, 241, .08);
            color: var(--sub-primary, var(--primary-color, #2563eb));
            box-shadow: 0 8px 18px rgba(99, 102, 241, .12);
        }

        .saved-address-card {
            position: relative;
            display: grid;
            grid-template-columns: auto 1fr auto;
            align-items: flex-start;
            gap: .85rem;
            margin-bottom: .75rem;
            padding: 1rem;
            border: 1.5px solid var(--sub-border, var(--border-color, #e2e8f0));
            border-radius: 14px;
            background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
            cursor: pointer;
            transition: border-color .18s ease, box-shadow .18s ease, background .18s ease, transform .18s ease;
        }

        .saved-address-card:last-child {
            margin-bottom: 0;
        }

        .saved-address-card:hover,
        .saved-address-card:has(input:checked),
        .saved-address-card.selected {
            border-color: var(--sub-primary, var(--primary-color, #2563eb));
            background: #fff;
            box-shadow: 0 12px 28px rgba(37, 99, 235, .1);
            transform: translateY(-1px);
        }

        .saved-address-card input[type="radio"] {
            width: 18px;
            height: 18px;
            margin-top: .15rem;
            accent-color: var(--sub-primary, var(--primary-color, #2563eb));
            flex-shrink: 0;
        }

        .saved-address-card .address-details {
            min-width: 0;
        }

        .saved-address-card .address-details strong {
            display: block;
            margin-bottom: .25rem;
            color: var(--sub-text, var(--text-primary, #0f172a));
            font-size: .95rem;
            font-weight: 800;
        }

        .saved-address-card .address-details p {
            margin: 0;
            color: var(--sub-muted, var(--text-secondary, #64748b));
            font-size: .875rem;
            line-height: 1.45;
        }

        .saved-address-card .badge {
            align-self: flex-start;
            border-radius: 999px;
            padding: .25rem .55rem;
            font-size: .7rem;
            font-weight: 800;
            letter-spacing: .02em;
            text-transform: uppercase;
        }

        .subscription-address-ui #shipping-address-form .form-group {
            margin-bottom: 1rem;
        }

        .subscription-address-ui #shipping-address-form .form-label {
            margin-bottom: .4rem;
            color: var(--sub-text, var(--text-primary, #0f172a));
            font-size: .78rem;
            font-weight: 800;
            letter-spacing: .02em;
        }

        .subscription-address-ui #shipping-address-form .form-input,
        .subscription-address-ui #shipping-address-form input,
        .subscription-address-ui #shipping-address-form select,
        .subscription-address-ui #shipping-address-form textarea {
            width: 100%;
            border: 1px solid var(--sub-border, var(--border-color, #e2e8f0));
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
            transition: border-color .18s ease, box-shadow .18s ease;
        }

        .subscription-address-ui #shipping-address-form .form-input:focus,
        .subscription-address-ui #shipping-address-form input:focus,
        .subscription-address-ui #shipping-address-form select:focus,
        .subscription-address-ui #shipping-address-form textarea:focus {
            border-color: var(--sub-primary, var(--primary-color, #2563eb));
            box-shadow: 0 0 0 3px rgba(99, 102, 241, .14);
            outline: none;
        }

        .subscription-address-ui .field-hint {
            margin-top: .25rem;
            color: var(--sub-muted, var(--text-secondary, #64748b));
            font-size: 0.8125rem;
            line-height: 1.5;
        }

        @media (max-width: 640px) {
            .subscription-address-ui .section-header {
                align-items: flex-start !important;
                flex-direction: column;
                gap: .75rem;
            }

            .sub-address-mode-btn {
                width: 100%;
            }

            .saved-address-card {
                grid-template-columns: auto 1fr;
            }

            .saved-address-card .badge {
                grid-column: 2;
                justify-self: flex-start;
            }
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
    'value'    => $member?->phone ?? '',
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

<?php if (!$isDigitalOnly || $requiresShipping): ?>

    <div class="subscription-address-ui" data-address-mode-root>
        <?php
        $addNewAddressBtn = '<button type="button" class="sub-address-mode-btn" id="sub-add-new-address-btn" onclick="showNewAddressForm()">+ Add new address</button>';
        $useExistingAddressBtn = '<button type="button" class="sub-address-mode-btn" id="sub-use-existing-address-btn" onclick="showSavedAddresses()">Use existing address</button>';
        ?>

        <!-- ── Saved addresses (logged-in users) ────────────────────────────── -->
        @include('checkout/components/form/form-section', [
        'title' => 'Use Existing Address',
        'id'    => 'saved-addresses-section',
        'style' => 'display: none;',
        'headerContent' => $addNewAddressBtn,
        ])
        <div id="saved-addresses-list"></div>
        @include('checkout/components/form/form-section', ['close' => true])

        <!-- ── Shipping / Delivery Address ──────────────────────────────────── -->
        @include('checkout/components/form/form-section', [
        'title'         => 'Add New Address',
        'id'            => 'shipping-address-form',
        'close'         => false,
        'headerContent' => $useExistingAddressBtn,
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
    </div>

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