<?php
/**
 * Billing form — Contact Information + Shipping Address sections.
 *
 * Handles both the saved-addresses flow (logged-in users) and
 * the manual address form (guests / "use different address").
 *
 * @var object|null $member Authenticated member object (nullable for guests).
 * @var bool $requiresShipping Whether the order needs a shipping address.
 * @var string $checkoutMode 'steps' | 'single-page'.
 */
$member = $member ?? null;
$requiresShipping = $requiresShipping ?? true;
$checkoutMode = $checkoutMode ?? 'single-page';

$countryOptions = [
        'US' => 'United States',
        'CA' => 'Canada',
        'GB' => 'United Kingdom',
        'AU' => 'Australia',
        'DE' => 'Germany',
        'FR' => 'France',
];
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
    </style>

    <!-- Contact Information -->
    <div class="form-section">
        <h2 class="section-title">Contact Information</h2>
        <div class="form-row">
            @include('checkout/components/form/form-group', [
            'name' => 'first_name',
            'label' => 'First Name',
            'required' => true,
            'value' => $member?->first_name ?? '',
            ])
            @include('checkout/components/form/form-group', [
            'name' => 'last_name',
            'label' => 'Last Name',
            'required' => true,
            'value' => $member?->last_name ?? '',
            ])
        </div>
        <div class="form-row">
            @include('checkout/components/form/form-group', [
            'name' => 'email',
            'label' => 'Email',
            'type' => 'email',
            'required' => true,
            'value' => $member?->email ?? '',
            ])
            @include('checkout/components/form/form-group', [
            'name' => 'phone',
            'label' => 'Phone',
            'type' => 'tel',
            ])
        </div>
    </div>

<?php if ($requiresShipping): ?>

    <!-- Saved addresses — revealed by JS when member has saved addresses -->
    <div class="form-section" id="saved-addresses-section" style="display: none;">
        <h2 class="section-title">Saved Addresses</h2>
        <div id="saved-addresses-list"></div>
        <button type="button"
                onclick="showNewAddressForm()"
                class="btn btn-secondary">
            Use Different Address
        </button>
    </div>

    <!-- Manual address form -->
    <div class="form-section" id="shipping-address-form">
        <div class="section-header"
             style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h2 class="section-title" style="margin-bottom: 0; padding-bottom: 0; border: none;">
                Shipping Address
            </h2>
            <button type="button"
                    id="back-to-saved-btn"
                    onclick="showSavedAddresses()"
                    class="btn btn-secondary"
                    style="display: none; width: auto; padding: 0.5rem 1rem;">
                ← Back to Saved Addresses
            </button>
        </div>

        @include('checkout/components/form/form-group', [
        'name' => 'address',
        'label' => 'Address',
        'required' => true,
        'class' => 'full-width',
        ])
        @include('checkout/components/form/form-group', [
        'name' => 'address2',
        'label' => 'Apartment, suite, etc. (optional)',
        'class' => 'full-width',
        ])

        <div class="form-row">
            @include('checkout/components/form/form-group', [
            'name' => 'city',
            'label' => 'City',
            'required' => true,
            ])
            @include('checkout/components/form/form-group', [
            'name' => 'state',
            'label' => 'State / Province',
            ])
        </div>

        <div class="form-row">
            @include('checkout/components/form/form-group', [
            'name' => 'postal_code',
            'label' => 'Postal Code',
            'required' => true,
            ])
            @include('checkout/components/form/select', [
            'name' => 'country',
            'id' => 'country-select',
            'label' => 'Country',
            'required' => true,
            'blank' => true,
            'blankLabel' => 'Select Country',
            'options' => $countryOptions,
            'selected' => $member?->country ?? '',
            'onChange' => 'handleCountryChange(this.value)',
            ])
        </div>
    </div>

<?php endif; ?>